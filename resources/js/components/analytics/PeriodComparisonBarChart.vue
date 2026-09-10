<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import {
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    HelpCircle,
    Layers,
} from '@lucide/vue';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import ChartSkeleton from '@/components/charts/ChartSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { registerChartDefaults } from '@/plugins/chartjs';

registerChartDefaults();

export interface PeriodComparisonChartData {
    title?: string;
    labels?: string[];
    base_series?: number[];
    compare_series?: number[];
    variance_series?: number[];
    base_label?: string;
    compare_label?: string;
}

interface Props {
    data?: PeriodComparisonChartData;
    comparisonType?: string;
    loading?: boolean;
    empty?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: () => ({
        title: '',
        labels: [],
        base_series: [],
        compare_series: [],
        variance_series: [],
        base_label: 'Periode Basis',
        compare_label: 'Periode Pembanding',
    }),
    comparisonType: 'yoy',
    loading: false,
    empty: false,
});

const { __ } = useTrans();

const hasData = computed(() => {
    if (props.empty) return false;
    const labels = props.data?.labels;
    return Array.isArray(labels) && labels.length > 0;
});

const chartLabels = computed(() => props.data?.labels ?? []);

const datasets = computed<any[]>(() => {
    if (!props.data) return [];

    const base = props.data.base_series ?? [];
    const compare = props.data.compare_series ?? [];
    const variance = props.data.variance_series ?? [];

    return [
        {
            type: 'bar' as const,
            label: props.data.base_label || __('Periode Basis'),
            data: base,
            backgroundColor: '#2563eb', // ISUZU Brand Navy/Blue
            borderRadius: 4,
            barPercentage: 0.75,
            categoryPercentage: 0.8,
            yAxisID: 'y',
            order: 2,
        },
        {
            type: 'bar' as const,
            label: props.data.compare_label || __('Periode Pembanding'),
            data: compare,
            backgroundColor: '#94a3b8', // Slate-400
            borderRadius: 4,
            barPercentage: 0.75,
            categoryPercentage: 0.8,
            yAxisID: 'y',
            order: 3,
        },
        {
            type: 'line' as const,
            label: __('Varians (%)'),
            data: variance,
            borderColor: '#cc0000', // ISUZU Brand Red
            backgroundColor: '#cc0000',
            pointBackgroundColor: '#cc0000',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 1.5,
            pointRadius: 3.5,
            pointHoverRadius: 6,
            borderWidth: 2,
            tension: 0.25,
            yAxisID: 'y1',
            order: 1,
        },
    ];
});

const chartOptions = computed<ChartOptions<'bar'>>(() => ({
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
                color: '#64748b',
                font: {
                    family: '"Instrument Sans", sans-serif',
                    size: 11,
                },
            },
        },
        y: {
            type: 'linear',
            position: 'left',
            grid: {
                color: 'rgba(226, 232, 240, 0.6)',
            },
            ticks: {
                color: '#64748b',
                font: {
                    family: '"Instrument Sans", sans-serif',
                    size: 11,
                },
                callback(val: any) {
                    return `${val} jam`;
                },
            },
            title: {
                display: true,
                text: __('Total Jam Lembur'),
                color: '#64748b',
                font: {
                    family: '"Instrument Sans", sans-serif',
                    size: 11,
                    weight: 500,
                },
            },
        },
        y1: {
            type: 'linear',
            position: 'right',
            grid: {
                drawOnChartArea: false,
            },
            ticks: {
                color: '#cc0000',
                font: {
                    family: '"Instrument Sans", sans-serif',
                    size: 11,
                    weight: 600,
                },
                callback(val: any) {
                    return `${Number(val) > 0 ? '+' : ''}${val}%`;
                },
            },
            title: {
                display: true,
                text: __('Varians (%)'),
                color: '#cc0000',
                font: {
                    family: '"Instrument Sans", sans-serif',
                    size: 11,
                    weight: 600,
                },
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
                usePointStyle: false,
                color: '#64748b',
                font: {
                    family: '"Instrument Sans", sans-serif',
                    size: 11,
                },
            },
        },
        tooltip: {
            backgroundColor: '#0f172a',
            titleColor: '#ffffff',
            bodyColor: '#f8fafc',
            borderColor: '#334155',
            borderWidth: 1,
            padding: 10,
            cornerRadius: 6,
            callbacks: {
                label(context) {
                    const label = context.dataset.label || '';
                    const val = Number(context.raw ?? 0);
                    if (context.datasetIndex === 2) {
                        return ` ${label}: ${val >= 0 ? '+' : ''}${val.toFixed(1)}%`;
                    }
                    return ` ${label}: ${val.toFixed(1)} jam`;
                },
            },
        },
    },
}));
</script>

<template>
    <Card
        class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        data-test="period-comparison-bar-chart"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <BarChart3 class="size-4 text-[#cc0000]" />
                        <span>{{
                            data?.title || __('Perbandingan Periode Lembur')
                        }}</span>
                    </CardTitle>
                    <CardDescription>
                        {{
                            __(
                                'Visualisasi jam lembur perbandingan berdampingan dengan garis overlay persentase selisih.',
                            )
                        }}
                    </CardDescription>
                </div>
                <div class="flex items-center gap-2">
                    <Badge
                        variant="outline"
                        class="font-mono text-xs text-slate-600 tabular-nums dark:text-slate-300"
                    >
                        {{ data?.base_label }} vs {{ data?.compare_label }}
                    </Badge>
                </div>
            </div>
        </CardHeader>
        <CardContent>
            <div class="relative h-80 w-full">
                <ChartSkeleton
                    v-if="loading"
                    height-class="h-80"
                    variant="bar"
                />

                <div
                    v-else-if="!hasData"
                    class="flex h-full w-full flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 p-6 text-center dark:border-slate-800"
                >
                    <Layers class="mb-2 size-8 text-slate-400" />
                    <p
                        class="text-sm font-medium text-slate-600 dark:text-slate-300"
                    >
                        {{
                            __(
                                'Belum ada data perbandingan untuk periode terpilih',
                            )
                        }}
                    </p>
                    <p class="mt-1 text-xs text-slate-400">
                        {{
                            __(
                                'Pilih rentang bulan lain atau ubah mode perbandingan.',
                            )
                        }}
                    </p>
                </div>

                <Bar
                    v-else
                    :data="{ labels: chartLabels, datasets }"
                    :options="chartOptions"
                />
            </div>
        </CardContent>
    </Card>
</template>
