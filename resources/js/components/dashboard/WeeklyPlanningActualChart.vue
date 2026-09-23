<script setup lang="ts">
import type { ChartData, ChartDataset, ChartOptions } from 'chart.js';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import {
    BarChart2,
    CalendarRange,
    CheckCircle2,
    MinusCircle,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { computed } from 'vue';
import { Chart } from 'vue-chartjs';
import ChartSkeleton from '@/components/charts/ChartSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { registerChartDefaults } from '@/plugins/chartjs';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    Title,
    Tooltip,
    Legend,
);
registerChartDefaults();

export interface WeeklyPlanningWeek {
    number: number;
    label: string;
    date_range: string;
    planned_hours: number;
    actual_production: number;
    actual_tpm: number;
    actual_project: number;
    actual_others: number;
    actual_total: number;
    is_future: boolean;
    is_current: boolean;
}

export interface WeeklyPlanningActualData {
    weeks: WeeklyPlanningWeek[];
    total_planned: number;
    total_actual: number;
    fiscal_year: number;
    fiscal_month: number;
    month_name: string;
    scope: {
        department_id: number | null;
        section_id: number | null;
    };
}

interface Props {
    data?: WeeklyPlanningActualData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: undefined,
    loading: false,
});

const { __ } = useTrans();

// Category color tokens (match employee summary table)
const CATEGORY_COLORS = {
    production: { solid: '#3b82f6', light: 'rgba(59,130,246,0.85)' },
    tpm: { solid: '#10b981', light: 'rgba(16,185,129,0.85)' },
    project: { solid: '#7c3aed', light: 'rgba(124,58,237,0.85)' },
    others: { solid: '#94a3b8', light: 'rgba(148,163,184,0.85)' },
    planned: { solid: '#cc0000', dash: '#cc0000' },
} as const;

const weekLabels = computed(
    () => props.data?.weeks.map((w) => `${w.label}\n${w.date_range}`) ?? [],
);

const isEmpty = computed(() => !props.data?.weeks?.length);

const totalDeviation = computed(() => {
    if (!props.data) {
        return 0;
    }
    return props.data.total_actual - props.data.total_planned;
});

const deviationPct = computed(() => {
    if (!props.data || props.data.total_planned === 0) {
        return 0;
    }
    return Math.round(
        (props.data.total_actual / props.data.total_planned) * 100,
    );
});

const burnZone = computed(() => {
    const pct = deviationPct.value;
    if (pct > 115) {
        return 'danger';
    }
    if (pct > 100) {
        return 'warning';
    }
    if (pct >= 85) {
        return 'on_track';
    }
    return 'safe';
});

const burnBadgeClass = computed(() => {
    switch (burnZone.value) {
        case 'danger':
            return 'bg-red-500/10 text-red-700 border-red-500/20 dark:text-red-300';
        case 'warning':
            return 'bg-amber-500/10 text-amber-700 border-amber-500/20 dark:text-amber-300';
        case 'on_track':
            return 'bg-blue-500/10 text-blue-700 border-blue-500/20 dark:text-blue-300';
        default:
            return 'bg-emerald-500/10 text-emerald-700 border-emerald-500/20 dark:text-emerald-300';
    }
});

const chartData = computed<ChartData<'bar' | 'line'>>(() => {
    const weeks = props.data?.weeks ?? [];

    const datasets: ChartDataset<'bar' | 'line'>[] = [
        // Stacked actual bars — Production
        {
            type: 'bar' as const,
            label: __('Produksi'),
            data: weeks.map((w) => w.actual_production),
            backgroundColor: weeks.map((w) =>
                w.is_future
                    ? 'rgba(59,130,246,0.25)'
                    : CATEGORY_COLORS.production.light,
            ),
            borderColor: CATEGORY_COLORS.production.solid,
            borderWidth: 1,
            borderRadius: 3,
            stack: 'actual',
            order: 2,
        },
        // TPM
        {
            type: 'bar' as const,
            label: 'TPM',
            data: weeks.map((w) => w.actual_tpm),
            backgroundColor: weeks.map((w) =>
                w.is_future
                    ? 'rgba(16,185,129,0.25)'
                    : CATEGORY_COLORS.tpm.light,
            ),
            borderColor: CATEGORY_COLORS.tpm.solid,
            borderWidth: 1,
            borderRadius: 3,
            stack: 'actual',
            order: 2,
        },
        // Project
        {
            type: 'bar' as const,
            label: __('Project'),
            data: weeks.map((w) => w.actual_project),
            backgroundColor: weeks.map((w) =>
                w.is_future
                    ? 'rgba(124,58,237,0.25)'
                    : CATEGORY_COLORS.project.light,
            ),
            borderColor: CATEGORY_COLORS.project.solid,
            borderWidth: 1,
            borderRadius: 3,
            stack: 'actual',
            order: 2,
        },
        // Others
        {
            type: 'bar' as const,
            label: __('Lainnya'),
            data: weeks.map((w) => w.actual_others),
            backgroundColor: weeks.map((w) =>
                w.is_future
                    ? 'rgba(148,163,184,0.25)'
                    : CATEGORY_COLORS.others.light,
            ),
            borderColor: CATEGORY_COLORS.others.solid,
            borderWidth: 1,
            borderRadius: 3,
            stack: 'actual',
            order: 2,
        },
        // Planned line overlay
        {
            type: 'line' as const,
            label: __('Planning'),
            data: weeks.map((w) => w.planned_hours),
            borderColor: CATEGORY_COLORS.planned.solid,
            backgroundColor: 'rgba(204,0,0,0.12)',
            borderWidth: 2.5,
            borderDash: [6, 4],
            pointRadius: 5,
            pointBackgroundColor: CATEGORY_COLORS.planned.solid,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            tension: 0.35,
            fill: false,
            stack: undefined,
            order: 1,
        },
    ];

    return {
        labels: weekLabels.value,
        datasets,
    };
});

const chartOptions = computed<ChartOptions<'bar'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: {
                boxWidth: 12,
                boxHeight: 12,
                padding: 16,
                font: { size: 11 },
            },
        },
        tooltip: {
            padding: 12,
            callbacks: {
                title: (items) => {
                    const week = props.data?.weeks[items[0]?.dataIndex];
                    if (!week) {
                        return '';
                    }
                    return `${week.label} (${week.date_range})`;
                },
                footer: (items) => {
                    const week = props.data?.weeks[items[0]?.dataIndex];
                    if (!week) {
                        return '';
                    }
                    const actualTotal = week.actual_total;
                    const planned = week.planned_hours;
                    const dev = actualTotal - planned;
                    const sign = dev >= 0 ? '+' : '';
                    return [
                        `────────────────`,
                        `${__('Total Aktual')}: ${actualTotal.toFixed(1)} Jam`,
                        `${__('Deviasi')}: ${sign}${dev.toFixed(1)} Jam`,
                    ];
                },
            },
        },
    },
    scales: {
        x: {
            stacked: true,
            grid: { display: false },
            ticks: {
                font: { size: 11 },
            },
        },
        y: {
            stacked: true,
            beginAtZero: true,
            grid: {
                color: 'rgba(226,232,240,0.7)',
            },
            ticks: {
                font: { size: 11, family: 'monospace' },
                callback: (v) => `${v} Jam`,
            },
        },
    },
}));
</script>

<template>
    <Card class="overflow-hidden">
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold"
                    >
                        <BarChart2 class="size-4 text-[#cc0000]" />
                        {{ __('Planning vs Actual — Mingguan') }}
                    </CardTitle>
                    <p
                        class="text-muted-foreground mt-0.5 flex items-center gap-1.5 text-xs"
                    >
                        <CalendarRange class="size-3.5" />
                        <span>{{ data?.month_name ?? '—' }}</span>
                        <span
                            v-if="
                                !data?.scope.department_id &&
                                !data?.scope.section_id
                            "
                            >· {{ __('Semua Departemen') }}</span
                        >
                    </p>
                </div>

                <!-- Summary Badges -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Planned total -->
                    <div
                        class="flex items-center gap-1.5 rounded-md border border-[#cc0000]/20 bg-[#cc0000]/5 px-2.5 py-1 text-xs font-semibold text-[#cc0000]"
                    >
                        <span
                            class="inline-block size-2 rounded-full border-2 border-[#cc0000] bg-transparent"
                        />
                        {{ __('Plan') }}:
                        {{ (data?.total_planned ?? 0).toFixed(1) }} Jam
                    </div>
                    <!-- Actual total -->
                    <div
                        class="flex items-center gap-1.5 rounded-md border border-slate-300 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300"
                    >
                        <span
                            class="inline-block size-2 rounded-full bg-blue-500"
                        />
                        {{ __('Aktual') }}:
                        {{ (data?.total_actual ?? 0).toFixed(1) }} Jam
                    </div>
                    <!-- Burn % badge -->
                    <Badge
                        v-if="data && data.total_planned > 0"
                        :class="burnBadgeClass"
                        class="border px-2 py-0.5 font-mono text-xs tabular-nums"
                    >
                        <TrendingUp
                            v-if="deviationPct > 100"
                            class="mr-1 size-3"
                        />
                        <TrendingDown
                            v-else-if="deviationPct < 85"
                            class="mr-1 size-3"
                        />
                        <CheckCircle2 v-else class="mr-1 size-3" />
                        {{ deviationPct }}%
                    </Badge>
                </div>
            </div>
        </CardHeader>

        <CardContent class="pt-0">
            <!-- Chart skeleton while loading -->
            <ChartSkeleton v-if="loading" height-class="h-72" />

            <!-- Empty state -->
            <div
                v-else-if="isEmpty"
                class="flex h-72 flex-col items-center justify-center gap-2 text-center text-sm"
            >
                <MinusCircle class="size-8 text-slate-300" />
                <p class="text-muted-foreground text-xs">
                    {{ __('Belum ada data planning untuk periode ini.') }}
                </p>
            </div>

            <!-- Mixed bar + line chart -->
            <div v-else class="h-72">
                <Chart
                    type="bar"
                    :data="chartData"
                    :options="chartOptions"
                    class="h-full w-full"
                />
            </div>

            <!-- Per-week summary table -->
            <div
                v-if="!loading && data?.weeks?.length"
                class="mt-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800"
            >
                <table class="w-full border-collapse text-xs">
                    <thead>
                        <tr
                            class="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900"
                        >
                            <th class="px-3 py-2 text-left">
                                {{ __('Minggu') }}
                            </th>
                            <th class="px-3 py-2 text-left">
                                {{ __('Periode') }}
                            </th>
                            <th class="px-3 py-2 text-right">
                                {{ __('Planning') }}
                            </th>
                            <th class="px-3 py-2 text-right">
                                {{ __('Produksi') }}
                            </th>
                            <th class="px-3 py-2 text-right">TPM</th>
                            <th class="px-3 py-2 text-right">
                                {{ __('Project') }}
                            </th>
                            <th class="px-3 py-2 text-right">
                                {{ __('Lainnya') }}
                            </th>
                            <th class="px-3 py-2 text-right">
                                {{ __('Total Aktual') }}
                            </th>
                            <th class="px-3 py-2 text-right">
                                {{ __('Realisasi') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <tr
                            v-for="week in data?.weeks"
                            :key="week.number"
                            :class="[
                                'transition-colors',
                                week.is_current
                                    ? 'bg-blue-50/60 dark:bg-blue-950/20'
                                    : week.is_future
                                      ? 'opacity-60'
                                      : 'hover:bg-slate-50/60 dark:hover:bg-slate-800/40',
                            ]"
                        >
                            <td class="px-3 py-2">
                                <span
                                    class="inline-flex items-center gap-1 font-bold"
                                    :class="
                                        week.is_current
                                            ? 'text-blue-700 dark:text-blue-400'
                                            : 'text-slate-900 dark:text-white'
                                    "
                                >
                                    {{ week.label }}
                                    <span
                                        v-if="week.is_current"
                                        class="size-1.5 animate-pulse rounded-full bg-blue-500"
                                    />
                                </span>
                            </td>
                            <td class="px-3 py-2 text-slate-500">
                                {{ week.date_range }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-mono text-[#cc0000] tabular-nums"
                            >
                                {{ week.planned_hours.toFixed(1) }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-mono text-blue-700 tabular-nums dark:text-blue-400"
                            >
                                {{ week.actual_production.toFixed(1) }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-mono text-emerald-700 tabular-nums dark:text-emerald-400"
                            >
                                {{ week.actual_tpm.toFixed(1) }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-mono text-violet-700 tabular-nums dark:text-violet-400"
                            >
                                {{ week.actual_project.toFixed(1) }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-mono text-slate-600 tabular-nums dark:text-slate-400"
                            >
                                {{ week.actual_others.toFixed(1) }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                            >
                                {{ week.actual_total.toFixed(1) }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <span
                                    v-if="
                                        !week.is_future &&
                                        week.planned_hours > 0
                                    "
                                    class="font-mono text-xs font-semibold tabular-nums"
                                    :class="{
                                        'text-[#cc0000]':
                                            week.actual_total /
                                                week.planned_hours >
                                            1.15,
                                        'text-amber-600 dark:text-amber-400':
                                            week.actual_total /
                                                week.planned_hours >
                                                1.0 &&
                                            week.actual_total /
                                                week.planned_hours <=
                                                1.15,
                                        'text-blue-600 dark:text-blue-400':
                                            week.actual_total /
                                                week.planned_hours >=
                                                0.85 &&
                                            week.actual_total /
                                                week.planned_hours <=
                                                1.0,
                                        'text-emerald-600 dark:text-emerald-400':
                                            week.actual_total /
                                                week.planned_hours <
                                            0.85,
                                    }"
                                >
                                    {{
                                        Math.round(
                                            (week.actual_total /
                                                week.planned_hours) *
                                                100,
                                        )
                                    }}%
                                </span>
                                <span
                                    v-else-if="week.is_future"
                                    class="text-slate-300 dark:text-slate-600"
                                    >—</span
                                >
                                <span v-else class="text-slate-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr
                            class="border-t-2 border-slate-300 bg-slate-50 font-bold dark:border-slate-700 dark:bg-slate-900"
                        >
                            <td
                                colspan="2"
                                class="px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300"
                            >
                                {{ __('TOTAL') }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-mono text-xs text-[#cc0000] tabular-nums"
                            >
                                {{ (data?.total_planned ?? 0).toFixed(1) }}
                            </td>
                            <td colspan="4" class="px-3 py-2" />
                            <td
                                class="px-3 py-2 text-right font-mono text-xs text-slate-900 tabular-nums dark:text-white"
                            >
                                {{ (data?.total_actual ?? 0).toFixed(1) }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <span
                                    v-if="data && data.total_planned > 0"
                                    class="font-mono text-xs font-bold tabular-nums"
                                    :class="burnBadgeClass"
                                >
                                    {{ deviationPct }}%
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
