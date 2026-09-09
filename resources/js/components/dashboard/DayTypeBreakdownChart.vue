<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import { Calendar, Split } from '@lucide/vue';
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
import { useTrans } from '@/composables/useTrans';

export interface DayTypeBreakdownData {
    labels: string[];
    hkn_hours: number[];
    hlr_hours: number[];
    total_hkn: number;
    total_hlr: number;
    grand_total: number;
    hlr_ratio_pct: number;
    fiscal_year: number;
    fiscal_month: number;
    month_name: string;
    scope: {
        department_id: number | null;
        section_id: number | null;
    };
}

interface Props {
    data?: DayTypeBreakdownData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: undefined,
    loading: false,
});

const { __ } = useTrans();

const datasets = computed<ChartDataset<'bar'>[]>(() => {
    if (!props.data?.labels?.length) {
        return [];
    }

    return [
        {
            label: __('Hari Kerja Normal (HKN)'),
            data: props.data.hkn_hours,
            backgroundColor: '#3b82f6',
            hoverBackgroundColor: '#2563eb',
            borderRadius: 4,
            barPercentage: 0.8,
            categoryPercentage: 0.7,
        },
        {
            label: __('Hari Libur Resmi (HLR)'),
            data: props.data.hlr_hours,
            backgroundColor: '#f59e0b',
            hoverBackgroundColor: '#d97706',
            borderRadius: 4,
            barPercentage: 0.8,
            categoryPercentage: 0.7,
        },
    ];
});

const chartOptions = computed<ChartOptions<'bar'>>(() => {
    return {
        responsive: true,
        maintainAspectRatio: false,
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
                        const hkn = props.data?.hkn_hours[idx] ?? 0;
                        const hlr = props.data?.hlr_hours[idx] ?? 0;
                        return `Total Pekan: ${roundOneDecimal(hkn + hlr)} jam`;
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

function roundOneDecimal(num: number): number {
    return Math.round(num * 10) / 10;
}
</script>

<template>
    <Card
        class="border-border/70 flex flex-col justify-between shadow-2xs"
        data-test="day-type-breakdown-card"
    >
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100"
                    >
                        <Split class="size-4 text-amber-500" />
                        <span>{{ __('Distribusi Hari: HKN vs HLR') }}</span>
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{
                            __(
                                'Perbandingan jam lembur hari kerja biasa dan hari libur mingguan pada :month',
                                { month: data?.month_name || '' },
                            )
                        }}
                    </CardDescription>
                </div>

                <!-- Holiday ratio indicator -->
                <Badge
                    variant="outline"
                    class="flex items-center gap-1 border-amber-500/30 bg-amber-500/10 font-mono text-[11px] font-semibold text-amber-700 tabular-nums dark:text-amber-300"
                >
                    <span
                        >{{ __('Porsi HLR') }}:
                        {{ data?.hlr_ratio_pct ?? 0 }}%</span
                    >
                </Badge>
            </div>
        </CardHeader>

        <CardContent class="space-y-3 pt-0">
            <!-- Custom Legend & Summary Strip -->
            <div
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200/80 bg-slate-50/70 px-3 py-2 text-xs dark:border-slate-800 dark:bg-slate-900/50"
            >
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm bg-blue-500" />
                        <span
                            class="font-medium text-slate-700 dark:text-slate-300"
                            >HKN:</span
                        >
                        <span
                            class="font-mono font-bold text-blue-700 tabular-nums dark:text-blue-300"
                        >
                            {{ data?.total_hkn ?? 0 }}h
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm bg-amber-500" />
                        <span
                            class="font-medium text-slate-700 dark:text-slate-300"
                            >HLR:</span
                        >
                        <span
                            class="font-mono font-bold text-amber-700 tabular-nums dark:text-amber-300"
                        >
                            {{ data?.total_hlr ?? 0 }}h
                        </span>
                    </div>
                </div>

                <div
                    class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400"
                >
                    <Calendar class="size-3.5" />
                    <span>{{ __('Total') }}:</span>
                    <span
                        class="font-mono font-bold text-slate-900 tabular-nums dark:text-slate-100"
                    >
                        {{ data?.grand_total ?? 0 }} {{ __('jam') }}
                    </span>
                </div>
            </div>

            <!-- Grouped Bar Chart Canvas -->
            <BaseBarChart
                :labels="data?.labels"
                :datasets="datasets"
                :options="chartOptions"
                :horizontal="false"
                :loading="loading"
                :empty="!data || data.grand_total === 0"
                height-class="h-60"
                :empty-text="__('Belum ada data lembur HKN/HLR')"
            />
        </CardContent>
    </Card>
</template>
