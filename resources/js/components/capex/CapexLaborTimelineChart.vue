<script setup lang="ts">
import {
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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

export interface TimelineWeekItem {
    week_number: number;
    label: string;
    date_range: string;
    actual_hours: number;
    cumulative_actual_hours: number | null;
    planned_cumulative_hours: number;
    is_current: boolean;
    is_future: boolean;
}

const props = defineProps<{
    timeline: TimelineWeekItem[];
    allocatedHours: number;
    burnIndexPct: number;
}>();

const { __ } = useTrans();

const actualLineColor = computed(() => {
    if (props.burnIndexPct > 115) return '#cc0000'; // ISUZU Red
    if (props.burnIndexPct > 100) return '#d97706'; // Amber Warning
    if (props.burnIndexPct >= 85) return '#0284c7'; // Sky Blue On Track
    return '#059669'; // Emerald Safe
});

const chartLabels = computed(() => {
    return props.timeline.map((w) => `${w.label}\n(${w.date_range})`);
});

const chartData = computed(() => {
    const plannedCumulative = props.timeline.map(
        (w) => w.planned_cumulative_hours,
    );
    const actualCumulative = props.timeline.map(
        (w) => w.cumulative_actual_hours,
    );

    return {
        labels: chartLabels.value,
        datasets: [
            {
                label: __('Target Alokasi Kumulatif'),
                data: plannedCumulative,
                borderColor: '#64748b',
                backgroundColor: 'transparent',
                borderWidth: 2,
                borderDash: [5, 5],
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
        ],
    };
});

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
                            return `${context.dataset.label}: —`;
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
                suggestedMax: Math.max(props.allocatedHours * 1.15, 20),
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
    <Card
        class="border-border bg-card"
        data-test="capex-labor-timeline-chart-card"
    >
        <CardHeader class="border-border border-b pb-3">
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-0.5">
                    <CardTitle class="text-foreground text-sm font-bold">
                        {{ __('Kurva Akumulasi Jam Tenaga Kerja (Burndown)') }}
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{
                            __(
                                'Perbandingan akumulasi jam lembur aktual vs. kurva rencana alokasi linear per minggu.',
                            )
                        }}
                    </CardDescription>
                </div>

                <div
                    class="text-muted-foreground font-mono text-xs font-semibold tabular-nums"
                >
                    {{ __('Plafon:') }}
                    {{
                        allocatedHours.toLocaleString('id-ID', {
                            minimumFractionDigits: 1,
                        })
                    }}
                    {{ __('jam') }}
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-3 p-5">
            <!-- Zone Semantics Header -->
            <div
                class="flex flex-wrap items-center justify-between gap-2 text-[11px]"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300"
                    >
                        <span
                            class="inline-block size-2 rounded-full bg-emerald-500"
                        />
                        <span>{{ __('Terkendali (<85%)') }}</span>
                    </span>
                    <span
                        class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300"
                    >
                        <span
                            class="inline-block size-2 rounded-full bg-sky-500"
                        />
                        <span>{{ __('Mendekati Batas (85–100%)') }}</span>
                    </span>
                    <span
                        class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300"
                    >
                        <span
                            class="inline-block size-2 rounded-full bg-red-600"
                        />
                        <span>{{ __('Overrun (>100%)') }}</span>
                    </span>
                </div>
            </div>

            <!-- Chart Canvas Container -->
            <div class="h-64 w-full sm:h-72">
                <Line :data="chartData" :options="chartOptions" />
            </div>
        </CardContent>
    </Card>
</template>
