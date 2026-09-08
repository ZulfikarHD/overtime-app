<script setup lang="ts">
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    FileCheck2,
    Users,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { QueueSubmission } from '@/components/overtime/SubmissionQueueRow.vue';
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

const props = defineProps<{
    open: boolean;
    action: 'APPROVED' | 'REJECTED';
    selectedSubmissions: QueueSubmission[];
    isProcessing?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (
        e: 'confirm',
        payload: {
            action: 'APPROVED' | 'REJECTED';
            rejection_reason?: string;
        },
    ): void;
}>();

const { __ } = useTrans();

const rejectionReason = ref('');

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            rejectionReason.value = '';
        }
    },
);

const isReject = computed(() => props.action === 'REJECTED');

const totalSubmissions = computed(() => props.selectedSubmissions.length);

const totalHours = computed(() => {
    return props.selectedSubmissions.reduce((sum, s) => {
        const h =
            typeof s.total_hours_cached === 'string'
                ? parseFloat(s.total_hours_cached)
                : Number(s.total_hours_cached || 0);
        return sum + (Number.isNaN(h) ? 0 : h);
    }, 0);
});

const totalHeadcount = computed(() => {
    return props.selectedSubmissions.reduce((sum, s) => {
        const count = s.items_count ?? s.items?.length ?? 0;
        return sum + count;
    }, 0);
});

const isRejectionReasonValid = computed(() => {
    if (!isReject.value) {
        return true;
    }
    return rejectionReason.value.trim().length >= 5;
});

const isConfirmDisabled = computed(() => {
    if (props.isProcessing || totalSubmissions.value === 0) {
        return true;
    }
    if (isReject.value && !isRejectionReasonValid.value) {
        return true;
    }
    return false;
});

function handleUpdateOpen(val: boolean) {
    if (!val && props.isProcessing) {
        return;
    }
    emit('update:open', val);
}

function handleClose() {
    if (props.isProcessing) {
        return;
    }
    emit('update:open', false);
}

function handleConfirm() {
    if (isConfirmDisabled.value) {
        return;
    }
    emit('confirm', {
        action: props.action,
        rejection_reason: isReject.value
            ? rejectionReason.value.trim()
            : undefined,
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="handleUpdateOpen">
        <DialogContent
            class="max-w-2xl sm:max-w-2xl"
            data-test="bulk-approval-confirm-modal"
        >
            <DialogHeader>
                <div class="flex items-center gap-2">
                    <div
                        class="flex size-9 items-center justify-center rounded-full"
                        :class="
                            isReject
                                ? 'bg-red-100 text-red-600 dark:bg-red-950 dark:text-red-400'
                                : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400'
                        "
                    >
                        <XCircle v-if="isReject" class="size-5" />
                        <CheckCircle2 v-else class="size-5" />
                    </div>
                    <div>
                        <DialogTitle
                            class="text-base font-bold text-slate-900 dark:text-white"
                            data-test="bulk-modal-title"
                        >
                            {{
                                isReject
                                    ? __('Konfirmasi Penolakan Massal')
                                    : __('Konfirmasi Persetujuan Massal')
                            }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-slate-500">
                            {{
                                isReject
                                    ? __(
                                          'Menolak seluruh item karyawan di pengajuan terpilih.',
                                      )
                                    : __(
                                          'Menyetujui seluruh item karyawan di pengajuan terpilih.',
                                      )
                            }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="space-y-4 py-2">
                <!-- Scope Summary Pill -->
                <div
                    class="rounded-lg border p-3 text-xs"
                    :class="
                        isReject
                            ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300'
                    "
                    data-test="bulk-scope-summary"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div class="font-medium">
                            {{
                                __(
                                    'Anda akan memproses :subs pengajuan secara serentak.',
                                    {
                                        subs: totalSubmissions,
                                    },
                                )
                            }}
                        </div>
                        <div
                            class="flex items-center gap-3 font-mono font-bold tabular-nums"
                        >
                            <span class="inline-flex items-center gap-1">
                                <Users class="size-3.5" />
                                {{ totalHeadcount }} {{ __('Karyawan') }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <Clock class="size-3.5" />
                                {{ totalHours.toFixed(1) }} {{ __('Jam') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Selected Submissions Table -->
                <div class="space-y-1.5">
                    <label
                        class="text-[11px] font-semibold tracking-wider text-slate-600 uppercase dark:text-slate-400"
                    >
                        {{
                            __('Daftar Pengajuan Terpilih (:count)', {
                                count: totalSubmissions,
                            })
                        }}
                    </label>
                    <div
                        class="max-h-48 overflow-y-auto rounded-md border border-slate-200 text-xs dark:border-slate-800"
                    >
                        <table
                            class="w-full text-left"
                            data-test="bulk-selected-table"
                        >
                            <thead
                                class="sticky top-0 border-b border-slate-200 bg-slate-50 text-[10px] font-semibold text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900"
                            >
                                <tr>
                                    <th class="px-3 py-1.5">
                                        {{ __('Kode Pengajuan') }}
                                    </th>
                                    <th class="px-3 py-1.5">
                                        {{ __('Seksi') }}
                                    </th>
                                    <th class="px-3 py-1.5">
                                        {{ __('Tanggal') }}
                                    </th>
                                    <th class="px-3 py-1.5 text-right">
                                        {{ __('Jam') }}
                                    </th>
                                    <th class="px-3 py-1.5 text-right">
                                        {{ __('Karyawan') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr
                                    v-for="sub in selectedSubmissions"
                                    :key="sub.id"
                                    class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50"
                                >
                                    <td
                                        class="px-3 py-1.5 font-mono font-semibold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ sub.submission_code }}
                                    </td>
                                    <td
                                        class="px-3 py-1.5 text-slate-600 dark:text-slate-400"
                                    >
                                        {{ sub.section?.name ?? '-' }}
                                    </td>
                                    <td
                                        class="px-3 py-1.5 font-mono text-slate-500 tabular-nums"
                                    >
                                        {{
                                            formatDateIndo(sub.operational_date)
                                        }}
                                    </td>
                                    <td
                                        class="px-3 py-1.5 text-right font-mono font-semibold text-slate-900 tabular-nums dark:text-white"
                                    >
                                        {{
                                            Number(
                                                sub.total_hours_cached,
                                            ).toFixed(1)
                                        }}
                                    </td>
                                    <td
                                        class="px-3 py-1.5 text-right font-mono text-slate-600 tabular-nums dark:text-slate-400"
                                    >
                                        {{
                                            sub.items_count ??
                                            sub.items?.length ??
                                            0
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Rejection Reason Input (Mandatory when rejecting) -->
                <div
                    v-if="isReject"
                    class="space-y-1.5"
                    data-test="bulk-rejection-reason-container"
                >
                    <label
                        class="block text-xs font-semibold text-slate-700 dark:text-slate-200"
                    >
                        {{ __('Alasan Penolakan Massal') }}
                        <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        v-model="rejectionReason"
                        rows="3"
                        class="w-full rounded-md border border-slate-300 p-2 text-xs focus:border-[#cc0000] focus:ring-1 focus:ring-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        :placeholder="
                            __(
                                'Contoh: Target shift terpenuhi, lembur tidak dialokasikan',
                            )
                        "
                        data-test="bulk-rejection-reason-input"
                    />
                    <div class="flex items-center justify-between text-[11px]">
                        <span
                            :class="
                                rejectionReason.trim().length > 0 &&
                                !isRejectionReasonValid
                                    ? 'font-medium text-red-500'
                                    : 'text-slate-400'
                            "
                        >
                            {{ __('Minimal 5 karakter wajib diisi.') }}
                        </span>
                        <span class="font-mono text-slate-400 tabular-nums">
                            {{ rejectionReason.trim().length }} / 1000
                        </span>
                    </div>
                </div>

                <!-- Consequence Warning -->
                <div
                    class="flex items-start gap-2 rounded-md bg-slate-50 p-2.5 text-[11px] text-slate-600 dark:bg-slate-900/60 dark:text-slate-400"
                >
                    <AlertTriangle class="size-4 shrink-0 text-amber-500" />
                    <span>
                        {{
                            __(
                                'Aksi ini akan mencatat riwayat audit resmi secara individual untuk setiap item karyawan yang diproses.',
                            )
                        }}
                    </span>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:gap-0">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="isProcessing"
                    data-test="btn-bulk-cancel"
                    @click="handleClose"
                >
                    {{ __('Batal') }}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :disabled="isConfirmDisabled"
                    :class="
                        isReject
                            ? 'bg-red-600 text-white hover:bg-red-700'
                            : 'bg-[#cc0000] text-white hover:bg-[#b30000]'
                    "
                    data-test="btn-bulk-confirm"
                    @click="handleConfirm"
                >
                    <span v-if="isProcessing">{{ __('Memproses...') }}</span>
                    <span v-else-if="isReject">{{
                        __('Konfirmasi & Tolak')
                    }}</span>
                    <span v-else>{{ __('Konfirmasi & Setujui') }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
