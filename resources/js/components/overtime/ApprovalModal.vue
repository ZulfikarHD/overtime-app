<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    Calendar,
    CheckCheck,
    Download,
    FileText,
    RefreshCw,
    User as UserIcon,
    X,
    XCircle,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import ApprovalItemRow, {
    type ApprovalItemData,
    type ItemDecision,
} from '@/components/overtime/ApprovalItemRow.vue';
import SectionBurnIndicator from '@/components/overtime/SectionBurnIndicator.vue';
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
import type { BurnIndicator } from '@/composables/useSectionRoster';
import { useTrans } from '@/composables/useTrans';
import { formatDateIndo, formatRupiah } from '@/lib/formatters';
import {
    approveItems as approveItemsRoute,
    show as showRoute,
} from '@/routes/overtime/submissions';
import { download as downloadSpklRoute } from '@/routes/overtime/submissions/spkl';

export interface ApprovalModalSubmission {
    id: number;
    submission_code: string;
    submission_date?: string;
    operational_date: string;
    day_type: 'HKN' | 'HLR';
    status:
        | 'DRAFT'
        | 'SUBMITTED'
        | 'PARTIALLY_APPROVED'
        | 'APPROVED'
        | 'REJECTED';
    total_hours_cached: string | number;
    submission_notes?: string | null;
    section?: { id: number; name: string; code: string } | null;
    department?: { id: number; name: string; code: string } | null;
    submitted_by?: { id: number; name: string; npk: string } | null;
    spkl_document?: {
        id: number;
        status: string;
        due_date: string;
        spkl_number?: string | null;
        file_name?: string | null;
    } | null;
    items?: ApprovalItemData[];
}

const props = defineProps<{
    open: boolean;
    submissionId?: number | null;
    initialData?: ApprovalModalSubmission | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (
        e: 'saved',
        payload: {
            message: string;
            submission: ApprovalModalSubmission;
            approved_count: number;
            rejected_count: number;
        },
    ): void;
}>();

const { __ } = useTrans();

const submission = ref<ApprovalModalSubmission | null>(
    props.initialData ?? null,
);
const burnIndicator = ref<BurnIndicator>({
    planned_hours: 0,
    actual_hours: 0,
    burn_pct: 0,
    burn_zone: 'safe',
});

const isLoading = ref(false);
const isSaving = ref(false);
const fetchError = ref<string | null>(null);
const conflictError = ref<string | null>(null);
const formError = ref<string | null>(null);

const sharedRejectionReason = ref('');
const showSharedRejectionPrompt = ref(false);

const decisions = reactive<Record<number, ItemDecision>>({});

function initDecisions(items: ApprovalItemData[]) {
    for (const key of Object.keys(decisions)) {
        delete decisions[Number(key)];
    }

    for (const item of items) {
        decisions[item.id] = {
            item_id: item.id,
            action: (item.status === 'APPROVED' || item.status === 'REJECTED'
                ? item.status
                : 'PENDING') as 'APPROVED' | 'REJECTED' | 'PENDING',
            rejection_reason: item.rejection_reason || '',
            lock_version: item.lock_version ?? 1,
        };
    }
}

async function loadSubmission(id: number) {
    isLoading.value = true;
    fetchError.value = null;
    conflictError.value = null;
    formError.value = null;

    try {
        const url = showRoute.url({ submission: id });
        const res = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!res.ok) {
            throw new Error(`Failed to load submission (${res.status})`);
        }

        const data = await res.json();
        submission.value = data.submission as ApprovalModalSubmission;

        if (data.burn_indicator) {
            burnIndicator.value = data.burn_indicator as BurnIndicator;
        }

        if (submission.value.items) {
            initDecisions(submission.value.items);
        }
    } catch (err: any) {
        console.error('Error fetching submission detail for approval:', err);
        fetchError.value = __('Gagal memuat rincian data pengajuan lembur.');
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => [props.open, props.submissionId] as const,
    ([isOpen, id]) => {
        if (isOpen && id) {
            if (props.initialData && props.initialData.id === id) {
                submission.value = props.initialData;
                if (props.initialData.items) {
                    initDecisions(props.initialData.items);
                }
            }
            loadSubmission(id);
        } else if (!isOpen) {
            submission.value = null;
            conflictError.value = null;
            formError.value = null;
            showSharedRejectionPrompt.value = false;
        }
    },
    { immediate: true },
);

function handleClose() {
    if (isSaving.value) {
        return;
    }
    emit('update:open', false);
}

function approveAll() {
    if (!submission.value?.items) {
        return;
    }
    showSharedRejectionPrompt.value = false;
    for (const item of submission.value.items) {
        if (decisions[item.id]) {
            decisions[item.id].action = 'APPROVED';
        }
    }
}

function rejectAll() {
    if (!submission.value?.items) {
        return;
    }
    showSharedRejectionPrompt.value = true;
    for (const item of submission.value.items) {
        if (decisions[item.id]) {
            decisions[item.id].action = 'REJECTED';
            if (sharedRejectionReason.value.trim().length >= 5) {
                decisions[item.id].rejection_reason =
                    sharedRejectionReason.value.trim();
            }
        }
    }
}

function applySharedRejection() {
    const reason = sharedRejectionReason.value.trim();
    if (reason.length < 5) {
        return;
    }
    if (!submission.value?.items) {
        return;
    }
    for (const item of submission.value.items) {
        if (decisions[item.id] && decisions[item.id].action === 'REJECTED') {
            decisions[item.id].rejection_reason = reason;
        }
    }
}

const itemsList = computed(() => submission.value?.items ?? []);

const approvedCount = computed(() => {
    return Object.values(decisions).filter((d) => d.action === 'APPROVED')
        .length;
});

const rejectedCount = computed(() => {
    return Object.values(decisions).filter((d) => d.action === 'REJECTED')
        .length;
});

const pendingCount = computed(() => {
    return Object.values(decisions).filter((d) => d.action === 'PENDING')
        .length;
});

const approvedCost = computed(() => {
    let sum = 0;
    for (const item of itemsList.value) {
        if (decisions[item.id]?.action === 'APPROVED') {
            const cost =
                typeof item.total_cost_snapshot === 'string'
                    ? parseFloat(item.total_cost_snapshot)
                    : item.total_cost_snapshot;
            sum += Number.isNaN(cost) ? 0 : Number(cost);
        }
    }
    return sum;
});

const hasInvalidRejection = computed(() => {
    for (const item of itemsList.value) {
        const d = decisions[item.id];
        if (d && d.action === 'REJECTED') {
            const reason = d.rejection_reason?.trim() ?? '';
            if (reason.length < 5) {
                return true;
            }
        }
    }
    return false;
});

const canSubmit = computed(() => {
    if (isSaving.value || isLoading.value || itemsList.value.length === 0) {
        return false;
    }
    if (hasInvalidRejection.value) {
        return false;
    }
    // Must have at least one decision that is APPROVED or REJECTED
    const hasDecided = Object.values(decisions).some(
        (d) => d.action === 'APPROVED' || d.action === 'REJECTED',
    );
    return hasDecided;
});

async function saveDecisions() {
    if (!submission.value || !canSubmit.value) {
        return;
    }

    isSaving.value = true;
    conflictError.value = null;
    formError.value = null;

    const csrfToken =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content || '';

    // Only send decisions for items that are APPROVED or REJECTED
    const payloadDecisions = Object.values(decisions)
        .filter((d) => d.action === 'APPROVED' || d.action === 'REJECTED')
        .map((d) => ({
            item_id: d.item_id,
            action: d.action,
            rejection_reason:
                d.action === 'REJECTED' ? d.rejection_reason?.trim() : null,
            lock_version: d.lock_version,
        }));

    try {
        const url = approveItemsRoute.url({ submission: submission.value.id });
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ decisions: payloadDecisions }),
        });

        const data = await res.json();

        if (res.status === 409) {
            conflictError.value =
                data.message ||
                __(
                    'Data pengajuan ini telah diperbarui oleh reviewer lain beberapa saat yang lalu. Keputusan Anda belum tersimpan.',
                );
            return;
        }

        if (!res.ok) {
            if (data.errors) {
                const firstErr = Object.values(data.errors)[0];
                formError.value = Array.isArray(firstErr)
                    ? firstErr[0]
                    : String(firstErr);
            } else {
                formError.value =
                    data.message ||
                    __('Gagal menyimpan keputusan persetujuan.');
            }
            return;
        }

        // Success: close modal and emit saved
        emit('saved', {
            message:
                data.message || __('Keputusan persetujuan berhasil disimpan.'),
            submission: data.submission,
            approved_count: data.approved_count ?? approvedCount.value,
            rejected_count: data.rejected_count ?? rejectedCount.value,
        });

        handleClose();
    } catch (err: any) {
        console.error('Error saving approval decisions:', err);
        formError.value = __(
            'Terjadi kesalahan jaringan saat menyimpan keputusan.',
        );
    } finally {
        isSaving.value = false;
    }
}

function handleDownloadSpkl() {
    if (submission.value?.spkl_document?.id && submission.value?.id) {
        window.open(
            downloadSpklRoute.url({ submission: submission.value.id }),
            '_blank',
        );
    }
}

function getStatusBadge(status: string) {
    switch (status) {
        case 'APPROVED':
            return {
                label: __('Disetujui'),
                class: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300',
            };
        case 'PARTIALLY_APPROVED':
            return {
                label: __('Disetujui Sebagian'),
                class: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950 dark:text-amber-300',
            };
        case 'REJECTED':
            return {
                label: __('Ditolak'),
                class: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-950 dark:text-red-300',
            };
        default:
            return {
                label: __('Menunggu Review'),
                class: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950 dark:text-blue-300',
            };
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="flex max-h-[92vh] w-full max-w-5xl flex-col p-0 sm:max-w-5xl"
            data-test="approval-modal"
        >
            <DialogTitle class="sr-only">
                {{ __('Persetujuan Lembur Karyawan') }}
            </DialogTitle>
            <DialogDescription class="sr-only">
                {{
                    __(
                        'Rincian jam lembur dan keputusan persetujuan per item karyawan.',
                    )
                }}
            </DialogDescription>

            <!-- Modal Header -->
            <DialogHeader
                class="border-b border-slate-200 p-4 pb-3 sm:p-5 sm:pb-4 dark:border-slate-800"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded bg-slate-100 px-2.5 py-1 font-mono text-xs font-bold text-slate-800 dark:bg-slate-800 dark:text-slate-200"
                                data-test="approval-modal-code"
                            >
                                {{
                                    submission?.submission_code ?? 'Loading...'
                                }}
                            </span>

                            <Badge
                                v-if="submission"
                                :class="getStatusBadge(submission.status).class"
                                class="text-[11px]"
                                data-test="approval-modal-status"
                            >
                                {{ getStatusBadge(submission.status).label }}
                            </Badge>

                            <Badge
                                v-if="submission"
                                variant="outline"
                                :class="
                                    submission.day_type === 'HKN'
                                        ? 'dark:bg-slate-850 bg-slate-50 text-slate-700 dark:text-slate-300'
                                        : 'bg-red-50 text-[#cc0000] dark:bg-red-950 dark:text-red-300'
                                "
                                class="text-[10px] font-bold"
                            >
                                {{ submission.day_type }}
                            </Badge>
                        </div>

                        <!-- Section & Submitter Subtitle -->
                        <div
                            v-if="submission"
                            class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            <span class="flex items-center gap-1">
                                <Calendar class="size-3.5" />
                                {{
                                    formatDateIndo(submission.operational_date)
                                }}
                            </span>
                            <span
                                class="font-medium text-slate-700 dark:text-slate-300"
                            >
                                {{ submission.section?.name ?? '-' }} ({{
                                    submission.department?.name ?? '-'
                                }})
                            </span>
                            <span class="flex items-center gap-1">
                                <UserIcon class="size-3.5" />
                                {{ submission.submitted_by?.name ?? '-' }}
                                <span class="font-mono text-slate-400"
                                    >({{ submission.submitted_by?.npk }})</span
                                >
                            </span>
                        </div>
                    </div>

                    <!-- SPKL Status Badge & Link -->
                    <div class="flex items-center gap-2">
                        <div
                            v-if="submission?.spkl_document"
                            class="flex items-center gap-1.5"
                        >
                            <span
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    submission.spkl_document.status ===
                                        'ATTACHED' ||
                                    submission.spkl_document.status ===
                                        'VERIFIED'
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300'
                                        : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300'
                                "
                                data-test="modal-spkl-badge"
                            >
                                <FileText class="size-3" />
                                SPKL: {{ submission.spkl_document.status }}
                            </span>

                            <Button
                                v-if="
                                    submission.spkl_document.file_name ||
                                    submission.spkl_document.status ===
                                        'ATTACHED' ||
                                    submission.spkl_document.status ===
                                        'VERIFIED'
                                "
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-7 text-xs"
                                data-test="btn-download-spkl"
                                @click="handleDownloadSpkl"
                            >
                                <Download class="mr-1 size-3" />
                                {{ __('Unduh SPKL') }}
                            </Button>
                        </div>

                        <span
                            class="hidden text-[11px] text-slate-400 sm:inline"
                            :title="
                                __(
                                    'Sesuai BR-05: SPKL pending tidak menghambat persetujuan lembur operasional.',
                                )
                            "
                        >
                            {{ __('SPKL Non-blocking (BR-05)') }}
                        </span>
                    </div>
                </div>

                <!-- Section Monthly Burn Indicator in Modal Header -->
                <div class="mt-3" data-test="modal-burn-indicator">
                    <SectionBurnIndicator :burn="burnIndicator" />
                </div>
            </DialogHeader>

            <!-- Dialog Body -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-5">
                <!-- Loading State -->
                <div
                    v-if="isLoading"
                    class="flex flex-col items-center justify-center py-12 text-slate-500"
                >
                    <RefreshCw class="size-8 animate-spin text-[#cc0000]" />
                    <p class="mt-2 text-xs font-medium">
                        {{ __('Memuat rincian data karyawan...') }}
                    </p>
                </div>

                <!-- Error Alert -->
                <div
                    v-else-if="fetchError"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                >
                    <div class="flex items-center gap-2 font-bold">
                        <AlertCircle class="size-4" />
                        <span>{{ __('Gagal Memuat Data') }}</span>
                    </div>
                    <p class="mt-1">{{ fetchError }}</p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="mt-3 text-xs"
                        @click="submissionId && loadSubmission(submissionId)"
                    >
                        {{ __('Coba Lagi') }}
                    </Button>
                </div>

                <!-- Main Review Content -->
                <div v-else-if="submission" class="space-y-4">
                    <!-- Optimistic Concurrency Conflict Banner (409) -->
                    <div
                        v-if="conflictError"
                        class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-xs text-amber-900 shadow-xs dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200"
                        data-test="conflict-banner"
                    >
                        <div class="flex items-start gap-2.5">
                            <AlertTriangle
                                class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400"
                            />
                            <div class="flex-1 space-y-1">
                                <h5
                                    class="font-bold text-amber-900 dark:text-amber-100"
                                >
                                    {{
                                        __(
                                            'Konflik Pembaruan Data (409 Conflict)',
                                        )
                                    }}
                                </h5>
                                <p>{{ conflictError }}</p>
                                <div class="pt-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        class="bg-amber-600 text-white hover:bg-amber-700 dark:bg-amber-700"
                                        data-test="btn-reload-conflict"
                                        @click="loadSubmission(submission.id)"
                                    >
                                        <RefreshCw class="mr-1.5 size-3.5" />
                                        {{ __('Muat Ulang Data Terbaru') }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Validation / General Form Error Alert -->
                    <div
                        v-if="formError"
                        class="rounded-lg border border-red-300 bg-red-50 p-3 text-xs text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                        data-test="form-error-banner"
                    >
                        <div class="flex items-center gap-2 font-bold">
                            <AlertCircle class="size-4 shrink-0 text-red-600" />
                            <span>{{ formError }}</span>
                        </div>
                    </div>

                    <!-- Batch Decision Toolbar -->
                    <div
                        class="dark:bg-slate-850 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800"
                        data-test="batch-controls"
                    >
                        <div class="text-xs text-slate-600 dark:text-slate-400">
                            <span
                                class="font-semibold text-slate-800 dark:text-slate-200"
                            >
                                {{ __('Tindakan Cepat Standup:') }}
                            </span>
                            {{
                                __(
                                    'Terapkan keputusan serentak untuk seluruh item.',
                                )
                            }}
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 border-emerald-300 text-xs text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950/50"
                                data-test="btn-approve-all"
                                @click="approveAll"
                            >
                                <CheckCheck class="mr-1.5 size-3.5" />
                                {{ __('Setujui Semua (Approve All)') }}
                            </Button>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 border-red-300 text-xs text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/50"
                                data-test="btn-reject-all"
                                @click="rejectAll"
                            >
                                <XCircle class="mr-1.5 size-3.5" />
                                {{ __('Tolak Semua (Reject All)') }}
                            </Button>
                        </div>
                    </div>

                    <!-- Shared Rejection Reason Prompt (when Reject All triggered) -->
                    <div
                        v-if="showSharedRejectionPrompt"
                        class="space-y-2 rounded-lg border border-red-200 bg-red-50/50 p-3 text-xs dark:border-red-900/60 dark:bg-red-950/30"
                        data-test="shared-rejection-prompt"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="font-bold text-red-900 dark:text-red-200"
                            >
                                {{
                                    __(
                                        'Alasan Penolakan Serentak (Wajib Diisi)',
                                    )
                                }}
                            </span>
                            <button
                                type="button"
                                class="text-slate-400 hover:text-slate-600"
                                @click="showSharedRejectionPrompt = false"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <input
                                v-model="sharedRejectionReason"
                                type="text"
                                :placeholder="
                                    __(
                                        'Contoh: Target shift terpenuhi, lembur tidak dialokasikan atau salah kategori.',
                                    )
                                "
                                class="h-8 flex-1 rounded-md border border-red-300 bg-white px-2.5 text-xs text-slate-900 focus:border-red-500 focus:ring-1 focus:ring-red-500 focus:outline-hidden dark:border-red-800 dark:bg-slate-900 dark:text-white"
                                data-test="input-shared-rejection"
                                @input="applySharedRejection"
                            />
                            <Button
                                type="button"
                                size="sm"
                                class="h-8 bg-red-600 text-xs text-white hover:bg-red-700"
                                :disabled="
                                    sharedRejectionReason.trim().length < 5
                                "
                                data-test="btn-apply-shared-rejection"
                                @click="applySharedRejection"
                            >
                                {{ __('Terapkan') }}
                            </Button>
                        </div>
                        <p class="text-[11px] text-slate-500">
                            {{
                                __(
                                    'Alasan ini akan otomatis disalin ke setiap item karyawan yang ditolak.',
                                )
                            }}
                        </p>
                    </div>

                    <!-- Scrollable Employee Item Rows -->
                    <div class="space-y-2.5" data-test="approval-items-list">
                        <ApprovalItemRow
                            v-for="item in itemsList"
                            :key="item.id"
                            :item="item"
                            :decision="
                                decisions[item.id] || {
                                    item_id: item.id,
                                    action: 'PENDING',
                                    rejection_reason: '',
                                    lock_version: item.lock_version ?? 1,
                                }
                            "
                            :disabled="isSaving"
                            @update:decision="decisions[item.id] = $event"
                        />
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <DialogFooter
                class="flex flex-col-reverse items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/70 p-4 sm:flex-row sm:p-5 dark:border-slate-800 dark:bg-slate-900"
            >
                <!-- Counters & Cost Preview -->
                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        data-test="counter-approved"
                    >
                        <span class="size-2 rounded-full bg-emerald-500" />
                        {{ approvedCount }} {{ __('Disetujui') }}
                    </span>

                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        data-test="counter-rejected"
                    >
                        <span class="size-2 rounded-full bg-red-500" />
                        {{ rejectedCount }} {{ __('Ditolak') }}
                    </span>

                    <span
                        v-if="pendingCount > 0"
                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 font-medium text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
                        data-test="counter-pending"
                    >
                        <span class="size-2 rounded-full bg-slate-400" />
                        {{ pendingCount }} {{ __('Pending') }}
                    </span>

                    <span
                        class="font-mono text-xs font-bold text-slate-800 tabular-nums dark:text-slate-200"
                        data-test="approved-cost-preview"
                    >
                        {{ __('Total Biaya Disetujui:') }}
                        <span class="text-emerald-600 dark:text-emerald-400">{{
                            formatRupiah(approvedCost)
                        }}</span>
                    </span>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="isSaving"
                        data-test="btn-cancel-modal"
                        @click="handleClose"
                    >
                        {{ __('Batal') }}
                    </Button>

                    <Button
                        type="button"
                        :disabled="!canSubmit"
                        class="bg-[#cc0000] text-white shadow-xs transition-all hover:bg-[#b30000] active:scale-95 disabled:opacity-50"
                        data-test="btn-save-decisions"
                        @click="saveDecisions"
                    >
                        <RefreshCw
                            v-if="isSaving"
                            class="mr-1.5 size-3.5 animate-spin"
                        />
                        {{
                            isSaving
                                ? __('Menyimpan...')
                                : __('Simpan Keputusan')
                        }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
