<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import { Building2, Info } from '@lucide/vue';
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
import { formatCompactRupiah, formatRupiah } from '@/lib/formatters';

export interface DepartmentCostItem {
    department_id: number;
    department_code: string;
    department_name: string;
    total_hours: number;
    total_cost: number;
    formatted_total_cost: string;
    planned_cost: number;
    formatted_planned_cost: string;
    budget_consumption_pct: number;
    is_over_budget: boolean;
    burn_zone: 'safe' | 'on_track' | 'warning' | 'danger';
    avg_rate_per_hour: number;
    formatted_avg_rate: string;
    capex_cost: number;
    opex_cost: number;
    trend: 'up' | 'down' | 'stable';
    trend_variance_pct: number;
}

interface Props {
    departmentCosts?: DepartmentCostItem[];
    loading?: boolean;
    empty?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    departmentCosts: () => [],
    loading: false,
    empty: false,
});

const { __ } = useTrans();

const hasData = computed(() => {
    if (props.empty) return false;
    return props.departmentCosts && props.departmentCosts.length > 0;
});

const sortedCosts = computed(() => {
    return [...props.departmentCosts].sort(
        (a, b) => b.total_cost - a.total_cost,
    );
});

const chartLabels = computed(() => {
    return sortedCosts.value.map((d) => d.department_name);
});

const datasets = computed<ChartDataset<'bar'>[]>(() => {
    if (sortedCosts.value.length === 0) return [];

    const data = sortedCosts.value.map((d) => d.total_cost);
    const backgroundColors = sortedCosts.value.map((d) => {
        if (d.burn_zone === 'danger' || d.is_over_budget) {
            return chartColors.isuzuRed;
        }
        if (d.burn_zone === 'warning') {
            return chartColors.warning;
        }
        if (d.burn_zone === 'on_track') {
            return chartColors.primary;
        }
        return chartColors.success;
    });

    return [
        {
            label: __('Total Biaya Lembur (Rp)'),
            data,
            backgroundColor: backgroundColors,
            borderRadius: 4,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        },
    ];
});

const chartOptions = computed<ChartOptions<'bar'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    scales: {
        x: {
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
                text: __('Realisasi Biaya (IDR)'),
                font: {
                    size: 11,
                    weight: 'bold',
                },
                color: '#94a3b8',
            },
        },
        y: {
            grid: {
                display: false,
            },
            ticks: {
                font: {
                    family: '"Instrument Sans", sans-serif',
                    size: 11,
                    weight: 600,
                },
                color: '#475569',
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
                label: (ctx) => {
                    const idx = ctx.dataIndex;
                    const item = sortedCosts.value[idx];
                    if (!item) return '';
                    const actualStr = formatRupiah(item.total_cost, {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0,
                    });
                    const budgetStr = formatRupiah(item.planned_cost, {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0,
                    });
                    return [
                        `${__('Realisasi')}: ${actualStr}`,
                        `${__('Anggaran')}: ${budgetStr}`,
                        `${__('Konsumsi')}: ${item.budget_consumption_pct}%`,
                    ];
                },
            },
        },
    },
}));
</script>

<template>
    <Card
        class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        data-test="cost-by-department-chart"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-1">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <Building2 class="size-4 text-[#cc0000]" />
                        <span>{{ __('Biaya Lembur per Departemen') }}</span>
                    </CardTitle>
                    <CardDescription>
                        {{
                            __(
                                'Peringkat realisasi pengeluaran lembur terverifikasi per departemen dengan indikator kepatuhan anggaran.',
                            )
                        }}
                    </CardDescription>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <Badge
                        variant="outline"
                        class="border-emerald-200 bg-emerald-50 text-[11px] font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                    >
                        {{ __('< 85% Aman') }}
                    </Badge>
                    <Badge
                        variant="outline"
                        class="border-blue-200 bg-blue-50 text-[11px] font-semibold text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300"
                    >
                        {{ __('85–100% Sesuai') }}
                    </Badge>
                    <Badge
                        variant="outline"
                        class="border-amber-200 bg-amber-50 text-[11px] font-semibold text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                    >
                        {{ __('101–115% Waspada') }}
                    </Badge>
                    <Badge
                        variant="outline"
                        class="border-red-200 bg-red-50 text-[11px] font-semibold text-red-700 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300"
                    >
                        {{ __('> 115% Defisit') }}
                    </Badge>
                </div>
            </div>
        </CardHeader>

        <CardContent>
            <BaseBarChart
                :labels="chartLabels"
                :datasets="datasets"
                :options="chartOptions"
                :horizontal="true"
                :loading="loading"
                :empty="!hasData"
                height-class="h-72"
                :empty-text="
                    __('Belum ada data biaya departemen untuk periode ini.')
                "
            />
            <div
                v-if="hasData && !loading"
                class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400"
            >
                <div class="flex items-center gap-1">
                    <Info class="size-3.5" />
                    <span>{{
                        __(
                            'Warna batang mencerminkan zona burn index dan pagu anggaran resmi masing-masing departemen.',
                        )
                    }}</span>
                </div>
                <div class="font-mono tabular-nums">
                    {{ sortedCosts.length }} {{ __('Departemen Terdata') }}
                </div>
            </div>
        </CardContent>
    </Card>
</template>
