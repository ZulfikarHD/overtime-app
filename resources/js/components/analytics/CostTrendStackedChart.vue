<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import { Layers, TrendingUp } from '@lucide/vue';
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
import { chartColors } from '@/composables/useChartTheme';
import { useTrans } from '@/composables/useTrans';
import { formatCompactRupiah, formatRupiah } from '@/lib/formatters';

export interface MonthlyTrendData {
    labels?: string[];
    opex_series?: number[];
    capex_series?: number[];
    total_series?: number[];
}

interface Props {
    trendData?: MonthlyTrendData;
    loading?: boolean;
    empty?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    trendData: () => ({
        labels: [],
        opex_series: [],
        capex_series: [],
        total_series: [],
    }),
    loading: false,
    empty: false,
});

const { __ } = useTrans();

const hasData = computed(() => {
    if (props.empty) return false;
    const labels = props.trendData?.labels;
    return Array.isArray(labels) && labels.length > 0;
});

const chartLabels = computed(() => props.trendData?.labels ?? []);

const datasets = computed<ChartDataset<'line'>[]>(() => {
    if (!props.trendData) return [];

    const opex = props.trendData.opex_series ?? [];
    const capex = props.trendData.capex_series ?? [];

    return [
        {
            label: __('OpEx (Rutin Operasional)'),
            data: opex,
            borderColor: chartColors.opex,
            backgroundColor: 'rgba(8, 145, 178, 0.45)', // Cyan tint
            fill: true,
            tension: 0.3,
            borderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: chartColors.opex,
        },
        {
            label: __('CapEx (Terkapitalisasi Proyek)'),
            data: capex,
            borderColor: chartColors.capex,
            backgroundColor: 'rgba(124, 58, 237, 0.45)', // Violet tint
            fill: true,
            tension: 0.3,
            borderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: chartColors.capex,
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
                display: false,
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
            stacked: true,
            beginAtZero: true,
            grid: {
                color: 'rgba(226, 232, 240, 0.6)',
            },
            ticks: {
                font: {
                    family: 'monospace',
                    size: 11,
                },
                color: '#64748b',
                callback: (val) => formatCompactRupiah(Number(val)),
            },
            title: {
                display: true,
                text: __('Akumulasi Biaya (IDR)'),
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
            position: 'top',
            align: 'end',
            labels: {
                boxWidth: 12,
                boxHeight: 12,
                usePointStyle: true,
                font: {
                    family: '"Instrument Sans", sans-serif',
                    size: 11,
                    weight: 600,
                },
                color: '#475569',
            },
        },
        tooltip: {
            backgroundColor: '#0f172a',
            titleColor: '#ffffff',
            bodyColor: '#e2e8f0',
            padding: 10,
            cornerRadius: 8,
            callbacks: {
                label: (ctx) => {
                    const label = ctx.dataset.label || '';
                    const val = Number(ctx.parsed.y) || 0;
                    const formatted = formatRupiah(val, {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0,
                    });
                    return `${label}: ${formatted}`;
                },
                afterBody: (tooltipItems) => {
                    let total = 0;
                    for (const item of tooltipItems) {
                        total += Number(item.parsed.y) || 0;
                    }
                    const totalStr = formatRupiah(total, {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0,
                    });
                    return `--------------------\n${__('Total Akumulasi')}: ${totalStr}`;
                },
            },
        },
    },
}));
</script>

<template>
    <Card
        class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        data-test="cost-trend-stacked-chart"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-1">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <TrendingUp class="size-4 text-[#cc0000]" />
                        <span>{{ __('Tren Biaya Lembur (6 Bulan)') }}</span>
                    </CardTitle>
                    <CardDescription>
                        {{
                            __(
                                'Distribusi realisasi biaya lembur bulanan dengan pemisahan akun belanja operasional (OpEx) vs investasi aset modal (CapEx).',
                            )
                        }}
                    </CardDescription>
                </div>

                <div class="flex items-center gap-2">
                    <Badge
                        variant="secondary"
                        class="bg-cyan-50 text-xs font-semibold text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-300"
                    >
                        {{ __('OpEx: Operasional') }}
                    </Badge>
                    <Badge
                        variant="secondary"
                        class="bg-violet-50 text-xs font-semibold text-violet-700 dark:bg-violet-950/40 dark:text-violet-300"
                    >
                        {{ __('CapEx: Terkapitalisasi') }}
                    </Badge>
                </div>
            </div>
        </CardHeader>

        <CardContent>
            <BaseLineChart
                :labels="chartLabels"
                :datasets="datasets"
                :options="chartOptions"
                :loading="loading"
                :empty="!hasData"
                height-class="h-72"
                :empty-text="__('Belum ada data tren biaya 6 bulan terakhir.')"
            />
            <div
                v-if="hasData && !loading"
                class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400"
            >
                <div class="flex items-center gap-1">
                    <Layers class="size-3.5" />
                    <span>{{
                        __(
                            'Grafik bertumpuk menampilkan total beban biaya kumulatif per periode.',
                        )
                    }}</span>
                </div>
                <div class="font-mono tabular-nums">
                    {{ chartLabels.length }} {{ __('Bulan Historis') }}
                </div>
            </div>
        </CardContent>
    </Card>
</template>
