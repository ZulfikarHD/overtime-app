<script setup lang="ts">
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

export interface RollingWeekItem {
    week_key: string;
    week_label: string;
    start_date: string;
    end_date: string;
    hours: number;
    is_over_limit: boolean;
    is_current_week: boolean;
}

const props = defineProps<{
    rollingWeeks: RollingWeekItem[];
    weeklyLimit: number;
    consecutiveWeeks: number;
    consecutiveWeeksAlert: number;
}>();

const { __ } = useTrans();

const chartLabels = computed(() => {
    return props.rollingWeeks.map((w) => {
        return `${w.week_label} (${w.start_date})`;
    });
});

const chartData = computed(() => {
    const backgroundColors = props.rollingWeeks.map((w) => {
        if (w.is_over_limit) {
            return props.consecutiveWeeks >= props.consecutiveWeeksAlert
                ? '#cc0000'
                : '#d97706';
        }
        return '#059669';
    });

    const hoverColors = props.rollingWeeks.map((w) => {
        if (w.is_over_limit) {
            return props.consecutiveWeeks >= props.consecutiveWeeksAlert
                ? '#b30000'
                : '#b45309';
        }
        return '#047857';
    });

    return {
        labels: chartLabels.value,
        datasets: [
            {
                label: __('Jam Lembur Disetujui (jam)'),
                data: props.rollingWeeks.map((w) => w.hours),
                backgroundColor: backgroundColors,
                hoverBackgroundColor: hoverColors,
                borderRadius: 6,
                borderSkipped: false,
                barPercentage: 0.55,
                categoryPercentage: 0.75,
            },
        ],
    };
});

const maxDataValue = computed(() => {
    const maxVal = Math.max(
        props.weeklyLimit,
        ...props.rollingWeeks.map((w) => w.hours),
        0,
    );
    return Math.ceil(maxVal * 1.25) || 25;
});

const chartOptions = computed(() => {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                backgroundColor: '#0f172a',
                titleColor: '#f8fafc',
                bodyColor: '#f8fafc',
                padding: 10,
                cornerRadius: 8,
                callbacks: {
                    title: (items: any[]) => {
                        if (!items.length) return '';
                        const index = items[0].dataIndex;
                        const w = props.rollingWeeks[index];
                        if (!w) return items[0].label;
                        return `${w.week_label}: ${w.start_date} – ${w.end_date}`;
                    },
                    label: (context: any) => {
                        const index = context.dataIndex;
                        const w = props.rollingWeeks[index];
                        if (!w) return `${context.parsed.y} jam`;
                        const diff = w.hours - props.weeklyLimit;
                        const diffText =
                            diff > 0
                                ? ` (+${diff.toFixed(1)} jam di atas batas)`
                                : ` (${Math.abs(diff).toFixed(1)} jam di bawah batas)`;
                        return [
                            `${__('Jam Lembur')}: ${w.hours.toFixed(1)} ${__('jam')}`,
                            `${__('Batas Mingguan')}: ${props.weeklyLimit.toFixed(1)} ${__('jam')}${diffText}`,
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
                        size: 11,
                        family: 'Instrument Sans, sans-serif',
                    },
                    color: '#64748b',
                },
            },
            y: {
                beginAtZero: true,
                suggestedMax: maxDataValue.value,
                grid: {
                    color: 'rgba(148, 163, 184, 0.15)',
                },
                ticks: {
                    font: {
                        size: 11,
                        family: 'monospace',
                    },
                    color: '#64748b',
                    callback: (value: any) => `${value}h`,
                },
            },
        },
    };
});
</script>

<template>
    <Card
        class="border-border shadow-xs"
        data-test="fatigue-rolling-chart-card"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-semibold"
                    >
                        <span>{{
                            __('Tren Beban Kerja Rolling 4 Minggu')
                        }}</span>
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{
                            __(
                                'Histori jam lembur per siklus mingguan (Senin - Minggu) dibandingkan batas rekomendasi keselamatan.',
                            )
                        }}
                    </CardDescription>
                </div>

                <Badge
                    variant="outline"
                    class="border-border bg-muted/30 self-start font-mono text-xs tabular-nums sm:self-auto"
                >
                    {{ __('Batas Mingguan:') }}
                    {{ weeklyLimit.toFixed(1) }} jam
                </Badge>
            </div>
        </CardHeader>

        <CardContent class="flex flex-col gap-4">
            <!-- Legend Indicators -->
            <div
                class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2 text-xs dark:border-slate-800"
            >
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-full bg-[#059669]"></span>
                        <span
                            class="font-medium text-slate-700 dark:text-slate-300"
                        >
                            {{ __('Dalam Batas Aman') }} (&le;{{
                                weeklyLimit.toFixed(0)
                            }}h)
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-full bg-[#d97706]"></span>
                        <span
                            class="font-medium text-slate-700 dark:text-slate-300"
                        >
                            {{ __('Mendekati / Melebihi Batas') }} (&gt;{{
                                weeklyLimit.toFixed(0)
                            }}h)
                        </span>
                    </div>
                </div>

                <div
                    class="text-muted-foreground font-mono text-[11px] tabular-nums"
                >
                    {{ __('Siklus 4 Minggu Terakhir') }}
                </div>
            </div>

            <!-- Bar Chart Container -->
            <div class="relative h-56 w-full">
                <Bar
                    v-if="rollingWeeks.length > 0"
                    :data="chartData"
                    :options="chartOptions"
                />
                <div
                    v-else
                    class="flex h-full items-center justify-center text-sm text-slate-400"
                >
                    {{ __('Belum ada riwayat jam lembur 4 minggu terakhir.') }}
                </div>
            </div>

            <!-- 4 Weeks Mini Stats Row -->
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div
                    v-for="w in rollingWeeks"
                    :key="w.week_key"
                    class="border-border/60 bg-muted/20 flex flex-col justify-between rounded-lg border p-2.5 transition-all"
                    :class="[
                        w.is_current_week
                            ? 'border-primary/50 bg-primary/5'
                            : '',
                    ]"
                >
                    <div class="flex items-center justify-between gap-1">
                        <span
                            class="truncate text-xs font-semibold"
                            :class="[
                                w.is_current_week
                                    ? 'text-primary'
                                    : 'text-foreground',
                            ]"
                        >
                            {{ w.week_label }}
                        </span>
                        <span
                            v-if="w.is_current_week"
                            class="bg-primary/10 text-primary rounded-xs px-1 text-[9px] font-bold uppercase"
                        >
                            {{ __('Aktif') }}
                        </span>
                    </div>

                    <div
                        class="text-muted-foreground mt-0.5 text-[10px] tabular-nums"
                    >
                        {{ w.start_date }} – {{ w.end_date }}
                    </div>

                    <div class="mt-2 flex items-baseline justify-between">
                        <span
                            class="font-mono text-base font-bold tabular-nums"
                            :class="[
                                w.is_over_limit
                                    ? 'text-amber-600 dark:text-amber-400'
                                    : 'text-emerald-700 dark:text-emerald-400',
                            ]"
                        >
                            {{ w.hours.toFixed(1) }}
                            <span class="text-xs font-normal">jam</span>
                        </span>

                        <span
                            class="font-mono text-[10px] font-medium"
                            :class="[
                                w.is_over_limit
                                    ? 'text-amber-600 dark:text-amber-400'
                                    : 'text-muted-foreground',
                            ]"
                        >
                            {{
                                w.is_over_limit
                                    ? `+${(w.hours - weeklyLimit).toFixed(1)}h`
                                    : `${(weeklyLimit - w.hours).toFixed(1)}h sisa`
                            }}
                        </span>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
