<script setup lang="ts">
import type { ChartDataset, ChartOptions, Plugin } from 'chart.js';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    HelpCircle,
    TrendingUp,
} from '@lucide/vue';
import { computed } from 'vue';
import BaseLineChart from '@/components/charts/BaseLineChart.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { chartColors, useChartTheme } from '@/composables/useChartTheme';
import { useTrans } from '@/composables/useTrans';

export interface DailyBurnChartData {
    labels: string[];
    plan_cumulative: number[];
    actual_cumulative: (number | null)[];
    ml_projected: (number | null)[];
    planned_hours: number;
    current_actual_hours: number;
    burn_index_pct: number;
    burn_zone: 'safe' | 'on_track' | 'warning' | 'danger';
    burn_zone_label: string;
    cutoff_day: number;
    days_in_month: number;
    fiscal_year: number;
    fiscal_month: number;
    month_name: string;
    scope: {
        department_id: number | null;
        section_id: number | null;
    };
    available_sections: {
        id: number;
        code: string;
        name: string;
    }[];
}

interface Props {
    data?: DailyBurnChartData;
    loading?: boolean;
    selectedSectionId?: number | null;
}

const props = withDefaults(defineProps<Props>(), {
    data: undefined,
    loading: false,
    selectedSectionId: null,
});

const emit = defineEmits<{
    (e: 'navigate-month', direction: 'prev' | 'next'): void;
    (e: 'select-section', sectionId: number | null): void;
}>();

const { __ } = useTrans();
const { getBurnZoneColor } = useChartTheme();

const actualLineColor = computed(() => {
    if (!props.data) {
        return chartColors.primary;
    }
    return getBurnZoneColor(props.data.burn_index_pct);
});

const zoneBadgeClass = computed(() => {
    const zone = props.data?.burn_zone ?? 'safe';
    switch (zone) {
        case 'danger':
            return 'bg-red-500/10 text-red-700 border-red-500/20 dark:text-red-300';
        case 'warning':
            return 'bg-amber-500/10 text-amber-700 border-amber-500/20 dark:text-amber-300';
        case 'on_track':
            return 'bg-blue-500/10 text-blue-700 border-blue-500/20 dark:text-blue-300';
        case 'safe':
        default:
            return 'bg-emerald-500/10 text-emerald-700 border-emerald-500/20 dark:text-emerald-300';
    }
});

const zoneIcon = computed(() => {
    const zone = props.data?.burn_zone ?? 'safe';
    switch (zone) {
        case 'danger':
        case 'warning':
            return AlertTriangle;
        case 'on_track':
            return TrendingUp;
        case 'safe':
        default:
            return CheckCircle2;
    }
});

const datasets = computed<ChartDataset<'line'>[]>(() => {
    if (!props.data) {
        return [];
    }

    const series: ChartDataset<'line'>[] = [
        {
            label: __('Rencana Kumulatif (Plan)'),
            data: props.data.plan_cumulative,
            borderColor: '#3b82f6',
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [6, 6],
            pointRadius: 2,
            pointHoverRadius: 5,
            pointBackgroundColor: '#3b82f6',
            tension: 0.05,
            spanGaps: false,
        },
        {
            label: __('Realisasi Disetujui (Actual)'),
            data: props.data.actual_cumulative as (number | null)[],
            borderColor: actualLineColor.value,
            backgroundColor: actualLineColor.value,
            borderWidth: 3,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: actualLineColor.value,
            tension: 0.1,
            spanGaps: false,
        },
    ];

    if (
        props.data.ml_projected &&
        props.data.ml_projected.some((val) => val !== null && val > 0)
    ) {
        series.push({
            label: __('Proyeksi AI / ML (Trajectory)'),
            data: props.data.ml_projected as (number | null)[],
            borderColor: '#8b5cf6',
            backgroundColor: '#8b5cf6',
            borderWidth: 2,
            borderDash: [3, 4],
            pointRadius: 3,
            pointStyle: 'triangle',
            pointBackgroundColor: '#8b5cf6',
            tension: 0.15,
            spanGaps: true,
        });
    }

    return series;
});

const chartPlugins = computed<Plugin<'line'>[]>(() => {
    const ceilingPlugin: Plugin<'line'> = {
        id: 'dailyBurnCeilingShading',
        beforeDraw(chart) {
            const {
                ctx,
                chartArea,
                scales: { y },
            } = chart;

            if (
                !chartArea ||
                !y ||
                !props.data ||
                props.data.planned_hours <= 0
            ) {
                return;
            }

            const { top, bottom, left, right, width } = chartArea;
            const plannedHours = props.data.planned_hours;
            const yCeiling = Math.min(
                bottom,
                Math.max(top, y.getPixelForValue(plannedHours)),
            );

            ctx.save();

            // 1. Shaded Warning Zone above 100% ceiling
            if (yCeiling > top) {
                ctx.fillStyle = 'rgba(220, 38, 38, 0.08)'; // Light red
                ctx.fillRect(left, top, width, yCeiling - top);
            }

            // 2. Dashed line for 100% Ceiling
            ctx.strokeStyle = 'rgba(220, 38, 38, 0.65)';
            ctx.lineWidth = 1.5;
            ctx.setLineDash([5, 4]);
            ctx.beginPath();
            ctx.moveTo(left, yCeiling);
            ctx.lineTo(right, yCeiling);
            ctx.stroke();

            // 3. Label for Budget Ceiling
            ctx.fillStyle = 'rgba(220, 38, 38, 0.9)';
            ctx.font = '10px "Instrument Sans", monospace';
            ctx.textAlign = 'right';
            ctx.fillText(
                `Plafon Anggaran (${plannedHours.toFixed(1)} jam)`,
                right - 6,
                yCeiling - 6,
            );

            ctx.restore();
        },
    };

    return [ceilingPlugin];
});

const chartOptions = computed<ChartOptions<'line'>>(() => {
    const plannedHours = props.data?.planned_hours ?? 0;
    const currentActual = props.data?.current_actual_hours ?? 0;
    const peakHours = Math.max(plannedHours, currentActual);
    const yMax = peakHours > 0 ? Math.ceil(peakHours * 1.3) : 100;

    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            x: {
                grid: {
                    color: 'rgba(226, 232, 240, 0.5)',
                },
                ticks: {
                    font: {
                        family: '"Instrument Sans", sans-serif',
                        size: 11,
                    },
                    color: '#64748b',
                },
                title: {
                    display: true,
                    text: __('Tanggal Operasional (Hari ke 1–:days)', {
                        days: props.data?.days_in_month ?? 31,
                    }),
                    font: {
                        size: 11,
                        weight: 'bold',
                    },
                    color: '#94a3b8',
                },
            },
            y: {
                beginAtZero: true,
                max: yMax,
                grid: {
                    color: 'rgba(226, 232, 240, 0.5)',
                },
                ticks: {
                    font: {
                        family: 'monospace',
                        size: 11,
                    },
                    color: '#64748b',
                    callback: (value) => `${value} h`,
                },
                title: {
                    display: true,
                    text: __('Kumulatif Jam Lembur (Jam)'),
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
                display: true,
                position: 'top',
                align: 'end',
                labels: {
                    boxWidth: 14,
                    boxHeight: 8,
                    usePointStyle: false,
                    font: {
                        size: 11,
                        weight: 500,
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
                    title: (items) => {
                        if (!items.length) return '';
                        const day = items[0].label;
                        return `${__('Tanggal')} ${day} ${props.data?.month_name ?? ''}`;
                    },
                    label: (context) => {
                        const val = context.parsed.y;
                        if (val === null || val === undefined) return '';
                        const label = context.dataset.label || '';
                        return `${label}: ${val.toFixed(1)} ${__('jam')}`;
                    },
                    afterBody: (items) => {
                        if (!items.length || !props.data) return [];
                        const index = items[0].dataIndex;
                        const actual = props.data.actual_cumulative[index];
                        const plan = props.data.plan_cumulative[index];
                        if (
                            actual === null ||
                            actual === undefined ||
                            plan === null ||
                            plan === undefined
                        ) {
                            return [];
                        }
                        const diff = actual - plan;
                        const sign = diff > 0 ? '+' : '';
                        const varianceStatus =
                            diff > 0
                                ? __('Lebih Cepat Dari Target (Peringatan)')
                                : __('Sesuai / Di Bawah Target (Aman)');
                        return [
                            `------------------------`,
                            `${__('Selisih Realisasi vs Rencana')}: ${sign}${diff.toFixed(1)} ${__('jam')}`,
                            `Status: ${varianceStatus}`,
                        ];
                    },
                },
            },
        },
    };
});
</script>

<template>
    <Card
        class="border-border/70 overflow-hidden shadow-xs"
        data-test="daily-burn-line-chart-card"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Activity
                            class="size-5 text-blue-600 dark:text-blue-400"
                        />
                        <CardTitle
                            class="text-lg font-bold tracking-tight text-slate-900 dark:text-white"
                        >
                            {{ __('Daily Burn Chart Index Overtime') }}
                        </CardTitle>
                        <Badge
                            v-if="data"
                            variant="outline"
                            :class="zoneBadgeClass"
                            class="font-mono text-xs tabular-nums"
                            data-test="daily-burn-zone-badge"
                        >
                            <component
                                :is="zoneIcon"
                                class="mr-1 size-3.5 shrink-0"
                            />
                            {{ data.burn_zone_label }} ({{
                                data.burn_index_pct
                            }}%)
                        </Badge>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{
                            __(
                                'Pelacakan kumulatif jam lembur terhadap kurva linier rencana kerja dan batas plafon anggaran.',
                            )
                        }}
                    </p>
                </div>

                <!-- Month Selector & Section Filter Controls -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Section Selector -->
                    <div
                        v-if="
                            data?.available_sections &&
                            data.available_sections.length > 1
                        "
                        class="flex items-center"
                    >
                        <select
                            :value="
                                selectedSectionId
                                    ? String(selectedSectionId)
                                    : 'all'
                            "
                            class="h-8 rounded-md border border-slate-300 bg-white px-2 text-xs font-medium text-slate-700 shadow-2xs focus:border-blue-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            data-test="daily-burn-section-select"
                            @change="
                                $emit(
                                    'select-section',
                                    ($event.target as HTMLSelectElement)
                                        .value === 'all'
                                        ? null
                                        : Number(
                                              (
                                                  $event.target as HTMLSelectElement
                                              ).value,
                                          ),
                                )
                            "
                        >
                            <option value="all">
                                {{ __('Semua Seksi (Departemen)') }}
                            </option>
                            <option
                                v-for="sec in data.available_sections"
                                :key="sec.id"
                                :value="String(sec.id)"
                            >
                                {{ sec.code }} - {{ sec.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Month Navigation -->
                    <div
                        class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50/80 p-0.5 dark:border-slate-800 dark:bg-slate-900/60"
                    >
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="size-7"
                            :title="__('Bulan Sebelumnya')"
                            data-test="prev-month-button"
                            @click="$emit('navigate-month', 'prev')"
                        >
                            <ChevronLeft class="size-4" />
                            <span class="sr-only">{{
                                __('Bulan Sebelumnya')
                            }}</span>
                        </Button>

                        <span
                            class="min-w-28 px-2 text-center font-mono text-xs font-semibold text-slate-700 dark:text-slate-200"
                            data-test="active-month-label"
                        >
                            {{ data?.month_name || '-' }}
                        </span>

                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="size-7"
                            :title="__('Bulan Berikutnya')"
                            data-test="next-month-button"
                            @click="$emit('navigate-month', 'next')"
                        >
                            <ChevronRight class="size-4" />
                            <span class="sr-only">{{
                                __('Bulan Berikutnya')
                            }}</span>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Metrics Strip -->
            <div
                v-if="data"
                class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 sm:grid-cols-4 dark:border-slate-800"
                data-test="daily-burn-stats-strip"
            >
                <div class="space-y-0.5">
                    <span
                        class="text-[11px] font-medium text-slate-500 dark:text-slate-400"
                    >
                        {{ __('Plafon Anggaran') }}
                    </span>
                    <p
                        class="font-mono text-sm font-bold text-slate-900 tabular-nums dark:text-white"
                    >
                        {{ data.planned_hours.toFixed(1) }}
                        <span class="text-xs font-normal text-slate-500"
                            >jam</span
                        >
                    </p>
                </div>

                <div class="space-y-0.5">
                    <span
                        class="text-[11px] font-medium text-slate-500 dark:text-slate-400"
                    >
                        {{ __('Realisasi Saat Ini') }}
                    </span>
                    <p
                        class="font-mono text-sm font-bold tabular-nums"
                        :style="{ color: actualLineColor }"
                    >
                        {{ data.current_actual_hours.toFixed(1) }}
                        <span class="text-xs font-normal text-slate-500"
                            >jam</span
                        >
                    </p>
                </div>

                <div class="space-y-0.5">
                    <span
                        class="text-[11px] font-medium text-slate-500 dark:text-slate-400"
                    >
                        {{ __('Sisa Anggaran') }}
                    </span>
                    <p
                        class="font-mono text-sm font-bold tabular-nums"
                        :class="
                            data.planned_hours - data.current_actual_hours < 0
                                ? 'text-red-600 dark:text-red-400'
                                : 'text-slate-700 dark:text-slate-200'
                        "
                    >
                        {{
                            (
                                data.planned_hours - data.current_actual_hours
                            ).toFixed(1)
                        }}
                        <span class="text-xs font-normal text-slate-500"
                            >jam</span
                        >
                    </p>
                </div>

                <div class="space-y-0.5">
                    <span
                        class="text-[11px] font-medium text-slate-500 dark:text-slate-400"
                    >
                        {{ __('Indeks Burn (%)') }}
                    </span>
                    <p
                        class="font-mono text-sm font-bold tabular-nums"
                        :style="{ color: actualLineColor }"
                    >
                        {{ data.burn_index_pct }}%
                    </p>
                </div>
            </div>
        </CardHeader>

        <CardContent class="pt-0">
            <!-- Main Hero Line Chart Canvas (height ~384px / h-96) -->
            <BaseLineChart
                :labels="data?.labels ?? []"
                :datasets="datasets"
                :options="chartOptions"
                :plugins="chartPlugins"
                :loading="loading"
                :empty="!data || data.labels.length === 0"
                height-class="h-96"
                data-test="daily-burn-line-canvas"
            />
        </CardContent>
    </Card>
</template>
