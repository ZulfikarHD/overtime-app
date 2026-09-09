<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDown,
    ArrowRight,
    ArrowUp,
    ArrowUpDown,
    Building2,
    Edit2,
    FolderKanban,
    RefreshCw,
    Trash2,
} from '@lucide/vue';
import type {
    CapexProjectRecord,
    DepartmentOption,
} from '@/components/admin/CapexProjectDrawer.vue';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { formatDateIndo } from '@/lib/formatters';
import capexProjectsRoute from '@/routes/admin/capex-projects';

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
}

export interface CapexProjectListItem extends CapexProjectRecord {
    consumed_hours?: number | string | null;
    consumed_cost?: number | string | null;
    computed_burn_index_pct?: number;
    computed_milestone_burn_ratio?: number;
    days_remaining?: number;
    is_overdue?: boolean;
    is_at_risk?: boolean;
}

const props = withDefaults(
    defineProps<{
        projects: PaginatedData<CapexProjectListItem>;
        isAdmin?: boolean;
        sortBy?: string;
        sortDir?: 'asc' | 'desc';
    }>(),
    {
        isAdmin: true,
        sortBy: 'created_at',
        sortDir: 'desc',
    },
);

const emit = defineEmits<{
    (e: 'sort', field: string): void;
    (e: 'edit', project: CapexProjectListItem): void;
    (e: 'status', project: CapexProjectListItem): void;
    (e: 'delete', project: CapexProjectListItem): void;
}>();

const { __ } = useTrans();

function toggleSort(field: string) {
    emit('sort', field);
}

function getStatusBadgeClass(status: string) {
    switch (status) {
        case 'PLANNING':
            return 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300';
        case 'ACTIVE':
            return 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300';
        case 'ON_HOLD':
            return 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300';
        case 'COMPLETED':
            return 'bg-sky-50 text-sky-700 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300';
        case 'CLOSED':
            return 'bg-zinc-100 text-zinc-600 border-zinc-300 dark:bg-zinc-800 dark:text-zinc-400';
        default:
            return 'bg-slate-100 text-slate-700';
    }
}

function getBurnIndexBadgeClass(index: number) {
    if (index > 115) {
        return 'bg-red-50 text-[#cc0000] border-red-200 dark:bg-red-950/40 dark:text-red-400 dark:border-red-900 font-bold';
    }
    if (index > 100) {
        return 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/40 dark:text-amber-300 font-semibold';
    }
    if (index >= 85) {
        return 'bg-sky-50 text-sky-700 border-sky-300 dark:bg-sky-950/40 dark:text-sky-300';
    }
    return 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300';
}

function getRowHighlightClass(item: CapexProjectListItem) {
    const burnIndex = item.computed_burn_index_pct ?? 0;
    const milestoneRatio = item.computed_milestone_burn_ratio ?? 0;
    const isAtRisk = item.is_at_risk;

    // Critical / Deficit (> 100% or > 90% with milestone > 1.2)
    if (burnIndex > 100 || (burnIndex > 90 && milestoneRatio > 1.2)) {
        return 'bg-red-50/40 dark:bg-red-950/20 border-l-4 border-l-[#cc0000]';
    }
    // Caution / At risk (>= 85% or is_at_risk)
    if (burnIndex >= 85 || isAtRisk) {
        return 'bg-amber-50/30 dark:bg-amber-950/15 border-l-4 border-l-amber-500';
    }
    return 'border-l-4 border-l-transparent';
}
</script>

<template>
    <Card class="border-border overflow-hidden">
        <div class="overflow-x-auto">
            <table
                class="w-full border-collapse text-left text-xs"
                data-test="capex-projects-table"
            >
                <thead>
                    <tr
                        class="border-border bg-muted/50 text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase select-none"
                    >
                        <!-- Flagged / Risk Indicator -->
                        <th class="w-8 p-3 text-center">
                            <span class="sr-only">{{
                                __('Status Risiko')
                            }}</span>
                            <span title="Status Risiko">⚠️</span>
                        </th>

                        <!-- Project Code (Sortable) -->
                        <th
                            class="hover:text-foreground cursor-pointer p-3 transition-colors"
                            @click="toggleSort('project_code')"
                            data-test="sort-project-code"
                        >
                            <div class="flex items-center gap-1">
                                <span>{{ __('Kode Proyek') }}</span>
                                <ArrowUp
                                    v-if="
                                        sortBy === 'project_code' &&
                                        sortDir === 'asc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowDown
                                    v-else-if="
                                        sortBy === 'project_code' &&
                                        sortDir === 'desc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="text-muted-foreground/40 size-3"
                                />
                            </div>
                        </th>

                        <!-- Name (Sortable) -->
                        <th
                            class="hover:text-foreground cursor-pointer p-3 transition-colors"
                            @click="toggleSort('name')"
                            data-test="sort-name"
                        >
                            <div class="flex items-center gap-1">
                                <span>{{ __('Nama Proyek') }}</span>
                                <ArrowUp
                                    v-if="
                                        sortBy === 'name' && sortDir === 'asc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowDown
                                    v-else-if="
                                        sortBy === 'name' && sortDir === 'desc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="text-muted-foreground/40 size-3"
                                />
                            </div>
                        </th>

                        <!-- Fixed Asset Tag -->
                        <th class="p-3">{{ __('Aset Tetap') }}</th>

                        <!-- Department (Sortable, shown if Admin or cross-dept) -->
                        <th
                            v-if="isAdmin"
                            class="hover:text-foreground cursor-pointer p-3 transition-colors"
                            @click="toggleSort('department')"
                            data-test="sort-department"
                        >
                            <div class="flex items-center gap-1">
                                <span>{{ __('Departemen') }}</span>
                                <ArrowUp
                                    v-if="
                                        sortBy === 'department' &&
                                        sortDir === 'asc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowDown
                                    v-else-if="
                                        sortBy === 'department' &&
                                        sortDir === 'desc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="text-muted-foreground/40 size-3"
                                />
                            </div>
                        </th>

                        <!-- Status (Sortable) -->
                        <th
                            class="hover:text-foreground cursor-pointer p-3 transition-colors"
                            @click="toggleSort('status')"
                            data-test="sort-status"
                        >
                            <div class="flex items-center gap-1">
                                <span>{{ __('Status') }}</span>
                                <ArrowUp
                                    v-if="
                                        sortBy === 'status' && sortDir === 'asc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowDown
                                    v-else-if="
                                        sortBy === 'status' &&
                                        sortDir === 'desc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="text-muted-foreground/40 size-3"
                                />
                            </div>
                        </th>

                        <!-- Allocated Hours (Sortable) -->
                        <th
                            class="hover:text-foreground cursor-pointer p-3 text-right transition-colors"
                            @click="toggleSort('allocated_labor_hours')"
                            data-test="sort-allocated-hours"
                        >
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Alokasi (Jam)') }}</span>
                                <ArrowUp
                                    v-if="
                                        sortBy === 'allocated_labor_hours' &&
                                        sortDir === 'asc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowDown
                                    v-else-if="
                                        sortBy === 'allocated_labor_hours' &&
                                        sortDir === 'desc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="text-muted-foreground/40 size-3"
                                />
                            </div>
                        </th>

                        <!-- Consumed Hours (Sortable) -->
                        <th
                            class="hover:text-foreground cursor-pointer p-3 text-right transition-colors"
                            @click="toggleSort('consumed_hours')"
                            data-test="sort-consumed-hours"
                        >
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Realisasi (Jam)') }}</span>
                                <ArrowUp
                                    v-if="
                                        sortBy === 'consumed_hours' &&
                                        sortDir === 'asc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowDown
                                    v-else-if="
                                        sortBy === 'consumed_hours' &&
                                        sortDir === 'desc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="text-muted-foreground/40 size-3"
                                />
                            </div>
                        </th>

                        <!-- Burn Index % (Sortable) -->
                        <th
                            class="hover:text-foreground cursor-pointer p-3 text-right transition-colors"
                            @click="toggleSort('burn_index')"
                            data-test="sort-burn-index"
                        >
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Indeks Burn') }}</span>
                                <ArrowUp
                                    v-if="
                                        sortBy === 'burn_index' &&
                                        sortDir === 'asc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowDown
                                    v-else-if="
                                        sortBy === 'burn_index' &&
                                        sortDir === 'desc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="text-muted-foreground/40 size-3"
                                />
                            </div>
                        </th>

                        <!-- Physical Progress % (Sortable) -->
                        <th
                            class="hover:text-foreground cursor-pointer p-3 text-right transition-colors"
                            @click="toggleSort('physical_progress_pct')"
                            data-test="sort-physical-progress"
                        >
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Kemajuan Fisik') }}</span>
                                <ArrowUp
                                    v-if="
                                        sortBy === 'physical_progress_pct' &&
                                        sortDir === 'asc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowDown
                                    v-else-if="
                                        sortBy === 'physical_progress_pct' &&
                                        sortDir === 'desc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="text-muted-foreground/40 size-3"
                                />
                            </div>
                        </th>

                        <!-- Milestone Burn Ratio (Sortable) -->
                        <th
                            class="hover:text-foreground cursor-pointer p-3 text-right transition-colors"
                            @click="toggleSort('milestone_burn_ratio')"
                            data-test="sort-milestone-ratio"
                        >
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Rasio Milestone') }}</span>
                                <ArrowUp
                                    v-if="
                                        sortBy === 'milestone_burn_ratio' &&
                                        sortDir === 'asc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowDown
                                    v-else-if="
                                        sortBy === 'milestone_burn_ratio' &&
                                        sortDir === 'desc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="text-muted-foreground/40 size-3"
                                />
                            </div>
                        </th>

                        <!-- Target End Date (Sortable) -->
                        <th
                            class="hover:text-foreground cursor-pointer p-3 text-right transition-colors"
                            @click="toggleSort('target_end_date')"
                            data-test="sort-target-date"
                        >
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Target Selesai') }}</span>
                                <ArrowUp
                                    v-if="
                                        sortBy === 'target_end_date' &&
                                        sortDir === 'asc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowDown
                                    v-else-if="
                                        sortBy === 'target_end_date' &&
                                        sortDir === 'desc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="text-muted-foreground/40 size-3"
                                />
                            </div>
                        </th>

                        <!-- Days Remaining (Sortable) -->
                        <th
                            class="hover:text-foreground cursor-pointer p-3 text-right transition-colors"
                            @click="toggleSort('days_remaining')"
                            data-test="sort-days-remaining"
                        >
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Sisa Hari') }}</span>
                                <ArrowUp
                                    v-if="
                                        sortBy === 'days_remaining' &&
                                        sortDir === 'asc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowDown
                                    v-else-if="
                                        sortBy === 'days_remaining' &&
                                        sortDir === 'desc'
                                    "
                                    class="size-3 text-[#cc0000]"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="text-muted-foreground/40 size-3"
                                />
                            </div>
                        </th>

                        <!-- Actions -->
                        <th class="p-3 text-center">
                            {{ __('Aksi') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-border divide-y">
                    <tr
                        v-for="item in projects.data"
                        :key="item.id"
                        class="hover:bg-muted/40 transition-colors"
                        :class="getRowHighlightClass(item)"
                        :data-test="`project-row-${item.id}`"
                    >
                        <!-- Flagged Risk Badge -->
                        <td class="p-3 text-center">
                            <span
                                v-if="item.is_at_risk"
                                class="inline-flex items-center justify-center text-amber-500"
                                :title="
                                    __(
                                        'Peringatan: Proyek berisiko tinggi (Milestone > 1.2 atau Burn Index > 90%)',
                                    )
                                "
                                :data-test="`risk-flag-${item.id}`"
                            >
                                ⚠️
                            </span>
                            <span v-else class="text-muted-foreground/30"
                                >—</span
                            >
                        </td>

                        <!-- Kode Proyek -->
                        <td
                            class="text-foreground p-3 font-mono font-bold whitespace-nowrap tabular-nums"
                        >
                            <Link
                                :href="
                                    capexProjectsRoute.show.url({
                                        capex_project: item.id,
                                    })
                                "
                                class="flex items-center gap-1 text-[#cc0000] hover:underline"
                                data-test="link-project-code"
                            >
                                {{ item.project_code }}
                            </Link>
                        </td>

                        <!-- Nama Proyek -->
                        <td
                            class="text-foreground max-w-xs truncate p-3 font-medium"
                            :title="item.name"
                        >
                            {{ item.name }}
                        </td>

                        <!-- Aset Tetap -->
                        <td
                            class="text-muted-foreground p-3 font-mono whitespace-nowrap tabular-nums"
                        >
                            {{ item.asset_code || '—' }}
                        </td>

                        <!-- Departemen (if Admin) -->
                        <td v-if="isAdmin" class="p-3 whitespace-nowrap">
                            <span
                                class="text-foreground inline-flex items-center gap-1 font-medium"
                            >
                                <Building2
                                    class="text-muted-foreground size-3"
                                />
                                {{ item.department?.code || '—' }}
                            </span>
                        </td>

                        <!-- Status -->
                        <td class="p-3 whitespace-nowrap">
                            <Badge
                                variant="outline"
                                :class="getStatusBadgeClass(item.status)"
                                class="text-[11px] font-semibold uppercase"
                                :data-test="`badge-status-${item.id}`"
                            >
                                {{ item.status }}
                            </Badge>
                        </td>

                        <!-- Alokasi Jam -->
                        <td
                            class="p-3 text-right font-mono whitespace-nowrap tabular-nums"
                        >
                            {{
                                Number(
                                    item.allocated_labor_hours,
                                ).toLocaleString('id-ID', {
                                    minimumFractionDigits: 1,
                                    maximumFractionDigits: 1,
                                })
                            }}
                        </td>

                        <!-- Realisasi Jam -->
                        <td
                            class="p-3 text-right font-mono font-semibold whitespace-nowrap tabular-nums"
                        >
                            {{
                                Number(item.consumed_hours || 0).toLocaleString(
                                    'id-ID',
                                    {
                                        minimumFractionDigits: 1,
                                        maximumFractionDigits: 1,
                                    },
                                )
                            }}
                        </td>

                        <!-- Indeks Burn -->
                        <td class="p-3 text-right whitespace-nowrap">
                            <Badge
                                variant="outline"
                                :class="
                                    getBurnIndexBadgeClass(
                                        item.computed_burn_index_pct ?? 0,
                                    )
                                "
                                class="font-mono text-[11px] tabular-nums"
                                :data-test="`badge-burn-${item.id}`"
                            >
                                {{
                                    (item.computed_burn_index_pct ?? 0).toFixed(
                                        1,
                                    )
                                }}%
                            </Badge>
                        </td>

                        <!-- Kemajuan Fisik -->
                        <td
                            class="p-3 text-right font-mono whitespace-nowrap tabular-nums"
                        >
                            <span class="font-semibold">
                                {{
                                    Number(
                                        item.physical_progress_pct || 0,
                                    ).toFixed(1)
                                }}%
                            </span>
                        </td>

                        <!-- Rasio Burn Milestone -->
                        <td
                            class="p-3 text-right font-mono whitespace-nowrap tabular-nums"
                        >
                            <div class="flex items-center justify-end gap-1">
                                <template
                                    v-if="
                                        Number(
                                            item.physical_progress_pct || 0,
                                        ) > 0
                                    "
                                >
                                    <span
                                        :class="
                                            (item.computed_milestone_burn_ratio ??
                                                0) > 1.2
                                                ? 'font-bold text-amber-600 dark:text-amber-400'
                                                : 'text-foreground font-semibold'
                                        "
                                        :title="
                                            (item.computed_milestone_burn_ratio ??
                                                0) > 1.2
                                                ? __(
                                                      'Lembur membakar lebih cepat dibanding kemajuan fisik!',
                                                  )
                                                : __('Rasio normal')
                                        "
                                        :data-test="`milestone-ratio-${item.id}`"
                                    >
                                        {{
                                            (
                                                item.computed_milestone_burn_ratio ??
                                                0
                                            ).toFixed(2)
                                        }}
                                    </span>
                                </template>
                                <span
                                    v-else
                                    class="text-muted-foreground text-[11px]"
                                    :title="
                                        __('Belum ada kemajuan fisik tercatat')
                                    "
                                >
                                    N/A
                                </span>
                            </div>
                        </td>

                        <!-- Target Selesai -->
                        <td
                            class="text-muted-foreground p-3 text-right font-mono whitespace-nowrap tabular-nums"
                        >
                            {{ formatDateIndo(item.target_end_date) }}
                        </td>

                        <!-- Sisa Hari -->
                        <td
                            class="p-3 text-right font-mono whitespace-nowrap tabular-nums"
                        >
                            <span
                                v-if="item.is_overdue"
                                class="font-bold text-[#cc0000]"
                            >
                                {{
                                    __('Lewat :days h', {
                                        days: Math.abs(
                                            item.days_remaining ?? 0,
                                        ),
                                    })
                                }}
                            </span>
                            <span
                                v-else-if="
                                    ['COMPLETED', 'CLOSED'].includes(
                                        item.status,
                                    )
                                "
                                class="text-muted-foreground"
                            >
                                —
                            </span>
                            <span v-else class="text-muted-foreground">
                                {{
                                    __(':days hari', {
                                        days: item.days_remaining ?? 0,
                                    })
                                }}
                            </span>
                        </td>

                        <!-- Aksi -->
                        <td class="p-3 text-center whitespace-nowrap">
                            <div
                                class="flex items-center justify-center gap-1.5"
                            >
                                <Link
                                    :href="
                                        capexProjectsRoute.show.url({
                                            capex_project: item.id,
                                        })
                                    "
                                    class="hover:bg-muted text-muted-foreground hover:text-foreground rounded-md p-1.5 transition-colors"
                                    :title="__('Lihat Detail Cockpit')"
                                    :data-test="`btn-detail-${item.id}`"
                                >
                                    <ArrowRight class="size-3.5" />
                                </Link>
                                <button
                                    type="button"
                                    class="hover:bg-muted text-muted-foreground hover:text-foreground rounded-md p-1.5 transition-colors"
                                    :title="__('Edit Master Data')"
                                    @click="emit('edit', item)"
                                    :data-test="`btn-edit-${item.id}`"
                                >
                                    <Edit2 class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="hover:bg-muted rounded-md p-1.5 text-sky-600 transition-colors hover:text-sky-700"
                                    :title="__('Ubah Status Proyek')"
                                    @click="emit('status', item)"
                                    :data-test="`btn-status-${item.id}`"
                                >
                                    <RefreshCw class="size-3.5" />
                                </button>
                                <button
                                    v-if="
                                        !item.consumed_hours ||
                                        Number(item.consumed_hours) === 0
                                    "
                                    type="button"
                                    class="text-muted-foreground rounded-md p-1.5 transition-colors hover:bg-red-50 hover:text-[#cc0000]"
                                    :title="__('Hapus Proyek')"
                                    @click="emit('delete', item)"
                                    :data-test="`btn-delete-${item.id}`"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="projects.data.length === 0">
                        <td
                            :colspan="isAdmin ? 14 : 13"
                            class="text-muted-foreground p-10 text-center"
                        >
                            <div
                                class="flex flex-col items-center justify-center space-y-2"
                            >
                                <FolderKanban
                                    class="text-muted-foreground/60 size-8"
                                />
                                <p
                                    class="text-foreground text-sm font-semibold"
                                >
                                    {{
                                        __(
                                            'Tidak ada proyek CapEx yang ditemukan',
                                        )
                                    }}
                                </p>
                                <p class="text-muted-foreground text-xs">
                                    {{
                                        __(
                                            'Sesuaikan filter pencarian atau daftarkan proyek belanja modal baru menggunakan tombol di atas.',
                                        )
                                    }}
                                </p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div
            v-if="projects.total > 0"
            class="border-border bg-card text-muted-foreground flex flex-col items-center justify-between gap-2 border-t p-3 text-xs sm:flex-row"
            data-test="pagination-footer"
        >
            <div>
                {{
                    __('Menampilkan :from - :to dari total :total proyek', {
                        from: projects.from ?? 0,
                        to: projects.to ?? 0,
                        total: projects.total,
                    })
                }}
            </div>
            <div class="flex items-center gap-1">
                <Link
                    v-if="projects.prev_page_url"
                    :href="projects.prev_page_url"
                    class="border-border bg-background hover:bg-muted text-foreground rounded-md border px-2.5 py-1 font-semibold"
                    data-test="btn-prev-page"
                >
                    {{ __('Sebelumnya') }}
                </Link>
                <span class="px-2 font-mono">
                    {{ projects.current_page }} / {{ projects.last_page }}
                </span>
                <Link
                    v-if="projects.next_page_url"
                    :href="projects.next_page_url"
                    class="border-border bg-background hover:bg-muted text-foreground rounded-md border px-2.5 py-1 font-semibold"
                    data-test="btn-next-page"
                >
                    {{ __('Selanjutnya') }}
                </Link>
            </div>
        </div>
    </Card>
</template>
