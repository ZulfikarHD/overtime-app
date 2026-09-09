<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import { CalendarRange, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';
import BaseLineChart from '@/components/charts/BaseLineChart.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

export interface TrendWorkingTimeData {
    labels: string[];
    hkn_series: number[];
    hlr_series: number[];
    total_series: number[];
    total_hkn: number;
    total_hlr: number;
    grand_total: number;
    fiscal_year: number;
    fiscal_month: number;
    month_name: string;
    scope: {
        department_id: number | null;
        section_id: number | null;
    };
}

interface Props {
    data?: TrendWorkingTimeData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: undefined,
    loading: false,
});

const { __ } = useTrans();

const datasets = computed<ChartDataset<'line'>[]>(() => {
    if (!props.data?.labels?.length) {
        return [];
    }

    return [
        {
            label: __('Hari Kerja Normal (HKN)'),
            data: props.data.hkn_series,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.08)',
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: '#3b82f6',
            fill: true,
            tension: 0.25,
        },
        {
            label: __('Hari Libur Resmi (HLR)'),
            data: props.data.hlr_series,
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245, 158, 11, 0.08)',
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: '#f59e0b',
            fill: true,
            tension: 0.25,
        },
    ];
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
                    label: (context) => {
                        const val = context.parsed.y ?? 0;
                        return ` ${context.dataset.label}: ${val} ${__('jam')}`;
                    },
                    footer: (items) => {
                        const idx = items[0]?.dataIndex ?? 0;
                        const total = props.data?.total_series[idx] ?? 0;
                        return `Total: ${total} jam`;
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
                        size: 11,
                    },
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
                    callback: (value) => `${value}h`,
                },
            },
        },
    };
});
</script>

<template>
    <Card
        class="border-border/70 flex flex-col justify-between shadow-2xs"
        data-test="trend-working-time-card"
    >
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100"
                    >
                        <CalendarRange class="size-4 text-blue-500" />
                        <span>{{ __('Tren Jam Kerja 12 Bulan') }}</span>
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{
                            __(
                                'Perbandingan tren lembur HKN vs HLR selama 12 bulan terakhir',
                            )
                        }}
                    </CardDescription>
                </div>

                <!-- Custom Legend Pills -->
                <div class="flex items-center gap-2 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-blue-500" />
                        <span class="text-slate-600 dark:text-slate-400"
                            >HKN</span
                        >
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-amber-500" />
                        <span class="text-slate-600 dark:text-slate-400"
                            >HLR</span
                        >
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4 pt-0">
            <!-- Summary KPI Strip -->
            <div
                class="grid grid-cols-3 gap-2 rounded-lg border border-slate-200/80 bg-slate-50/70 p-2 text-center dark:border-slate-800 dark:bg-slate-900/50"
            >
                <div>
                    <span
                        class="block text-[10px] font-medium text-slate-500 dark:text-slate-400"
                    >
                        {{ __('Total 12 Bulan') }}
                    </span>
                    <span
                        class="font-mono text-xs font-bold text-slate-900 tabular-nums dark:text-slate-100"
                    >
                        {{ data?.grand_total ?? 0 }}h
                    </span>
                </div>
                <div>
                    <span
                        class="block text-[10px] font-medium text-blue-600 dark:text-blue-400"
                    >
                        {{ __('Total HKN') }}
                    </span>
                    <span
                        class="font-mono text-xs font-bold text-blue-700 tabular-nums dark:text-blue-300"
                    >
                        {{ data?.total_hkn ?? 0 }}h
                    </span>
                </div>
                <div>
                    <span
                        class="block text-[10px] font-medium text-amber-600 dark:text-amber-400"
                    >
                        {{ __('Total HLR') }}
                    </span>
                    <span
                        class="font-mono text-xs font-bold text-amber-700 tabular-nums dark:text-amber-300"
                    >
                        {{ data?.total_hlr ?? 0 }}h
                    </span>
                </div>
            </div>

            <!-- Line Chart Canvas -->
            <BaseLineChart
                :labels="data?.labels"
                :datasets="datasets"
                :options="chartOptions"
                :loading="loading"
                :empty="!data || data.grand_total === 0"
                height-class="h-56"
                :empty-text="__('Belum ada riwayat lembur 12 bulan')"
            />
        </CardContent>
    </Card>
</template>
