<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import { Activity } from '@lucide/vue';
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
import { useZoneBandsPlugin } from '@/composables/useZoneBandsPlugin';
import { BURN_ZONE_STYLES } from '@/lib/burnZone';
import type { ZoneBoundaries } from '@/lib/burnZone';
import { formatShortDate } from '@/lib/earlyWarning';
import type { SeriesPoint } from '@/lib/earlyWarning';

/** The plan itself: 100% of the daily pacing target. */
const PLAN_PCT = 100;

const props = defineProps<{
    series: SeriesPoint[];
    limits: ZoneBoundaries;
    /** The alert date, drawn with a larger point. */
    alertDate: string;
}>();

const { __, locale } = useTrans();

const zoneBandsPlugin = useZoneBandsPlugin(() => props.limits);

const labels = computed(() =>
    props.series.map((point) => formatShortDate(point.date, locale.value)),
);

function constantLine(value: number): number[] {
    return props.series.map(() => value);
}

function limitDataset(
    label: string,
    value: number,
    color: string,
    dash: number[],
): ChartDataset<'line'> {
    return {
        label,
        data: constantLine(value),
        borderColor: color,
        backgroundColor: 'transparent',
        borderWidth: 1.5,
        borderDash: dash,
        pointRadius: 0,
        pointHoverRadius: 0,
    };
}

const datasets = computed<ChartDataset<'line'>[]>(() => {
    if (props.series.length === 0) {
        return [];
    }

    const zoneHex = props.series.map(
        (point) => BURN_ZONE_STYLES[point.zone].hex,
    );

    return [
        {
            label: __('Indeks Bulan Berjalan (%)'),
            data: props.series.map((point) => point.index_pct),
            borderColor: '#475569',
            backgroundColor: 'transparent',
            borderWidth: 2,
            pointRadius: props.series.map((point) =>
                point.date === props.alertDate ? 6 : 4,
            ),
            pointHoverRadius: 7,
            pointBackgroundColor: zoneHex,
            pointBorderColor: zoneHex,
            fill: false,
            tension: 0.15,
        },
        limitDataset(
            __('Batas Hijau'),
            props.limits.green_max_pct,
            BURN_ZONE_STYLES.green.hex,
            [4, 4],
        ),
        limitDataset(
            __('Batas Kuning'),
            props.limits.yellow_max_pct,
            BURN_ZONE_STYLES.yellow.hex,
            [4, 4],
        ),
        limitDataset(__('Rencana (100% Pacing)'), PLAN_PCT, '#64748b', [8, 4]),
    ];
});

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.95)',
            titleColor: '#f8fafc',
            bodyColor: '#cbd5e1',
            padding: 10,
            callbacks: {
                label: (context) => {
                    if (context.datasetIndex !== 0) {
                        return ` ${context.dataset.label}: ${context.parsed.y}%`;
                    }

                    const point = props.series[context.dataIndex];

                    if (!point) {
                        return '';
                    }

                    return [
                        ` ${__('Indeks Burn Bulan Berjalan')}: ${point.index_pct}% (${__(BURN_ZONE_STYLES[point.zone].label)})`,
                        ` ${__('Realisasi Jam')}: ${point.actual_hours} ${__('jam')}`,
                    ];
                },
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { font: { family: 'monospace', size: 10 }, maxRotation: 0 },
        },
        y: {
            beginAtZero: true,
            suggestedMax: Math.max(
                130,
                Math.ceil(
                    Math.max(
                        ...props.series.map((point) => point.index_pct),
                        PLAN_PCT,
                    ) * 1.1,
                ),
            ),
            grid: { color: 'rgba(226, 232, 240, 0.6)' },
            ticks: {
                font: { family: 'monospace', size: 11 },
                callback: (value) => `${value}%`,
            },
        },
    },
}));
</script>

<template>
    <Card class="shadow-2xs" data-test="alert-index-chart">
        <CardHeader class="pb-3">
            <CardTitle
                class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100"
            >
                <Activity class="size-4 text-[#cc0000]" />
                <span>{{ __('Last 7 working days') }}</span>
            </CardTitle>
            <CardDescription class="text-xs">
                {{ __('Month-to-date index against the department limits.') }}
            </CardDescription>
        </CardHeader>
        <CardContent class="pt-0">
            <BaseLineChart
                :labels="labels"
                :datasets="datasets"
                :options="chartOptions"
                :plugins="[zoneBandsPlugin]"
                :empty="series.length === 0"
                height-class="h-60"
                :empty-text="__('Belum ada data indeks lembur harian')"
            />
        </CardContent>
    </Card>
</template>
