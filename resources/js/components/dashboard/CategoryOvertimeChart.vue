<script setup lang="ts">
import type { ChartData, ChartDataset, ChartOptions } from 'chart.js';
import { BarChart3, MinusCircle } from '@lucide/vue';
import { computed } from 'vue';
import BaseBarChart from '@/components/charts/BaseBarChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

export interface CategoryOvertimeCategoryItem {
    key: 'production' | 'tpm' | 'project' | 'others';
    label: string;
    hours: number;
    planned_hours: number;
    percentage: number;
    percentage_of_plan: number;
    color: string;
}

export interface CategoryOvertimeInputData {
    total_hours: number;
    total_planned: number;
    categories: CategoryOvertimeCategoryItem[];
    fiscal_year: number;
    fiscal_month: number;
    month_name: string;
    scope: {
        department_id: number | null;
        section_id: number | null;
    };
}

interface Props {
    data?: CategoryOvertimeInputData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: undefined,
    loading: false,
});

const { __ } = useTrans();

const CATEGORY_LABELS: Record<string, string> = {
    production: 'Produksi',
    tpm: 'TPM',
    project: 'Project',
    others: 'Others',
};

// ISUZU palette: Plan = navy blue, Actual = ISUZU orange
const COLOR_PLAN = '#1e3a5f';
const COLOR_ACTUAL = '#e8601c';

const isEmpty = computed(
    () =>
        !props.data?.categories?.length ||
        props.data.categories.every(
            (c) => c.hours === 0 && c.planned_hours === 0,
        ),
);

const chartLabels = computed(() =>
    (props.data?.categories ?? []).map(
        (c) => CATEGORY_LABELS[c.key] ?? c.label,
    ),
);

const chartDatasets = computed<ChartDataset<'bar'>[]>(() => [
    {
        label: __('Plan'),
        data: (props.data?.categories ?? []).map((c) => c.planned_hours),
        backgroundColor: COLOR_PLAN,
        borderColor: COLOR_PLAN,
        borderWidth: 0,
        borderRadius: 3,
        barThickness: 12,
    },
    {
        label: __('Aktual'),
        data: (props.data?.categories ?? []).map((c) => c.hours),
        backgroundColor: COLOR_ACTUAL,
        borderColor: COLOR_ACTUAL,
        borderWidth: 0,
        borderRadius: 3,
        barThickness: 12,
    },
]);

const chartOptions = computed<ChartOptions<'bar'>>(() => {
    const maxVal = Math.max(
        ...(props.data?.categories ?? []).flatMap((c) => [
            c.hours,
            c.planned_hours,
        ]),
        1,
    );

    return {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                display: true,
                position: 'top',
                align: 'end',
                labels: {
                    boxWidth: 10,
                    boxHeight: 10,
                    font: { size: 10 },
                    padding: 8,
                },
            },
            tooltip: {
                padding: 10,
                callbacks: {
                    label: (ctx) => {
                        const hours = (ctx.parsed.x as number).toFixed(1);
                        return ` ${ctx.dataset.label}: ${hours} Jam`;
                    },
                    afterBody: (items) => {
                        const cat =
                            props.data?.categories?.[items[0]?.dataIndex];
                        if (!cat || !cat.planned_hours) return [];
                        return [
                            `Realisasi: ${cat.percentage_of_plan}% dari plan`,
                        ];
                    },
                },
            },
        },
        scales: {
            x: {
                beginAtZero: true,
                max: Math.ceil(maxVal * 1.15),
                ticks: {
                    font: { size: 10, family: 'monospace' },
                    callback: (v) => `${v}`,
                },
                grid: { color: 'rgba(226,232,240,0.5)' },
            },
            y: {
                grid: { display: false },
                ticks: { font: { size: 10 } },
            },
        },
    };
});

const chartData = computed<ChartData<'bar'>>(() => ({
    labels: chartLabels.value,
    datasets: chartDatasets.value,
}));

const totalBurnPct = computed(() => {
    const plan = props.data?.total_planned ?? 0;
    const actual = props.data?.total_hours ?? 0;
    return plan > 0 ? Math.round((actual / plan) * 100) : null;
});
</script>

<template>
    <Card class="overflow-hidden">
        <CardHeader class="pb-2">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-bold"
                    >
                        <BarChart3 class="size-4 text-[#cc0000]" />
                        {{ __('Category Overtime') }}
                    </CardTitle>
                    <p class="text-muted-foreground mt-0.5 text-[11px]">
                        {{ data?.month_name ?? '—' }} · Plan vs Aktual per
                        Kategori
                    </p>
                </div>
                <!-- Aggregate burn pill -->
                <div
                    v-if="totalBurnPct !== null"
                    class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold tabular-nums"
                    :class="{
                        'bg-red-100 text-red-700': totalBurnPct > 100,
                        'bg-amber-100 text-amber-700':
                            totalBurnPct > 85 && totalBurnPct <= 100,
                        'bg-emerald-100 text-emerald-700': totalBurnPct <= 85,
                    }"
                >
                    {{ totalBurnPct }}% realisasi
                </div>
            </div>
        </CardHeader>

        <CardContent class="pt-0">
            <!-- Loading skeleton -->
            <div v-if="loading" class="space-y-3 px-1 pt-2">
                <div v-for="n in 4" :key="n" class="flex items-center gap-3">
                    <div
                        class="h-3 w-16 animate-pulse rounded bg-slate-200 dark:bg-slate-800"
                    />
                    <div
                        class="h-6 animate-pulse rounded bg-slate-100 dark:bg-slate-800/60"
                        :style="{ width: `${30 * n}%` }"
                    />
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-else-if="isEmpty"
                class="flex h-40 flex-col items-center justify-center gap-2 text-center"
            >
                <MinusCircle class="size-7 text-slate-300" />
                <p class="text-muted-foreground text-xs">
                    {{ __('Belum ada data untuk periode ini.') }}
                </p>
            </div>

            <!-- Grouped horizontal bar chart -->
            <div v-else class="h-52">
                <BaseBarChart
                    :labels="chartLabels"
                    :datasets="chartDatasets"
                    :options="chartOptions"
                    :horizontal="true"
                    height-class="h-52"
                />
            </div>

            <!-- Per-category detail rows -->
            <div
                v-if="!loading && data?.categories?.length"
                class="mt-2 space-y-1"
            >
                <div
                    v-for="cat in data.categories"
                    :key="cat.key"
                    class="flex items-center gap-2 text-[11px]"
                >
                    <span class="w-20 truncate text-slate-500">{{
                        CATEGORY_LABELS[cat.key] ?? cat.label
                    }}</span>
                    <span
                        class="font-mono font-bold text-[#1e3a5f] tabular-nums"
                    >
                        {{ cat.planned_hours.toFixed(1) }}
                    </span>
                    <span class="text-slate-300">/</span>
                    <span
                        class="font-mono font-semibold text-[#e8601c] tabular-nums"
                    >
                        {{ cat.hours.toFixed(1) }}
                    </span>
                    <div
                        class="h-1 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                    >
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :style="{
                                width: `${cat.planned_hours > 0 ? Math.min((cat.hours / cat.planned_hours) * 100, 100) : 0}%`,
                                backgroundColor: COLOR_ACTUAL,
                            }"
                        />
                    </div>
                    <span
                        class="w-9 text-right font-mono text-slate-400 tabular-nums"
                    >
                        {{ cat.percentage_of_plan }}%
                    </span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
