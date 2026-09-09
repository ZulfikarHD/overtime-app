<script setup lang="ts">
import {
    Chart as ChartJS,
    Legend,
    LinearScale,
    type Plugin,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import { computed } from 'vue';
import { Scatter } from 'vue-chartjs';
import { Badge } from '@/components/ui/badge';
import { useTrans } from '@/composables/useTrans';

ChartJS.register(LinearScale, PointElement, Title, Tooltip, Legend);

export interface ScatterPlotData {
    current_burn_pct: number;
    cumulative_actual_hours: number;
    planned_budget_hours: number;
    burn_zone:
        | 'ZONE_1_EXCELLENT'
        | 'ZONE_2_GOOD'
        | 'ZONE_3_WARNING'
        | 'ZONE_4_POOR';
    threshold_hours_75_pct: number;
    threshold_burn_100_pct: number;
    max_x_scale: number;
    max_y_scale: number;
}

const props = defineProps<{
    scatterData: ScatterPlotData;
    sectionName: string;
}>();

const { __ } = useTrans();

const currentPointColor = computed(() => {
    switch (props.scatterData.burn_zone) {
        case 'ZONE_1_EXCELLENT':
            return '#059669'; // Emerald
        case 'ZONE_2_GOOD':
            return '#0284c7'; // Sky blue
        case 'ZONE_3_WARNING':
            return '#d97706'; // Amber
        case 'ZONE_4_POOR':
        default:
            return '#cc0000'; // ISUZU Red
    }
});

const currentZoneDetails = computed(() => {
    switch (props.scatterData.burn_zone) {
        case 'ZONE_1_EXCELLENT':
            return {
                title: __('Zona 1: Sangat Baik (Aman)'),
                desc: __(
                    'Penggunaan jam lembur di bawah kuota dan burn velocity stabil.',
                ),
                badgeClass:
                    'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300',
            };
        case 'ZONE_2_GOOD':
            return {
                title: __('Zona 2: Terkendali (Baik)'),
                desc: __(
                    'Realisasi jam mendekati kuota akhir bulan namun rasio burn tetap dalam batas.',
                ),
                badgeClass:
                    'bg-sky-50 text-sky-700 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300',
            };
        case 'ZONE_3_WARNING':
            return {
                title: __('Zona 3: Peringatan (Burn Cepat)'),
                desc: __(
                    'Laju pemakaian jam lembur terlalu cepat pada minggu awal.',
                ),
                badgeClass:
                    'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300',
            };
        case 'ZONE_4_POOR':
        default:
            return {
                title: __('Zona 4: Defisit (Melebihi Kuota)'),
                desc: __(
                    'Jam lembur telah melebihi target anggaran bulanan seksi.',
                ),
                badgeClass:
                    'bg-red-50 text-[#cc0000] border-red-300 dark:bg-red-950/60 dark:text-red-300',
            };
    }
});

const chartData = computed(() => {
    return {
        datasets: [
            {
                label: props.sectionName || __('Posisi Seksi'),
                data: [
                    {
                        x: props.scatterData.current_burn_pct,
                        y: props.scatterData.cumulative_actual_hours,
                    },
                ],
                backgroundColor: currentPointColor.value,
                borderColor: '#ffffff',
                borderWidth: 2,
                pointRadius: 9,
                pointHoverRadius: 12,
            },
        ],
    };
});

// Custom 4-Quadrant Shading Plugin
const quadrantPlugin: Plugin = {
    id: 'budgetMatrixQuadrantPlugin',
    beforeDraw(chart) {
        const {
            ctx,
            chartArea,
            scales: { x, y },
        } = chart;

        if (!chartArea || !x || !y) {
            return;
        }

        const { top, bottom, left, right } = chartArea;
        const xSplit = Math.max(left, Math.min(right, x.getPixelForValue(100)));
        const ySplit = Math.max(
            top,
            Math.min(
                bottom,
                y.getPixelForValue(props.scatterData.threshold_hours_75_pct),
            ),
        );

        ctx.save();

        // Top-Left: Zone 2 (Good / Controlled high hours)
        ctx.fillStyle = 'rgba(2, 132, 199, 0.07)';
        ctx.fillRect(left, top, xSplit - left, ySplit - top);

        // Top-Right: Zone 4 (Poor / High burn & Over hours)
        ctx.fillStyle = 'rgba(204, 0, 0, 0.08)';
        ctx.fillRect(xSplit, top, right - xSplit, ySplit - top);

        // Bottom-Left: Zone 1 (Excellent / Low burn & Low hours)
        ctx.fillStyle = 'rgba(5, 150, 105, 0.07)';
        ctx.fillRect(left, ySplit, xSplit - left, bottom - ySplit);

        // Bottom-Right: Zone 3 (Warning / High burn early)
        ctx.fillStyle = 'rgba(217, 119, 6, 0.07)';
        ctx.fillRect(xSplit, ySplit, right - xSplit, bottom - ySplit);

        // Dividing Crosshairs
        ctx.strokeStyle = 'rgba(148, 163, 184, 0.5)';
        ctx.lineWidth = 1;
        ctx.setLineDash([4, 4]);

        // Vertical dividing line at 100% burn
        ctx.beginPath();
        ctx.moveTo(xSplit, top);
        ctx.lineTo(xSplit, bottom);
        ctx.stroke();

        // Horizontal dividing line at 75% hours
        ctx.beginPath();
        ctx.moveTo(left, ySplit);
        ctx.lineTo(right, ySplit);
        ctx.stroke();

        // Faint Quadrant Watermark Labels
        ctx.font = '10px sans-serif';
        ctx.setLineDash([]);

        // Zone 1
        ctx.fillStyle = 'rgba(5, 150, 105, 0.6)';
        ctx.textAlign = 'left';
        ctx.fillText('Zona 1: Sangat Baik', left + 8, bottom - 8);

        // Zone 2
        ctx.fillStyle = 'rgba(2, 132, 199, 0.6)';
        ctx.textAlign = 'left';
        ctx.fillText('Zona 2: Terkendali', left + 8, top + 14);

        // Zone 3
        ctx.fillStyle = 'rgba(217, 119, 6, 0.6)';
        ctx.textAlign = 'right';
        ctx.fillText('Zona 3: Peringatan', right - 8, bottom - 8);

        // Zone 4
        ctx.fillStyle = 'rgba(204, 0, 0, 0.6)';
        ctx.textAlign = 'right';
        ctx.fillText('Zona 4: Defisit Kritis', right - 8, top + 14);

        ctx.restore();
    },
};

const chartOptions = computed(() => {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                backgroundColor: 'rgba(15, 23, 42, 0.92)',
                titleFont: { size: 12, weight: 'bold' as const },
                bodyFont: { size: 11, family: 'monospace' },
                padding: 10,
                callbacks: {
                    title() {
                        return props.sectionName;
                    },
                    label(context: any) {
                        return [
                            `Indeks Burn: ${context.parsed.x.toFixed(1)}%`,
                            `Realisasi: ${context.parsed.y.toFixed(1)} jam`,
                            `Status: ${currentZoneDetails.value.title}`,
                        ];
                    },
                },
            },
        },
        scales: {
            x: {
                min: 0,
                suggestedMax: Math.max(150, props.scatterData.max_x_scale),
                grid: {
                    color: 'rgba(226, 232, 240, 0.4)',
                },
                ticks: {
                    font: { size: 10, family: 'monospace' },
                    callback(value: any) {
                        return `${value}%`;
                    },
                },
                title: {
                    display: true,
                    text: __('Indeks Burn (%)'),
                    font: { size: 11, weight: 'bold' as const },
                    color: '#64748b',
                },
            },
            y: {
                min: 0,
                suggestedMax: Math.max(50, props.scatterData.max_y_scale),
                grid: {
                    color: 'rgba(226, 232, 240, 0.4)',
                },
                ticks: {
                    font: { size: 10, family: 'monospace' },
                    callback(value: any) {
                        return `${value} j`;
                    },
                },
                title: {
                    display: true,
                    text: __('Realisasi Jam Lembur (Jam)'),
                    font: { size: 11, weight: 'bold' as const },
                    color: '#64748b',
                },
            },
        },
    };
});
</script>

<template>
    <div class="space-y-3" data-test="budget-matrix-scatter-container">
        <!-- Current Position Badge & Zone Description -->
        <div
            class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-slate-50/70 p-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900/50"
        >
            <div class="space-y-0.5">
                <div class="flex items-center gap-2">
                    <span
                        class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                    >
                        {{ __('Posisi Saat Ini') }}:
                    </span>
                    <Badge
                        variant="outline"
                        class="border px-2 py-0.5 text-xs font-semibold"
                        :class="currentZoneDetails.badgeClass"
                        data-test="scatter-current-zone-badge"
                    >
                        {{ currentZoneDetails.title }}
                    </Badge>
                </div>
                <p class="text-muted-foreground text-[11px]">
                    {{ currentZoneDetails.desc }}
                </p>
            </div>

            <!-- Exact Coordinates -->
            <div
                class="flex items-center gap-3 font-mono text-xs text-slate-600 tabular-nums dark:text-slate-300"
            >
                <div>
                    <span class="text-muted-foreground font-sans text-[10px]"
                        >Burn:</span
                    >
                    <span class="ml-1 font-bold"
                        >{{ scatterData.current_burn_pct.toFixed(1) }}%</span
                    >
                </div>
                <span>•</span>
                <div>
                    <span class="text-muted-foreground font-sans text-[10px]"
                        >{{ __('Realisasi') }}:</span
                    >
                    <span class="ml-1 font-bold"
                        >{{ scatterData.cumulative_actual_hours.toFixed(1) }}
                        {{ __('jam') }}</span
                    >
                </div>
            </div>
        </div>

        <!-- Canvas Container -->
        <div class="h-60 w-full sm:h-64">
            <Scatter
                :data="chartData"
                :options="chartOptions"
                :plugins="[quadrantPlugin]"
            />
        </div>
    </div>
</template>
