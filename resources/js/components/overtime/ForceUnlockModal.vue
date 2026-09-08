<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AlertTriangle, KeyRound, Loader2, Unlock } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
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
import { formatDateIndo } from '@/lib/formatters';
import { unlock as unlockRoute } from '@/routes/overtime/submissions';

export interface UnlockTargetSubmission {
    id: number;
    submission_code: string;
    status: string;
    operational_date?: string;
    total_hours_cached?: string | number;
    section?: { id?: number; name?: string; code?: string } | null;
}

const props = defineProps<{
    open: boolean;
    submission: UnlockTargetSubmission | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'unlocked', submissionId: number): void;
}>();

const { __ } = useTrans();

const reason = ref('');
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            reason.value = '';
            errorMessage.value = null;
            isSubmitting.value = false;
        }
    },
);

const isReasonValid = computed(() => reason.value.trim().length >= 5);
const canSubmit = computed(
    () =>
        isReasonValid.value && !isSubmitting.value && props.submission !== null,
);

function handleClose() {
    if (isSubmitting.value) {
        return;
    }
    emit('update:open', false);
}

function handleConfirm() {
    if (!props.submission || !canSubmit.value) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = null;

    router.patch(
        unlockRoute.url(props.submission.id),
        {
            reason: reason.value.trim(),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                const subId = props.submission?.id;
                emit('update:open', false);
                if (subId) {
                    emit('unlocked', subId);
                }
            },
            onError: (errors: Record<string, string>) => {
                errorMessage.value =
                    errors.reason ||
                    errors.message ||
                    __('Gagal membuka kunci pengajuan.');
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog
        :open="open"
        @update:open="(val) => !isSubmitting && emit('update:open', val)"
    >
        <DialogContent class="sm:max-w-lg" data-test="force-unlock-modal">
            <DialogHeader class="space-y-2">
                <div class="flex items-center gap-2">
                    <span
                        class="flex size-9 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400"
                    >
                        <KeyRound class="size-5" />
                    </span>
                    <DialogTitle
                        class="text-base font-bold text-slate-900 dark:text-white"
                        data-test="unlock-modal-title"
                    >
                        {{ __('⚠️ Buka Kunci Pengajuan (Admin Override)') }}
                    </DialogTitle>
                </div>
                <DialogDescription
                    class="text-xs text-slate-500 dark:text-slate-400"
                >
                    {{
                        __(
                            'Tindakan ini memerlukan wewenang Administrator dan akan dicatat secara permanen pada audit trail sistem.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="submission" class="space-y-4 py-2">
                <!-- Submission Snapshot Card -->
                <div
                    class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs dark:border-slate-800 dark:bg-slate-900/60"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div>
                            <span class="text-[11px] text-slate-500"
                                >{{ __('Kode Pengajuan') }}:</span
                            >
                            <span
                                class="ml-1.5 font-mono font-bold text-slate-900 dark:text-white"
                                data-test="unlock-submission-code"
                            >
                                {{ submission.submission_code }}
                            </span>
                        </div>
                        <Badge
                            variant="outline"
                            class="border-amber-200 bg-amber-50 text-[10px] font-semibold text-amber-800 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300"
                        >
                            {{ submission.status }}
                        </Badge>
                    </div>

                    <div
                        class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-slate-600 dark:text-slate-400"
                    >
                        <span v-if="submission.section">
                            {{ __('Seksi') }}:
                            <strong
                                class="text-slate-700 dark:text-slate-200"
                                >{{ submission.section.name }}</strong
                            >
                        </span>
                        <span v-if="submission.operational_date">
                            {{ __('Tanggal') }}:
                            <strong
                                class="text-slate-700 dark:text-slate-200"
                                >{{
                                    formatDateIndo(submission.operational_date)
                                }}</strong
                            >
                        </span>
                        <span v-if="submission.total_hours_cached">
                            {{ __('Total Jam') }}:
                            <strong
                                class="font-mono text-slate-700 tabular-nums dark:text-slate-200"
                                >{{
                                    Number(
                                        submission.total_hours_cached,
                                    ).toFixed(1)
                                }}
                                jam</strong
                            >
                        </span>
                    </div>
                </div>

                <!-- Consequence Warning Box -->
                <div
                    class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50/70 p-3 text-xs text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300"
                >
                    <AlertTriangle
                        class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                    />
                    <p class="leading-relaxed">
                        {{
                            __(
                                'Membuka kunci akan mengembalikan status seluruh item pengajuan ini ke MENUNGGU REVIEW (SUBMITTED) sehingga dapat diperbaiki oleh Team Leader. Seluruh rekaman persetujuan sebelumnya akan dicatat ulang.',
                            )
                        }}
                    </p>
                </div>

                <!-- Mandatory Reason Textarea -->
                <div class="space-y-1.5">
                    <label
                        class="block text-xs font-semibold text-slate-700 dark:text-slate-300"
                        for="unlock-reason"
                    >
                        {{
                            __(
                                'Alasan Pembukaan Kunci (Wajib diisi untuk audit HR & Finance)',
                            )
                        }}
                        <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="unlock-reason"
                        v-model="reason"
                        rows="3"
                        :disabled="isSubmitting"
                        :placeholder="
                            __(
                                'Contoh: Koreksi NPK operator yang salah catat atas memo HR No. 124/HR/IX/2026',
                            )
                        "
                        class="w-full rounded-md border border-slate-300 bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        data-test="input-unlock-reason"
                    ></textarea>

                    <div class="flex items-center justify-between text-[11px]">
                        <span
                            :class="
                                isReasonValid
                                    ? 'text-slate-500 dark:text-slate-400'
                                    : 'text-amber-600 dark:text-amber-400'
                            "
                            data-test="unlock-reason-counter"
                        >
                            {{ reason.trim().length }} / 5
                            {{ __('karakter minimal') }}
                        </span>
                        <span
                            v-if="!isReasonValid && reason.length > 0"
                            class="text-amber-600 dark:text-amber-400"
                        >
                            {{ __('Minimal 5 karakter diperlukan.') }}
                        </span>
                    </div>

                    <p
                        v-if="errorMessage"
                        class="text-xs font-semibold text-red-600 dark:text-red-400"
                        data-test="unlock-error-message"
                    >
                        {{ errorMessage }}
                    </p>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:gap-0">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="isSubmitting"
                    @click="handleClose"
                    data-test="btn-cancel-unlock"
                >
                    {{ __('Batal') }}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    class="bg-amber-600 text-white hover:bg-amber-700 active:scale-95 dark:bg-amber-600 dark:hover:bg-amber-700"
                    :disabled="!canSubmit"
                    @click="handleConfirm"
                    data-test="btn-confirm-unlock"
                >
                    <Loader2
                        v-if="isSubmitting"
                        class="mr-1.5 size-3.5 animate-spin"
                    />
                    <Unlock v-else class="mr-1.5 size-3.5" />
                    <span>{{
                        isSubmitting
                            ? __('Membuka Kunci...')
                            : __('Buka Kunci Pengajuan')
                    }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
