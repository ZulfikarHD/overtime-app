<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Bot, ChevronDown, ChevronUp, Clock, Eye, Unlock } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTrans } from '@/composables/useTrans';
import { formatDateIndo, formatRupiah } from '@/lib/formatters';
import type { User } from '@/types';

export interface QueueItemEmployee {
    id: number;
    npk?: string | null;
    full_name?: string | null;
    job_position?: string | null;
}

export interface QueueItemCapex {
    id: number;
    project_code: string;
    name: string;
}

export interface QueueItemAnomaly {
    id: number;
    anomaly_score?: string | number;
    anomaly_reasons?: string[] | Record<string, unknown>;
    is_dismissed?: boolean;
}

export interface QueueItem {
    id: number;
    npk_snapshot: string;
    hours_production: string | number;
    hours_tpm: string | number;
    hours_project: string | number;
    hours_others: string | number;
    total_hours: string | number;
    total_cost_snapshot: string | number;
    status: string;
    task_description?: string | null;
    employee?: QueueItemEmployee | null;
    capex_project?: QueueItemCapex | null;
    anomaly_logs?: QueueItemAnomaly[];
}

export interface QueueSubmission {
    id: number;
    submission_code: string;
    operational_date: string;
    day_type: 'HKN' | 'HLR';
    status:
        | 'DRAFT'
        | 'SUBMITTED'
        | 'PARTIALLY_APPROVED'
        | 'APPROVED'
        | 'REJECTED';
    total_hours_cached: string | number;
    total_cost_cached?: string | number | null;
    items_count?: number;
    anomaly_count?: number;
    section?: { id: number; name: string; code: string } | null;
    department?: { id: number; name: string; code: string } | null;
    submitted_by?: { id: number; name: string; npk: string } | null;
    spkl_document?: {
        id: number;
        status: string;
        due_date: string;
        spkl_number?: string | null;
    } | null;
    items?: QueueItem[];
}

const props = defineProps<{
    submission: QueueSubmission;
    selected?: boolean;
    isAdmin?: boolean;
}>();

const emit = defineEmits<{
    toggleSelect: [id: number];
    review: [id: number];
    unlock: [id: number];
}>();

const { __ } = useTrans();
const page = usePage();
const currentUser = computed(() => page.props.auth?.user as User | undefined);
const userIsAdmin = computed(
    () => props.isAdmin ?? currentUser.value?.role === 'admin',
);
const isLocked = computed(
    () =>
        props.submission.status === 'APPROVED' ||
        props.submission.status === 'PARTIALLY_APPROVED',
);
const expanded = ref(false);

const items = computed(() => props.submission.items ?? []);
const anomalyCount = computed(() =>
    Number(props.submission.anomaly_count ?? 0),
);
const itemCount = computed(() =>
    Number(props.submission.items_count ?? items.value.length),
);

function toNumber(value: string | number | null | undefined): number {
    if (value === null || value === undefined || value === '') {
        return 0;
    }
    const n = typeof value === 'string' ? parseFloat(value) : value;
    return Number.isNaN(n) ? 0 : n;
}

function formatHours(value: string | number | null | undefined): string {
    return toNumber(value).toFixed(1);
}

function isSpklOverdue(dueDateStr?: string | null): boolean {
    if (!dueDateStr) {
        return false;
    }
    const today = new Date().toISOString().slice(0, 10);
    return dueDateStr < today;
}

function getSpklBadge(submission: QueueSubmission) {
    const doc = submission.spkl_document;
    if (!doc) {
        return {
            label: __('Belum Dilampirkan'),
            class: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-900',
            overdue: false,
        };
    }

    if (doc.status === 'PENDING' && isSpklOverdue(doc.due_date)) {
        return {
            label: __('⚠️ Terlambat'),
            class: 'bg-red-50 text-[#cc0000] border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900',
            overdue: true,
        };
    }

    if (doc.status === 'PENDING') {
        return {
            label: __('Pending'),
            class: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-900',
            overdue: false,
        };
    }

    if (doc.status === 'ATTACHED' || doc.status === 'VERIFIED') {
        return {
            label: __('Terlampir'),
            class: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-900',
            overdue: false,
        };
    }

    return {
        label: doc.status,
        class: 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300',
        overdue: false,
    };
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
                class: 'bg-sky-50 text-sky-800 border-sky-200 dark:bg-sky-950/60 dark:text-sky-300',
            };
    }
}

const spklBadge = computed(() => getSpklBadge(props.submission));
const statusBadge = computed(() => getStatusBadge(props.submission.status));
</script>

<template>
    <tbody
        class="border-b border-slate-100 dark:border-slate-800"
        :data-test="`approval-row-${submission.id}`"
    >
        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
            <td class="px-3 py-2.5 text-center">
                <input
                    type="checkbox"
                    class="rounded border-slate-300 text-[#cc0000] focus:ring-[#cc0000]"
                    :checked="selected"
                    :data-test="`approval-row-checkbox-${submission.id}`"
                    @change="emit('toggleSelect', submission.id)"
                />
            </td>
            <td class="px-3 py-2.5">
                <div
                    class="font-mono text-xs font-bold text-slate-900 dark:text-white"
                >
                    {{ submission.submission_code }}
                </div>
                <div class="mt-0.5 flex items-center gap-1.5">
                    <span
                        class="font-mono text-[11px] text-slate-500 tabular-nums"
                    >
                        {{ formatDateIndo(submission.operational_date) }}
                    </span>
                    <span
                        class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold"
                        :class="
                            submission.day_type === 'HLR'
                                ? 'border border-rose-200 bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'
                                : 'border border-slate-200 bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                        "
                    >
                        {{ submission.day_type }}
                    </span>
                </div>
            </td>
            <td class="px-3 py-2.5">
                <div
                    class="text-xs font-semibold text-slate-800 dark:text-slate-100"
                >
                    {{ submission.section?.name ?? '-' }}
                </div>
                <div class="text-[11px] text-slate-500">
                    {{ submission.submitted_by?.name ?? '-' }}
                </div>
            </td>
            <td class="px-3 py-2.5 text-right">
                <div
                    class="font-mono text-xs font-bold text-slate-900 tabular-nums dark:text-white"
                >
                    {{ formatHours(submission.total_hours_cached) }}
                    {{ __('jam') }}
                </div>
                <div class="text-[11px] text-slate-500 tabular-nums">
                    {{ itemCount }} {{ __('Org') }}
                </div>
            </td>
            <td class="px-3 py-2.5 text-right">
                <div
                    class="font-mono text-xs font-semibold text-slate-900 tabular-nums dark:text-white"
                >
                    {{
                        formatRupiah(submission.total_cost_cached, {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0,
                        })
                    }}
                </div>
            </td>
            <td class="px-3 py-2.5">
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <span
                                class="inline-flex cursor-help items-center gap-1 rounded border px-2 py-0.5 text-[10px] font-semibold"
                                :class="spklBadge.class"
                                data-test="spkl-status-badge"
                            >
                                <Clock
                                    v-if="
                                        submission.spkl_document?.status ===
                                        'PENDING'
                                    "
                                    class="size-3"
                                />
                                {{ spklBadge.label }}
                            </span>
                        </TooltipTrigger>
                        <TooltipContent class="max-w-xs text-xs">
                            {{
                                __(
                                    'SPKL pending tidak menghambat persetujuan lembur operasional.',
                                )
                            }}
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </td>
            <td class="px-3 py-2.5">
                <div class="flex flex-col items-start gap-1">
                    <Badge
                        variant="outline"
                        class="text-[10px] font-semibold"
                        :class="statusBadge.class"
                        data-test="submission-status-badge"
                    >
                        {{ statusBadge.label }}
                    </Badge>
                    <span
                        v-if="anomalyCount > 0"
                        class="inline-flex items-center gap-1 rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[10px] font-semibold text-violet-800 dark:border-violet-900 dark:bg-violet-950/50 dark:text-violet-300"
                        data-test="ml-anomaly-badge"
                    >
                        <Bot class="size-3" />
                        {{
                            __('🤖 :count anomali flagged', {
                                count: anomalyCount,
                            })
                        }}
                    </span>
                </div>
            </td>
            <td class="px-3 py-2.5 text-right">
                <div class="inline-flex items-center gap-1.5">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-8 px-2 text-slate-500"
                        :data-test="`expand-row-${submission.id}`"
                        :aria-expanded="expanded"
                        @click="expanded = !expanded"
                    >
                        <ChevronUp v-if="expanded" class="size-4" />
                        <ChevronDown v-else class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="h-8 bg-[#cc0000] px-2.5 text-xs font-semibold text-white hover:bg-[#b30000]"
                        :data-test="`review-btn-${submission.id}`"
                        @click="emit('review', submission.id)"
                    >
                        <Eye class="mr-1 size-3.5" />
                        {{ __('Tinjau') }}
                    </Button>
                    <Button
                        v-if="userIsAdmin && isLocked"
                        type="button"
                        size="sm"
                        variant="outline"
                        class="h-8 border-amber-300 bg-amber-50 px-2.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-300"
                        :data-test="`unlock-btn-${submission.id}`"
                        @click="emit('unlock', submission.id)"
                    >
                        <Unlock class="mr-1 size-3.5" />
                        {{ __('Buka Kunci') }}
                    </Button>
                </div>
            </td>
        </tr>

        <tr v-if="expanded" data-test="inline-row-summary">
            <td
                colspan="8"
                class="bg-slate-50/80 px-4 py-3 dark:bg-slate-900/60"
            >
                <div
                    class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800"
                >
                    <table class="w-full text-left text-[11px]">
                        <thead>
                            <tr
                                class="border-b border-slate-200 bg-white text-slate-500 dark:border-slate-800 dark:bg-slate-950"
                            >
                                <th class="px-2.5 py-2 font-semibold">
                                    {{ __('NPK') }}
                                </th>
                                <th class="px-2.5 py-2 font-semibold">
                                    {{ __('Nama') }}
                                </th>
                                <th
                                    class="px-2.5 py-2 text-right font-semibold"
                                >
                                    {{ __('Prod') }}
                                </th>
                                <th
                                    class="px-2.5 py-2 text-right font-semibold"
                                >
                                    {{ __('TPM') }}
                                </th>
                                <th
                                    class="px-2.5 py-2 text-right font-semibold"
                                >
                                    {{ __('CapEx') }}
                                </th>
                                <th
                                    class="px-2.5 py-2 text-right font-semibold"
                                >
                                    {{ __('Lainnya') }}
                                </th>
                                <th
                                    class="px-2.5 py-2 text-right font-semibold"
                                >
                                    {{ __('Total') }}
                                </th>
                                <th
                                    class="px-2.5 py-2 text-right font-semibold"
                                >
                                    {{ __('Biaya') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in items"
                                :key="item.id"
                                class="border-b border-slate-100 last:border-0 dark:border-slate-800"
                                :data-test="`inline-item-${item.id}`"
                            >
                                <td
                                    class="px-2.5 py-2 font-mono text-slate-600"
                                >
                                    {{ item.npk_snapshot }}
                                </td>
                                <td class="px-2.5 py-2">
                                    <div
                                        class="font-semibold text-slate-800 dark:text-slate-100"
                                    >
                                        {{
                                            item.employee?.full_name ??
                                            item.npk_snapshot
                                        }}
                                    </div>
                                    <div
                                        v-if="item.capex_project"
                                        class="mt-0.5 inline-flex items-center rounded border border-sky-300 bg-sky-100 px-1.5 py-0.5 text-[10px] font-bold text-sky-800 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-300"
                                    >
                                        CapEx:
                                        {{ item.capex_project.project_code }}
                                    </div>
                                    <div
                                        v-if="
                                            (item.anomaly_logs?.length ?? 0) > 0
                                        "
                                        class="mt-0.5 text-[10px] font-semibold text-violet-700 dark:text-violet-300"
                                    >
                                        🤖 {{ __('Anomali terdeteksi') }}
                                    </div>
                                </td>
                                <td
                                    class="px-2.5 py-2 text-right font-mono tabular-nums"
                                >
                                    {{ formatHours(item.hours_production) }}
                                </td>
                                <td
                                    class="px-2.5 py-2 text-right font-mono tabular-nums"
                                >
                                    {{ formatHours(item.hours_tpm) }}
                                </td>
                                <td
                                    class="px-2.5 py-2 text-right font-mono text-sky-700 tabular-nums dark:text-sky-300"
                                >
                                    {{ formatHours(item.hours_project) }}
                                </td>
                                <td
                                    class="px-2.5 py-2 text-right font-mono tabular-nums"
                                >
                                    {{ formatHours(item.hours_others) }}
                                </td>
                                <td
                                    class="px-2.5 py-2 text-right font-mono font-bold tabular-nums"
                                >
                                    {{ formatHours(item.total_hours) }}
                                </td>
                                <td
                                    class="px-2.5 py-2 text-right font-mono tabular-nums"
                                >
                                    {{
                                        formatRupiah(item.total_cost_snapshot, {
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}
                                </td>
                            </tr>
                            <tr v-if="items.length === 0">
                                <td
                                    colspan="8"
                                    class="px-2.5 py-4 text-center text-slate-500"
                                >
                                    {{ __('Tidak ada item karyawan.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 flex justify-end">
                    <button
                        type="button"
                        class="text-xs font-semibold text-[#cc0000] hover:underline"
                        :data-test="`open-full-review-${submission.id}`"
                        @click="emit('review', submission.id)"
                    >
                        {{ __('Buka Review Lengkap →') }}
                    </button>
                </div>
            </td>
        </tr>
    </tbody>
</template>
