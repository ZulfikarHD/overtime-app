<script setup lang="ts">
import {
    Activity,
    AlertCircle,
    ArrowDown,
    ArrowRight,
    ArrowUp,
    ArrowUpDown,
    ArrowUpRight,
    Flame,
    MoveRight,
    Search,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { SectionSnapshotData } from '@/components/dashboard/BurnIndexCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    snapshots: SectionSnapshotData[];
    isCrossDepartment?: boolean;
}>();

const emit = defineEmits<{
    (e: 'selectSection', sectionId: number): void;
}>();

const { __ } = useTrans();

const searchQuery = ref('');
const statusFilter = ref<
    'all' | 'safe' | 'on_track' | 'warning' | 'danger' | 'unconfigured'
>('all');

type SortField =
    | 'burn_index_pct'
    | 'section_name'
    | 'planned_budget_hours'
    | 'cumulative_actual_hours'
    | 'remaining_budget_hours'
    | 'burn_velocity'
    | 'projected_total_hours';

const sortField = ref<SortField>('burn_index_pct');
const sortDirection = ref<'asc' | 'desc'>('desc');

function toggleSort(field: SortField) {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = field === 'section_name' ? 'asc' : 'desc';
    }
}

const filteredAndSortedSnapshots = computed(() => {
    let result = [...props.snapshots];

    // 1. Text Search Query
    if (searchQuery.value.trim() !== '') {
        const q = searchQuery.value.toLowerCase().trim();
        result = result.filter(
            (s) =>
                (s.section_name?.toLowerCase() || '').includes(q) ||
                (s.section_code?.toLowerCase() || '').includes(q) ||
                (s.department_name?.toLowerCase() || '').includes(q) ||
                (s.department_code?.toLowerCase() || '').includes(q),
        );
    }

    // 2. Status Filter
    if (statusFilter.value !== 'all') {
        result = result.filter((s) => {
            if (statusFilter.value === 'unconfigured') {
                return !s.is_budget_configured;
            }
            if (!s.is_budget_configured) {
                return false;
            }
            if (statusFilter.value === 'danger') {
                return s.burn_index_pct > 115 || s.burn_zone === 'ZONE_4_POOR';
            }
            if (statusFilter.value === 'warning') {
                return (
                    (s.burn_index_pct > 100 && s.burn_index_pct <= 115) ||
                    s.burn_zone === 'ZONE_3_WARNING'
                );
            }
            if (statusFilter.value === 'on_track') {
                return s.burn_index_pct >= 85 && s.burn_index_pct <= 100;
            }
            if (statusFilter.value === 'safe') {
                return s.burn_index_pct < 85;
            }
            return true;
        });
    }

    // 3. Sorting
    result.sort((a, b) => {
        let aVal = a[sortField.value];
        let bVal = b[sortField.value];

        // Push unconfigured to bottom if sorting by numerical metrics
        if (
            sortField.value === 'burn_index_pct' ||
            sortField.value === 'planned_budget_hours'
        ) {
            if (!a.is_budget_configured && b.is_budget_configured) return 1;
            if (a.is_budget_configured && !b.is_budget_configured) return -1;
        }

        if (typeof aVal === 'string' && typeof bVal === 'string') {
            const cmp = aVal.localeCompare(bVal);
            return sortDirection.value === 'asc' ? cmp : -cmp;
        }

        aVal = Number(aVal) || 0;
        bVal = Number(bVal) || 0;

        return sortDirection.value === 'asc' ? aVal - bVal : bVal - aVal;
    });

    return result;
});

function getBurnStatusClass(pct: number): string {
    if (pct > 115) {
        return 'bg-red-50 text-[#cc0000] border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900';
    }
    if (pct > 100) {
        return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900';
    }
    if (pct >= 85) {
        return 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-900';
    }
    return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900';
}

function getZoneBadge(zone: string): { label: string; class: string } {
    switch (zone) {
        case 'ZONE_1_EXCELLENT':
            return {
                label: __('Zona 1: Aman'),
                class: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900',
            };
        case 'ZONE_2_GOOD':
            return {
                label: __('Zona 2: Baik'),
                class: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-900',
            };
        case 'ZONE_3_WARNING':
            return {
                label: __('Zona 3: Waspada'),
                class: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900',
            };
        case 'ZONE_4_POOR':
        default:
            return {
                label: __('Zona 4: Defisit'),
                class: 'bg-red-50 text-[#cc0000] border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900',
            };
    }
}

function getRowBackgroundClass(item: SectionSnapshotData): string {
    if (!item.is_budget_configured) {
        return 'hover:bg-slate-50/80 dark:hover:bg-slate-800/40';
    }
    if (item.burn_zone === 'ZONE_4_POOR' || item.burn_index_pct > 115) {
        return 'bg-red-50/40 hover:bg-red-50/80 dark:bg-red-950/15 dark:hover:bg-red-950/30';
    }
    if (item.burn_zone === 'ZONE_3_WARNING' || item.burn_index_pct > 100) {
        return 'bg-amber-50/40 hover:bg-amber-50/80 dark:bg-amber-950/15 dark:hover:bg-amber-950/30';
    }
    return 'hover:bg-slate-50/80 dark:hover:bg-slate-800/40';
}
</script>

<template>
    <div class="space-y-4" data-test="section-burn-table-container">
        <!-- Filter Toolbar -->
        <div
            class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
        >
            <!-- Search Input -->
            <div class="relative w-full sm:w-72">
                <Search
                    class="text-muted-foreground absolute top-2.5 left-2.5 size-3.5"
                />
                <Input
                    v-model="searchQuery"
                    :placeholder="__('Cari seksi atau kode...')"
                    class="bg-card h-9 pl-8 text-xs"
                    data-test="input-search-table-sections"
                />
            </div>

            <!-- Status Filter Pills -->
            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <button
                    type="button"
                    class="cursor-pointer rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors"
                    :class="
                        statusFilter === 'all'
                            ? 'border-transparent bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                            : 'bg-card text-muted-foreground border-slate-200 hover:bg-slate-50 dark:border-slate-800'
                    "
                    @click="statusFilter = 'all'"
                    data-test="filter-all"
                >
                    {{ __('Semua') }} ({{ snapshots.length }})
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors"
                    :class="
                        statusFilter === 'safe'
                            ? 'border-transparent bg-emerald-600 text-white'
                            : 'bg-card border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-400'
                    "
                    @click="statusFilter = 'safe'"
                    data-test="filter-safe"
                >
                    {{ __('Aman (<85%)') }}
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors"
                    :class="
                        statusFilter === 'on_track'
                            ? 'border-transparent bg-sky-600 text-white'
                            : 'bg-card border-sky-200 text-sky-700 hover:bg-sky-50 dark:border-sky-900 dark:text-sky-400'
                    "
                    @click="statusFilter = 'on_track'"
                    data-test="filter-on-track"
                >
                    {{ __('Terkendali (85–100%)') }}
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors"
                    :class="
                        statusFilter === 'warning'
                            ? 'border-transparent bg-amber-600 text-white'
                            : 'bg-card border-amber-200 text-amber-700 hover:bg-amber-50 dark:border-amber-900 dark:text-amber-400'
                    "
                    @click="statusFilter = 'warning'"
                    data-test="filter-warning"
                >
                    {{ __('Peringatan (101–115%)') }}
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors"
                    :class="
                        statusFilter === 'danger'
                            ? 'border-transparent bg-[#cc0000] text-white'
                            : 'bg-card border-red-200 text-[#cc0000] hover:bg-red-50 dark:border-red-900 dark:text-red-400'
                    "
                    @click="statusFilter = 'danger'"
                    data-test="filter-danger"
                >
                    {{ __('Defisit (>115%)') }}
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors"
                    :class="
                        statusFilter === 'unconfigured'
                            ? 'border-transparent bg-slate-600 text-white'
                            : 'bg-card border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-400'
                    "
                    @click="statusFilter = 'unconfigured'"
                    data-test="filter-unconfigured"
                >
                    {{ __('Belum Diatur') }}
                </button>
            </div>
        </div>

        <!-- Ranked Section Table -->
        <div
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="overflow-x-auto">
                <table
                    class="w-full text-left text-xs"
                    data-test="table-ranked-sections"
                >
                    <thead
                        class="border-b border-slate-200 bg-slate-50/90 text-[11px] font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300"
                    >
                        <tr>
                            <th class="w-12 px-3 py-3 text-center">#</th>
                            <th
                                class="cursor-pointer px-4 py-3 select-none hover:text-slate-900 dark:hover:text-white"
                                @click="toggleSort('section_name')"
                            >
                                <div class="flex items-center gap-1">
                                    <span>{{ __('Seksi & Kode') }}</span>
                                    <ArrowUpDown
                                        class="size-3 text-slate-400"
                                    />
                                </div>
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-right select-none hover:text-slate-900 dark:hover:text-white"
                                @click="toggleSort('planned_budget_hours')"
                            >
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <span>{{ __('Rencana') }}</span>
                                    <ArrowUpDown
                                        class="size-3 text-slate-400"
                                    />
                                </div>
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-right select-none hover:text-slate-900 dark:hover:text-white"
                                @click="toggleSort('cumulative_actual_hours')"
                            >
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <span>{{ __('Realisasi') }}</span>
                                    <ArrowUpDown
                                        class="size-3 text-slate-400"
                                    />
                                </div>
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-right select-none hover:text-slate-900 dark:hover:text-white"
                                @click="toggleSort('remaining_budget_hours')"
                            >
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <span>{{ __('Sisa') }}</span>
                                    <ArrowUpDown
                                        class="size-3 text-slate-400"
                                    />
                                </div>
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center select-none hover:text-slate-900 dark:hover:text-white"
                                @click="toggleSort('burn_index_pct')"
                            >
                                <div
                                    class="flex items-center justify-center gap-1"
                                >
                                    <span>{{ __('Indeks Burn') }}</span>
                                    <ArrowUpDown
                                        class="size-3 text-slate-400"
                                    />
                                </div>
                            </th>
                            <th class="px-3 py-3 text-center">
                                {{ __('Zona Kontrol') }}
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-right select-none hover:text-slate-900 dark:hover:text-white"
                                @click="toggleSort('burn_velocity')"
                            >
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <span>{{ __('Kecepatan') }}</span>
                                    <ArrowUpDown
                                        class="size-3 text-slate-400"
                                    />
                                </div>
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-right select-none hover:text-slate-900 dark:hover:text-white"
                                @click="toggleSort('projected_total_hours')"
                            >
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <span>{{ __('Proyeksi') }}</span>
                                    <ArrowUpDown
                                        class="size-3 text-slate-400"
                                    />
                                </div>
                            </th>
                            <th class="px-3 py-3 text-center">
                                {{ __('Trajektori') }}
                            </th>
                            <th class="w-24 px-4 py-3 text-center">
                                {{ __('Aksi') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800/80"
                    >
                        <tr
                            v-for="(item, index) in filteredAndSortedSnapshots"
                            :key="item.id"
                            class="group cursor-pointer transition-colors"
                            :class="getRowBackgroundClass(item)"
                            @click="emit('selectSection', item.section_id)"
                            :data-test="`row-section-${item.section_code}`"
                        >
                            <!-- Rank # -->
                            <td
                                class="px-3 py-3 text-center font-mono text-xs font-bold text-slate-400 group-hover:text-slate-700 dark:group-hover:text-slate-200"
                            >
                                {{ index + 1 }}
                            </td>

                            <!-- Section Name & Code -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="font-bold text-slate-900 transition-colors group-hover:text-[#cc0000] dark:text-white dark:group-hover:text-red-400"
                                    >
                                        {{ item.section_name }}
                                    </span>
                                </div>
                                <div
                                    class="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-1.5 font-mono text-[11px]"
                                >
                                    <span
                                        class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        {{ item.section_code }}
                                    </span>
                                    <span
                                        v-if="
                                            isCrossDepartment &&
                                            item.department_code
                                        "
                                        class="text-slate-400"
                                    >
                                        • {{ item.department_code }}
                                    </span>
                                </div>
                            </td>

                            <!-- Planned Budget Hours -->
                            <td
                                class="px-3 py-3 text-right font-mono text-slate-600 tabular-nums dark:text-slate-400"
                            >
                                <span v-if="item.is_budget_configured">
                                    {{ item.planned_budget_hours.toFixed(1) }}
                                </span>
                                <span
                                    v-else
                                    class="text-muted-foreground italic"
                                >
                                    -
                                </span>
                            </td>

                            <!-- Cumulative Actual Hours -->
                            <td
                                class="px-3 py-3 text-right font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                            >
                                {{ item.cumulative_actual_hours.toFixed(1) }}
                            </td>

                            <!-- Remaining Budget Hours -->
                            <td
                                class="px-3 py-3 text-right font-mono font-semibold tabular-nums"
                                :class="
                                    !item.is_budget_configured
                                        ? 'text-muted-foreground'
                                        : item.remaining_budget_hours < 0
                                          ? 'font-bold text-[#cc0000]'
                                          : 'text-slate-700 dark:text-slate-300'
                                "
                            >
                                <span v-if="item.is_budget_configured">
                                    {{ item.remaining_budget_hours.toFixed(1) }}
                                </span>
                                <span
                                    v-else
                                    class="text-muted-foreground italic"
                                >
                                    -
                                </span>
                            </td>

                            <!-- Burn Index % -->
                            <td class="px-3 py-3 text-center">
                                <Badge
                                    v-if="item.is_budget_configured"
                                    variant="outline"
                                    class="border px-2 py-0.5 font-mono text-[11px] font-bold tabular-nums"
                                    :class="
                                        getBurnStatusClass(item.burn_index_pct)
                                    "
                                    :data-test="`badge-burn-${item.section_code}`"
                                >
                                    {{ item.burn_index_pct.toFixed(1) }}%
                                </Badge>
                                <span
                                    v-else
                                    class="text-muted-foreground text-[11px] italic"
                                >
                                    {{ __('Belum Diatur') }}
                                </span>
                            </td>

                            <!-- Budget Control Matrix Zone -->
                            <td class="px-3 py-3 text-center">
                                <Badge
                                    variant="outline"
                                    class="border px-2 py-0.5 text-[10px] font-semibold"
                                    :class="getZoneBadge(item.burn_zone).class"
                                >
                                    {{ getZoneBadge(item.burn_zone).label }}
                                </Badge>
                            </td>

                            <!-- Weekly Burn Velocity -->
                            <td
                                class="px-3 py-3 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                            >
                                {{ item.burn_velocity.toFixed(1) }}
                                <span class="text-muted-foreground text-[10px]"
                                    >j/mg</span
                                >
                            </td>

                            <!-- Projected Period-End Total -->
                            <td
                                class="px-3 py-3 text-right font-mono font-semibold text-slate-900 tabular-nums dark:text-white"
                            >
                                {{ item.projected_total_hours.toFixed(1) }}
                            </td>

                            <!-- Trajectory Indicator -->
                            <td class="px-3 py-3 text-center">
                                <span
                                    v-if="item.trajectory === 'on_pace'"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    <MoveRight class="size-3" />
                                    <span>{{ __('Aman') }}</span>
                                </span>
                                <span
                                    v-else-if="
                                        item.trajectory === 'trending_over'
                                    "
                                    class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-600 dark:text-amber-400"
                                >
                                    <ArrowUpRight class="size-3" />
                                    <span>{{ __('Waspada') }}</span>
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 text-[11px] font-bold text-[#cc0000]"
                                >
                                    <ArrowUp class="size-3" />
                                    <span>{{ __('Kritis') }}</span>
                                </span>
                            </td>

                            <!-- Action: Open Drawer -->
                            <td class="px-4 py-3 text-center">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 cursor-pointer px-2 text-xs text-slate-600 group-hover:text-[#cc0000] dark:text-slate-300 dark:group-hover:text-red-400"
                                    :data-test="`btn-detail-${item.section_code}`"
                                    @click.stop="
                                        emit('selectSection', item.section_id)
                                    "
                                >
                                    <span>{{ __('Detail') }}</span>
                                    <ArrowRight class="ml-1 size-3" />
                                </Button>
                            </td>
                        </tr>

                        <!-- Empty State Row -->
                        <tr v-if="filteredAndSortedSnapshots.length === 0">
                            <td
                                colspan="11"
                                class="text-muted-foreground py-12 text-center"
                            >
                                <Flame
                                    class="text-muted-foreground/40 mx-auto mb-2 size-8"
                                />
                                <div
                                    class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    {{
                                        __(
                                            'Tidak ada data seksi yang cocok dengan filter',
                                        )
                                    }}
                                </div>
                                <div
                                    class="text-muted-foreground mt-0.5 text-[11px]"
                                >
                                    {{
                                        __(
                                            'Coba ubah kata kunci pencarian atau bersihkan filter status.',
                                        )
                                    }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
