<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import { Award, ShieldAlert, Users } from '@lucide/vue';
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

export interface LeaderboardItem {
    employee_id: number;
    npk: string;
    name: string;
    full_name: string;
    section_code: string;
    total_hours: number;
    soft_limit_hours: number;
    percentage_of_limit: number;
    zone: 'safe' | 'warning' | 'danger';
    zone_color: string;
}

export interface LeaderboardData {
    items: LeaderboardItem[];
    fiscal_year: number;
    fiscal_month: number;
    month_name: string;
    soft_limit_hours: number;
    scope: {
        department_id: number | null;
        section_id: number | null;
    };
}

interface Props {
    data?: LeaderboardData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: undefined,
    loading: false,
});

const { __ } = useTrans();

const chartLabels = computed(() => {
    if (!props.data?.items?.length) {
        return [];
    }
    return props.data.items.map(
        (item) => `${item.name} (${item.section_code})`,
    );
});

const barColors = computed(() => {
    if (!props.data?.items?.length) {
        return [];
    }
    return props.data.items.map((item) => item.zone_color);
});

const datasets = computed<ChartDataset<'bar'>[]>(() => {
    if (!props.data?.items?.length) {
        return [];
    }
    return [
        {
            label: __('Total Jam Lembur'),
            data: props.data.items.map((item) => item.total_hours),
            backgroundColor: barColors.value,
            hoverBackgroundColor: barColors.value,
            borderRadius: 4,
            barThickness: props.data.items.length > 6 ? 14 : 20,
        },
    ];
});

const softLimit = computed(() => props.data?.soft_limit_hours ?? 80);

const hasOverLimitEmployees = computed(() => {
    return props.data?.items?.some((i) => i.zone !== 'safe') ?? false;
});

const chartOptions = computed<ChartOptions<'bar'>>(() => {
    const maxVal = props.data?.items?.length
        ? Math.max(
              ...props.data.items.map((i) => i.total_hours),
              softLimit.value,
          ) * 1.15
        : softLimit.value * 1.2;

    return {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                titleColor: '#f8fafc',
                bodyColor: '#cbd5e1',
                padding: 10,
                boxPadding: 4,
                callbacks: {
                    title: (context) => {
                        const idx = context[0]?.dataIndex ?? 0;
                        const item = props.data?.items[idx];
                        return item ? `${item.full_name} (${item.npk})` : '';
                    },
                    label: (context) => {
                        const idx = context.dataIndex;
                        const item = props.data?.items[idx];
                        if (!item) {
                            return '';
                        }
                        return [
                            ` ${__('Total Lembur')}: ${item.total_hours} ${__('jam')}`,
                            ` ${__('Seksi')}: ${item.section_code}`,
                            ` ${__('Porsi Batas')}: ${item.percentage_of_limit}% (${__('Batas')}: ${item.soft_limit_hours} ${__('jam')})`,
                        ];
                    },
                },
            },
        },
        scales: {
            x: {
                min: 0,
                max: Math.ceil(maxVal),
                grid: {
                    color: 'rgba(226, 232, 240, 0.6)',
                },
                ticks: {
                    font: {
                        family: 'monospace',
                        size: 11,
                    },
                    callback: (value) => `${value} jam`,
                },
            },
            y: {
                grid: {
                    display: false,
                },
                ticks: {
                    font: {
                        size: 11,
                    },
                    autoSkip: false,
                },
            },
        },
    };
});
</script>

<template>
    <Card
        class="border-border/70 flex flex-col justify-between shadow-2xs"
        data-test="overtime-leaderboard-card"
    >
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100"
                    >
                        <Award class="size-4 text-amber-500" />
                        <span>{{
                            __('Peringkat Lembur Karyawan (Top 10)')
                        }}</span>
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{
                            __(
                                'Karyawan dengan jam lembur tertinggi pada :month',
                                { month: data?.month_name || '' },
                            )
                        }}
                    </CardDescription>
                </div>

                <Badge
                    v-if="hasOverLimitEmployees"
                    variant="outline"
                    class="flex items-center gap-1 border-amber-500/30 bg-amber-500/10 text-[11px] font-semibold text-amber-700 dark:text-amber-300"
                >
                    <ShieldAlert class="size-3" />
                    <span>{{ __('Melewati Batas Soft') }}</span>
                </Badge>
                <Badge
                    v-else
                    variant="outline"
                    class="flex items-center gap-1 border-emerald-500/30 bg-emerald-500/10 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300"
                >
                    <Users class="size-3" />
                    <span>{{ __('Dalam Batas Kebijakan') }}</span>
                </Badge>
            </div>
        </CardHeader>

        <CardContent class="space-y-3 pt-0">
            <!-- Limit guideline reference -->
            <div
                class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-1.5 text-xs text-slate-600 dark:bg-slate-900/50 dark:text-slate-400"
            >
                <span>{{ __('Batas Kebijakan Bulanan (Soft Limit):') }}</span>
                <span
                    class="font-mono font-bold text-slate-900 tabular-nums dark:text-slate-100"
                >
                    {{ softLimit }} {{ __('jam / bulan') }}
                </span>
            </div>

            <!-- Horizontal Bar Chart -->
            <BaseBarChart
                :labels="chartLabels"
                :datasets="datasets"
                :options="chartOptions"
                :horizontal="true"
                :loading="loading"
                :empty="!data?.items?.length"
                height-class="h-64"
                :empty-text="
                    __('Belum ada data lembur yang disetujui pada periode ini')
                "
            />
        </CardContent>
    </Card>
</template>
