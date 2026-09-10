<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import { DollarSign, Scale } from '@lucide/vue';
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

export interface BudgetVsActualData {
    labels?: string[];
    planned_series?: number[];
    actual_series?: number[];
    variance_series?: number[];
    is_section_breakdown?: boolean;
}

interface Props {
    data?: BudgetVsActualData;
    loading?: boolean;
    empty?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: () => ({
        labels: [],
        planned_series: [],
        actual_series: [],
        variance_series: [],
        is_section_breakdown: false,
    }),
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

const datasets = computed<ChartDataset<'bar'>[]>(() => {
    if (!props.data) return [];

    const planned = props.data.planned_series ?? [];
    const actual = props.data.actual_series ?? [];

    const actualColors = actual.map((actVal, idx) => {
        const planVal = planned[idx] ?? 0;
        if (planVal > 0 && actVal > planVal) {
            return chartColors.isuzuRed; // Over budget deficit
        }
        return chartColors.success; // Within budget
    });

    return [
        {
            label: __('Plafon Anggaran (Rp)'),
            data: planned,
            backgroundColor: '#94a3b8', // Slate-400
            borderRadius: 4,
            barPercentage: 0.7,
            categoryPercentage: 0.8,
        },
        {
            label: __('Realisasi Biaya (Rp)'),
            data: actual,
            backgroundColor: actualColors,
            borderRadius: 4,
            barPercentage: 0.7,
            categoryPercentage: 0.8,
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
                font: {
                    family: '"Instrument Sans", sans-serif',
                    size: 11,
                    weight: 600,
                },
                color: '#475569',
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
                callback: (val) => formatCompactRupiah(Number(val)),
            },
            title: {
                display: true,
                text: __('Nilai Biaya (IDR)'),
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
            position: 'top',
            align: 'end',
            labels: {
                boxWidth: 12,
                boxHeight: 12,
                font: {
                    family: '"Instrument Sans", sans-serif',
                    size: 11,
                    weight: 600,
                },
                color: '#475569',
            },
        },
        tooltip: {
            backgroundColor: '#0f172a',
            titleColor: '#ffffff',
            bodyColor: '#e2e8f0',
            padding: 10,
            cornerRadius: 8,
            callbacks: {
                label: (ctx) => {
                    const label = ctx.dataset.label || '';
                    const val = Number(ctx.parsed.y) || 0;
                    const formatted = formatRupiah(val, {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0,
                    });
                    return `${label}: ${formatted}`;
                },
                afterBody: (tooltipItems) => {
                    const planItem = tooltipItems.find(
                        (i) => i.datasetIndex === 0,
                    );
                    const actItem = tooltipItems.find(
                        (i) => i.datasetIndex === 1,
                    );
                    if (!planItem || !actItem) return '';
                    const planVal = Number(planItem.parsed.y) || 0;
                    const actVal = Number(actItem.parsed.y) || 0;
                    const diff = actVal - planVal;
                    const diffFormatted = formatRupiah(Math.abs(diff), {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0,
                    });
                    if (diff > 0) {
                        return `--------------------\n${__('Varian')}: +${diffFormatted} (${__('Defisit Over Budget')})`;
                    }
                    return `--------------------\n${__('Varian')}: -${diffFormatted} (${__('Sisa Surplus')})`;
                },
            },
        },
    },
}));
</script>

<template>
    <Card
        class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        data-test="budget-vs-actual-bar-chart"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-1">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <Scale class="size-4 text-[#cc0000]" />
                        <span>{{ __('Anggaran vs Realisasi Biaya') }}</span>
                    </CardTitle>
                    <CardDescription>
                        {{
                            data?.is_section_breakdown
                                ? __(
                                      'Perbandingan alokasi pagu anggaran terhadap realisasi aktual per seksi dalam departemen terpilih.',
                                  )
                                : __(
                                      'Perbandingan alokasi pagu anggaran resmi terhadap realisasi biaya lembur aktual per departemen.',
                                  )
                        }}
                    </CardDescription>
                </div>

                <div class="flex items-center gap-2">
                    <Badge
                        variant="secondary"
                        class="bg-slate-100 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    >
                        {{ __('Plafon Anggaran') }}
                    </Badge>
                    <Badge
                        variant="outline"
                        class="border-emerald-200 bg-emerald-50 text-xs font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                    >
                        {{ __('Realisasi Aman') }}
                    </Badge>
                    <Badge
                        variant="outline"
                        class="border-red-200 bg-red-50 text-xs font-semibold text-red-700 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300"
                    >
                        {{ __('Defisit (Over)') }}
                    </Badge>
                </div>
            </div>
        </CardHeader>

        <CardContent>
            <BaseBarChart
                :labels="chartLabels"
                :datasets="datasets"
                :options="chartOptions"
                :horizontal="false"
                :loading="loading"
                :empty="!hasData"
                height-class="h-72"
                :empty-text="
                    __(
                        'Belum ada data anggaran vs realisasi untuk periode ini.',
                    )
                "
            />
            <div
                v-if="hasData && !loading"
                class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400"
            >
                <div class="flex items-center gap-1">
                    <DollarSign class="size-3.5" />
                    <span>{{
                        __(
                            'Batang merah menandai unit kerja yang telah melampaui plafon biaya yang disahkan.',
                        )
                    }}</span>
                </div>
                <div class="font-mono tabular-nums">
                    {{ chartLabels.length }}
                    {{
                        data?.is_section_breakdown
                            ? __('Seksi')
                            : __('Departemen')
                    }}
                </div>
            </div>
        </CardContent>
    </Card>
</template>
