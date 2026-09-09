<script setup lang="ts">
import type { ChartData, ChartDataset, ChartOptions, Plugin } from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import ChartSkeleton from '@/components/charts/ChartSkeleton.vue';
import { useTrans } from '@/composables/useTrans';
import { registerChartDefaults } from '@/plugins/chartjs';

registerChartDefaults();

interface Props {
    labels?: string[];
    datasets: ChartDataset<'line'>[];
    options?: ChartOptions<'line'>;
    plugins?: Plugin<'line'>[];
    loading?: boolean;
    empty?: boolean;
    heightClass?: string;
    emptyText?: string;
}

const props = withDefaults(defineProps<Props>(), {
    labels: () => [],
    options: () => ({}),
    plugins: () => [],
    loading: false,
    empty: false,
    heightClass: 'h-64',
    emptyText: '',
});

const { __ } = useTrans();

const isActuallyEmpty = computed(() => {
    if (props.empty) {
        return true;
    }
    if (!props.datasets || props.datasets.length === 0) {
        return true;
    }
    return props.datasets.every(
        (ds) => !ds.data || (Array.isArray(ds.data) && ds.data.length === 0),
    );
});

const chartData = computed<ChartData<'line'>>(() => ({
    labels: props.labels,
    datasets: props.datasets,
}));

const mergedOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    ...props.options,
}));
</script>

<template>
    <div
        class="relative w-full"
        :class="heightClass"
        data-slot="base-line-chart"
    >
        <ChartSkeleton
            v-if="loading"
            :height-class="heightClass"
            variant="chart"
        />

        <div
            v-else-if="isActuallyEmpty"
            class="flex h-full w-full flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 p-6 text-center dark:border-slate-800"
        >
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                {{ emptyText || __('Belum ada data') }}
            </p>
        </div>

        <Line
            v-else
            :data="chartData"
            :options="mergedOptions"
            :plugins="plugins"
        />
    </div>
</template>
