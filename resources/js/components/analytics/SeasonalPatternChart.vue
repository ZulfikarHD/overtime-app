<script setup lang="ts">
import type { ChartDataset, ChartOptions, Plugin } from 'chart.js';
import { Calendar, Flame } from '@lucide/vue';
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

interface Props {
    labels?: string[];
    monthlyAverages?: number[];
    grandAverage?: number;
    peakQuarter?: string;
    peakQuarterIndex?: number;
    loading?: boolean;
    empty?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    labels: () => [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agu',
        'Sep',
        'Okt',
        'Nov',
        'Des',
    ],
    monthlyAverages: () => [],
    grandAverage: 0,
    peakQuarter: 'Q4 (Okt–Des)',
    peakQuarterIndex: 4,
    loading: false,
    empty: false,
});

const { __ } = useTrans();

const hasData = computed(() => {
    if (props.empty) return false;
    return (
        Array.isArray(props.monthlyAverages) &&
        props.monthlyAverages.length > 0 &&
        props.monthlyAverages.some((v) => v > 0)
    );
});

// Canvas plugin to highlight the peak quarter months
const peakQuarterShadingPlugin = computed<Plugin<'line'>>(() => ({
    id: 'peakQuarterShadingPlugin',
    beforeDraw(chart) {
        const {
            ctx,
            chartArea,
            scales: { x },
        } = chart;
        if (!chartArea || !x) return;

        const qIdx = props.peakQuarterIndex || 4;
        // Q1: indices 0..2, Q2: 3..5, Q3: 6..8, Q4: 9..11
        const startIdx = (qIdx - 1) * 3;
        const endIdx = startIdx + 2;

        const xStart = Math.max(
            chartArea.left,
            x.getPixelForValue(startIdx) - x.width / 24,
        );
        const xEnd = Math.min(
            chartArea.right,
            x.getPixelForValue(endIdx) + x.width / 24,
        );

        ctx.save();
        // Subtle amber background tint for peak quarter
        ctx.fillStyle = 'rgba(245, 158, 11, 0.08)';
        ctx.fillRect(
            xStart,
            chartArea.top,
            xEnd - xStart,
            chartArea.bottom - chartArea.top,
        );

        // Top border line
        ctx.strokeStyle = 'rgba(217, 119, 6, 0.5)';
        ctx.lineWidth = 1;
        ctx.setLineDash([4, 3]);
        ctx.beginPath();
        ctx.moveTo(xStart, chartArea.top);
        ctx.lineTo(xEnd, chartArea.top);
        ctx.stroke();

        ctx.restore();
    },
}));

const datasets = computed<ChartDataset<'line'>[]>(() => {
    if (!hasData.value) return [];

    const series: ChartDataset<'line'>[] = [
        {
            label: __('Rata-rata Jam Lembur Bulanan'),
            data: props.monthlyAverages,
            borderColor: '#f59e0b', // Amber
            backgroundColor: 'rgba(245, 158, 11, 0.12)',
            borderWidth: 2.5,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#d97706',
            fill: true,
            tension: 0.25,
        },
    ];

    if (props.grandAverage > 0) {
        series.push({
            label: __('Garis Rata-rata Tahunan'),
            data: Array(props.labels.length).fill(props.grandAverage),
            borderColor: '#94a3b8',
            borderWidth: 1.5,
            borderDash: [5, 4],
            pointRadius: 0,
            fill: false,
        });
    }

    return series;
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
                color: 'rgba(226, 232, 240, 0.5)',
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
            beginAtZero: true,
            grid: {
                color: 'rgba(226, 232, 240, 0.5)',
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
                text: __('Rata-rata Jam (Jam)'),
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
                    return `${__('Bulan')}: ${items[0].label}`;
                },
                label: (context) => {
                    const dsIndex = context.datasetIndex;
                    const val = Number(context.parsed.y);
                    if (dsIndex === 0) {
                        return `${__('Rata-rata Lembur')}: ${val.toFixed(1)} ${__('jam')}`;
                    }
                    return `${__('Rata-rata Tahunan')}: ${val.toFixed(1)} ${__('jam')}`;
                },
                afterBody: (items) => {
                    if (!items.length || props.grandAverage <= 0) return [];
                    const index = items[0].dataIndex;
                    const val = props.monthlyAverages[index] ?? 0;
                    const diff = val - props.grandAverage;
                    const diffPct = (diff / props.grandAverage) * 100;
                    const sign = diff >= 0 ? '+' : '';
                    return [
                        `------------------------`,
                        `${__('Variansi vs Rata-rata')}: ${sign}${diffPct.toFixed(1)}%`,
                    ];
                },
            },
        },
    },
}));
</script>

<template>
    <Card
        class="border-border/70 overflow-hidden shadow-2xs"
        data-test="seasonal-pattern-chart-card"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <Calendar
                            class="size-5 text-amber-600 dark:text-amber-400"
                        />
                        <CardTitle
                            class="text-base font-bold tracking-tight text-slate-900 dark:text-white"
                        >
                            {{ __('Analisis Pola Musiman') }}
                        </CardTitle>
                        <Badge
                            variant="outline"
                            class="border-amber-300 bg-amber-50 font-mono text-[11px] text-amber-700 tabular-nums dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                            data-test="peak-quarter-badge"
                        >
                            <Flame class="mr-1 size-3 text-amber-600" />
                            {{ __('Puncak') }}: {{ peakQuarter }}
                        </Badge>
                    </div>
                    <CardDescription class="mt-1 text-xs">
                        {{
                            __(
                                'Profil siklus tahunan rata-rata jam lembur 12 bulan (Januari–Desember) dengan arsiran kuartal puncak.',
                            )
                        }}
                    </CardDescription>
                </div>

                <!-- Legend Indicators -->
                <div
                    class="flex flex-wrap items-center gap-3 text-xs text-slate-600 dark:text-slate-300"
                >
                    <div class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-full bg-[#f59e0b]"></span>
                        <span>{{ __('Rata-rata Bulanan') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span
                            class="inline-block h-0.5 w-4 border-t-2 border-dashed border-slate-400"
                        ></span>
                        <span>{{ __('Rata-rata Tahunan') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span
                            class="inline-block size-3 rounded-xs border border-amber-300 bg-amber-100/60 dark:border-amber-800 dark:bg-amber-950/40"
                        ></span>
                        <span>{{ __('Zona Puncak') }}</span>
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="pt-1">
            <BaseLineChart
                :labels="labels"
                :datasets="datasets"
                :options="chartOptions"
                :plugins="[peakQuarterShadingPlugin]"
                :loading="loading"
                :empty="!hasData"
                height-class="h-72"
                data-test="seasonal-pattern-canvas"
            />
        </CardContent>
    </Card>
</template>
