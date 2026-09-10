<script setup lang="ts">
import type { ChartDataset, ChartOptions, Plugin } from 'chart.js';
import { BarChart3, HelpCircle, Layers } from '@lucide/vue';
import { computed } from 'vue';
import BaseBarChart from '@/components/charts/BaseBarChart.vue';
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

interface DatasetItem extends ChartDataset<'bar'> {
    ci_lower?: number[];
    ci_upper?: number[];
}

interface ChartDataProp {
    labels?: string[];
    datasets?: DatasetItem[];
}

interface Props {
    chartData?: ChartDataProp;
    targetMonthName?: string;
    loading?: boolean;
    empty?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    chartData: () => ({ labels: [], datasets: [] }),
    targetMonthName: '',
    loading: false,
    empty: false,
});

const { __ } = useTrans();

const hasData = computed(() => {
    if (props.empty) return false;
    const datasets = props.chartData?.datasets;
    if (!datasets || datasets.length === 0) return false;
    const first = datasets[0];
    return Array.isArray(first?.data) && first.data.length > 0;
});

const errorBarPlugin = computed<Plugin<'bar'>>(() => ({
    id: 'forecastErrorBarWhiskerPlugin',
    afterDatasetDraw(chart, args) {
        const {
            ctx,
            scales: { y },
        } = chart;
        const meta = chart.getDatasetMeta(args.index);
        const dataset = chart.data.datasets[args.index] as DatasetItem;
        if (!dataset?.ci_lower || !dataset?.ci_upper) return;

        ctx.save();
        ctx.strokeStyle = '#334155'; // slate-700
        ctx.fillStyle = '#334155';
        ctx.lineWidth = 1.5;

        meta.data.forEach((bar, index) => {
            const ciLower = dataset.ci_lower?.[index];
            const ciUpper = dataset.ci_upper?.[index];
            if (ciLower === undefined || ciUpper === undefined) return;

            const xPos = bar.x;
            const yLower = y.getPixelForValue(ciLower);
            const yUpper = y.getPixelForValue(ciUpper);
            const capWidth = 5;

            // Vertical whisker stem
            ctx.beginPath();
            ctx.moveTo(xPos, yLower);
            ctx.lineTo(xPos, yUpper);
            ctx.stroke();

            // Upper horizontal cap
            ctx.beginPath();
            ctx.moveTo(xPos - capWidth, yUpper);
            ctx.lineTo(xPos + capWidth, yUpper);
            ctx.stroke();

            // Lower horizontal cap
            ctx.beginPath();
            ctx.moveTo(xPos - capWidth, yLower);
            ctx.lineTo(xPos + capWidth, yLower);
            ctx.stroke();
        });

        ctx.restore();
    },
}));

const formattedDatasets = computed<ChartDataset<'bar'>[]>(() => {
    const rawDatasets = props.chartData?.datasets ?? [];
    if (rawDatasets.length === 0) return [];

    return rawDatasets.map((ds) => ({
        ...ds,
        label: ds.label ?? __('Prediksi Jam Lembur (Jam)'),
        backgroundColor: '#3b82f6', // HKN blue
        hoverBackgroundColor: '#2563eb',
        borderColor: '#1d4ed8',
        borderWidth: 1,
        borderRadius: 4,
        barPercentage: 0.55,
        categoryPercentage: 0.7,
    }));
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
                font: {
                    family: '"Instrument Sans", sans-serif',
                    size: 11,
                    weight: 600,
                },
                color: '#64748b',
            },
            title: {
                display: true,
                text: __('Kode Seksi Produksi'),
                font: {
                    size: 11,
                    weight: 'bold',
                },
                color: '#94a3b8',
            },
        },
        y: {
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
                callback: (value) => `${value} h`,
            },
            title: {
                display: true,
                text: __('Proyeksi Jam (Jam)'),
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
                    return `${__('Seksi')}: ${items[0].label}`;
                },
                label: (context) => {
                    const val = Number(context.parsed.y);
                    return `${__('Prediksi Kebutuhan')}: ${val.toFixed(1)} ${__('jam')}`;
                },
                afterBody: (items) => {
                    if (!items.length) return [];
                    const index = items[0].dataIndex;
                    const ds = props.chartData?.datasets?.[0];
                    const ciLower = ds?.ci_lower?.[index];
                    const ciUpper = ds?.ci_upper?.[index];

                    if (ciLower !== undefined && ciUpper !== undefined) {
                        const margin = Math.abs(ciUpper - ciLower) / 2;
                        return [
                            `------------------------`,
                            `${__('Rentang Keyakinan')}: ${ciLower.toFixed(1)} – ${ciUpper.toFixed(1)} jam`,
                            `${__('Toleransi Ketidakpastian')}: ±${margin.toFixed(1)} jam`,
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
        data-test="forecast-bar-chart-card"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <BarChart3
                            class="size-5 text-blue-600 dark:text-blue-400"
                        />
                        <CardTitle
                            class="text-base font-bold tracking-tight text-slate-900 dark:text-white"
                        >
                            {{ __('Prediksi Jam Lembur Bulan Depan') }}
                        </CardTitle>
                        <Badge
                            v-if="targetMonthName"
                            variant="secondary"
                            class="font-mono text-[11px] tabular-nums"
                            data-test="target-month-badge"
                        >
                            {{ targetMonthName }}
                        </Badge>
                    </div>
                    <CardDescription class="mt-1 text-xs">
                        {{
                            __(
                                'Estimasi alokasi jam lembur per seksi dengan batas error bar (interval keyakinan 90%).',
                            )
                        }}
                    </CardDescription>
                </div>

                <!-- Legend Indicators -->
                <div
                    class="flex items-center gap-3 text-xs text-slate-600 dark:text-slate-300"
                >
                    <div class="flex items-center gap-1.5">
                        <span class="size-3 rounded-xs bg-[#3b82f6]"></span>
                        <span>{{ __('Prediksi Jam') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span
                            class="inline-block h-3 w-1.5 border-y-2 border-slate-700 bg-slate-700 dark:border-slate-300 dark:bg-slate-300"
                        ></span>
                        <span>{{ __('Error Bar (± Margin)') }}</span>
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="pt-1">
            <BaseBarChart
                :labels="chartData?.labels ?? []"
                :datasets="formattedDatasets"
                :options="chartOptions"
                :plugins="[errorBarPlugin]"
                :loading="loading"
                :empty="!hasData"
                height-class="h-72"
                data-test="forecast-bar-canvas"
            />
        </CardContent>
    </Card>
</template>
