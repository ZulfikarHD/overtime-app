<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import {
    Activity,
    BarChart3,
    Clock,
    DollarSign,
    Gauge,
    Layers,
    ShieldAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import BaseBarChart from '@/components/charts/BaseBarChart.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

export interface ComparisonScenarioItem {
    id?: string;
    name: string;
    hours: number;
    cost: number;
    formatted_cost?: string;
    burn_index: number;
    safety_risk: number;
    type: 'baseline' | 'current' | 'saved';
}

interface Props {
    baseline?: {
        hours: number;
        cost: number;
        burn_index: number;
        safety_risk: number;
    };
    current?: {
        name: string;
        hours: number;
        cost: number;
        burn_index: number;
        safety_risk: number;
    };
    savedScenarios?: Array<{
        id: string;
        name: string;
        projected_hours: number;
        projected_cost: number;
        projected_burn_index: number;
        safety_risk_score: number;
    }>;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    baseline: () => ({
        hours: 450,
        cost: 22500000,
        burn_index: 90.0,
        safety_risk: 12.0,
    }),
    current: () => ({
        name: 'Skenario Aktif',
        hours: 450,
        cost: 22500000,
        burn_index: 90.0,
        safety_risk: 12.0,
    }),
    savedScenarios: () => [],
    loading: false,
});

const { __ } = useTrans();

type MetricKey = 'hours' | 'cost' | 'burn_index' | 'safety_risk';
const activeMetric = ref<MetricKey>('hours');

// Consolidated list of scenarios for comparison (Baseline + Current + up to 3 Saved)
const scenariosList = computed<ComparisonScenarioItem[]>(() => {
    const list: ComparisonScenarioItem[] = [
        {
            id: 'baseline',
            name: __('Baseline (Aktual)'),
            hours: props.baseline.hours,
            cost: props.baseline.cost,
            burn_index: props.baseline.burn_index,
            safety_risk: props.baseline.safety_risk,
            type: 'baseline',
        },
        {
            id: 'current',
            name: props.current.name || __('Skenario Aktif'),
            hours: props.current.hours,
            cost: props.current.cost,
            burn_index: props.current.burn_index,
            safety_risk: props.current.safety_risk,
            type: 'current',
        },
    ];

    // Take up to 3 most recent saved scenarios
    const saved = props.savedScenarios.slice(0, 3);
    for (const s of saved) {
        list.push({
            id: s.id,
            name: s.name,
            hours: s.projected_hours,
            cost: s.projected_cost,
            burn_index: s.projected_burn_index,
            safety_risk: s.safety_risk_score,
            type: 'saved',
        });
    }

    return list;
});

const chartLabels = computed(() => {
    return scenariosList.value.map((s) => s.name);
});

// Color mapping for scenarios
const scenarioColors = [
    '#64748b', // Slate for Baseline
    '#cc0000', // ISUZU Red for Active Scenario
    '#0284c7', // Sky Blue for Saved 1
    '#d97706', // Amber for Saved 2
    '#10b981', // Emerald for Saved 3
];

const datasets = computed<ChartDataset<'bar'>[]>(() => {
    const values = scenariosList.value.map((s) => {
        switch (activeMetric.value) {
            case 'hours':
                return s.hours;
            case 'cost':
                return Math.round((s.cost / 1_000_000) * 10) / 10; // Millions IDR
            case 'burn_index':
                return s.burn_index;
            case 'safety_risk':
                return s.safety_risk;
        }
    });

    const metricLabel =
        activeMetric.value === 'hours'
            ? __('Jam Lembur (Jam)')
            : activeMetric.value === 'cost'
              ? __('Estimasi Biaya (Juta Rp)')
              : activeMetric.value === 'burn_index'
                ? __('Proyeksi Burn Index (%)')
                : __('Skor Risiko K3 (%)');

    const backgroundColors = scenariosList.value.map(
        (_, idx) => scenarioColors[idx % scenarioColors.length],
    );

    return [
        {
            label: metricLabel,
            data: values,
            backgroundColor: backgroundColors,
            borderColor: backgroundColors,
            borderWidth: 1,
            borderRadius: 6,
        },
    ];
});

const chartOptions = computed<ChartOptions<'bar'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const val = ctx.raw as number;
                    if (activeMetric.value === 'hours') {
                        return `${val.toLocaleString('id-ID')} Jam`;
                    }
                    if (activeMetric.value === 'cost') {
                        return `Rp ${val.toLocaleString('id-ID', { minimumFractionDigits: 1 })} Juta`;
                    }
                    return `${val.toFixed(1)}%`;
                },
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: {
                color: 'rgba(148, 163, 184, 0.1)',
            },
            ticks: {
                callback: (val) => {
                    if (activeMetric.value === 'cost') {
                        return `${val} Jt`;
                    }
                    if (
                        activeMetric.value === 'burn_index' ||
                        activeMetric.value === 'safety_risk'
                    ) {
                        return `${val}%`;
                    }
                    return val;
                },
            },
        },
        x: {
            grid: {
                display: false,
            },
            ticks: {
                font: {
                    weight: 600,
                },
            },
        },
    },
}));

function formatRupiah(val: number): string {
    return 'Rp ' + Math.round(val).toLocaleString('id-ID');
}
</script>

<template>
    <div class="space-y-4" data-test="scenario-comparison-chart">
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader class="pb-3">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                        >
                            <BarChart3
                                class="size-4 text-slate-700 dark:text-slate-300"
                            />
                            <span>{{
                                __(
                                    'Komparasi Skenario: Baseline vs Skenario Aktif vs Tersimpan',
                                )
                            }}</span>
                        </CardTitle>
                        <CardDescription class="text-xs text-slate-500">
                            {{
                                __(
                                    'Bandingkan parameter kunci antara data aktual berjalan, konfigurasi simulasi saat ini, dan skenario tersimpan.',
                                )
                            }}
                        </CardDescription>
                    </div>

                    <!-- Metric Switcher Pills -->
                    <div
                        class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 dark:border-slate-800 dark:bg-slate-800"
                        data-test="metric-switcher-buttons"
                    >
                        <button
                            type="button"
                            class="cursor-pointer rounded-md px-2.5 py-1 text-xs font-semibold transition-all"
                            :class="
                                activeMetric === 'hours'
                                    ? 'bg-white text-slate-900 shadow-2xs dark:bg-slate-900 dark:text-white'
                                    : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                            "
                            @click="activeMetric = 'hours'"
                        >
                            {{ __('Jam Lembur') }}
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-md px-2.5 py-1 text-xs font-semibold transition-all"
                            :class="
                                activeMetric === 'cost'
                                    ? 'bg-white text-slate-900 shadow-2xs dark:bg-slate-900 dark:text-white'
                                    : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                            "
                            @click="activeMetric = 'cost'"
                        >
                            {{ __('Biaya (Jt)') }}
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-md px-2.5 py-1 text-xs font-semibold transition-all"
                            :class="
                                activeMetric === 'burn_index'
                                    ? 'bg-white text-slate-900 shadow-2xs dark:bg-slate-900 dark:text-white'
                                    : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                            "
                            @click="activeMetric = 'burn_index'"
                        >
                            {{ __('Burn Index') }}
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-md px-2.5 py-1 text-xs font-semibold transition-all"
                            :class="
                                activeMetric === 'safety_risk'
                                    ? 'bg-white text-slate-900 shadow-2xs dark:bg-slate-900 dark:text-white'
                                    : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                            "
                            @click="activeMetric = 'safety_risk'"
                        >
                            {{ __('Risiko K3') }}
                        </button>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="space-y-5">
                <!-- Bar Chart Canvas -->
                <div class="h-64 w-full">
                    <BaseBarChart
                        :labels="chartLabels"
                        :datasets="datasets"
                        :options="chartOptions"
                        :loading="loading"
                        height-class="h-64"
                    />
                </div>

                <!-- Comprehensive Comparison Matrix Table -->
                <div class="space-y-2">
                    <div
                        class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                    >
                        {{ __('Matriks Komparasi Parameter Skenario') }}
                    </div>
                    <div
                        class="overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                    >
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="dark:bg-slate-850 border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold tracking-wider text-slate-500 uppercase dark:border-slate-800"
                                >
                                    <th class="p-2.5">
                                        {{ __('Nama Skenario') }}
                                    </th>
                                    <th class="p-2.5 text-right">
                                        {{ __('Jam Lembur') }}
                                    </th>
                                    <th class="p-2.5 text-right">
                                        {{ __('Estimasi Biaya') }}
                                    </th>
                                    <th class="p-2.5 text-right">
                                        {{ __('Burn Index') }}
                                    </th>
                                    <th class="p-2.5 text-right">
                                        {{ __('Skor Risiko K3') }}
                                    </th>
                                    <th class="p-2.5">
                                        {{ __('Status Indeks') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr
                                    v-for="(item, idx) in scenariosList"
                                    :key="item.id || idx"
                                    class="transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-800/40"
                                >
                                    <td class="p-2.5">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="size-2.5 shrink-0 rounded-full"
                                                :style="{
                                                    backgroundColor:
                                                        scenarioColors[
                                                            idx %
                                                                scenarioColors.length
                                                        ],
                                                }"
                                            ></span>
                                            <span
                                                class="font-semibold text-slate-900 dark:text-white"
                                                :class="{
                                                    'text-[#cc0000] dark:text-red-400':
                                                        item.type === 'current',
                                                }"
                                            >
                                                {{ item.name }}
                                            </span>
                                            <Badge
                                                v-if="item.type === 'baseline'"
                                                variant="outline"
                                                class="px-1.5 py-0 text-[10px]"
                                            >
                                                {{ __('Baseline') }}
                                            </Badge>
                                            <Badge
                                                v-else-if="
                                                    item.type === 'current'
                                                "
                                                class="border-none bg-red-100 px-1.5 py-0 text-[10px] text-[#cc0000] hover:bg-red-200 dark:bg-red-950/60 dark:text-red-300"
                                            >
                                                {{ __('Aktif') }}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td
                                        class="p-2.5 text-right font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                                    >
                                        {{ item.hours.toFixed(1) }}
                                        {{ __('Jam') }}
                                    </td>
                                    <td
                                        class="p-2.5 text-right font-mono font-semibold text-slate-800 tabular-nums dark:text-slate-200"
                                    >
                                        {{ formatRupiah(item.cost) }}
                                    </td>
                                    <td
                                        class="p-2.5 text-right font-mono font-bold tabular-nums"
                                        :class="
                                            item.burn_index > 115
                                                ? 'text-[#cc0000]'
                                                : item.burn_index > 100
                                                  ? 'text-amber-600'
                                                  : 'text-emerald-600'
                                        "
                                    >
                                        {{ item.burn_index.toFixed(1) }}%
                                    </td>
                                    <td
                                        class="p-2.5 text-right font-mono font-semibold tabular-nums"
                                        :class="
                                            item.safety_risk > 30
                                                ? 'text-[#cc0000]'
                                                : item.safety_risk >= 15
                                                  ? 'text-amber-600'
                                                  : 'text-emerald-600'
                                        "
                                    >
                                        {{ item.safety_risk.toFixed(1) }}%
                                    </td>
                                    <td class="p-2.5">
                                        <span
                                            v-if="item.burn_index > 115"
                                            class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-[#cc0000] dark:bg-red-950/60 dark:text-red-400"
                                        >
                                            {{ __('Defisit Kritis') }}
                                        </span>
                                        <span
                                            v-else-if="item.burn_index > 100"
                                            class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300"
                                        >
                                            {{ __('Peringatan') }}
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                                        >
                                            {{ __('Aman') }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
