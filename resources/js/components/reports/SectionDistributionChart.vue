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
import { useTrans } from '@/composables/useTrans';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

export interface PeerDistributionItem {
    employee_id: number | null;
    npk: string;
    name: string;
    job_position: string | null;
    hours: number;
    variance_hours: number;
    is_current_employee: boolean;
    rank: number;
}

const props = defineProps<{
    distribution: PeerDistributionItem[];
    sectionAverageHours: number;
    isAnonymized: boolean;
}>();

const { __ } = useTrans();

const chartLabels = computed(() => {
    return props.distribution.map((item) => {
        if (item.is_current_employee) {
            return `${item.name} (${__('Anda')})`;
        }
        return item.name;
    });
});

const chartData = computed(() => {
    const backgroundColors = props.distribution.map((item) =>
        item.is_current_employee ? '#cc0000' : '#94a3b8',
    );
    const hoverColors = props.distribution.map((item) =>
        item.is_current_employee ? '#b30000' : '#64748b',
    );

    return {
        labels: chartLabels.value,
        datasets: [
            {
                label: __('Jam Lembur Disetujui (jam)'),
                data: props.distribution.map((item) => item.hours),
                backgroundColor: backgroundColors,
                hoverBackgroundColor: hoverColors,
                borderRadius: 4,
                borderSkipped: false,
                barPercentage: 0.7,
                categoryPercentage: 0.8,
            },
        ],
    };
});

const maxDataValue = computed(() => {
    const maxVal = Math.max(
        props.sectionAverageHours,
        ...props.distribution.map((d) => d.hours),
        0,
    );
    return Math.ceil(maxVal * 1.15) || 10;
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
                        const item = props.distribution[index];
                        if (!item) return items[0].label;
                        return `${item.name} (${item.npk})`;
                    },
                    label: (context: any) => {
                        const index = context.dataIndex;
                        const item = props.distribution[index];
                        if (!item) return `${context.parsed.y} jam`;
                        const sign = item.variance_hours > 0 ? '+' : '';
                        return [
                            `${__('Total')}: ${item.hours.toFixed(1)} ${__('jam')}`,
                            `${__('Deviasi Rata-rata')}: ${sign}${item.variance_hours.toFixed(1)} ${__('jam')}`,
                            `${__('Peringkat')}: #${item.rank} ${__('dari')} ${props.distribution.length}`,
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
                    maxRotation: 45,
                    minRotation: 0,
                    callback: function (val: any, index: number) {
                        const item = props.distribution[index];
                        if (!item) return '';
                        // Truncate long names for chart readability
                        const name = item.is_current_employee
                            ? `★ ${item.name}`
                            : item.name;
                        return name.length > 14
                            ? `${name.substring(0, 12)}…`
                            : name;
                    },
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
    <div
        class="flex flex-col gap-3"
        data-test="section-distribution-chart-wrapper"
    >
        <!-- Chart Header & Legend Bar -->
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-2 dark:border-slate-800"
        >
            <div class="flex items-center gap-4 text-xs">
                <div class="flex items-center gap-1.5">
                    <span
                        class="size-3 rounded-xs bg-[#cc0000] ring-1 ring-[#cc0000]/20"
                    ></span>
                    <span
                        class="font-medium text-slate-700 dark:text-slate-300"
                    >
                        {{ __('Karyawan Terpilih') }}
                    </span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span
                        class="size-3 rounded-xs bg-[#94a3b8] ring-1 ring-slate-400/20"
                    ></span>
                    <span
                        class="font-medium text-slate-700 dark:text-slate-300"
                    >
                        {{ __('Rekan Seksi') }}
                    </span>
                </div>
            </div>

            <div
                class="flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300"
            >
                <span class="text-muted-foreground">{{
                    __('Rata-rata Seksi')
                }}</span>
                <span class="font-bold tabular-nums"
                    >{{ sectionAverageHours.toFixed(1) }} jam</span
                >
            </div>
        </div>

        <!-- Bar Chart Container -->
        <div class="relative h-64 w-full">
            <Bar
                v-if="distribution.length > 0"
                :data="chartData"
                :options="chartOptions"
            />
            <div
                v-else
                class="flex h-full items-center justify-center text-sm text-slate-400"
            >
                {{
                    __('Belum ada data lembur anggota seksi pada periode ini.')
                }}
            </div>
        </div>
    </div>
</template>
