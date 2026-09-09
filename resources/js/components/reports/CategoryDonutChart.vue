<script setup lang="ts">
import { ArcElement, Chart as ChartJS, Legend, Tooltip } from 'chart.js';
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

ChartJS.register(ArcElement, Tooltip, Legend);

export interface CategoryBreakdownData {
    production: number;
    tpm: number;
    project: number;
    others: number;
    total: number;
    production_pct: number;
    tpm_pct: number;
    project_pct: number;
    others_pct: number;
}

const props = defineProps<{
    breakdown: CategoryBreakdownData;
}>();

const { __ } = useTrans();

const totalHours = computed(() => {
    return Math.max(0, props.breakdown.total);
});

const chartData = computed(() => {
    if (totalHours.value <= 0) {
        return {
            labels: [__('Belum Ada Jam')],
            datasets: [
                {
                    data: [1],
                    backgroundColor: ['#e2e8f0'],
                    borderColor: ['#ffffff'],
                    borderWidth: 2,
                },
            ],
        };
    }

    return {
        labels: [__('Produksi'), __('TPM'), __('CapEx Proyek'), __('Lainnya')],
        datasets: [
            {
                data: [
                    props.breakdown.production,
                    props.breakdown.tpm,
                    props.breakdown.project,
                    props.breakdown.others,
                ],
                backgroundColor: [
                    '#059669', // Emerald - Production
                    '#d97706', // Amber - TPM
                    '#0284c7', // Sky Blue - CapEx Project
                    '#64748b', // Slate - Others
                ],
                hoverBackgroundColor: [
                    '#047857',
                    '#b45309',
                    '#0369a1',
                    '#475569',
                ],
                borderColor: ['#ffffff', '#ffffff', '#ffffff', '#ffffff'],
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
                        let hours = 0;
                        let pct = 0;
                        if (index === 0) {
                            hours = props.breakdown.production;
                            pct = props.breakdown.production_pct;
                        } else if (index === 1) {
                            hours = props.breakdown.tpm;
                            pct = props.breakdown.tpm_pct;
                        } else if (index === 2) {
                            hours = props.breakdown.project;
                            pct = props.breakdown.project_pct;
                        } else {
                            hours = props.breakdown.others;
                            pct = props.breakdown.others_pct;
                        }
                        return `${context.label}: ${hours.toFixed(1)} jam (${pct.toFixed(1)}%)`;
                    },
                },
            },
        },
    };
});
</script>

<template>
    <Card class="border-border shadow-xs" data-test="category-donut-chart-card">
        <CardHeader class="pb-2">
            <CardTitle class="text-sm font-semibold">
                {{ __('Distribusi Kategori Lembur') }}
            </CardTitle>
            <CardDescription class="text-xs">
                {{
                    __(
                        'Proporsi alokasi jam lembur berdasarkan kategori pekerjaan bulan ini.',
                    )
                }}
            </CardDescription>
        </CardHeader>
        <CardContent class="flex flex-col items-center justify-center pt-2">
            <!-- Donut Chart & Center Metric -->
            <div class="relative size-44 md:size-48">
                <Doughnut :data="chartData" :options="chartOptions" />
                <div
                    class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center"
                >
                    <span
                        class="text-muted-foreground font-sans text-[11px] font-medium"
                    >
                        {{ __('Total Bulan Ini') }}
                    </span>
                    <span
                        class="font-mono text-xl font-black tracking-tight text-slate-900 tabular-nums sm:text-2xl dark:text-white"
                    >
                        {{ totalHours.toFixed(1) }}
                    </span>
                    <span class="text-muted-foreground font-sans text-[10px]">
                        {{ __('jam disetujui') }}
                    </span>
                </div>
            </div>

            <!-- Custom Clean Legend -->
            <div class="mt-4 grid w-full grid-cols-2 gap-2 text-xs">
                <!-- Produksi -->
                <div
                    class="border-border/60 bg-muted/20 flex items-center justify-between rounded-md border p-2"
                >
                    <div class="flex items-center gap-1.5 truncate">
                        <span
                            class="size-2.5 shrink-0 rounded-full bg-[#059669]"
                        />
                        <span
                            class="truncate font-medium text-slate-700 dark:text-slate-200"
                            >{{ __('Produksi') }}</span
                        >
                    </div>
                    <span
                        class="font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                    >
                        {{ breakdown.production.toFixed(1) }} h
                    </span>
                </div>

                <!-- TPM -->
                <div
                    class="border-border/60 bg-muted/20 flex items-center justify-between rounded-md border p-2"
                >
                    <div class="flex items-center gap-1.5 truncate">
                        <span
                            class="size-2.5 shrink-0 rounded-full bg-[#d97706]"
                        />
                        <span
                            class="truncate font-medium text-slate-700 dark:text-slate-200"
                            >{{ __('TPM') }}</span
                        >
                    </div>
                    <span
                        class="font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                    >
                        {{ breakdown.tpm.toFixed(1) }} h
                    </span>
                </div>

                <!-- CapEx Proyek -->
                <div
                    class="border-border/60 bg-muted/20 flex items-center justify-between rounded-md border p-2"
                >
                    <div class="flex items-center gap-1.5 truncate">
                        <span
                            class="size-2.5 shrink-0 rounded-full bg-[#0284c7]"
                        />
                        <span
                            class="truncate font-medium text-sky-800 dark:text-sky-300"
                            >{{ __('CapEx') }}</span
                        >
                    </div>
                    <span
                        class="font-mono font-bold text-sky-800 tabular-nums dark:text-sky-300"
                    >
                        {{ breakdown.project.toFixed(1) }} h
                    </span>
                </div>

                <!-- Lainnya -->
                <div
                    class="border-border/60 bg-muted/20 flex items-center justify-between rounded-md border p-2"
                >
                    <div class="flex items-center gap-1.5 truncate">
                        <span
                            class="size-2.5 shrink-0 rounded-full bg-[#64748b]"
                        />
                        <span
                            class="truncate font-medium text-slate-600 dark:text-slate-300"
                            >{{ __('Lainnya') }}</span
                        >
                    </div>
                    <span
                        class="font-mono font-bold text-slate-600 tabular-nums dark:text-slate-300"
                    >
                        {{ breakdown.others.toFixed(1) }} h
                    </span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
