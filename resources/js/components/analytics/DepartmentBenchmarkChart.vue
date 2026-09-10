<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import { Building2, Layers, TrendingDown, TrendingUp } from '@lucide/vue';
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
import { chartColors } from '@/composables/useChartTheme';
import { useTrans } from '@/composables/useTrans';
import { registerChartDefaults } from '@/plugins/chartjs';

registerChartDefaults();

export interface DepartmentBenchmarkItem {
    id: number;
    code: string;
    name: string;
    base_hours: number;
    compare_hours: number;
    variance_hours: number;
    variance_pct: number;
    base_cost: number;
    compare_cost: number;
    cost_variance_pct: number;
    burn_index_pct: number;
    burn_zone: 'safe' | 'on_track' | 'warning' | 'danger';
}

interface Props {
    benchmarks?: DepartmentBenchmarkItem[];
    baseLabel?: string;
    compareLabel?: string;
    isManagerScoped?: boolean;
    loading?: boolean;
    empty?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    benchmarks: () => [],
    baseLabel: 'Periode Basis',
    compareLabel: 'Periode Pembanding',
    isManagerScoped: false,
    loading: false,
    empty: false,
});

const { __ } = useTrans();

const hasData = computed(() => {
    if (props.empty) return false;
    return props.benchmarks && props.benchmarks.length > 0;
});

const sortedBenchmarks = computed(() => {
    return [...props.benchmarks].sort((a, b) => b.base_hours - a.base_hours);
});

const chartLabels = computed(() => {
    return sortedBenchmarks.value.map((b) => b.name);
});

const datasets = computed<ChartDataset<'bar'>[]>(() => {
    if (sortedBenchmarks.value.length === 0) return [];

    const baseData = sortedBenchmarks.value.map((b) => b.base_hours);
    const compareData = sortedBenchmarks.value.map((b) => b.compare_hours);

    return [
        {
            label: props.baseLabel || __('Periode Basis'),
            data: baseData,
            backgroundColor: '#2563eb', // Blue-600
            borderRadius: 4,
            barPercentage: 0.7,
            categoryPercentage: 0.8,
        },
        {
            label: props.compareLabel || __('Periode Pembanding'),
            data: compareData,
            backgroundColor: '#94a3b8', // Slate-400
            borderRadius: 4,
            barPercentage: 0.7,
            categoryPercentage: 0.8,
        },
    ];
});

const chartOptions = computed<ChartOptions<'bar'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    interaction: {
        mode: 'index',
        intersect: false,
    },
    scales: {
        x: {
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
                text: __('Total Jam Lembur Disetujui'),
                color: '#64748b',
                font: {
                    family: '"Instrument Sans", sans-serif',
                    size: 11,
                },
            },
        },
        y: {
            grid: {
                display: false,
            },
            ticks: {
                color: '#1e293b',
                font: {
                    family: '"Instrument Sans", sans-serif',
                    size: 11,
                    weight: 500,
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
                    return ` ${label}: ${val.toFixed(1)} jam`;
                },
                afterBody(items) {
                    if (items.length > 0) {
                        const index = items[0].dataIndex;
                        const item = sortedBenchmarks.value[index];
                        if (item) {
                            const varSign = item.variance_hours >= 0 ? '+' : '';
                            return [
                                ` Selisih: ${varSign}${item.variance_hours.toFixed(1)} jam (${item.variance_pct >= 0 ? '+' : ''}${item.variance_pct.toFixed(1)}%)`,
                                ` Burn Index: ${item.burn_index_pct.toFixed(1)}%`,
                            ];
                        }
                    }
                    return [];
                },
            },
        },
    },
}));

function getZoneBadge(zone: string) {
    switch (zone) {
        case 'danger':
            return {
                label: __('Kritis (>115%)'),
                class: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900',
            };
        case 'warning':
            return {
                label: __('Peringatan (101-115%)'),
                class: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900',
            };
        case 'on_track':
            return {
                label: __('Sesuai Target (85-100%)'),
                class: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-900',
            };
        default:
            return {
                label: __('Aman (<85%)'),
                class: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900',
            };
    }
}
</script>

<template>
    <Card
        class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        data-test="department-benchmark-chart"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <Building2 class="size-4 text-[#cc0000]" />
                        <span>
                            {{
                                isManagerScoped
                                    ? __('Benchmarking Antar Seksi')
                                    : __('Benchmarking Komparatif Departemen')
                            }}
                        </span>
                    </CardTitle>
                    <CardDescription>
                        {{
                            __(
                                'Peringkat jam lembur aktual basis vs pembanding beserta varians pertumbuhan.',
                            )
                        }}
                    </CardDescription>
                </div>
            </div>
        </CardHeader>
        <CardContent class="space-y-6">
            <!-- Horizontal Bar Chart -->
            <div class="relative h-72 w-full">
                <ChartSkeleton
                    v-if="loading"
                    height-class="h-72"
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
                        {{ __('Belum ada data benchmarking departemen') }}
                    </p>
                </div>

                <Bar
                    v-else
                    :data="{ labels: chartLabels, datasets }"
                    :options="chartOptions"
                />
            </div>

            <!-- Benchmark Ranking Detail List -->
            <div
                v-if="hasData && !loading"
                class="divide-y divide-slate-100 rounded-lg border border-slate-200/80 dark:divide-slate-800 dark:border-slate-800"
            >
                <div
                    v-for="(item, idx) in sortedBenchmarks"
                    :key="item.id"
                    class="flex flex-col gap-2 p-3 hover:bg-slate-50/50 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-slate-800/40"
                    data-test="benchmark-item-row"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ idx + 1 }}
                        </span>
                        <div>
                            <div
                                class="text-xs font-bold text-slate-900 dark:text-white"
                            >
                                {{ item.name }}
                            </div>
                            <div class="text-2xs font-mono text-slate-400">
                                {{ item.code }}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 text-xs">
                        <div class="text-right">
                            <span class="text-slate-500"
                                >{{ props.baseLabel }}:
                            </span>
                            <span
                                class="font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                            >
                                {{ item.base_hours.toFixed(1) }} jam
                            </span>
                        </div>

                        <div class="text-right">
                            <span class="text-slate-500"
                                >{{ props.compareLabel }}:
                            </span>
                            <span
                                class="font-mono font-medium text-slate-600 tabular-nums dark:text-slate-300"
                            >
                                {{ item.compare_hours.toFixed(1) }} jam
                            </span>
                        </div>

                        <div
                            class="flex items-center gap-1 font-mono font-bold tabular-nums"
                        >
                            <span
                                class="text-2xs inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 font-semibold"
                                :class="
                                    item.variance_pct <= 0
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                        : 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300'
                                "
                            >
                                <component
                                    :is="
                                        item.variance_pct <= 0
                                            ? TrendingDown
                                            : TrendingUp
                                    "
                                    class="size-3"
                                />
                                <span
                                    >{{ item.variance_pct >= 0 ? '+' : ''
                                    }}{{ item.variance_pct.toFixed(1) }}%</span
                                >
                            </span>
                        </div>

                        <div>
                            <Badge
                                variant="outline"
                                class="text-2xs"
                                :class="getZoneBadge(item.burn_zone).class"
                            >
                                {{ getZoneBadge(item.burn_zone).label }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
