<script setup lang="ts">
import {
    AlertTriangle,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    X,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useTrans } from '@/composables/useTrans';

export interface BulkActionSkipItem {
    id: number;
    code: string;
    reason: string;
}

export interface BulkActionResult {
    processed_submissions_count: number;
    skipped_submissions_count: number;
    processed_items_count: number;
    skipped_items_count: number;
    processed_submission_ids: number[];
    skipped: BulkActionSkipItem[];
    message: string;
}

const props = defineProps<{
    result: BulkActionResult;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const { __ } = useTrans();

const showDetails = ref(false);
let autoDismissTimer: ReturnType<typeof setTimeout> | null = null;

const hasSkips = computed(() => props.result.skipped_submissions_count > 0);

onMounted(() => {
    // Auto-dismiss after 6 seconds if no skips or approver hasn't expanded details
    startTimer();
});

onUnmounted(() => {
    clearTimer();
});

function startTimer() {
    clearTimer();
    autoDismissTimer = setTimeout(() => {
        if (!showDetails.value) {
            emit('close');
        }
    }, 6000);
}

function clearTimer() {
    if (autoDismissTimer) {
        clearTimeout(autoDismissTimer);
        autoDismissTimer = null;
    }
}

function toggleDetails() {
    showDetails.value = !showDetails.value;
    if (showDetails.value) {
        clearTimer();
    } else {
        startTimer();
    }
}
</script>

<template>
    <div
        class="fixed top-5 right-5 z-50 w-full max-w-md transition-all duration-200"
        data-test="bulk-action-result-toast"
        @mouseenter="clearTimer"
        @mouseleave="!showDetails && startTimer()"
    >
        <div
            class="rounded-lg border p-4 shadow-lg backdrop-blur-xs"
            :class="
                hasSkips
                    ? 'border-amber-300 bg-amber-50/95 text-amber-900 dark:border-amber-700 dark:bg-amber-950/95 dark:text-amber-100'
                    : 'border-emerald-300 bg-emerald-50/95 text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/95 dark:text-emerald-100'
            "
        >
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 shrink-0">
                        <AlertTriangle
                            v-if="hasSkips"
                            class="size-5 text-amber-600 dark:text-amber-400"
                        />
                        <CheckCircle2
                            v-else
                            class="size-5 text-emerald-600 dark:text-emerald-400"
                        />
                    </div>
                    <div class="space-y-1">
                        <div
                            class="text-xs font-bold"
                            data-test="bulk-toast-title"
                        >
                            {{
                                hasSkips
                                    ? __('Proses Massal Sebagian Selesai')
                                    : __('Persetujuan Massal Selesai')
                            }}
                        </div>
                        <p
                            class="text-xs leading-relaxed"
                            data-test="bulk-toast-message"
                        >
                            {{ result.message }}
                        </p>
                        <div
                            class="flex flex-wrap items-center gap-2 pt-1 font-mono text-[11px] font-semibold tabular-nums"
                        >
                            <span
                                class="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-300"
                            >
                                ✓ {{ result.processed_items_count }}
                                {{ __('item berhasil') }}
                            </span>
                            <span
                                v-if="hasSkips"
                                class="inline-flex items-center gap-1 text-amber-700 dark:text-amber-300"
                            >
                                • ⚠️ {{ result.skipped_items_count }}
                                {{ __('item dilewati') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        class="rounded-md p-1 transition-colors hover:bg-black/5 dark:hover:bg-white/10"
                        data-test="bulk-toast-close"
                        @click="emit('close')"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>

            <!-- Skipped Details Toggle & List -->
            <div
                v-if="hasSkips"
                class="mt-3 border-t border-amber-200/80 pt-2 dark:border-amber-800/80"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between text-[11px] font-semibold text-amber-800 hover:text-amber-950 dark:text-amber-300 dark:hover:text-amber-100"
                    data-test="toggle-bulk-skip-details"
                    @click="toggleDetails"
                >
                    <span>
                        {{
                            showDetails
                                ? __('Sembunyikan rincian pengajuan dilewati')
                                : __(
                                      'Lihat rincian pengajuan dilewati (:count)',
                                      {
                                          count: result.skipped_submissions_count,
                                      },
                                  )
                        }}
                    </span>
                    <ChevronUp v-if="showDetails" class="size-3.5" />
                    <ChevronDown v-else class="size-3.5" />
                </button>

                <div
                    v-if="showDetails"
                    class="mt-2 max-h-36 space-y-1.5 overflow-y-auto rounded border border-amber-200 bg-white/60 p-2 text-[11px] dark:border-amber-800 dark:bg-black/30"
                    data-test="bulk-skip-details-list"
                >
                    <div
                        v-for="skip in result.skipped"
                        :key="skip.id"
                        class="flex flex-col border-b border-amber-100/60 pb-1 last:border-0 last:pb-0 dark:border-amber-800/60"
                    >
                        <span
                            class="font-mono font-bold text-amber-950 dark:text-amber-200"
                        >
                            {{ skip.code }}
                        </span>
                        <span class="text-amber-800/90 dark:text-amber-300/90">
                            {{ skip.reason }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
