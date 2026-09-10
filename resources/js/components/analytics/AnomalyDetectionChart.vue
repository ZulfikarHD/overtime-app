<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import {
    Activity,
    AlertOctagon,
    AlertTriangle,
    BarChart3,
    CheckCircle2,
    Info,
    ShieldAlert,
} from '@lucide/vue';
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

export interface AnomalyItem {
    date: string;
    label: string;
    index: number;
    hours: number;
    anomaly_score: number;
    reasons: string[];
}

export interface AnomalyDetectionData {
    labels: string[];
    dates: string[];
    daily_hours: number[];
    mean: number;
    std_dev: number;
    upper_band: number;
    lower_band: number;
    unusual_patterns_count: number;
    summary: string;
    anomalies: AnomalyItem[];
}

interface Props {
    data?: AnomalyDetectionData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: () => ({
        labels: [],
        dates: [],
        daily_hours: [],
        mean: 0,
        std_dev: 0,
        upper_band: 0,
        lower_band: 0,
        unusual_patterns_count: 0,
        summary: '',
        anomalies: [],
    }),
    loading: false,
});

const { __ } = useTrans();

const anomalyPointsSeries = computed(() => {
    if (!props.data.daily_hours || props.data.daily_hours.length === 0) {
        return [];
    }

    const anomalyDateSet = new Set(
        (props.data.anomalies || []).map((a) => a.date),
    );

    return props.data.dates.map((d, idx) => {
        if (anomalyDateSet.has(d)) {
            return props.data.daily_hours[idx];
        }
        return null;
    });
});

const chartDatasets = computed<ChartDataset<'line'>[]>(() => {
    const len = props.data.labels?.length || 0;
    if (len === 0) {
        return [];
    }

    const meanLine = Array(len).fill(props.data.mean);
    const upperLine = Array(len).fill(props.data.upper_band);
    const lowerLine = Array(len).fill(props.data.lower_band);

    return [
        {
            label: __('Jam Lembur Aktual'),
            data: props.data.daily_hours,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.08)',
            borderWidth: 2,
            tension: 0.2,
            pointRadius: 2.5,
            pointBackgroundColor: '#2563eb',
            order: 3,
        },
        {
            label: __('Batas Atas (+1σ)'),
            data: upperLine,
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245, 158, 11, 0.08)',
            borderWidth: 1.5,
            borderDash: [5, 4],
            pointRadius: 0,
            fill: '+1',
            order: 4,
        },
        {
            label: __('Batas Bawah (-1σ)'),
            data: lowerLine,
            borderColor: '#f59e0b',
            borderWidth: 1.5,
            borderDash: [5, 4],
            pointRadius: 0,
            fill: false,
            order: 5,
        },
        {
            label: __('Rata-rata (Mean)'),
            data: meanLine,
            borderColor: '#94a3b8',
            borderWidth: 1.5,
            borderDash: [2, 2],
            pointRadius: 0,
            fill: false,
            order: 6,
        },
        {
            label: __('Titik Anomali'),
            data: anomalyPointsSeries.value,
            showLine: false,
            pointRadius: 6,
            pointHoverRadius: 8,
            pointBackgroundColor: '#dc2626',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            order: 1,
        },
    ];
});

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'top',
            labels: {
                boxWidth: 12,
                boxHeight: 12,
                font: {
                    size: 11,
                },
            },
        },
        tooltip: {
            callbacks: {
                label(context) {
                    const val = context.parsed.y;
                    if (val === null || val === undefined) {
                        return '';
                    }
                    if (context.dataset.label === __('Titik Anomali')) {
                        return `${__('⚠️ Lonjakan Anomali')}: ${val.toFixed(1)} ${__('jam')}`;
                    }
                    return `${context.dataset.label}: ${val.toFixed(1)} ${__('jam')}`;
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: false,
            },
            ticks: {
                maxRotation: 45,
                autoSkip: true,
                maxTicksLimit: 15,
                font: {
                    size: 10,
                },
            },
        },
        y: {
            beginAtZero: true,
            title: {
                display: true,
                text: __('Jam Lembur'),
                font: {
                    size: 11,
                },
            },
            ticks: {
                font: {
                    family: 'monospace',
                    size: 10,
                },
            },
        },
    },
}));
</script>

<template>
    <div id="anomaly-chart" data-test="anomaly-detection-chart">
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader
                class="flex flex-col gap-2 pb-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <Activity class="size-5 text-[#cc0000]" />
                        <span>{{
                            __(
                                'Deteksi Anomali Jam Lembur Harian (30 Hari Terakhir)',
                            )
                        }}</span>
                    </CardTitle>
                    <CardDescription>
                        {{
                            __(
                                'Pita bayangan menampilkan rentang batas wajar statistik (rata-rata ± 1 deviasi standar). Titik merah menandai deviasi tajam.',
                            )
                        }}
                    </CardDescription>
                </div>

                <div class="flex items-center gap-2">
                    <Badge
                        v-if="data.unusual_patterns_count > 0"
                        variant="destructive"
                        class="border-red-200 bg-red-50 text-[#cc0000] dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                        data-test="badge-unusual-count"
                    >
                        {{ data.unusual_patterns_count }}
                        {{ __('Pola Tidak Biasa') }}
                    </Badge>
                    <Badge
                        v-else
                        variant="outline"
                        class="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
                    >
                        {{ __('Stabil (0 Anomali)') }}
                    </Badge>
                </div>
            </CardHeader>

            <CardContent class="space-y-4">
                <!-- Line Chart Canvas -->
                <div class="h-72 w-full">
                    <BaseLineChart
                        :labels="data.labels"
                        :datasets="chartDatasets"
                        :options="chartOptions"
                        :loading="loading"
                        height-class="h-72"
                    />
                </div>

                <!-- 4 Statistical Metric Mini Cards -->
                <div
                    class="grid grid-cols-2 gap-3 sm:grid-cols-4"
                    data-test="anomaly-stats-grid"
                >
                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-800/40"
                        data-test="stat-card-mean"
                    >
                        <span class="text-[11px] font-medium text-slate-500">
                            {{ __('Rata-rata Harian (Mean)') }}
                        </span>
                        <div
                            class="mt-1 font-mono text-base font-bold text-slate-900 tabular-nums dark:text-white"
                            data-test="stat-mean"
                        >
                            {{ data.mean.toFixed(1) }}
                            <span class="text-xs font-normal text-slate-500"
                                >jam</span
                            >
                        </div>
                    </div>

                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-800/40"
                        data-test="stat-card-std-dev"
                    >
                        <span class="text-[11px] font-medium text-slate-500">
                            {{ __('Deviasi Standar (σ)') }}
                        </span>
                        <div
                            class="mt-1 font-mono text-base font-bold text-slate-900 tabular-nums dark:text-white"
                            data-test="stat-std-dev"
                        >
                            ± {{ data.std_dev.toFixed(1) }}
                            <span class="text-xs font-normal text-slate-500"
                                >jam</span
                            >
                        </div>
                    </div>

                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-800/40"
                        data-test="stat-card-normal-range"
                    >
                        <span class="text-[11px] font-medium text-slate-500">
                            {{ __('Rentang Wajar (±1σ)') }}
                        </span>
                        <div
                            class="mt-1 font-mono text-base font-bold text-amber-600 tabular-nums dark:text-amber-400"
                        >
                            {{ data.lower_band.toFixed(1) }} -
                            {{ data.upper_band.toFixed(1) }}
                            <span class="text-xs font-normal text-slate-500"
                                >jam</span
                            >
                        </div>
                    </div>

                    <div
                        class="rounded-lg border p-3"
                        :class="
                            data.unusual_patterns_count > 0
                                ? 'border-red-200 bg-red-50/60 dark:border-red-900/40 dark:bg-red-950/20'
                                : 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/40 dark:bg-emerald-950/20'
                        "
                        data-test="stat-card-anomalies"
                    >
                        <span
                            class="text-[11px] font-medium"
                            :class="
                                data.unusual_patterns_count > 0
                                    ? 'text-red-700 dark:text-red-400'
                                    : 'text-emerald-700 dark:text-emerald-400'
                            "
                        >
                            {{ __('Pola Terdeteksi') }}
                        </span>
                        <div
                            class="mt-1 font-mono text-base font-bold tabular-nums"
                            :class="
                                data.unusual_patterns_count > 0
                                    ? 'text-[#cc0000] dark:text-red-400'
                                    : 'text-emerald-700 dark:text-emerald-300'
                            "
                            data-test="stat-anomalies"
                        >
                            {{ data.unusual_patterns_count }}
                            <span class="text-xs font-normal">{{
                                __('hari')
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- Anomaly Summary Footer Banner -->
                <div
                    class="flex items-center gap-2 rounded-lg border p-3 text-xs"
                    :class="
                        data.unusual_patterns_count > 0
                            ? 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200'
                            : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-300'
                    "
                    data-test="anomaly-summary-banner"
                >
                    <Info class="size-4 shrink-0" />
                    <span>{{
                        data.summary ||
                        __(
                            'Tidak ada pola anomali lonjakan yang terdeteksi dalam 30 hari terakhir.',
                        )
                    }}</span>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
