<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import { TrendingUp } from '@lucide/vue';
import { computed } from 'vue';
import BaseLineChart from '@/components/charts/BaseLineChart.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

export interface TrendProjectionData {
    labels?: string[];
    historical_series?: (number | null)[];
    projected_series?: (number | null)[];
    ci_lower_series?: (number | null)[];
    ci_upper_series?: (number | null)[];
}

interface Props {
    data?: TrendProjectionData;
    loading?: boolean;
    empty?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: () => ({
        labels: [],
        historical_series: [],
        projected_series: [],
        ci_lower_series: [],
        ci_upper_series: [],
    }),
    loading: false,
    empty: false,
});

const { __ } = useTrans();

const hasData = computed(() => {
    if (props.empty) return false;
    const labels = props.data?.labels;
    return Array.isArray(labels) && labels.length > 0;
});

const datasets = computed<ChartDataset<'line'>[]>(() => {
    if (!props.data) return [];

    const hist = props.data.historical_series ?? [];
    const proj = props.data.projected_series ?? [];
    const ciUpper = props.data.ci_upper_series ?? [];
    const ciLower = props.data.ci_lower_series ?? [];

    return [
        {
            label: __('Realisasi Historis'),
            data: hist,
            borderColor: '#2563eb', // Blue
            backgroundColor: '#2563eb',
            borderWidth: 2.5,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#2563eb',
            tension: 0.15,
            spanGaps: false,
        },
        {
            label: __('Proyeksi Model (ML / Baseline)'),
            data: proj,
            borderColor: '#8b5cf6', // Violet
            backgroundColor: '#8b5cf6',
            borderWidth: 2.5,
            borderDash: [5, 5],
            pointRadius: 4,
            pointStyle: 'triangle',
            pointHoverRadius: 6,
            pointBackgroundColor: '#8b5cf6',
            tension: 0.2,
            spanGaps: false,
        },
        {
            label: __('Batas Atas Keyakinan 90%'),
            data: ciUpper,
            borderColor: 'rgba(139, 92, 246, 0.35)',
            borderWidth: 1,
            borderDash: [3, 3],
            pointRadius: 0,
            fill: false,
            tension: 0.2,
        },
        {
            label: __('Pita Keyakinan 90% (CI)'),
            data: ciLower,
            borderColor: 'rgba(139, 92, 246, 0.35)',
            borderWidth: 1,
            borderDash: [3, 3],
            backgroundColor: 'rgba(139, 92, 246, 0.12)',
            fill: '-1', // Fill up to previous dataset (ciUpper)
            pointRadius: 0,
            tension: 0.2,
        },
    ];
});

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    scales: {
        x: {
            grid: {
                color: 'rgba(226, 232, 240, 0.6)',
            },
            ticks: {
                font: {
                    family: '"Instrument Sans", sans-serif',
                    size: 11,
                    weight: 600,
                },
                color: '#64748b',
            },
        },
        y: {
            beginAtZero: false,
            grid: {
                color: 'rgba(226, 232, 240, 0.6)',
            },
            ticks: {
                font: {
                    family: 'monospace',
                    size: 11,
                },
                color: '#64748b',
                callback: (val) => `${val} h`,
            },
            title: {
                display: true,
                text: __('Total Jam Lembur Bulanan (Jam)'),
                font: {
                    size: 11,
                    weight: 'bold',
                },
                color: '#94a3b8',
            },
        },
    },
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            backgroundColor: '#0f172a',
            titleColor: '#ffffff',
            bodyColor: '#e2e8f0',
            padding: 10,
            cornerRadius: 8,
            callbacks: {
                title: (items) => {
                    if (!items.length) return '';
                    return `${__('Periode')}: ${items[0].label}`;
                },
                label: (context) => {
                    const dsIndex = context.datasetIndex;
                    const val = context.parsed.y;
                    if (val === null || val === undefined) return '';

                    if (dsIndex === 0) {
                        return `${__('Realisasi Historis')}: ${val.toFixed(1)} ${__('jam')}`;
                    }
                    if (dsIndex === 1) {
                        return `${__('Proyeksi Model')}: ${val.toFixed(1)} ${__('jam')}`;
                    }
                    return '';
                },
                afterBody: (items) => {
                    if (!items.length) return [];
                    const index = items[0].dataIndex;
                    const lower = props.data?.ci_lower_series?.[index];
                    const upper = props.data?.ci_upper_series?.[index];

                    if (
                        lower !== null &&
                        lower !== undefined &&
                        upper !== null &&
                        upper !== undefined
                    ) {
                        return [
                            `------------------------`,
                            `${__('Rentang 90% Keyakinan')}: ${lower.toFixed(1)} – ${upper.toFixed(1)} jam`,
                        ];
                    }
                    return [];
                },
            },
        },
    },
}));
</script>

<template>
    <Card
        class="border-border/70 overflow-hidden shadow-2xs"
        data-test="trend-projection-chart-card"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <TrendingUp
                            class="size-5 text-violet-600 dark:text-violet-400"
                        />
                        <CardTitle
                            class="text-base font-bold tracking-tight text-slate-900 dark:text-white"
                        >
                            {{ __('Prediksi Tren (6 Bulan)') }}
                        </CardTitle>
                        <Badge
                            variant="outline"
                            class="border-violet-200 bg-violet-50 font-mono text-[11px] text-violet-700 tabular-nums dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-300"
                        >
                            {{ __('3 Histori + 3 Proyeksi') }}
                        </Badge>
                    </div>
                    <CardDescription class="mt-1 text-xs">
                        {{
                            __(
                                'Kombinasi 3 bulan histori realisasi dan 3 bulan estimasi proyeksi tren dengan pita ketidakpastian 90%.',
                            )
                        }}
                    </CardDescription>
                </div>

                <!-- Legend Indicators -->
                <div
                    class="flex flex-wrap items-center gap-3 text-xs text-slate-600 dark:text-slate-300"
                >
                    <div class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-full bg-[#2563eb]"></span>
                        <span>{{ __('Historis') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-full bg-[#8b5cf6]"></span>
                        <span>{{ __('Proyeksi') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span
                            class="inline-block h-2.5 w-4 rounded-xs border border-violet-400 bg-violet-200/50 dark:bg-violet-900/40"
                        ></span>
                        <span>{{ __('Pita Keyakinan 90%') }}</span>
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="pt-1">
            <BaseLineChart
                :labels="data?.labels ?? []"
                :datasets="datasets"
                :options="chartOptions"
                :loading="loading"
                :empty="!hasData"
                height-class="h-72"
                data-test="trend-projection-canvas"
            />
        </CardContent>
    </Card>
</template>
