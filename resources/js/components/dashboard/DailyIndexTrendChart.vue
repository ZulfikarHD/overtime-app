<script setup lang="ts">
import type { ChartDataset, ChartOptions, Plugin } from 'chart.js';
import { Activity, AlertCircle } from '@lucide/vue';
import { computed } from 'vue';
import BaseLineChart from '@/components/charts/BaseLineChart.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { BURN_ZONE_STYLES, describeZoneRange } from '@/lib/burnZone';
import type { BurnZone } from '@/lib/burnZone';

export interface DailyIndexTrendData {
    labels: string[];
    daily_indices: (number | null)[];
    daily_hours: (number | null)[];
    planned_daily_pacing_hours: number;
    threshold_pct: number;
    green_max_pct: number;
    yellow_max_pct: number;
    daily_zones: (BurnZone | null)[];
    average_index: number;
    average_zone: BurnZone;
    cutoff_day: number;
    days_in_month: number;
    fiscal_year: number;
    fiscal_month: number;
    month_name: string;
    scope: {
        department_id: number | null;
        section_id: number | null;
    };
}

interface Props {
    data?: DailyIndexTrendData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: undefined,
    loading: false,
});

const { __ } = useTrans();

const planPct = computed(() => props.data?.threshold_pct || 100);

const greenMax = computed(() => props.data?.green_max_pct ?? 70);

const yellowMax = computed(() => props.data?.yellow_max_pct ?? 90);

const boundaries = computed(() => ({
    green_max_pct: greenMax.value,
    yellow_max_pct: yellowMax.value,
}));

const averageStyle = computed(
    () => BURN_ZONE_STYLES[props.data?.average_zone ?? 'green'],
);

function constantLine(value: number): number[] {
    return (props.data?.labels ?? []).map(() => value);
}

const datasets = computed<ChartDataset<'line'>[]>(() => {
    if (!props.data?.labels?.length) {
        return [];
    }

    return [
        {
            label: __('Indeks Kontribusi Harian (%)'),
            data: props.data.daily_indices,
            borderColor: '#475569',
            backgroundColor: 'transparent',
            borderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 7,
            pointBackgroundColor: props.data.daily_zones.map((zone) =>
                zone ? BURN_ZONE_STYLES[zone].hex : 'transparent',
            ),
            pointBorderColor: props.data.daily_zones.map((zone) =>
                zone ? BURN_ZONE_STYLES[zone].hex : 'transparent',
            ),
            fill: false,
            tension: 0.15,
            spanGaps: false,
        },
        {
            label: __('Batas Hijau'),
            data: constantLine(greenMax.value),
            borderColor: BURN_ZONE_STYLES.green.hex,
            backgroundColor: 'transparent',
            borderWidth: 1.5,
            borderDash: [4, 4],
            pointRadius: 0,
            pointHoverRadius: 0,
        },
        {
            label: __('Batas Kuning'),
            data: constantLine(yellowMax.value),
            borderColor: BURN_ZONE_STYLES.yellow.hex,
            backgroundColor: 'transparent',
            borderWidth: 1.5,
            borderDash: [4, 4],
            pointRadius: 0,
            pointHoverRadius: 0,
        },
        {
            label: __('Rencana (100% Pacing)'),
            data: constantLine(planPct.value),
            borderColor: '#64748b',
            backgroundColor: 'transparent',
            borderWidth: 1.5,
            borderDash: [8, 4],
            pointRadius: 0,
            pointHoverRadius: 0,
        },
    ];
});

/**
 * Paints the three tolerance bands (Hijau / Kuning / Merah) behind the line.
 */
const zoneBandsPlugin = computed<Plugin<'line'>>(() => ({
    id: 'earlyWarningZoneBands',
    beforeDatasetsDraw(chart) {
        const {
            ctx,
            chartArea,
            scales: { y },
        } = chart;

        if (!chartArea || !y) {
            return;
        }

        const { top, bottom, left, width } = chartArea;
        const clamp = (value: number): number =>
            Math.min(bottom, Math.max(top, y.getPixelForValue(value)));
        const yGreen = clamp(greenMax.value);
        const yYellow = clamp(yellowMax.value);

        ctx.save();
        ctx.fillStyle = 'rgba(22, 163, 74, 0.08)';
        ctx.fillRect(left, yGreen, width, bottom - yGreen);
        ctx.fillStyle = 'rgba(245, 158, 11, 0.12)';
        ctx.fillRect(left, yYellow, width, yGreen - yYellow);
        ctx.fillStyle = 'rgba(220, 38, 38, 0.09)';
        ctx.fillRect(left, top, width, yYellow - top);
        ctx.restore();
    },
}));

const chartOptions = computed<ChartOptions<'line'>>(() => {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
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
                    title: (items) => {
                        const day = items[0]?.label ?? '';
                        return `${__('Tanggal')} ${day} ${props.data?.month_name ?? ''}`;
                    },
                    label: (context) => {
                        const idx = context.dataIndex;
                        const dsIdx = context.datasetIndex;
                        if (dsIdx === 1) {
                            return ` ${__('Batas Hijau')}: ${context.parsed.y}%`;
                        }
                        if (dsIdx === 2) {
                            return ` ${__('Batas Kuning')}: ${context.parsed.y}%`;
                        }
                        if (dsIdx === 3) {
                            return ` ${__('Rencana (100% Pacing)')}: ${context.parsed.y}%`;
                        }
                        const indexVal = props.data?.daily_indices[idx];
                        const hoursVal = props.data?.daily_hours[idx];
                        if (indexVal === null || indexVal === undefined) {
                            return ` ${__('Belum ada data')}`;
                        }
                        const zone = props.data?.daily_zones[idx];
                        return [
                            ` ${__('Indeks Burn Harian')}: ${indexVal}%${zone ? ` (${__(BURN_ZONE_STYLES[zone].label)})` : ''}`,
                            ` ${__('Realisasi Jam')}: ${hoursVal ?? 0} ${__('jam')}`,
                        ];
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
                        family: 'monospace',
                        size: 10,
                    },
                    maxRotation: 0,
                },
            },
            y: {
                beginAtZero: true,
                suggestedMax: Math.max(
                    130,
                    Math.ceil(
                        Math.max(
                            ...(props.data?.daily_indices ?? []).map(
                                (v) => v ?? 0,
                            ),
                            planPct.value,
                        ) * 1.1,
                    ),
                ),
                grid: {
                    color: 'rgba(226, 232, 240, 0.6)',
                },
                ticks: {
                    font: {
                        family: 'monospace',
                        size: 11,
                    },
                    callback: (value) => `${value}%`,
                },
            },
        },
    };
});
</script>

<template>
    <Card
        class="border-border/70 flex flex-col justify-between shadow-2xs"
        data-test="daily-index-trend-card"
    >
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100"
                    >
                        <Activity class="size-4 text-[#cc0000]" />
                        <span>{{
                            __('Early Warning: Indeks Kontribusi Harian')
                        }}</span>
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{
                            __(
                                'Tingkat konsumsi lembur harian terhadap target pacing pada :month',
                                { month: data?.month_name || '' },
                            )
                        }}
                    </CardDescription>
                </div>

                <div class="flex items-center gap-2">
                    <Badge
                        variant="outline"
                        class="font-mono text-[11px] font-semibold tabular-nums"
                        :class="averageStyle.badge"
                        data-test="early-warning-average-badge"
                    >
                        {{ __('Rata-rata') }}: {{ data?.average_index ?? 0 }}% ·
                        {{ __(averageStyle.label) }}
                    </Badge>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-3 pt-0">
            <!-- Pacing Reference Guideline -->
            <div
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200/80 bg-slate-50/70 px-3 py-2 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400"
            >
                <div class="flex items-center gap-1.5">
                    <AlertCircle class="size-3.5 text-slate-400" />
                    <span>{{ __('Target Pacing Harian:') }}</span>
                    <span
                        class="font-mono font-bold text-slate-900 tabular-nums dark:text-slate-100"
                    >
                        {{ data?.planned_daily_pacing_hours ?? 0 }}
                        {{ __('jam / hari') }}
                    </span>
                </div>

                <div
                    class="flex flex-wrap items-center gap-3 font-mono text-[11px]"
                    data-test="early-warning-legend"
                >
                    <span
                        v-for="zone in ['green', 'yellow', 'red'] as const"
                        :key="zone"
                        class="flex items-center gap-1"
                        :data-test="`early-warning-legend-${zone}`"
                    >
                        <span
                            class="inline-block size-2.5 rounded-xs"
                            :class="BURN_ZONE_STYLES[zone].solid"
                        />
                        <span
                            >{{ __(BURN_ZONE_STYLES[zone].label) }}
                            {{ describeZoneRange(zone, boundaries) }}</span
                        >
                    </span>
                    <span class="flex items-center gap-1 text-slate-500">
                        <span
                            class="inline-block h-0.5 w-3 border-b border-dashed border-slate-500"
                        />
                        <span>{{ __('Rencana 100%') }}</span>
                    </span>
                </div>
            </div>

            <!-- Line Chart Canvas -->
            <BaseLineChart
                :labels="data?.labels"
                :datasets="datasets"
                :options="chartOptions"
                :plugins="[zoneBandsPlugin]"
                :loading="loading"
                :empty="!data || data.cutoff_day === 0"
                height-class="h-60"
                :empty-text="__('Belum ada data indeks lembur harian')"
            />
        </CardContent>
    </Card>
</template>
