<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTrans } from '@/composables/useTrans';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description: string;
        confirmText?: string;
        cancelText?: string;
        variant?: 'destructive' | 'default';
        loading?: boolean;
    }>(),
    {
        confirmText: 'Lanjutkan',
        cancelText: 'Batal',
        variant: 'destructive',
        loading: false,
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
    (e: 'cancel'): void;
}>();

const { __ } = useTrans();

function handleClose() {
    emit('update:open', false);
    emit('cancel');
}

function handleConfirm() {
    emit('confirm');
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <div
                        v-if="variant === 'destructive'"
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-950/60"
                    >
                        <AlertTriangle
                            class="size-5 text-red-600 dark:text-red-400"
                        />
                    </div>
                    <div>
                        <DialogTitle class="text-base leading-6 font-semibold">
                            {{ title }}
                        </DialogTitle>
                    </div>
                </div>
                <DialogDescription
                    class="text-muted-foreground pt-2 text-sm leading-relaxed"
                >
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="pt-4">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="loading"
                    @click="handleClose"
                >
                    {{ cancelText || __('Cancel') }}
                </Button>
                <Button
                    type="button"
                    :variant="variant"
                    :disabled="loading"
                    @click="handleConfirm"
                >
                    {{ loading ? __('Loading...') : confirmText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
