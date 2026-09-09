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

export interface SectionCapexOpexItem {
    section_id: number;
    section_code: string;
    section_name: string;
    capex_hours: number;
    opex_hours: number;
    total_hours: number;
    capex_ratio_pct: number;
}

const props = defineProps<{
    sections: SectionCapexOpexItem[];
}>();

const { __ } = useTrans();

const chartLabels = computed(() => {
    return props.sections.map((s) => s.section_name || s.section_code);
});

const chartData = computed(() => {
    return {
        labels: chartLabels.value,
        datasets: [
            {
                label: __('CapEx Proyek (jam)'),
                data: props.sections.map((s) => s.capex_hours),
                backgroundColor: '#0284c7', // Sky Blue
                hoverBackgroundColor: '#0369a1',
                borderRadius: 4,
                borderSkipped: false,
                barPercentage: 0.7,
                categoryPercentage: 0.6,
            },
            {
                label: __('OpEx Rutin (jam)'),
                data: props.sections.map((s) => s.opex_hours),
                backgroundColor: '#64748b', // Slate
                hoverBackgroundColor: '#475569',
                borderRadius: 4,
                borderSkipped: false,
                barPercentage: 0.7,
                categoryPercentage: 0.6,
            },
        ],
    };
});

const chartOptions = computed(() => {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top' as const,
                align: 'end' as const,
                labels: {
                    boxWidth: 12,
                    boxHeight: 12,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    font: {
                        size: 11,
                        family: 'Instrument Sans, sans-serif',
                    },
                },
            },
            tooltip: {
                backgroundColor: '#0f172a',
                titleColor: '#f8fafc',
                bodyColor: '#f8fafc',
                padding: 10,
                cornerRadius: 8,
                callbacks: {
                    afterBody: function (context: any) {
                        const idx = context[0]?.dataIndex;
                        if (idx !== undefined && props.sections[idx]) {
                            const sec = props.sections[idx];
                            return `${__('Rasio CapEx')}: ${sec.capex_ratio_pct.toFixed(1)}%\n${__('Total Jam')}: ${sec.total_hours.toFixed(1)} jam`;
                        }
                        return '';
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
                    },
                    maxRotation: 45,
                    minRotation: 0,
                },
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(226, 232, 240, 0.6)',
                },
                ticks: {
                    font: {
                        size: 10,
                        family: 'monospace',
                    },
                    callback: function (val: any) {
                        return `${val} h`;
                    },
                },
            },
        },
    };
});
</script>

<template>
    <div
        class="h-72 w-full p-2 md:h-80"
        data-test="capex-opex-section-bar-chart"
    >
        <div
            v-if="sections.length === 0"
            class="flex h-full items-center justify-center text-xs text-slate-400"
        >
            {{ __('Tidak ada data perbandingan seksi') }}
        </div>
        <Bar v-else :data="chartData" :options="chartOptions" />
    </div>
</template>
