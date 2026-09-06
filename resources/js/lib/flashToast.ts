import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

let lastHandledMessage = '';
let lastHandledTime = 0;

function handleFlashToast(data?: FlashToast | null): void {
    if (!data || !data.message) {
        return;
    }

    const now = Date.now();
    // Debounce identical toast within 600ms
    if (lastHandledMessage === data.message && now - lastHandledTime < 600) {
        return;
    }

    lastHandledMessage = data.message;
    lastHandledTime = now;

    if (data.type && typeof toast[data.type] === 'function') {
        toast[data.type](data.message);
    } else {
        toast(data.message);
    }
}

function handleFlashBag(
    flash?:
        | {
              success?: string | null;
              error?: string | null;
              warning?: string | null;
              info?: string | null;
              toast?: FlashToast | null;
          }
        | Record<string, unknown>
        | null,
): void {
    if (!flash) {
        return;
    }

    if (flash.toast && typeof flash.toast === 'object') {
        handleFlashToast(flash.toast as FlashToast);
    } else if (typeof flash.success === 'string' && flash.success.trim()) {
        handleFlashToast({ type: 'success', message: flash.success });
    } else if (typeof flash.error === 'string' && flash.error.trim()) {
        handleFlashToast({ type: 'error', message: flash.error });
    } else if (typeof flash.warning === 'string' && flash.warning.trim()) {
        handleFlashToast({ type: 'warning', message: flash.warning });
    } else if (typeof flash.info === 'string' && flash.info.trim()) {
        handleFlashToast({ type: 'info', message: flash.info });
    }
}

export function initializeFlashToast(): void {
    // 1. Listen for Inertia v3 custom flash events
    router.on('flash', (event) => {
        const flash = event.detail.flash;
        handleFlashBag(flash);
    });

    // 2. Listen for Inertia navigation completions and check shared page props
    router.on('navigate', (event) => {
        const page = event.detail.page;
        const flash = page?.props?.flash;
        handleFlashBag(flash);
    });
}
