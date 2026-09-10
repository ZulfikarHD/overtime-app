<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Gauge,
    Info,
    ShieldAlert,
    TrendingUp,
} from '@lucide/vue';
import { computed } from 'vue';
import BaseLineChart from '@/components/charts/BaseLineChart.vue';
import ChartSkeleton from '@/components/charts/ChartSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

export interface OptimalZoneData {
    labels?: string[];
    hours_series?: number[];
    efficiency_series?: number[];
    current_weekly_avg?: number;
    zones?: {
        under_utilized?: {
            min: number;
            max: number;
            color: string;
            label: string;
        };
        sweet_spot?: { min: number; max: number; color: string; label: string };
        over_threshold?: {
            min: number;
            max: number;
            color: string;
            label: string;
        };
    };
}

interface Props {
    data?: OptimalZoneData;
    warningThreshold?: number;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: () => ({
        labels: [
            '0h',
            '4h',
            '8h',
            '12h',
            '14h',
            '15.2h',
            '16h',
            '18h',
            '20h',
            '24h',
            '28h',
            '32h',
        ],
        hours_series: [0, 4, 8, 12, 14, 15.2, 16, 18, 20, 24, 28, 32],
        efficiency_series: [
            60, 70.5, 81.1, 91.6, 96.8, 100, 98.3, 94, 82, 69.7, 57.3, 45,
        ],
        current_weekly_avg: 14.8,
        zones: {
            under_utilized: {
                min: 0,
                max: 12,
                color: '#10b981',
                label: 'Di Bawah Kapasitas (< 12 jam)',
            },
            sweet_spot: {
                min: 12,
                max: 18,
                color: '#0284c7',
                label: 'Zona Wajar (12–18 jam)',
            },
            over_threshold: {
                min: 20,
                max: 32,
                color: '#cc0000',
                label: 'Ambang Kelelahan (> 20 jam)',
            },
        },
    }),
    warningThreshold: 20.0,
    loading: false,
});

const { __ } = useTrans();

const currentAvg = computed(() => props.data?.current_weekly_avg ?? 0);

const currentStatusBadge = computed(() => {
    const avg = currentAvg.value;
    if (avg < 12) {
        return {
            label: __('Di Bawah Kapasitas (< 12 jam)'),
            class: 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300',
            icon: CheckCircle2,
            zone: 'under_utilized',
        };
    }
    if (avg <= (props.warningThreshold ?? 20)) {
        return {
            label: __('Zona Wajar / Sweet Spot (12–18 jam)'),
            class: 'bg-sky-100 text-sky-800 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300',
            icon: Activity,
            zone: 'sweet_spot',
        };
    }
    return {
        label: __('Ambang Batas Kelelahan (> :hrs jam)', {
            hrs: props.warningThreshold ?? 20,
        }),
        class: 'bg-red-100 text-red-800 border-red-300 dark:bg-red-950/60 dark:text-red-300',
        icon: ShieldAlert,
        zone: 'over_threshold',
    };
});

const chartDatasets = computed<ChartDataset<'line'>[]>(() => {
    const eff = props.data?.efficiency_series ?? [
        60, 75, 88, 96, 99, 100, 98, 94, 82, 65, 50, 40,
    ];

    const efficiencyDataset: ChartDataset<'line'> = {
        label: __('Kurva Efisiensi Output (%)'),
        data: eff,
        borderColor: '#0284c7',
        backgroundColor: 'rgba(2, 132, 199, 0.12)',
        fill: true,
        tension: 0.35,
        borderWidth: 2.5,
        pointRadius: 4,
        pointHoverRadius: 6,
        pointBackgroundColor: (ctx) => {
            const idx = ctx.dataIndex;
            const hours = props.data?.hours_series?.[idx] ?? 0;
            if (hours >= 12 && hours <= 18) {
                return '#0284c7'; // Sweet spot blue
            }
            if (hours > (props.warningThreshold ?? 20)) {
                return '#cc0000'; // Critical red
            }
            return '#10b981'; // Under capacity green
        },
    };

    return [efficiencyDataset];
});

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        x: {
            title: {
                display: true,
                text: __('Alokasi Jam Lembur per Karyawan (Jam/Minggu)'),
                color: '#64748b',
                font: { size: 11, weight: 'bold' },
            },
            grid: {
                color: 'rgba(226, 232, 240, 0.5)',
            },
            ticks: {
                font: { family: 'monospace' },
            },
        },
        y: {
            title: {
                display: true,
                text: __('Indeks Produktivitas Relatif (%)'),
                color: '#64748b',
                font: { size: 11, weight: 'bold' },
            },
            grid: {
                color: 'rgba(226, 232, 240, 0.5)',
            },
            ticks: {
                font: { family: 'monospace' },
            },
            min: 30,
            max: 110,
        },
    },
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            callbacks: {
                label(context) {
                    const idx = context.dataIndex;
                    const hours = props.data?.hours_series?.[idx] ?? 0;
                    const val = context.parsed.y;
                    let zoneName = __('Di Bawah Kapasitas');
                    if (hours >= 12 && hours <= 18) {
                        zoneName = __('Zona Wajar (Sweet Spot)');
                    } else if (hours > (props.warningThreshold ?? 20)) {
                        zoneName = __('Risiko Kelelahan (Over-Threshold)');
                    }
                    return [
                        `${__('Efisiensi')}: ${val}%`,
                        `${__('Beban')}: ${hours} jam/minggu`,
                        `${__('Status')}: ${zoneName}`,
                    ];
                },
            },
        },
    },
}));
</script>

<template>
    <Card
        class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        data-test="optimal-level-zone-card"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <Gauge class="size-4 text-[#cc0000]" />
                        <span>{{
                            __('Tingkat Lembur Optimal (Sweet Spot Analysis)')
                        }}</span>
                    </CardTitle>
                    <CardDescription class="mt-1 text-xs">
                        {{
                            __(
                                'Frontier produktivitas tenaga kerja lini perakitan: rentang 12–18 jam/minggu menghasilkan output maksimal tanpa memicu defisit kualitas.',
                            )
                        }}
                    </CardDescription>
                </div>

                <!-- Current Section/Dept Average Marker Badge -->
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold shadow-2xs"
                        :class="currentStatusBadge.class"
                        data-test="current-avg-marker-badge"
                    >
                        <component
                            :is="currentStatusBadge.icon"
                            class="size-3.5 shrink-0"
                        />
                        <span>
                            {{ __('Rata-rata Saat Ini') }}:
                            <strong class="font-mono tabular-nums"
                                >{{ currentAvg }} jam/minggu</strong
                            >
                        </span>
                    </span>
                </div>
            </div>
        </CardHeader>

        <CardContent>
            <!-- Loading Skeleton -->
            <ChartSkeleton v-if="loading" height-class="h-64" variant="chart" />

            <!-- Area Chart Canvas -->
            <div v-else class="space-y-4">
                <div class="h-64 w-full">
                    <BaseLineChart
                        :labels="data?.labels"
                        :datasets="chartDatasets"
                        :options="chartOptions"
                        height-class="h-64"
                    />
                </div>

                <!-- 3 Operational Zone Indicators (Ergonomic Pill Cards) -->
                <div
                    class="grid gap-2.5 border-t border-slate-100 pt-3 text-xs sm:grid-cols-3 dark:border-slate-800"
                    data-test="optimal-zone-pills"
                >
                    <!-- Zone 1: Under-utilized -->
                    <div
                        class="flex items-start gap-2.5 rounded-lg border border-emerald-200 bg-emerald-50/50 p-2.5 dark:border-emerald-950/80 dark:bg-emerald-950/20"
                    >
                        <div
                            class="mt-0.5 size-2 shrink-0 rounded-full bg-emerald-500"
                        ></div>
                        <div>
                            <div
                                class="font-bold text-emerald-900 dark:text-emerald-300"
                            >
                                {{ __('Di Bawah Kapasitas (< 12 jam)') }}
                            </div>
                            <p
                                class="mt-0.5 text-[11px] text-emerald-700 dark:text-emerald-400"
                            >
                                {{
                                    __(
                                        'Kapasitas shift belum terutilisasi maksimal; potensi output per jam masih bisa ditingkatkan.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- Zone 2: Sweet Spot -->
                    <div
                        class="flex items-start gap-2.5 rounded-lg border border-sky-300 bg-sky-50/70 p-2.5 dark:border-sky-950/80 dark:bg-sky-950/25"
                    >
                        <div
                            class="mt-0.5 size-2 shrink-0 rounded-full bg-sky-500"
                        ></div>
                        <div>
                            <div
                                class="flex items-center gap-1 font-bold text-sky-900 dark:text-sky-300"
                            >
                                <span>{{ __('Zona Wajar (12–18 jam)') }}</span>
                                <span
                                    class="py-0.2 rounded bg-sky-200/80 px-1 text-[9px] font-bold text-sky-800 dark:bg-sky-900 dark:text-sky-200"
                                >
                                    {{ __('Sweet Spot') }}
                                </span>
                            </div>
                            <p
                                class="mt-0.5 text-[11px] text-sky-700 dark:text-sky-400"
                            >
                                {{
                                    __(
                                        'Titik efisiensi puncak (15.2 jam); rasio biaya per unit terendah tanpa degradasi fokus teknisi.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- Zone 3: Over Threshold -->
                    <div
                        class="flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50/50 p-2.5 dark:border-red-950/80 dark:bg-red-950/20"
                    >
                        <div
                            class="mt-0.5 size-2 shrink-0 rounded-full bg-[#cc0000]"
                        ></div>
                        <div>
                            <div
                                class="font-bold text-red-900 dark:text-red-300"
                            >
                                {{
                                    __('Ambang Kelelahan (> :hrs jam)', {
                                        hrs: warningThreshold,
                                    })
                                }}
                            </div>
                            <p
                                class="mt-0.5 text-[11px] text-red-700 dark:text-red-400"
                            >
                                {{
                                    __(
                                        'Penurunan produktivitas marjinal; peningkatan risiko defect lini dan insiden keselamatan kerja.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
