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
import { useTrans } from '@/composables/useTrans';

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

const actualLineColor = computed(() => {
    if (props.burnIndexPct > 115) {
        return '#cc0000'; // ISUZU Red
    }
    if (props.burnIndexPct > 100) {
        return '#d97706'; // Amber Warning
    }
    if (props.burnIndexPct >= 85) {
        return '#0284c7'; // Sky Blue On Track
    }
    return '#059669'; // Emerald Safe
});

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

// Custom Chart.js plugin to shade the >100% warning zone and 85-100% on-track corridor
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
        const y100 = Math.min(
            bottom,
            Math.max(top, y.getPixelForValue(props.plannedBudgetHours)),
        );
        const y85 = Math.min(
            bottom,
            Math.max(top, y.getPixelForValue(props.plannedBudgetHours * 0.85)),
        );

        ctx.save();

        // 1. Shaded Warning Zone above 100% ceiling
        if (y100 > top) {
            ctx.fillStyle = 'rgba(239, 68, 68, 0.08)'; // Light red
            ctx.fillRect(left, top, width, y100 - top);
        }

        // 2. Shaded Safe Corridor 85% - 100%
        if (y85 > y100) {
            ctx.fillStyle = 'rgba(2, 132, 199, 0.06)'; // Light blue
            ctx.fillRect(left, y100, width, y85 - y100);
        }

        // 3. Dashed line for 100% Quota
        ctx.strokeStyle = 'rgba(239, 68, 68, 0.4)';
        ctx.lineWidth = 1;
        ctx.setLineDash([4, 4]);
        ctx.beginPath();
        ctx.moveTo(left, y100);
        ctx.lineTo(right, y100);
        ctx.stroke();

        // Label for 100% ceiling
        ctx.fillStyle = 'rgba(220, 38, 38, 0.85)';
        ctx.font = '10px monospace';
        ctx.textAlign = 'right';
        ctx.fillText(
            `100% (${props.plannedBudgetHours.toFixed(1)} jam)`,
            right - 6,
            y100 - 4,
        );

        // 4. Dashed line for 85% Corridor
        ctx.strokeStyle = 'rgba(2, 132, 199, 0.35)';
        ctx.lineWidth = 1;
        ctx.setLineDash([3, 3]);
        ctx.beginPath();
        ctx.moveTo(left, y85);
        ctx.lineTo(right, y85);
        ctx.stroke();

        // Label for 85% corridor
        ctx.fillStyle = 'rgba(2, 132, 199, 0.85)';
        ctx.font = '10px monospace';
        ctx.textAlign = 'right';
        ctx.fillText(
            `85% (${(props.plannedBudgetHours * 0.85).toFixed(1)} jam)`,
            right - 6,
            y85 - 4,
        );

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
                >
                    <span
                        class="inline-block size-2.5 rounded-xs border border-red-300 bg-red-100 dark:border-red-800 dark:bg-red-950/60"
                    />
                    <span>{{ __('Zona Defisit (>100% Kuota)') }}</span>
                </span>
                <span
                    class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300"
                >
                    <span
                        class="inline-block size-2.5 rounded-xs border border-sky-300 bg-sky-100 dark:border-sky-800 dark:bg-sky-950/60"
                    />
                    <span>{{ __('Koridor Terkendali (85–100%)') }}</span>
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
