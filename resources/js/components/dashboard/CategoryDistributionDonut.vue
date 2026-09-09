<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import { PieChart } from '@lucide/vue';
import { computed, ref } from 'vue';
import BaseDonutChart from '@/components/charts/BaseDonutChart.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

export interface CategoryItem {
    key: 'production' | 'tpm' | 'project' | 'others';
    label: string;
    hours: number;
    percentage: number;
    color: string;
}

export interface CategoryDistributionData {
    total_hours: number;
    categories: CategoryItem[];
    fiscal_year: number;
    fiscal_month: number;
    month_name: string;
    scope: {
        department_id: number | null;
        section_id: number | null;
    };
}

interface Props {
    data?: CategoryDistributionData;
    loading?: boolean;
    selectedCategory?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    data: undefined,
    loading: false,
    selectedCategory: null,
});

const emit = defineEmits<{
    (e: 'select-category', key: string | null): void;
}>();

const { __ } = useTrans();
const activeCategory = ref<string | null>(props.selectedCategory);

function toggleCategory(key: string) {
    if (activeCategory.value === key) {
        activeCategory.value = null;
    } else {
        activeCategory.value = key;
    }
    emit('select-category', activeCategory.value);
}

const chartLabels = computed(() => {
    if (!props.data?.categories) {
        return [];
    }
    return props.data.categories.map((c) => c.label);
});

const datasets = computed<ChartDataset<'doughnut'>[]>(() => {
    if (!props.data?.categories || props.data.total_hours === 0) {
        return [];
    }

    return [
        {
            data: props.data.categories.map((c) => c.hours),
            backgroundColor: props.data.categories.map((c) => c.color),
            hoverBackgroundColor: props.data.categories.map((c) => c.color),
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 6,
        },
    ];
});

const chartOptions = computed<ChartOptions<'doughnut'>>(() => {
    return {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
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
                        const idx = context.dataIndex;
                        const cat = props.data?.categories[idx];
                        if (!cat) {
                            return '';
                        }
                        return ` ${cat.label}: ${cat.hours} jam (${cat.percentage}%)`;
                    },
                },
            },
        },
    };
});
</script>

<template>
    <Card
        class="border-border/70 flex flex-col justify-between shadow-2xs"
        data-test="category-distribution-donut-card"
    >
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100"
                    >
                        <PieChart class="size-4 text-violet-500" />
                        <span>{{ __('Distribusi Kategori Lembur') }}</span>
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{
                            __(
                                'Komposisi jam lembur berdasarkan jenis pekerjaan pada :month',
                                { month: data?.month_name || '' },
                            )
                        }}
                    </CardDescription>
                </div>

                <div class="text-right">
                    <span
                        class="text-[11px] font-medium text-slate-500 dark:text-slate-400"
                    >
                        {{ __('Total Terverifikasi') }}
                    </span>
                    <p
                        class="font-mono text-sm font-bold text-slate-900 tabular-nums dark:text-slate-100"
                    >
                        {{ data?.total_hours ?? 0 }} {{ __('jam') }}
                    </p>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4 pt-0">
            <!-- Donut Chart Canvas -->
            <div class="relative">
                <BaseDonutChart
                    :labels="chartLabels"
                    :datasets="datasets"
                    :options="chartOptions"
                    :loading="loading"
                    :empty="!data || data.total_hours === 0"
                    height-class="h-44"
                    :empty-text="__('Belum ada data kategori lembur')"
                />
            </div>

            <!-- External Clickable Legend Pills (UX Plan §4.4) -->
            <div
                class="grid grid-cols-2 gap-2"
                data-test="category-legend-grid"
            >
                <button
                    v-for="cat in data?.categories || []"
                    :key="cat.key"
                    type="button"
                    class="flex items-center justify-between rounded-lg border p-2 text-left transition-all hover:bg-slate-50 dark:hover:bg-slate-800/60"
                    :class="[
                        activeCategory === cat.key
                            ? 'border-slate-800 bg-slate-100 ring-1 ring-slate-400 dark:border-slate-300 dark:bg-slate-800'
                            : 'border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900',
                    ]"
                    :title="__('Klik untuk filter tabel karyawan')"
                    @click="toggleCategory(cat.key)"
                >
                    <div class="flex min-w-0 items-center gap-2">
                        <span
                            class="size-2.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: cat.color }"
                        />
                        <span
                            class="truncate text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            {{ cat.label }}
                        </span>
                    </div>

                    <div class="shrink-0 pl-1 text-right">
                        <span
                            class="font-mono text-xs font-semibold text-slate-900 tabular-nums dark:text-slate-100"
                        >
                            {{ cat.hours }}h
                        </span>
                        <span
                            class="block font-mono text-[10px] text-slate-500 tabular-nums dark:text-slate-400"
                        >
                            {{ cat.percentage }}%
                        </span>
                    </div>
                </button>
            </div>
        </CardContent>
    </Card>
</template>
