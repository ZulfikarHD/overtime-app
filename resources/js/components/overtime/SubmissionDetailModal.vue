<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    Download,
    FileText,
    FolderKanban,
    Lock,
    Paperclip,
    Pencil,
    RefreshCw,
    Users,
    X,
} from '@lucide/vue';
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
import { formatDateIndo, formatRupiah } from '@/lib/formatters';
import {
    edit as editRoute,
    show as showRoute,
} from '@/routes/overtime/submissions';
import {
    download as downloadSpklRoute,
    verify as verifySpklRoute,
} from '@/routes/overtime/submissions/spkl';

export interface SubmissionItemDetail {
    id: number;
    employee_id: number;
    npk_snapshot: string;
    employee?: {
        id: number;
        npk: string;
        full_name: string;
        job_position: string;
        hourly_rate: number | string | null;
    } | null;
    capex_project_id?: number | null;
    capex_project?: {
        id: number;
        project_code: string;
        name: string;
    } | null;
    hours_production: string | number;
    hours_tpm: string | number;
    hours_project: string | number;
    hours_others: string | number;
    total_hours: string | number;
    hourly_rate_snapshot: string | number;
    total_cost_snapshot: string | number;
    rca_category?: string | null;
    rca_notes?: string | null;
    task_description?: string | null;
    status: string;
    policy_warning?: {
        level: 'none' | 'warning' | 'danger';
        message: string;
        weekly_total: number;
        consecutive_weeks: number;
        weekly_limit: number;
    } | null;
}

export interface SubmissionDetail {
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
    created_at?: string;
    updated_at?: string;
    department?: { id: number; name: string; code: string } | null;
    section?: { id: number; name: string; code: string } | null;
    submitted_by?: { id: number; name: string; npk: string } | null;
    spkl_document?: {
        id: number;
        status: string;
        due_date: string;
        spkl_number?: string | null;
        file_name?: string | null;
    } | null;
    items?: SubmissionItemDetail[];
}

const props = defineProps<{
    open: boolean;
    submissionId?: number | null;
    initialData?: SubmissionDetail | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'attachSpkl', value: any): void;
    (e: 'verified'): void;
}>();

const { __ } = useTrans();
const page = usePage();

const canVerifySpkl = computed(() => {
    const role = (page.props as any).auth?.user?.role;
    return role === 'admin' || role === 'manager';
});

const isVerifying = ref(false);

function handleVerifySpkl() {
    if (!detail.value) {
        return;
    }
    isVerifying.value = true;
    router.patch(
        verifySpklRoute.url({ submission: detail.value.id }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                isVerifying.value = false;
                if (detail.value?.spkl_document) {
                    detail.value.spkl_document.status = 'VERIFIED';
                }
                emit('verified');
            },
            onError: () => {
                isVerifying.value = false;
            },
        },
    );
}

function handleAttachSpkl() {
    if (!detail.value) {
        return;
    }
    emit('attachSpkl', {
        id: detail.value.id,
        submission_code: detail.value.submission_code,
        operational_date: detail.value.operational_date,
        section_name: detail.value.section?.name,
        total_hours: detail.value.total_hours_cached,
        spkl_document: detail.value.spkl_document,
    });
    handleClose();
}

const detail = ref<SubmissionDetail | null>(props.initialData ?? null);
const isLoading = ref(false);
const fetchError = ref<string | null>(null);

async function loadSubmissionDetail(id: number) {
    isLoading.value = true;
    fetchError.value = null;
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
        const data = (await res.json()) as { submission: SubmissionDetail };
        detail.value = data.submission;
    } catch (err: any) {
        console.error('Error fetching submission detail:', err);
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
                detail.value = props.initialData;
            } else {
                void loadSubmissionDetail(id);
            }
        } else if (!isOpen) {
            fetchError.value = null;
        }
    },
    { immediate: true },
);

function handleClose() {
    emit('update:open', false);
}

const isEditable = computed(() => {
    if (!detail.value) {
        return false;
    }
    return (
        detail.value.status === 'SUBMITTED' || detail.value.status === 'DRAFT'
    );
});

const isLocked = computed(() => {
    if (!detail.value) {
        return false;
    }
    return (
        detail.value.status === 'APPROVED' ||
        detail.value.status === 'PARTIALLY_APPROVED'
    );
});

const totalCalculatedCost = computed(() => {
    if (!detail.value?.items) {
        return 0;
    }
    return detail.value.items.reduce((sum, item) => {
        return sum + Number(item.total_cost_snapshot || 0);
    }, 0);
});

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
        case 'DRAFT':
            return {
                label: __('Draf'),
                class: 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300',
            };
        default:
            return {
                label: __('Menunggu Review'),
                class: 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300',
            };
    }
}

function isSpklOverdue(dueDateStr?: string | null): boolean {
    if (!dueDateStr) {
        return false;
    }
    const today = new Date().toISOString().slice(0, 10);
    return dueDateStr < today;
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="flex max-h-[90vh] w-full max-w-4xl flex-col p-0 sm:max-w-4xl"
            data-test="submission-detail-modal"
        >
            <!-- Modal Header -->
            <DialogHeader
                class="border-b border-slate-200 p-4 pb-3 sm:p-6 sm:pb-4 dark:border-slate-800"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span
                            class="rounded bg-slate-100 px-2.5 py-1 font-mono text-xs font-bold text-slate-800 dark:bg-slate-800 dark:text-slate-200"
                            data-test="modal-submission-code"
                        >
                            {{ detail?.submission_code ?? 'Loading...' }}
                        </span>
                        <Badge
                            v-if="detail"
                            :class="getStatusBadge(detail.status).class"
                            class="text-[11px]"
                            data-test="modal-status-badge"
                        >
                            {{ getStatusBadge(detail.status).label }}
                        </Badge>
                        <Badge
                            v-if="detail"
                            variant="outline"
                            :class="
                                detail.day_type === 'HKN'
                                    ? 'dark:bg-slate-850 bg-slate-50 text-slate-700 dark:text-slate-300'
                                    : 'bg-red-50 text-[#cc0000] dark:bg-red-950 dark:text-red-300'
                            "
                            class="text-[10px] font-bold"
                        >
                            {{ detail.day_type }}
                        </Badge>
                    </div>

                    <div
                        v-if="detail?.section"
                        class="text-xs text-slate-500 dark:text-slate-400"
                    >
                        <span class="font-medium text-slate-900 dark:text-white"
                            >{{ detail.department?.name }} &bull;
                            {{ detail.section.name }}</span
                        >
                    </div>
                </div>

                <DialogTitle
                    class="mt-2 text-base font-bold text-slate-900 sm:text-lg dark:text-white"
                >
                    {{ __('Rincian Pengajuan Lembur Shift') }}
                </DialogTitle>

                <DialogDescription
                    class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400"
                >
                    <span
                        v-if="detail?.operational_date"
                        class="inline-flex items-center gap-1"
                    >
                        <Calendar class="size-3.5" />
                        <span>{{
                            formatDateIndo(detail.operational_date)
                        }}</span>
                    </span>
                    <span
                        v-if="detail?.submitted_by"
                        class="inline-flex items-center gap-1"
                    >
                        <Users class="size-3.5" />
                        <span
                            >{{ __('Diajukan oleh:') }}
                            <strong
                                class="font-semibold text-slate-700 dark:text-slate-300"
                                >{{ detail.submitted_by.name }} ({{
                                    detail.submitted_by.npk
                                }})</strong
                            ></span
                        >
                    </span>
                </DialogDescription>
            </DialogHeader>

            <!-- Modal Body (Scrollable) -->
            <div class="flex-1 space-y-4 overflow-y-auto p-4 sm:p-6">
                <!-- Loading State -->
                <div
                    v-if="isLoading"
                    class="flex flex-col items-center justify-center py-12 text-slate-400"
                    data-test="modal-loading-state"
                >
                    <RefreshCw class="size-6 animate-spin text-[#cc0000]" />
                    <p class="mt-2 text-xs font-semibold">
                        {{ __('Memuat data rincian pengajuan...') }}
                    </p>
                </div>

                <!-- Error State -->
                <div
                    v-else-if="fetchError"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-xs text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                >
                    <div class="flex items-center gap-2">
                        <AlertCircle class="size-4 text-red-600" />
                        <span>{{ fetchError }}</span>
                    </div>
                </div>

                <div v-else-if="detail" class="space-y-4">
                    <!-- SPKL Status Banner -->
                    <div
                        v-if="detail.spkl_document"
                        class="flex flex-col gap-3 rounded-lg border p-3 text-xs sm:flex-row sm:items-center sm:justify-between"
                        :class="
                            detail.spkl_document.status === 'ATTACHED' ||
                            detail.spkl_document.status === 'VERIFIED'
                                ? 'border-emerald-200 bg-emerald-50/70 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300'
                                : isSpklOverdue(detail.spkl_document.due_date)
                                  ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300'
                                  : 'border-amber-200 bg-amber-50/70 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300'
                        "
                        data-test="modal-spkl-banner"
                    >
                        <div class="flex items-center gap-2">
                            <FileText class="size-4 shrink-0" />
                            <div>
                                <span class="font-bold">
                                    {{
                                        detail.spkl_document.status ===
                                        'ATTACHED'
                                            ? __('📎 SPKL: Dokumen Terlampir')
                                            : detail.spkl_document.status ===
                                                'VERIFIED'
                                              ? __(
                                                    '✅ SPKL: Terverifikasi oleh Manajer',
                                                )
                                              : isSpklOverdue(
                                                      detail.spkl_document
                                                          .due_date,
                                                  )
                                                ? __(
                                                      '⚠️ SPKL: Terlambat Melampirkan Dokumen Fisik',
                                                  )
                                                : __(
                                                      '⏳ SPKL: Belum Dilampirkan (Fleksibel BR-05)',
                                                  )
                                    }}
                                </span>
                                <span
                                    v-if="
                                        detail.spkl_document.due_date &&
                                        detail.spkl_document.status ===
                                            'PENDING'
                                    "
                                    class="ml-2 font-mono text-[11px]"
                                >
                                    ({{
                                        __('Batas waktu: :date', {
                                            date: formatDateIndo(
                                                detail.spkl_document.due_date,
                                            ),
                                        })
                                    }})
                                </span>
                                <div
                                    v-if="detail.spkl_document.spkl_number"
                                    class="mt-0.5 font-mono text-[11px] font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    {{ __('No. Fisik:') }}
                                    {{ detail.spkl_document.spkl_number }}
                                </div>
                            </div>
                        </div>

                        <!-- SPKL Actions -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Download button if file exists -->
                            <a
                                v-if="detail.spkl_document.file_name"
                                :href="
                                    downloadSpklRoute.url({
                                        submission: detail.id,
                                    })
                                "
                                target="_blank"
                                class="inline-flex h-7 items-center gap-1 rounded border border-emerald-300 bg-white px-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-50 dark:border-emerald-800 dark:bg-slate-900 dark:text-emerald-200"
                                data-test="btn-download-spkl"
                            >
                                <Download class="size-3" />
                                <span>{{ __('Unduh SPKL') }}</span>
                            </a>

                            <!-- Manager Verify Button -->
                            <Button
                                v-if="
                                    canVerifySpkl &&
                                    detail.spkl_document.status === 'ATTACHED'
                                "
                                type="button"
                                size="sm"
                                @click="handleVerifySpkl"
                                :disabled="isVerifying"
                                class="h-7 bg-emerald-700 px-2 text-xs font-semibold text-white hover:bg-emerald-800"
                                data-test="btn-modal-verify-spkl"
                            >
                                <CheckCircle2 class="mr-1 size-3" />
                                <span>{{ __('Verifikasi SPKL') }}</span>
                            </Button>

                            <!-- Attach / Replace SPKL Button -->
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="handleAttachSpkl"
                                class="h-7 border-slate-300 bg-white px-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                data-test="btn-modal-attach-spkl"
                            >
                                <Paperclip class="mr-1 size-3" />
                                <span>{{
                                    detail.spkl_document.status === 'PENDING'
                                        ? __('Lampirkan SPKL')
                                        : __('Ganti Berkas')
                                }}</span>
                            </Button>
                        </div>
                    </div>

                    <!-- Batch Notes (if provided) -->
                    <div
                        v-if="detail.submission_notes"
                        class="dark:bg-slate-850 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs dark:border-slate-800"
                        data-test="modal-submission-notes"
                    >
                        <span
                            class="font-semibold text-slate-700 dark:text-slate-300"
                            >{{ __('Catatan Pengajuan Batch:') }}</span
                        >
                        <p class="mt-0.5 text-slate-600 dark:text-slate-400">
                            {{ detail.submission_notes }}
                        </p>
                    </div>

                    <!-- Line Items Table -->
                    <div
                        class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="overflow-x-auto">
                            <table
                                class="w-full border-collapse text-left text-xs"
                            >
                                <thead>
                                    <tr
                                        class="dark:bg-slate-850 border-b border-slate-200 bg-slate-50 text-[11px] font-semibold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:text-slate-400"
                                    >
                                        <th class="w-8 p-2.5 text-center">#</th>
                                        <th class="p-2.5">
                                            {{ __('Karyawan (NPK & Nama)') }}
                                        </th>
                                        <th class="p-2.5 text-right">
                                            {{ __('Produksi') }}
                                        </th>
                                        <th class="p-2.5 text-right">
                                            {{ __('TPM') }}
                                        </th>
                                        <th class="p-2.5 text-right">
                                            {{ __('CapEx') }}
                                        </th>
                                        <th class="p-2.5 text-right">
                                            {{ __('Lainnya') }}
                                        </th>
                                        <th class="p-2.5 text-right font-bold">
                                            {{ __('Total Jam') }}
                                        </th>
                                        <th class="p-2.5 text-right">
                                            {{ __('Tarif Snapshot') }}
                                        </th>
                                        <th class="p-2.5 text-right">
                                            {{ __('Total Biaya') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-slate-800"
                                >
                                    <tr
                                        v-for="(item, idx) in detail.items ??
                                        []"
                                        :key="item.id"
                                        class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/50"
                                        :data-test="`detail-item-row-${item.id}`"
                                    >
                                        <td
                                            class="p-2.5 text-center font-mono text-slate-400"
                                        >
                                            {{ idx + 1 }}
                                        </td>
                                        <td class="p-2.5">
                                            <div
                                                class="font-semibold text-slate-900 dark:text-white"
                                            >
                                                {{
                                                    item.employee?.full_name ??
                                                    item.npk_snapshot
                                                }}
                                            </div>
                                            <div
                                                class="font-mono text-[10px] text-slate-400"
                                            >
                                                {{ item.npk_snapshot }}
                                                <span
                                                    v-if="
                                                        item.employee
                                                            ?.job_position
                                                    "
                                                >
                                                    &bull;
                                                    {{
                                                        item.employee
                                                            .job_position
                                                    }}
                                                </span>
                                            </div>

                                            <!-- RCA / Task notes indicator if present -->
                                            <div
                                                v-if="
                                                    item.rca_category ||
                                                    item.task_description
                                                "
                                                class="mt-1 flex flex-wrap items-center gap-1 text-[10px]"
                                            >
                                                <span
                                                    v-if="item.rca_category"
                                                    class="py-0.2 rounded bg-amber-50 px-1.5 font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                                                >
                                                    RCA: {{ item.rca_category }}
                                                </span>
                                                <span
                                                    v-if="item.task_description"
                                                    class="text-slate-500 italic dark:text-slate-400"
                                                >
                                                    "{{
                                                        item.task_description
                                                    }}"
                                                </span>
                                            </div>

                                            <!-- Policy advisory badge (E03-06, BR-06) -->
                                            <div
                                                v-if="
                                                    item.policy_warning &&
                                                    item.policy_warning
                                                        .level !== 'none'
                                                "
                                                class="mt-1 flex flex-wrap items-center gap-1"
                                            >
                                                <span
                                                    v-if="
                                                        item.policy_warning
                                                            .level === 'danger'
                                                    "
                                                    class="inline-flex items-center gap-1 rounded border border-red-200 bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold text-[#cc0000] dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                                                    :title="
                                                        __(
                                                            'Beban kerja tinggi: :weeks minggu berturut-turut melebihi batas. Bersifat informasi (BR-06).',
                                                            {
                                                                weeks: item
                                                                    .policy_warning
                                                                    .consecutive_weeks,
                                                            },
                                                        )
                                                    "
                                                    :data-test="`detail-policy-warning-${item.id}`"
                                                >
                                                    <span>🔴</span>
                                                    <span
                                                        class="font-mono font-bold tabular-nums"
                                                        >{{
                                                            __(
                                                                'High Workload: :weeks consecutive weeks over limit',
                                                                {
                                                                    weeks: item
                                                                        .policy_warning
                                                                        .consecutive_weeks,
                                                                },
                                                            )
                                                        }}</span
                                                    >
                                                </span>
                                                <span
                                                    v-else-if="
                                                        item.policy_warning
                                                            .level === 'warning'
                                                    "
                                                    class="inline-flex items-center gap-1 rounded border border-amber-300 bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                                                    :title="
                                                        __(
                                                            'Batas mingguan terlampaui (:total/:limit jam) — Bersifat informasi (BR-06).',
                                                            {
                                                                total: Number(
                                                                    item
                                                                        .policy_warning
                                                                        .weekly_total,
                                                                ).toFixed(1),
                                                                limit: Number(
                                                                    item
                                                                        .policy_warning
                                                                        .weekly_limit,
                                                                ).toFixed(1),
                                                            },
                                                        )
                                                    "
                                                    :data-test="`detail-policy-warning-${item.id}`"
                                                >
                                                    <span>⚠️</span>
                                                    <span
                                                        class="font-mono tabular-nums"
                                                        >{{
                                                            __(
                                                                'Weekly limit may be exceeded (:total/:limit hrs)',
                                                                {
                                                                    total: Number(
                                                                        item
                                                                            .policy_warning
                                                                            .weekly_total,
                                                                    ).toFixed(
                                                                        1,
                                                                    ),
                                                                    limit: Number(
                                                                        item
                                                                            .policy_warning
                                                                            .weekly_limit,
                                                                    ).toFixed(
                                                                        1,
                                                                    ),
                                                                },
                                                            )
                                                        }}</span
                                                    >
                                                </span>
                                            </div>
                                        </td>
                                        <td
                                            class="p-2.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                        >
                                            {{
                                                Number(
                                                    item.hours_production,
                                                ).toFixed(1)
                                            }}
                                        </td>
                                        <td
                                            class="p-2.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                        >
                                            {{
                                                Number(item.hours_tpm).toFixed(
                                                    1,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="p-2.5 text-right font-mono tabular-nums"
                                        >
                                            <div
                                                :class="
                                                    Number(item.hours_project) >
                                                    0
                                                        ? 'font-bold text-sky-700 dark:text-sky-300'
                                                        : 'text-slate-700 dark:text-slate-300'
                                                "
                                            >
                                                {{
                                                    Number(
                                                        item.hours_project,
                                                    ).toFixed(1)
                                                }}
                                            </div>
                                            <div
                                                v-if="
                                                    Number(item.hours_project) >
                                                        0 && item.capex_project
                                                "
                                                class="py-0.2 mt-0.5 inline-flex items-center gap-0.5 rounded bg-sky-100 px-1 font-mono text-[9px] font-bold text-sky-800 dark:bg-sky-950 dark:text-sky-300"
                                            >
                                                <FolderKanban
                                                    class="size-2.5 text-sky-600 dark:text-sky-400"
                                                />
                                                {{
                                                    item.capex_project
                                                        .project_code
                                                }}
                                            </div>
                                        </td>
                                        <td
                                            class="p-2.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                        >
                                            {{
                                                Number(
                                                    item.hours_others,
                                                ).toFixed(1)
                                            }}
                                        </td>
                                        <td
                                            class="p-2.5 text-right font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                                        >
                                            {{
                                                Number(
                                                    item.total_hours,
                                                ).toFixed(1)
                                            }}
                                        </td>
                                        <td
                                            class="p-2.5 text-right font-mono text-[11px] text-slate-500 tabular-nums"
                                        >
                                            {{
                                                formatRupiah(
                                                    Number(
                                                        item.hourly_rate_snapshot,
                                                    ),
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="p-2.5 text-right font-mono font-bold text-[#cc0000] tabular-nums dark:text-red-400"
                                        >
                                            {{
                                                formatRupiah(
                                                    Number(
                                                        item.total_cost_snapshot,
                                                    ),
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Summary Totals Strip -->
                    <div
                        class="dark:bg-slate-850 flex flex-wrap items-center justify-between gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800"
                        data-test="modal-summary-strip"
                    >
                        <div class="flex items-center gap-4">
                            <div>
                                <div
                                    class="text-[10px] font-semibold text-slate-500 uppercase"
                                >
                                    {{ __('Total Tenaga Kerja') }}
                                </div>
                                <div
                                    class="font-mono text-sm font-bold text-slate-900 tabular-nums dark:text-white"
                                >
                                    {{ detail.items?.length ?? 0 }}
                                    <span
                                        class="text-xs font-normal text-slate-500"
                                        >{{ __('Orang') }}</span
                                    >
                                </div>
                            </div>
                            <div
                                class="h-8 w-px bg-slate-200 dark:bg-slate-700"
                            />
                            <div>
                                <div
                                    class="text-[10px] font-semibold text-slate-500 uppercase"
                                >
                                    {{ __('Total Jam Lembur') }}
                                </div>
                                <div
                                    class="font-mono text-sm font-bold text-slate-900 tabular-nums dark:text-white"
                                >
                                    {{
                                        Number(
                                            detail.total_hours_cached,
                                        ).toFixed(1)
                                    }}
                                    <span
                                        class="text-xs font-normal text-slate-500"
                                        >{{ __('Jam') }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="text-right">
                            <div
                                class="text-[10px] font-semibold text-slate-500 uppercase"
                            >
                                {{ __('Total Estimasi Biaya (Terkunci)') }}
                            </div>
                            <div
                                class="font-mono text-base font-bold text-[#cc0000] tabular-nums dark:text-red-400"
                                data-test="modal-total-cost"
                            >
                                {{ formatRupiah(totalCalculatedCost) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <DialogFooter
                class="flex flex-wrap items-center justify-between border-t border-slate-200 p-4 sm:p-6 dark:border-slate-800"
            >
                <div>
                    <!-- Locked Status Warning (Approved or Partially Approved) -->
                    <div
                        v-if="isLocked"
                        class="inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400"
                        data-test="modal-locked-notice"
                    >
                        <Lock class="size-3.5 text-amber-500" />
                        <span>{{
                            __(
                                'Pengajuan sudah diproses oleh Manajer dan terkunci permanen.',
                            )
                        }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="handleClose"
                        data-test="btn-close-modal"
                    >
                        {{ __('Tutup') }}
                    </Button>

                    <!-- Edit Button for SUBMITTED / DRAFT -->
                    <Link
                        v-if="isEditable && detail"
                        :href="editRoute.url(detail.id)"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md bg-[#cc0000] px-4 text-xs font-bold text-white shadow-xs hover:bg-[#b30000] active:scale-95"
                        data-test="btn-modal-edit"
                    >
                        <Pencil class="size-3.5" />
                        <span>{{ __('Edit Pengajuan') }}</span>
                    </Link>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
