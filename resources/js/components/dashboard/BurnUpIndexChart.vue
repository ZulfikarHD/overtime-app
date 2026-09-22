<script setup lang="ts">
import type { ChartData, ChartDataset, ChartOptions } from 'chart.js';
import { TrendingDown, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';
import BaseBarChart from '@/components/charts/BaseBarChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

export interface DailyBurnUpIndexData {
    labels: string[];
    plan_index_cumulative: number[];
    actual_index_cumulative: (number | null)[];
    total_plan_index: number;
    total_actual_index: number;
    burn_index_pct: number;
    cutoff_day: number;
    days_in_month: number;
    fiscal_year: number;
    fiscal_month: number;
    month_name: string;
    scope: {
        department_id: number | null;
        section_id: number | null;
    };
}

interface Props {
    data?: DailyBurnUpIndexData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: undefined,
    loading: false,
});

const { __ } = useTrans();

// ISUZU palette: Plan = navy blue (#1e3a5f), Actual = ISUZU orange (#e8601c)
const COLOR_PLAN = '#1e3a5f';
const COLOR_ACTUAL_BASE = '#e8601c';

const isEmpty = computed(
    () => !props.data || props.data.plan_index_cumulative.every((v) => v === 0),
);

const chartDatasets = computed<ChartDataset<'bar'>[]>(() => {
    const planData = props.data?.plan_index_cumulative ?? [];
    const actualData = props.data?.actual_index_cumulative ?? [];

    return [
        {
            label: __('Plan'),
            data: planData,
            backgroundColor: COLOR_PLAN,
            borderColor: COLOR_PLAN,
            borderWidth: 0,
            borderRadius: 2,
            barThickness: 'flex' as const,
            maxBarThickness: 14,
        },
        {
            label: __('Aktual'),
            data: actualData,
            backgroundColor: COLOR_ACTUAL_BASE,
            borderColor: COLOR_ACTUAL_BASE,
            borderWidth: 0,
            borderRadius: 2,
            barThickness: 'flex' as const,
            maxBarThickness: 14,
        },
    ];
});

const chartOptions = computed<ChartOptions<'bar'>>(() => {
    const maxVal = Math.max(...(props.data?.plan_index_cumulative ?? [0]), 1);

    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                display: true,
                position: 'top',
                align: 'end',
                labels: {
                    boxWidth: 10,
                    boxHeight: 10,
                    font: { size: 10 },
                    padding: 10,
                },
            },
            tooltip: {
                padding: 10,
                callbacks: {
                    title: (items) => `Hari ${items[0]?.label}`,
                    label: (ctx) => {
                        const val = ctx.parsed.y;
                        if (val === null || val === undefined) return '';
                        return ` ${ctx.dataset.label}: ${val.toFixed(1)} idx`;
                    },
                },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: {
                    font: { size: 9, family: 'monospace' },
                    maxRotation: 0,
                    autoSkip: true,
                    maxTicksLimit: 15,
                },
            },
            y: {
                beginAtZero: true,
                max: Math.ceil(maxVal * 1.1),
                grid: { color: 'rgba(226,232,240,0.5)' },
                ticks: {
                    font: { size: 10, family: 'monospace' },
                    callback: (v) => {
                        const n = Number(v);
                        return n >= 1000 ? `${(n / 1000).toFixed(1)}k` : `${n}`;
                    },
                },
                title: {
                    display: true,
                    text: __('Index Kumulatif'),
                    font: { size: 9 },
                    color: '#94a3b8',
                },
            },
        },
    };
});

const chartData = computed<ChartData<'bar'>>(() => ({
    labels: props.data?.labels ?? [],
    datasets: chartDatasets.value,
}));

const burnZone = computed(() => {
    const pct = props.data?.burn_index_pct ?? 0;
    if (pct > 115) return 'danger';
    if (pct > 100) return 'warning';
    if (pct >= 85) return 'on_track';
    return 'safe';
});

const burnClass = computed(() => {
    return {
        danger: 'text-red-600 font-bold',
        warning: 'text-amber-600 font-bold',
        on_track: 'text-blue-700 font-semibold',
        safe: 'text-emerald-700 font-semibold',
    }[burnZone.value];
});
</script>

<template>
    <Card class="overflow-hidden">
        <CardHeader class="pb-2">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-bold"
                    >
                        <TrendingUp class="size-4 text-[#cc0000]" />
                        {{ __('Burn-Up Chart Index Overtime') }}
                    </CardTitle>
                    <p class="text-muted-foreground mt-0.5 text-[11px]">
                        {{ data?.month_name ?? '—' }} · Plan × Aktual
                        (Kumulatif, Day to Date)
                    </p>
                </div>

                <!-- Summary badges -->
                <div class="flex items-center gap-3 text-[11px]">
                    <div class="flex items-center gap-1.5">
                        <span
                            class="inline-block size-2.5 rounded-sm"
                            :style="{ backgroundColor: '#1e3a5f' }"
                        />
                        <span class="text-slate-500">Plan:</span>
                        <span
                            class="font-mono font-bold text-[#1e3a5f] tabular-nums"
                        >
                            {{ (data?.total_plan_index ?? 0).toFixed(1) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span
                            class="inline-block size-2.5 rounded-sm"
                            :style="{ backgroundColor: '#e8601c' }"
                        />
                        <span class="text-slate-500">Aktual:</span>
                        <span
                            class="font-mono font-bold text-[#e8601c] tabular-nums"
                        >
                            {{ (data?.total_actual_index ?? 0).toFixed(1) }}
                        </span>
                    </div>
                    <div
                        class="flex items-center gap-1 rounded-full px-2 py-0.5"
                        :class="{
                            'bg-red-100': burnZone === 'danger',
                            'bg-amber-100': burnZone === 'warning',
                            'bg-blue-100': burnZone === 'on_track',
                            'bg-emerald-100': burnZone === 'safe',
                        }"
                    >
                        <TrendingUp
                            v-if="(data?.burn_index_pct ?? 0) > 100"
                            class="size-3 text-red-600"
                        />
                        <TrendingDown v-else class="size-3 text-emerald-600" />
                        <span :class="burnClass">
                            {{ (data?.burn_index_pct ?? 0).toFixed(1) }}%
                        </span>
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="pt-0">
            <!-- Loading skeleton -->
            <div
                v-if="loading"
                class="h-52 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/40"
            />

            <!-- Empty state -->
            <div
                v-else-if="isEmpty"
                class="flex h-52 flex-col items-center justify-center gap-2 text-center"
            >
                <TrendingUp class="size-8 text-slate-300" />
                <p class="text-muted-foreground text-xs">
                    {{ __('Belum ada data untuk periode ini.') }}
                </p>
            </div>

            <!-- Grouped bar chart -->
            <div v-else class="h-52">
                <BaseBarChart
                    :labels="data?.labels ?? []"
                    :datasets="chartDatasets"
                    :options="chartOptions"
                    height-class="h-52"
                />
            </div>
        </CardContent>
    </Card>
</template>
