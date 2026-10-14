<script setup lang="ts">
import {
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    LineElement,
    type Plugin,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import { useBurnZone } from '@/composables/useBurnZone';
import { useTrans } from '@/composables/useTrans';
import { BURN_ZONE_STYLES } from '@/lib/burnZone';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
);

export interface WeekBurndownItem {
    week_number: number;
    label: string;
    date_range: string;
    planned_hours: number;
    actual_hours: number | null;
    cumulative_planned_hours: number;
    cumulative_actual_hours: number | null;
    hkn_hours: number | null;
    hlr_hours: number | null;
    burn_pct: number | null;
    deviation_hours: number | null;
    is_future: boolean;
    is_current: boolean;
}

const props = defineProps<{
    weeks: WeekBurndownItem[];
    plannedBudgetHours: number;
    burnIndexPct: number;
    mlTrajectory?: (number | null)[] | null;
}>();

const { __ } = useTrans();
const { boundaries, classify, rangeOf } = useBurnZone();

const actualLineColor = computed(
    () => BURN_ZONE_STYLES[classify(props.burnIndexPct)].hex,
);

const chartLabels = computed(() => {
    return props.weeks.map((w) => `${w.label}\n(${w.date_range})`);
});

const chartData = computed(() => {
    const plannedCumulative = props.weeks.map(
        (w) => w.cumulative_planned_hours,
    );
    const actualCumulative = props.weeks.map((w) => w.cumulative_actual_hours);

    const datasets: any[] = [
        {
            label: __('Rencana Kumulatif (Target)'),
            data: plannedCumulative,
            borderColor: '#64748b',
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [6, 6],
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: '#64748b',
            tension: 0.1,
            spanGaps: false,
        },
        {
            label: __('Realisasi Kumulatif (Disetujui)'),
            data: actualCumulative,
            borderColor: actualLineColor.value,
            backgroundColor: actualLineColor.value,
            borderWidth: 3,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: actualLineColor.value,
            tension: 0.15,
            spanGaps: false,
        },
    ];

    if (props.mlTrajectory && props.mlTrajectory.some((val) => val !== null)) {
        datasets.push({
            label: __('Proyeksi AI / ML'),
            data: props.mlTrajectory,
            borderColor: '#8b5cf6',
            backgroundColor: '#8b5cf6',
            borderWidth: 2,
            borderDash: [2, 4],
            pointRadius: 3,
            pointStyle: 'triangle',
            pointBackgroundColor: '#8b5cf6',
            tension: 0.2,
            spanGaps: true,
        });
    }

    return {
        labels: chartLabels.value,
        datasets,
    };
});

// Custom Chart.js plugin to shade the 3 tolerance zones (Hijau / Kuning / Merah)
const zoneShadingPlugin: Plugin = {
    id: 'burndownZoneShading',
    beforeDraw(chart) {
        const {
            ctx,
            chartArea,
            scales: { y },
        } = chart;

        if (!chartArea || !y || props.plannedBudgetHours <= 0) {
            return;
        }

        const { top, bottom, left, right, width } = chartArea;
        const pixelFor = (pct: number): number =>
            Math.min(
                bottom,
                Math.max(
                    top,
                    y.getPixelForValue(props.plannedBudgetHours * (pct / 100)),
                ),
            );

        const yGreen = pixelFor(boundaries.value.green_max_pct);
        const yYellow = pixelFor(boundaries.value.yellow_max_pct);
        const yPlan = pixelFor(100);

        ctx.save();

        // Green zone: 0 → green_max
        ctx.fillStyle = 'rgba(22, 163, 74, 0.07)';
        ctx.fillRect(left, yGreen, width, bottom - yGreen);

        // Yellow zone: green_max → yellow_max
        ctx.fillStyle = 'rgba(245, 158, 11, 0.09)';
        ctx.fillRect(left, yYellow, width, yGreen - yYellow);

        // Red zone: above yellow_max
        ctx.fillStyle = 'rgba(220, 38, 38, 0.08)';
        ctx.fillRect(left, top, width, yYellow - top);

        const drawLine = (
            yPos: number,
            color: string,
            dash: number[],
            label: string,
        ) => {
            ctx.strokeStyle = color;
            ctx.lineWidth = 1;
            ctx.setLineDash(dash);
            ctx.beginPath();
            ctx.moveTo(left, yPos);
            ctx.lineTo(right, yPos);
            ctx.stroke();

            ctx.fillStyle = color;
            ctx.font = '10px monospace';
            ctx.textAlign = 'right';
            ctx.fillText(label, right - 6, yPos - 4);
        };

        drawLine(
            yGreen,
            'rgba(22, 163, 74, 0.8)',
            [3, 3],
            `${boundaries.value.green_max_pct}% (${((props.plannedBudgetHours * boundaries.value.green_max_pct) / 100).toFixed(1)} jam)`,
        );
        drawLine(
            yYellow,
            'rgba(217, 119, 6, 0.85)',
            [3, 3],
            `${boundaries.value.yellow_max_pct}% (${((props.plannedBudgetHours * boundaries.value.yellow_max_pct) / 100).toFixed(1)} jam)`,
        );

        if (yPlan !== yYellow && yPlan !== yGreen) {
            drawLine(
                yPlan,
                'rgba(100, 116, 139, 0.6)',
                [6, 4],
                `Plan 100% (${props.plannedBudgetHours.toFixed(1)} jam)`,
            );
        }

        ctx.restore();
    },
};

const chartOptions = computed(() => {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index' as const,
            intersect: false,
        },
        plugins: {
            legend: {
                position: 'top' as const,
                labels: {
                    boxWidth: 12,
                    boxHeight: 12,
                    usePointStyle: true,
                    font: {
                        size: 11,
                    },
                },
            },
            tooltip: {
                backgroundColor: 'rgba(15, 23, 42, 0.92)',
                titleFont: { size: 12, weight: 'bold' as const },
                bodyFont: { size: 11, family: 'monospace' },
                padding: 10,
                callbacks: {
                    label(context: any) {
                        const val = context.parsed.y;
                        if (val === null || val === undefined) {
                            return `${context.dataset.label}: -`;
                        }
                        return `${context.dataset.label}: ${val.toFixed(1)} jam`;
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
                        size: 10,
                    },
                },
            },
            y: {
                beginAtZero: true,
                suggestedMax: Math.max(props.plannedBudgetHours * 1.15, 40),
                grid: {
                    color: 'rgba(226, 232, 240, 0.6)',
                },
                ticks: {
                    font: {
                        size: 10,
                        family: 'monospace',
                    },
                    callback(value: any) {
                        return `${value} j`;
                    },
                },
            },
        },
    };
});
</script>

<template>
    <div class="space-y-2" data-test="burndown-line-chart-container">
        <!-- Zone Indicator Legend Header -->
        <div
            class="flex flex-wrap items-center justify-between gap-2 text-[11px]"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300"
                    data-test="burndown-legend-green"
                >
                    <span
                        class="inline-block size-2.5 rounded-xs border border-emerald-300 bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/60"
                    />
                    <span>{{ __('Hijau') }} ({{ rangeOf('green') }})</span>
                </span>
                <span
                    class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300"
                    data-test="burndown-legend-yellow"
                >
                    <span
                        class="inline-block size-2.5 rounded-xs border border-amber-300 bg-amber-100 dark:border-amber-800 dark:bg-amber-950/60"
                    />
                    <span>{{ __('Kuning') }} ({{ rangeOf('yellow') }})</span>
                </span>
                <span
                    class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300"
                    data-test="burndown-legend-red"
                >
                    <span
                        class="inline-block size-2.5 rounded-xs border border-red-300 bg-red-100 dark:border-red-800 dark:bg-red-950/60"
                    />
                    <span>{{ __('Merah') }} ({{ rangeOf('red') }})</span>
                </span>
            </div>
            <div
                v-if="plannedBudgetHours > 0"
                class="text-muted-foreground font-mono text-[10px] tabular-nums"
            >
                {{ __('Plafon Anggaran') }}: {{ plannedBudgetHours.toFixed(1) }}
                {{ __('jam') }}
            </div>
        </div>

        <!-- Canvas Container -->
        <div class="h-64 w-full sm:h-72">
            <Line
                :data="chartData"
                :options="chartOptions"
                :plugins="[zoneShadingPlugin]"
            />
        </div>
    </div>
</template>
