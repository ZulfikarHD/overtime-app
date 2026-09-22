<script setup lang="ts">
import type { ChartData, ChartDataset, ChartOptions } from 'chart.js';
import { CalendarDays, MinusCircle } from '@lucide/vue';
import { computed } from 'vue';
import BaseBarChart from '@/components/charts/BaseBarChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

export interface YtdOvertimeIndexData {
    labels: string[];
    plan_index: number[];
    actual_index: number[];
    man_power: number[];
    fiscal_year: number;
    scope: {
        department_id: number | null;
        section_id: number | null;
    };
}

interface Props {
    data?: YtdOvertimeIndexData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: undefined,
    loading: false,
});

const { __ } = useTrans();

const isEmpty = computed(
    () =>
        !props.data ||
        (props.data.plan_index.every((v) => v === 0) &&
            props.data.actual_index.every((v) => v === 0)),
);

// Year to date totals
const ytdPlanTotal = computed(() => {
    return (props.data?.plan_index ?? []).reduce((s, v) => s + v, 0);
});
const ytdActualTotal = computed(() => {
    return (props.data?.actual_index ?? []).reduce((s, v) => s + v, 0);
});
const ytdBurnPct = computed(() =>
    ytdPlanTotal.value > 0
        ? Math.round((ytdActualTotal.value / ytdPlanTotal.value) * 100)
        : null,
);

const chartDatasets = computed<ChartDataset<'bar' | 'line'>[]>(() => [
    {
        type: 'bar' as const,
        label: __('Index Plan'),
        data: props.data?.plan_index ?? [],
        backgroundColor: 'rgba(30, 58, 95, 0.85)', // navy
        borderColor: '#1e3a5f',
        borderWidth: 0,
        borderRadius: 3,
        maxBarThickness: 30,
        order: 2,
    } as ChartDataset<'bar'>,
    {
        type: 'bar' as const,
        label: __('Index Aktual'),
        data: props.data?.actual_index ?? [],
        backgroundColor: 'rgba(232, 96, 28, 0.88)', // ISUZU orange
        borderColor: '#e8601c',
        borderWidth: 0,
        borderRadius: 3,
        maxBarThickness: 30,
        order: 3,
    } as ChartDataset<'bar'>,
    {
        type: 'line' as const,
        label: __('Jumlah MP'),
        data: props.data?.man_power ?? [],
        borderColor: '#dc2626',
        backgroundColor: 'rgba(220,38,38,0.15)',
        borderWidth: 2,
        pointRadius: 4,
        pointBackgroundColor: '#dc2626',
        tension: 0.3,
        yAxisID: 'y2',
        order: 1,
    } as ChartDataset<'line'>,
]);

const chartOptions = computed<ChartOptions<'bar'>>(() => {
    const maxIdx = Math.max(...(props.data?.plan_index ?? [0]), 1);
    const maxMp = Math.max(...(props.data?.man_power ?? [0]), 1);

    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                display: true,
                position: 'bottom',
                align: 'center',
                labels: {
                    boxWidth: 10,
                    boxHeight: 10,
                    font: { size: 10 },
                    padding: 10,
                    filter: (item) => item.text !== undefined,
                },
            },
            tooltip: {
                padding: 10,
                callbacks: {
                    label: (ctx) => {
                        const val = ctx.parsed.y ?? 0;
                        if (ctx.dataset.label?.includes('MP')) {
                            return ` ${ctx.dataset.label}: ${val} orang`;
                        }
                        return ` ${ctx.dataset.label}: ${val.toFixed(1)} idx`;
                    },
                },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: {
                    font: { size: 9 },
                    maxRotation: 30,
                    autoSkip: false,
                    callback: (_val, idx) => {
                        // Show only month name portion (before the parenthesis)
                        const label = props.data?.labels?.[idx] ?? '';
                        const parts = label.split('(');
                        return parts[0]?.trim() ?? label;
                    },
                },
            },
            y: {
                beginAtZero: true,
                max: Math.ceil(maxIdx * 1.15),
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
                    text: __('Index Overtime'),
                    font: { size: 9 },
                    color: '#94a3b8',
                },
            },
            y2: {
                beginAtZero: true,
                position: 'right',
                max: Math.ceil(maxMp * 1.5),
                grid: { display: false },
                ticks: {
                    font: { size: 10, family: 'monospace' },
                    color: '#dc2626',
                },
                title: {
                    display: true,
                    text: 'MP',
                    font: { size: 9 },
                    color: '#dc2626',
                },
            },
        },
    };
});

const chartData = computed<ChartData<'bar'>>(() => ({
    labels: props.data?.labels ?? [],
    datasets: chartDatasets.value as ChartDataset<'bar'>[],
}));
</script>

<template>
    <Card class="overflow-hidden">
        <CardHeader class="pb-2">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-bold"
                    >
                        <CalendarDays class="size-4 text-[#cc0000]" />
                        {{ __('Total Index Overtime Year to Date (YTD)') }}
                        {{ data?.fiscal_year ?? '' }}
                    </CardTitle>
                    <p class="text-muted-foreground mt-0.5 text-[11px]">
                        {{
                            __(
                                'Plan vs Aktual per Bulan — Index = Jam × Koefisien Hari',
                            )
                        }}
                    </p>
                </div>

                <!-- YTD summary badges -->
                <div class="flex items-center gap-3 text-[11px]">
                    <div class="flex items-center gap-1.5">
                        <span
                            class="inline-block size-2.5 rounded-sm bg-[#1e3a5f]"
                        />
                        <span class="text-slate-500">YTD Plan:</span>
                        <span
                            class="font-mono font-bold text-[#1e3a5f] tabular-nums"
                        >
                            {{ ytdPlanTotal.toFixed(1) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span
                            class="inline-block size-2.5 rounded-sm bg-[#e8601c]"
                        />
                        <span class="text-slate-500">YTD Aktual:</span>
                        <span
                            class="font-mono font-bold text-[#e8601c] tabular-nums"
                        >
                            {{ ytdActualTotal.toFixed(1) }}
                        </span>
                    </div>
                    <div
                        v-if="ytdBurnPct !== null"
                        class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                        :class="{
                            'bg-red-100 text-red-700': ytdBurnPct > 100,
                            'bg-amber-100 text-amber-700':
                                ytdBurnPct > 50 && ytdBurnPct <= 100,
                            'bg-slate-100 text-slate-600': ytdBurnPct <= 50,
                        }"
                    >
                        {{ ytdBurnPct }}% YTD
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="pt-0">
            <!-- Loading skeleton -->
            <div
                v-if="loading"
                class="h-56 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/40"
            />

            <!-- Empty state -->
            <div
                v-else-if="isEmpty"
                class="flex h-56 flex-col items-center justify-center gap-2 text-center"
            >
                <MinusCircle class="size-8 text-slate-300" />
                <p class="text-muted-foreground text-xs">
                    {{ __('Belum ada data YTD untuk tahun ini.') }}
                </p>
            </div>

            <!-- Mixed bar + line chart -->
            <div v-else class="h-56">
                <BaseBarChart
                    :labels="data?.labels ?? []"
                    :datasets="chartDatasets as any"
                    :options="chartOptions as any"
                    height-class="h-56"
                />
            </div>
        </CardContent>
    </Card>
</template>
