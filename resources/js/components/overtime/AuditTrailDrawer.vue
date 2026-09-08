<script setup lang="ts">
import {
    AlertCircle,
    ArrowRight,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Clock,
    FileSpreadsheet,
    FileText,
    History,
    KeyRound,
    Lock,
    ShieldAlert,
    ShieldCheck,
    Unlock,
    User as UserIcon,
    X,
    XCircle,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/composables/useTrans';
import { formatDateIndo, formatRupiah } from '@/lib/formatters';
import { audit as auditRoute } from '@/routes/overtime/items';

export interface AuditItemDetails {
    id: number;
    submission_id: number;
    submission_code?: string;
    operational_date?: string;
    day_type?: 'HKN' | 'HLR';
    department?: { id: number; name: string; code: string } | null;
    section?: { id: number; name: string; code: string } | null;
    employee?: {
        id: number;
        npk: string;
        full_name: string;
        job_position: string;
    } | null;
    npk_snapshot: string;
    status: string;
    total_hours: number | string;
    hours_production?: number | string;
    hours_tpm?: number | string;
    hours_project?: number | string;
    hours_others?: number | string;
    total_cost_snapshot?: number | string;
    rejection_reason?: string | null;
    lock_version?: number;
    capex_project?: {
        id: number;
        project_code: string;
        name: string;
    } | null;
}

export interface AuditActor {
    id: number;
    name: string;
    npk: string;
    role: string;
}

export interface AuditRecord {
    id: number;
    action: string;
    actor: AuditActor | null;
    previous_state: Record<string, unknown> | null;
    new_state: Record<string, unknown> | null;
    notes: string | null;
    ip_address: string | null;
    created_at: string | null;
    created_at_human: string | null;
}

export interface AuditDiffField {
    key: string;
    label: string;
    oldValue: string;
    newValue: string;
}

const props = defineProps<{
    open: boolean;
    itemId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const { __ } = useTrans();

const isLoading = ref(false);
const fetchError = ref<string | null>(null);
const itemData = ref<AuditItemDetails | null>(null);
const audits = ref<AuditRecord[]>([]);
const expandedMetadata = reactive<Record<number, boolean>>({});

function toggleMetadata(auditId: number) {
    expandedMetadata[auditId] = !expandedMetadata[auditId];
}

async function fetchAuditTrail(id: number) {
    isLoading.value = true;
    fetchError.value = null;

    try {
        const url = auditRoute.url({ item: id });
        const res = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!res.ok) {
            if (res.status === 403) {
                throw new Error(
                    __(
                        'Anda tidak memiliki wewenang untuk melihat riwayat audit item ini.',
                    ),
                );
            }
            throw new Error(__('Gagal memuat data riwayat audit dari server.'));
        }

        const data = await res.json();
        itemData.value = data.item;
        audits.value = data.audits ?? [];
    } catch (err: unknown) {
        console.error('Error fetching audit trail:', err);
        fetchError.value =
            err instanceof Error
                ? err.message
                : __('Terjadi kesalahan saat memuat riwayat audit.');
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => [props.open, props.itemId] as const,
    ([isOpen, id]) => {
        if (isOpen && id) {
            fetchAuditTrail(id);
        } else if (!isOpen) {
            itemData.value = null;
            audits.value = [];
            fetchError.value = null;
        }
    },
    { immediate: true },
);

function getStatusBadge(status: string) {
    const s = (status || '').toUpperCase();
    if (s === 'APPROVED') {
        return {
            label: __('Disetujui'),
            classes:
                'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
        };
    }
    if (s === 'REJECTED') {
        return {
            label: __('Ditolak'),
            classes:
                'bg-red-100 text-red-800 border-red-300 dark:bg-red-950/60 dark:text-red-300 dark:border-red-800',
        };
    }
    if (s === 'PARTIALLY_APPROVED') {
        return {
            label: __('Disetujui Sebagian'),
            classes:
                'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
        };
    }
    return {
        label: __('Menunggu Review'),
        classes:
            'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800',
    };
}

function getActionMeta(action: string) {
    const a = (action || '').toUpperCase();
    switch (a) {
        case 'SUBMITTED':
            return {
                label: __('Diajukan'),
                colorClass:
                    'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/50 dark:text-blue-300 dark:border-blue-800',
                icon: FileText,
            };
        case 'APPROVED':
            return {
                label: __('Disetujui'),
                colorClass:
                    'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800',
                icon: CheckCircle2,
            };
        case 'REJECTED':
            return {
                label: __('Ditolak'),
                colorClass:
                    'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/50 dark:text-red-300 dark:border-red-800',
                icon: XCircle,
            };
        case 'ADMIN_UNLOCK':
            return {
                label: __('Buka Kunci Admin'),
                colorClass:
                    'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800',
                icon: Unlock,
            };
        case 'EXPORT':
            return {
                label: __('Ekspor Data'),
                colorClass:
                    'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/50 dark:text-purple-300 dark:border-purple-800',
                icon: FileSpreadsheet,
            };
        default:
            return {
                label: action,
                colorClass:
                    'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
                icon: History,
            };
    }
}

function formatStatusValue(val: unknown): string {
    const s = String(val ?? '').toUpperCase();
    if (s === 'APPROVED') return __('Disetujui');
    if (s === 'REJECTED') return __('Ditolak');
    if (s === 'PENDING') return __('Pending');
    if (s === 'SUBMITTED') return __('Menunggu Review');
    return String(val ?? '-');
}

function formatFieldValue(key: string, val: unknown): string {
    if (val === null || val === undefined || val === '') {
        return '-';
    }
    if (key === 'status') {
        return formatStatusValue(val);
    }
    if (key === 'total_cost_snapshot' || key === 'hourly_rate_snapshot') {
        return formatRupiah(val as number | string, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        });
    }
    if (
        key === 'hours_production' ||
        key === 'hours_tpm' ||
        key === 'hours_project' ||
        key === 'hours_others'
    ) {
        return `${Number(val).toFixed(1)} jam`;
    }
    return String(val);
}

const FIELD_LABELS: Record<string, string> = {
    status: 'Status',
    rejection_reason: 'Alasan Penolakan',
    lock_version: 'Versi Kunci (Lock Version)',
    total_cost_snapshot: 'Estimasi Biaya',
    hourly_rate_snapshot: 'Tarif Upah Per Jam',
    hours_production: 'Jam Produksi',
    hours_tpm: 'Jam TPM',
    hours_project: 'Jam CapEx Proyek',
    hours_others: 'Jam Lainnya',
    task_description: 'Deskripsi Tugas',
    rca_category: 'Kategori RCA',
    rca_notes: 'Catatan RCA',
    reviewed_at: 'Waktu Review',
    reviewed_by_user_id: 'ID Peninjau',
};

function getFieldLabel(key: string): string {
    return FIELD_LABELS[key] ?? key;
}

function computeDiff(
    previousState: Record<string, unknown> | null,
    newState: Record<string, unknown> | null,
): AuditDiffField[] {
    if (!newState) {
        return [];
    }

    // Initial creation / submission
    if (!previousState) {
        const initialKeys = [
            'status',
            'hours_production',
            'hours_tpm',
            'hours_project',
            'hours_others',
            'total_cost_snapshot',
            'task_description',
        ];
        const diffs: AuditDiffField[] = [];
        for (const k of initialKeys) {
            if (newState[k] !== undefined && newState[k] !== null) {
                diffs.push({
                    key: k,
                    label: getFieldLabel(k),
                    oldValue: '-',
                    newValue: formatFieldValue(k, newState[k]),
                });
            }
        }
        return diffs;
    }

    const diffs: AuditDiffField[] = [];
    const ignoredKeys = new Set([
        'id',
        'overtime_submission_id',
        'employee_id',
        'created_at',
        'updated_at',
        'policy_warning',
    ]);

    for (const key of Object.keys(newState)) {
        if (ignoredKeys.has(key)) {
            continue;
        }

        const oldVal = previousState[key];
        const newVal = newState[key];

        // Deep/loose comparison
        const oldStr = JSON.stringify(oldVal);
        const newStr = JSON.stringify(newVal);

        if (oldStr !== newStr) {
            diffs.push({
                key,
                label: getFieldLabel(key),
                oldValue: formatFieldValue(key, oldVal),
                newValue: formatFieldValue(key, newVal),
            });
        }
    }

    return diffs;
}
</script>

<template>
    <Sheet
        :open="open"
        @update:open="(val: boolean) => emit('update:open', val)"
    >
        <SheetContent
            side="right"
            class="flex h-full w-full flex-col overflow-hidden p-0 sm:max-w-xl"
            data-test="audit-trail-drawer"
        >
            <!-- Drawer Header -->
            <SheetHeader
                class="border-b border-slate-200 bg-slate-50/70 p-5 dark:border-slate-800 dark:bg-slate-900/60"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <History class="size-5 text-[#cc0000]" />
                            <SheetTitle
                                class="text-base font-bold text-slate-900 dark:text-white"
                            >
                                {{ __('Riwayat Perubahan Item') }}
                            </SheetTitle>
                        </div>
                        <SheetDescription
                            class="text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{
                                __(
                                    'Jejak audit permanen (immutable ledger) mencatat setiap keputusan dan perubahan status karyawan.',
                                )
                            }}
                        </SheetDescription>
                    </div>

                    <!-- Close Button inside Header -->
                    <SheetClose
                        class="rounded-md p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        data-test="btn-close-audit-drawer"
                    >
                        <X class="size-4" />
                        <span class="sr-only">{{ __('Tutup') }}</span>
                    </SheetClose>
                </div>

                <!-- Item Summary Card -->
                <div
                    v-if="itemData"
                    class="mt-3 rounded-lg border border-slate-200 bg-white p-3 shadow-2xs dark:border-slate-800 dark:bg-slate-950"
                    data-test="audit-item-summary"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span
                                    class="font-mono text-xs font-bold text-slate-900 dark:text-white"
                                    data-test="audit-item-npk"
                                >
                                    {{ itemData.npk_snapshot }}
                                </span>
                                <span class="text-xs text-slate-400">•</span>
                                <span
                                    class="truncate text-xs font-semibold text-slate-800 dark:text-slate-200"
                                    data-test="audit-item-name"
                                >
                                    {{
                                        itemData.employee?.full_name ??
                                        itemData.npk_snapshot
                                    }}
                                </span>
                            </div>
                            <div
                                class="mt-0.5 flex flex-wrap items-center gap-2 text-[11px] text-slate-500"
                            >
                                <span class="font-mono">{{
                                    itemData.submission_code
                                }}</span>
                                <span v-if="itemData.section"
                                    >({{ itemData.section.name }})</span
                                >
                                <span
                                    v-if="itemData.operational_date"
                                    class="font-mono"
                                >
                                    {{
                                        formatDateIndo(
                                            itemData.operational_date,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <span
                                class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold"
                                :class="getStatusBadge(itemData.status).classes"
                                data-test="audit-item-current-status"
                            >
                                {{ getStatusBadge(itemData.status).label }}
                            </span>
                        </div>
                    </div>
                </div>
            </SheetHeader>

            <!-- Scrollable Timeline Body -->
            <div
                class="flex-1 overflow-y-auto p-5"
                data-test="audit-timeline-container"
            >
                <!-- Loading State -->
                <div
                    v-if="isLoading"
                    class="flex flex-col items-center justify-center py-12 text-center"
                    data-test="audit-loading-state"
                >
                    <Spinner class="size-8 text-[#cc0000]" />
                    <p class="mt-3 text-xs text-slate-500">
                        {{ __('Memuat linimasa riwayat audit...') }}
                    </p>
                </div>

                <!-- Error State -->
                <div
                    v-else-if="fetchError"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-xs text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300"
                    data-test="audit-error-state"
                >
                    <div class="flex items-center gap-2 font-bold">
                        <AlertCircle class="size-4 shrink-0 text-red-600" />
                        <span>{{ __('Gagal Memuat Riwayat') }}</span>
                    </div>
                    <p class="mt-1.5">{{ fetchError }}</p>
                    <Button
                        v-if="itemId"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="mt-3 text-xs"
                        @click="fetchAuditTrail(itemId)"
                    >
                        {{ __('Coba Lagi') }}
                    </Button>
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="audits.length === 0"
                    class="flex flex-col items-center justify-center py-12 text-center"
                    data-test="audit-empty-state"
                >
                    <History
                        class="size-10 text-slate-300 dark:text-slate-600"
                    />
                    <p
                        class="mt-2 text-xs font-semibold text-slate-700 dark:text-slate-300"
                    >
                        {{ __('Belum Ada Riwayat Perubahan') }}
                    </p>
                    <p class="text-[11px] text-slate-500">
                        {{
                            __('Item ini belum mencatat rekaman riwayat audit.')
                        }}
                    </p>
                </div>

                <!-- Chronological Audit Timeline -->
                <div
                    v-else
                    class="relative space-y-6 pl-6 before:absolute before:top-2 before:bottom-2 before:left-2.5 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-800"
                >
                    <div
                        v-for="(audit, index) in audits"
                        :key="audit.id"
                        class="relative"
                        :data-test="`audit-event-row-${audit.id}`"
                    >
                        <!-- Timeline Node Dot -->
                        <div
                            class="absolute top-1 -left-6 flex size-5 items-center justify-center rounded-full border-2 border-white bg-slate-900 text-white shadow-xs dark:border-slate-950 dark:bg-slate-100 dark:text-slate-900"
                            :class="{
                                'bg-[#cc0000]! text-white!':
                                    audit.action === 'REJECTED',
                                'bg-emerald-600! text-white!':
                                    audit.action === 'APPROVED',
                                'bg-blue-600! text-white!':
                                    audit.action === 'SUBMITTED',
                                'bg-amber-600! text-white!':
                                    audit.action === 'ADMIN_UNLOCK',
                            }"
                        >
                            <component
                                :is="getActionMeta(audit.action).icon"
                                class="size-2.5"
                            />
                        </div>

                        <!-- Audit Card -->
                        <div
                            class="rounded-lg border border-slate-200 bg-white p-3.5 shadow-2xs transition-all dark:border-slate-800 dark:bg-slate-900/90"
                            :data-test="`audit-card-${audit.id}`"
                        >
                            <!-- Card Header: Action & Timestamp -->
                            <div
                                class="flex flex-wrap items-center justify-between gap-1.5 border-b border-slate-100 pb-2 dark:border-slate-800/80"
                            >
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[11px] font-bold"
                                        :class="
                                            getActionMeta(audit.action)
                                                .colorClass
                                        "
                                        :data-test="`audit-action-badge-${audit.id}`"
                                    >
                                        {{ getActionMeta(audit.action).label }}
                                    </span>

                                    <span
                                        v-if="index === 0"
                                        class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        {{ __('Terbaru') }}
                                    </span>
                                </div>

                                <div
                                    class="flex items-center gap-1 font-mono text-[11px] text-slate-500 tabular-nums"
                                >
                                    <Clock class="size-3 text-slate-400" />
                                    <span
                                        :data-test="`audit-timestamp-${audit.id}`"
                                    >
                                        {{
                                            audit.created_at_human ??
                                            audit.created_at
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- Actor Information -->
                            <div
                                class="mt-2.5 flex items-center justify-between text-xs"
                            >
                                <div class="flex items-center gap-1.5">
                                    <UserIcon
                                        class="size-3.5 shrink-0 text-slate-400"
                                    />
                                    <div class="flex items-center gap-1">
                                        <span
                                            class="font-semibold text-slate-800 dark:text-slate-200"
                                            :data-test="`audit-actor-name-${audit.id}`"
                                        >
                                            {{
                                                audit.actor?.name ??
                                                __('Sistem Otomatis')
                                            }}
                                        </span>
                                        <span
                                            v-if="audit.actor?.npk"
                                            class="font-mono text-[10px] text-slate-500"
                                            :data-test="`audit-actor-npk-${audit.id}`"
                                        >
                                            ({{ audit.actor.npk }})
                                        </span>
                                    </div>
                                </div>

                                <span
                                    v-if="audit.actor?.role"
                                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 uppercase dark:bg-slate-800 dark:text-slate-300"
                                    :data-test="`audit-actor-role-${audit.id}`"
                                >
                                    {{ audit.actor.role }}
                                </span>
                            </div>

                            <!-- Summary Note / Rejection Reason -->
                            <div
                                v-if="audit.notes"
                                class="mt-2.5 rounded-md border p-2 text-xs"
                                :class="
                                    audit.action === 'REJECTED'
                                        ? 'border-red-200 bg-red-50/70 text-red-900 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200'
                                        : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300'
                                "
                                :data-test="`audit-note-${audit.id}`"
                            >
                                <div
                                    class="text-[10px] font-bold tracking-wider uppercase"
                                    :class="
                                        audit.action === 'REJECTED'
                                            ? 'text-red-700 dark:text-red-400'
                                            : 'text-slate-500'
                                    "
                                >
                                    {{
                                        audit.action === 'REJECTED'
                                            ? __('Alasan Penolakan:')
                                            : __('Catatan:')
                                    }}
                                </div>
                                <p class="mt-0.5 leading-relaxed font-medium">
                                    {{ audit.notes }}
                                </p>
                            </div>

                            <!-- Visual State Diff Section -->
                            <div
                                class="mt-3"
                                :data-test="`audit-diff-container-${audit.id}`"
                            >
                                <div
                                    class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                >
                                    {{
                                        audit.previous_state
                                            ? __(
                                                  'Perubahan Nilai (State Diff):',
                                              )
                                            : __('Nilai Awal (Initial State):')
                                    }}
                                </div>

                                <div class="mt-1.5 space-y-1.5">
                                    <div
                                        v-for="diff in computeDiff(
                                            audit.previous_state,
                                            audit.new_state,
                                        )"
                                        :key="diff.key"
                                        class="dark:bg-slate-850 flex items-center justify-between gap-2 rounded border border-slate-100 bg-slate-50/60 px-2 py-1 text-xs dark:border-slate-800"
                                        :data-test="`diff-row-${audit.id}-${diff.key}`"
                                    >
                                        <span
                                            class="font-medium text-slate-600 dark:text-slate-300"
                                        >
                                            {{ diff.label }}
                                        </span>

                                        <div
                                            class="flex items-center gap-1.5 font-mono text-[11px] tabular-nums"
                                        >
                                            <span
                                                v-if="audit.previous_state"
                                                class="rounded bg-red-50 px-1.5 py-0.5 text-red-700 line-through dark:bg-red-950/40 dark:text-red-400"
                                                :data-test="`diff-old-${audit.id}-${diff.key}`"
                                            >
                                                {{ diff.oldValue }}
                                            </span>
                                            <ArrowRight
                                                v-if="audit.previous_state"
                                                class="size-3 text-slate-400"
                                            />
                                            <span
                                                class="rounded bg-emerald-50 px-1.5 py-0.5 font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                                                :data-test="`diff-new-${audit.id}-${diff.key}`"
                                            >
                                                {{ diff.newValue }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Collapsible Technical Metadata & IP Address -->
                            <div
                                class="mt-3 border-t border-slate-100 pt-2 dark:border-slate-800"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 transition-colors hover:text-slate-800 dark:hover:text-slate-200"
                                    :data-test="`toggle-metadata-${audit.id}`"
                                    @click="toggleMetadata(audit.id)"
                                >
                                    <component
                                        :is="
                                            expandedMetadata[audit.id]
                                                ? ChevronDown
                                                : ChevronRight
                                        "
                                        class="size-3"
                                    />
                                    <span>{{
                                        __('Detail Teknis & Metadata')
                                    }}</span>
                                    <span
                                        v-if="audit.ip_address"
                                        class="font-mono text-[10px] text-slate-400"
                                    >
                                        ({{ audit.ip_address }})
                                    </span>
                                </button>

                                <div
                                    v-if="expandedMetadata[audit.id]"
                                    class="mt-2 space-y-1.5 rounded-md bg-slate-900 p-2 font-mono text-[10px] text-slate-200 dark:bg-slate-950"
                                    :data-test="`metadata-body-${audit.id}`"
                                >
                                    <div
                                        class="flex justify-between border-b border-slate-800 pb-1"
                                    >
                                        <span class="text-slate-400"
                                            >IP Address:</span
                                        >
                                        <span>{{
                                            audit.ip_address ?? 'N/A'
                                        }}</span>
                                    </div>
                                    <div
                                        class="flex justify-between border-b border-slate-800 pb-1"
                                    >
                                        <span class="text-slate-400"
                                            >Audit ID:</span
                                        >
                                        <span>#{{ audit.id }}</span>
                                    </div>
                                    <div
                                        class="flex justify-between border-b border-slate-800 pb-1"
                                    >
                                        <span class="text-slate-400"
                                            >Raw Timestamp:</span
                                        >
                                        <span>{{ audit.created_at }}</span>
                                    </div>
                                    <div class="pt-1">
                                        <span
                                            class="mb-0.5 block text-slate-400"
                                            >New State JSON:</span
                                        >
                                        <pre
                                            class="max-h-24 overflow-x-auto text-[9px] text-emerald-400"
                                            >{{
                                                JSON.stringify(
                                                    audit.new_state,
                                                    null,
                                                    2,
                                                )
                                            }}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Drawer Footer with Immutable Guarantee Notice -->
            <SheetFooter
                class="border-t border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex w-full flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div
                        class="flex items-center gap-1.5 text-[11px] text-slate-500"
                    >
                        <ShieldCheck class="size-4 shrink-0 text-emerald-600" />
                        <span class="font-medium">
                            {{
                                __(
                                    'Catatan audit bersifat permanen dan tidak dapat diubah (Immutable Ledger).',
                                )
                            }}
                        </span>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-xs"
                        data-test="btn-footer-close-audit"
                        @click="emit('update:open', false)"
                    >
                        {{ __('Tutup') }}
                    </Button>
                </div>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
