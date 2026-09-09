<script setup lang="ts">
import type { ChartData, ChartDataset, ChartOptions } from 'chart.js';
import { computed } from 'vue';
import { Bar, Line } from 'vue-chartjs';
import ChartSkeleton from '@/components/charts/ChartSkeleton.vue';
import { chartColors } from '@/composables/useChartTheme';
import { useTrans } from '@/composables/useTrans';
import { registerChartDefaults } from '@/plugins/chartjs';

registerChartDefaults();

interface Props {
    data: number[];
    labels?: string[];
    type?: 'line' | 'bar';
    color?: string;
    fillColor?: string;
    heightClass?: string;
    loading?: boolean;
    empty?: boolean;
    emptyText?: string;
    unit?: string;
}

const props = withDefaults(defineProps<Props>(), {
    labels: () => [],
    type: 'line',
    color: chartColors.primary,
    fillColor: undefined,
    heightClass: 'h-10',
    loading: false,
    empty: false,
    emptyText: '',
    unit: '',
});

const { __ } = useTrans();

const isActuallyEmpty = computed(() => {
    return props.empty || !props.data || props.data.length === 0;
});

const generatedLabels = computed(() => {
    if (props.labels && props.labels.length === props.data.length) {
        return props.labels;
    }
    return props.data.map((_, i) => `${i + 1}`);
});

const chartData = computed<ChartData<'line' | 'bar'>>(() => {
    if (props.type === 'bar') {
        const dataset: ChartDataset<'bar'> = {
            data: props.data,
            backgroundColor: props.color,
            hoverBackgroundColor: props.color,
            borderRadius: 2,
            borderSkipped: false,
            barThickness: 'flex',
            maxBarThickness: 10,
        };
        return {
            labels: generatedLabels.value,
            datasets: [dataset],
        };
    }

    const dataset: ChartDataset<'line'> = {
        data: props.data,
        borderColor: props.color,
        borderWidth: 2,
        tension: 0.35,
        fill: Boolean(props.fillColor),
        backgroundColor: props.fillColor,
        pointRadius: 0,
        pointHoverRadius: 3,
        pointHoverBackgroundColor: props.color,
        pointHoverBorderColor: '#ffffff',
        pointHoverBorderWidth: 1.5,
    };

    return {
        labels: generatedLabels.value,
        datasets: [dataset],
    };
});

const chartOptions = computed<ChartOptions<'line' | 'bar'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    layout: {
        padding: {
            top: 4,
            bottom: 4,
            left: 2,
            right: 2,
        },
    },
    scales: {
        x: {
            display: false,
            grid: { display: false },
        },
        y: {
            display: false,
            grid: { display: false },
            beginAtZero: true,
        },
    },
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            enabled: true,
            displayColors: false,
            padding: 6,
            caretSize: 4,
            callbacks: {
                title: (items) => {
                    if (!items.length) {
                        return '';
                    }
                    const item = items[0];
                    return item.label ? `${item.label}` : '';
                },
                label: (item) => {
                    const val = item.raw;
                    return props.unit ? `${val} ${props.unit}` : `${val}`;
                },
            },
        },
    },
}));
</script>

<template>
    <div
        class="relative w-full"
        :class="heightClass"
        data-slot="base-mini-sparkline"
    >
        <ChartSkeleton
            v-if="loading"
            :height-class="heightClass"
            variant="sparkline"
        />

        <div
            v-else-if="isActuallyEmpty"
            class="flex h-full w-full items-center justify-center text-xs text-slate-400"
        >
            <span>{{ emptyText || '-' }}</span>
        </div>

        <template v-else>
            <Bar
                v-if="type === 'bar'"
                :data="chartData as ChartData<'bar'>"
                :options="chartOptions as ChartOptions<'bar'>"
            />
            <Line
                v-else
                :data="chartData as ChartData<'line'>"
                :options="chartOptions as ChartOptions<'line'>"
            />
        </template>
    </div>
</template>
