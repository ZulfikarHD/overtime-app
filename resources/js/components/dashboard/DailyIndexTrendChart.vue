<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import { Activity, AlertCircle } from '@lucide/vue';
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

export interface DailyIndexTrendData {
    labels: string[];
    daily_indices: (number | null)[];
    daily_hours: (number | null)[];
    planned_daily_pacing_hours: number;
    threshold_pct: number;
    average_index: number;
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
    data?: DailyIndexTrendData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: undefined,
    loading: false,
});

const { __ } = useTrans();

const thresholdLineData = computed(() => {
    if (!props.data?.labels?.length) {
        return [];
    }
    const threshold = props.data.threshold_pct || 100;
    return props.data.labels.map(() => threshold);
});

const datasets = computed<ChartDataset<'line'>[]>(() => {
    if (!props.data?.labels?.length) {
        return [];
    }

    const series: ChartDataset<'line'>[] = [
        {
            label: __('Indeks Kontribusi Harian (%)'),
            data: props.data.daily_indices,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.08)',
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: props.data.daily_indices.map((val) => {
                if (val === null) {
                    return 'transparent';
                }
                return val > (props.data?.threshold_pct ?? 100)
                    ? '#dc2626'
                    : '#2563eb';
            }),
            fill: true,
            tension: 0.15,
            spanGaps: false,
        },
        {
            label: __('Batas Kebijakan (100% Pacing)'),
            data: thresholdLineData.value,
            borderColor: '#dc2626',
            backgroundColor: 'transparent',
            borderWidth: 1.5,
            borderDash: [6, 4],
            pointRadius: 0,
            pointHoverRadius: 0,
        },
    ];

    return series;
});

const chartOptions = computed<ChartOptions<'line'>>(() => {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                titleColor: '#f8fafc',
                bodyColor: '#cbd5e1',
                padding: 10,
                callbacks: {
                    title: (items) => {
                        const day = items[0]?.label ?? '';
                        return `${__('Tanggal')} ${day} ${props.data?.month_name ?? ''}`;
                    },
                    label: (context) => {
                        const idx = context.dataIndex;
                        const dsIdx = context.datasetIndex;
                        if (dsIdx === 1) {
                            return ` ${__('Batas Pacing')}: ${context.parsed.y}%`;
                        }
                        const indexVal = props.data?.daily_indices[idx];
                        const hoursVal = props.data?.daily_hours[idx];
                        if (indexVal === null || indexVal === undefined) {
                            return ` ${__('Belum ada data')}`;
                        }
                        return [
                            ` ${__('Indeks Burn Harian')}: ${indexVal}%`,
                            ` ${__('Realisasi Jam')}: ${hoursVal ?? 0} ${__('jam')}`,
                        ];
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
                    font: {
                        family: 'monospace',
                        size: 10,
                    },
                    maxRotation: 0,
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
                    callback: (value) => `${value}%`,
                },
            },
        },
    };
});
</script>

<template>
    <Card
        class="border-border/70 flex flex-col justify-between shadow-2xs"
        data-test="daily-index-trend-card"
    >
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100"
                    >
                        <Activity class="size-4 text-blue-600" />
                        <span>{{ __('Tren Indeks Kontribusi Harian') }}</span>
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{
                            __(
                                'Tingkat konsumsi lembur harian terhadap target pacing pada :month',
                                { month: data?.month_name || '' },
                            )
                        }}
                    </CardDescription>
                </div>

                <div class="flex items-center gap-2">
                    <Badge
                        variant="outline"
                        class="border-blue-500/20 bg-blue-500/10 font-mono text-[11px] font-semibold text-blue-700 tabular-nums dark:text-blue-300"
                    >
                        {{ __('Rata-rata') }}: {{ data?.average_index ?? 0 }}%
                    </Badge>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-3 pt-0">
            <!-- Pacing Reference Guideline -->
            <div
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200/80 bg-slate-50/70 px-3 py-2 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400"
            >
                <div class="flex items-center gap-1.5">
                    <AlertCircle class="size-3.5 text-slate-400" />
                    <span>{{ __('Target Pacing Harian:') }}</span>
                    <span
                        class="font-mono font-bold text-slate-900 tabular-nums dark:text-slate-100"
                    >
                        {{ data?.planned_daily_pacing_hours ?? 0 }}
                        {{ __('jam / hari') }}
                    </span>
                </div>

                <div class="flex items-center gap-2 font-mono text-[11px]">
                    <span class="flex items-center gap-1">
                        <span class="inline-block h-0.5 w-3 bg-blue-600" />
                        <span>{{ __('Indeks Harian') }}</span>
                    </span>
                    <span
                        class="flex items-center gap-1 text-red-600 dark:text-red-400"
                    >
                        <span
                            class="inline-block h-0.5 w-3 border-b border-dashed border-red-600"
                        />
                        <span>{{ __('Batas 100%') }}</span>
                    </span>
                </div>
            </div>

            <!-- Line Chart Canvas -->
            <BaseLineChart
                :labels="data?.labels"
                :datasets="datasets"
                :options="chartOptions"
                :loading="loading"
                :empty="!data || data.cutoff_day === 0"
                height-class="h-60"
                :empty-text="__('Belum ada data indeks lembur harian')"
            />
        </CardContent>
    </Card>
</template>
