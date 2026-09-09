<script setup lang="ts">
import { ArcElement, Chart as ChartJS, Legend, Tooltip } from 'chart.js';
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps<{
    capexHours: number;
    opexHours: number;
    capexCostIdr?: number;
    opexCostIdr?: number;
    capexRatioPct: number;
    opexRatioPct: number;
}>();

const { __ } = useTrans();

const totalHours = computed(() => {
    return Math.max(0, props.capexHours + props.opexHours);
});

const chartData = computed(() => {
    if (totalHours.value <= 0) {
        return {
            labels: [__('Belum Ada Jam')],
            datasets: [
                {
                    data: [1],
                    backgroundColor: ['#cbd5e1'],
                    borderColor: ['#ffffff'],
                    borderWidth: 2,
                },
            ],
        };
    }

    return {
        labels: [__('CapEx (Proyek/Aset)'), __('OpEx (Lembur Rutin)')],
        datasets: [
            {
                data: [props.capexHours, props.opexHours],
                backgroundColor: ['#0284c7', '#64748b'],
                hoverBackgroundColor: ['#0369a1', '#475569'],
                borderColor: ['#ffffff', '#ffffff'],
                borderWidth: 2,
            },
        ],
    };
});

const chartOptions = computed(() => {
    return {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                enabled: totalHours.value > 0,
                backgroundColor: '#0f172a',
                titleColor: '#f8fafc',
                bodyColor: '#f8fafc',
                padding: 10,
                cornerRadius: 8,
                callbacks: {
                    label: function (context: any) {
                        const index = context.dataIndex;
                        const isCapex = index === 0;
                        const hours = isCapex
                            ? props.capexHours
                            : props.opexHours;
                        const ratio = isCapex
                            ? props.capexRatioPct
                            : props.opexRatioPct;
                        const cost = isCapex
                            ? props.capexCostIdr
                            : props.opexCostIdr;

                        let line = `${context.label}: ${hours.toFixed(1)} jam (${ratio.toFixed(1)}%)`;
                        if (cost !== undefined && cost > 0) {
                            line += ` • ${formatRupiah(cost, { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
                        }
                        return line;
                    },
                },
            },
        },
    };
});
</script>

<template>
    <div
        class="flex flex-col items-center justify-center p-2"
        data-test="capex-opex-donut-chart"
    >
        <div class="relative size-48 md:size-52">
            <Doughnut :data="chartData" :options="chartOptions" />
            <!-- Centered KPI Metric -->
            <div
                class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center"
            >
                <span
                    class="text-muted-foreground font-sans text-[11px] font-medium"
                >
                    {{ __('Rasio CapEx') }}
                </span>
                <span
                    class="font-mono text-2xl font-black tracking-tight text-sky-700 tabular-nums dark:text-sky-400"
                >
                    {{ totalHours > 0 ? `${capexRatioPct.toFixed(1)}%` : '0%' }}
                </span>
                <span
                    class="text-muted-foreground font-mono text-[10px] tabular-nums"
                >
                    {{ totalHours.toFixed(1) }} {{ __('jam total') }}
                </span>
            </div>
        </div>

        <!-- Custom Accessible Legend -->
        <div
            class="mt-4 flex flex-wrap items-center justify-center gap-4 text-xs"
        >
            <div
                class="flex items-center gap-1.5 text-sky-700 dark:text-sky-300"
            >
                <span class="size-2.5 rounded-full bg-[#0284c7]" />
                <span class="font-medium">{{ __('CapEx') }}:</span>
                <span class="font-mono font-bold tabular-nums">
                    {{ capexHours.toFixed(1) }} {{ __('jam') }} ({{
                        capexRatioPct.toFixed(1)
                    }}%)
                </span>
            </div>
            <div
                class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300"
            >
                <span class="size-2.5 rounded-full bg-[#64748b]" />
                <span class="font-medium">{{ __('OpEx') }}:</span>
                <span class="font-mono font-bold tabular-nums">
                    {{ opexHours.toFixed(1) }} {{ __('jam') }} ({{
                        opexRatioPct.toFixed(1)
                    }}%)
                </span>
            </div>
        </div>
    </div>
</template>
