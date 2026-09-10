<script setup lang="ts">
import type { ChartData, ChartDataset, ChartOptions } from 'chart.js';
import {
    Activity,
    AlertCircle,
    CheckCircle2,
    Filter,
    HelpCircle,
    Info,
    ScatterChart,
    TrendingUp,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import BaseScatterChart from '@/components/charts/BaseScatterChart.vue';
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

export interface ScatterPoint {
    section_id: number;
    section_code: string;
    section_name: string;
    month: string;
    year_month: string;
    x: number;
    y: number;
}

export interface SectionItem {
    id: number | string;
    code: string;
    name: string;
}

export interface OvertimeVsProductionData {
    erp_connected: boolean;
    message?: string | null;
    correlation_r?: number | null;
    r_squared?: number | null;
    regression?: {
        slope: number;
        intercept: number;
        formula: string;
    };
    trend_line?: Array<{ x: number; y: number }>;
    scatter_points?: ScatterPoint[];
    sections?: SectionItem[];
}

interface Props {
    data?: OvertimeVsProductionData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: () => ({
        erp_connected: false,
        message: 'N/A — Integrasi data produksi ERP belum terhubung',
        correlation_r: null,
        r_squared: null,
        regression: { slope: 0, intercept: 0, formula: 'y = 0' },
        trend_line: [],
        scatter_points: [],
        sections: [],
    }),
    loading: false,
});

const { __ } = useTrans();

const selectedSection = ref<string | number>('all');

const filteredPoints = computed<ScatterPoint[]>(() => {
    const pts = props.data?.scatter_points ?? [];
    if (selectedSection.value === 'all') {
        return pts;
    }
    const secId = Number(selectedSection.value);
    return pts.filter((p) => p.section_id === secId);
});

// Recompute regression & correlation on client side when section is filtered
const calculatedRegression = computed(() => {
    const pts = filteredPoints.value;
    if (pts.length < 2) {
        return {
            r: props.data?.correlation_r ?? null,
            rSquared: props.data?.r_squared ?? null,
            slope: props.data?.regression?.slope ?? 0,
            intercept: props.data?.regression?.intercept ?? 0,
            formula: props.data?.regression?.formula ?? 'y = 0',
            trendLine: props.data?.trend_line ?? [],
        };
    }

    if (selectedSection.value === 'all' && props.data?.regression) {
        return {
            r: props.data.correlation_r ?? null,
            rSquared: props.data.r_squared ?? null,
            slope: props.data.regression.slope,
            intercept: props.data.regression.intercept,
            formula: props.data.regression.formula,
            trendLine: props.data.trend_line ?? [],
        };
    }

    const x = pts.map((p) => p.x);
    const y = pts.map((p) => p.y);
    const n = x.length;
    const meanX = x.reduce((a, b) => a + b, 0) / n;
    const meanY = y.reduce((a, b) => a + b, 0) / n;

    let num = 0;
    let denX = 0;
    let denY = 0;

    for (let i = 0; i < n; i++) {
        const dx = x[i] - meanX;
        const dy = y[i] - meanY;
        num += dx * dy;
        denX += dx * dx;
        denY += dy * dy;
    }

    const r = denX > 0 && denY > 0 ? num / Math.sqrt(denX * denY) : 0;
    const slope = denX > 0 ? num / denX : 0;
    const intercept = meanY - slope * meanX;
    const minX = Math.min(...x);
    const maxX = Math.max(...x);

    return {
        r: Math.round(r * 1000) / 1000,
        rSquared: Math.round(r * r * 1000) / 1000,
        slope: Math.round(slope * 1000) / 1000,
        intercept: Math.round(intercept * 10) / 10,
        formula: `y = ${slope >= 0 ? slope.toFixed(3) : '-' + Math.abs(slope).toFixed(3)}x + ${intercept.toFixed(1)}`,
        trendLine: [
            {
                x: minX,
                y: Math.max(
                    0,
                    Math.round((slope * minX + intercept) * 10) / 10,
                ),
            },
            {
                x: maxX,
                y: Math.max(
                    0,
                    Math.round((slope * maxX + intercept) * 10) / 10,
                ),
            },
        ],
    };
});

const correlationBadgeInfo = computed(() => {
    const r = calculatedRegression.value.r;
    if (r === null) {
        return {
            label: __('Data ERP Belum Ada'),
            class: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        };
    }
    const abs = Math.abs(r);
    if (abs >= 0.7) {
        return {
            label: __('Korelasi Kuat (r = :r)', { r: r.toFixed(2) }),
            class: 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300',
        };
    }
    if (abs >= 0.4) {
        return {
            label: __('Korelasi Sedang (r = :r)', { r: r.toFixed(2) }),
            class: 'bg-sky-100 text-sky-800 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300',
        };
    }
    return {
        label: __('Korelasi Lemah (r = :r)', { r: r.toFixed(2) }),
        class: 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300',
    };
});

const chartDatasets = computed<ChartDataset<'scatter'>[]>(() => {
    if (!props.data?.erp_connected || filteredPoints.value.length === 0) {
        return [];
    }

    const scatterDataset: ChartDataset<'scatter'> = {
        type: 'scatter',
        label: __('Data Realisasi Bulanan'),
        data: filteredPoints.value.map((p) => ({ x: p.x, y: p.y })),
        backgroundColor: 'rgba(2, 132, 199, 0.75)',
        borderColor: '#0284c7',
        borderWidth: 1.5,
        pointRadius: 5,
        pointHoverRadius: 7,
        showLine: false,
    };

    const lineDataset: ChartDataset<'scatter'> = {
        type: 'scatter',
        label: __('Garis Regresi Linear (Tren)'),
        data: calculatedRegression.value.trendLine,
        borderColor: '#cc0000',
        backgroundColor: 'transparent',
        borderWidth: 2,
        borderDash: [5, 5],
        pointRadius: 0,
        pointHoverRadius: 0,
        showLine: true,
    };

    return [scatterDataset, lineDataset];
});

const chartOptions = computed<ChartOptions<'scatter'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        x: {
            title: {
                display: true,
                text: __('Volume Output Produksi (Unit Kendaraan)'),
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
                text: __('Total Jam Lembur (Jam)'),
                color: '#64748b',
                font: { size: 11, weight: 'bold' },
            },
            grid: {
                color: 'rgba(226, 232, 240, 0.5)',
            },
            ticks: {
                font: { family: 'monospace' },
            },
            beginAtZero: true,
        },
    },
    plugins: {
        legend: {
            display: true,
            position: 'top',
            labels: {
                usePointStyle: true,
                boxWidth: 8,
                font: { size: 11 },
            },
        },
        tooltip: {
            callbacks: {
                label(context) {
                    if (context.datasetIndex === 1) {
                        return `${__('Regresi Linear')}: ${calculatedRegression.value.formula}`;
                    }
                    const raw = context.raw as { x: number; y: number };
                    const pt = filteredPoints.value[context.dataIndex];
                    if (pt) {
                        return [
                            `${pt.section_name} (${pt.section_code}) - ${pt.month}`,
                            `${__('Volume')}: ${new Intl.NumberFormat('id-ID').format(pt.x)} unit`,
                            `${__('Lembur')}: ${new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1 }).format(pt.y)} jam`,
                        ];
                    }
                    return `${__('Volume')}: ${raw.x} unit | ${__('Lembur')}: ${raw.y} jam`;
                },
            },
        },
    },
}));
</script>

<template>
    <Card
        class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        data-test="overtime-production-scatter-card"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <ScatterChart class="size-4 text-[#cc0000]" />
                        <span>{{
                            __('Lembur vs Volume Produksi (Scatter & Regresi)')
                        }}</span>
                    </CardTitle>
                    <CardDescription class="mt-1 text-xs">
                        {{
                            __(
                                'Sebaran data empiris jam lembur terhadap unit produksi bulanan per seksi dengan garis proyeksi linear.',
                            )
                        }}
                    </CardDescription>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Section Selector Filter -->
                    <div
                        v-if="data?.erp_connected"
                        class="flex items-center gap-1.5"
                    >
                        <Filter class="size-3 text-slate-400" />
                        <select
                            v-model="selectedSection"
                            class="h-8 rounded-md border border-slate-300 bg-white px-2.5 text-xs font-medium text-slate-700 shadow-2xs focus:border-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            data-test="select-scatter-section"
                        >
                            <option
                                v-for="sec in data.sections ?? []"
                                :key="sec.id"
                                :value="sec.id"
                            >
                                {{ sec.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Correlation Badge -->
                    <span
                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-bold"
                        :class="correlationBadgeInfo.class"
                        data-test="scatter-correlation-badge"
                    >
                        <Activity class="size-3" />
                        <span>{{ correlationBadgeInfo.label }}</span>
                    </span>
                </div>
            </div>
        </CardHeader>

        <CardContent>
            <!-- Loading Skeleton -->
            <ChartSkeleton v-if="loading" height-class="h-72" variant="chart" />

            <!-- ERP Disconnected Fallback Banner (Zero Crash Guarantee) -->
            <div
                v-else-if="!data?.erp_connected"
                class="flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/60 p-8 text-center dark:border-slate-800 dark:bg-slate-950/40"
                data-test="erp-disconnected-banner"
            >
                <div
                    class="mb-3 flex size-12 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400"
                >
                    <AlertCircle class="size-6" />
                </div>
                <h4
                    class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                >
                    {{
                        data?.message ||
                        __('N/A — Integrasi data produksi ERP belum terhubung')
                    }}
                </h4>
                <p
                    class="mt-1 max-w-md text-xs text-slate-500 dark:text-slate-400"
                >
                    {{
                        __(
                            'Koneksi interface sistem ERP untuk volume unit produksi belum aktif. Grafik regresi bivariat akan aktif secara otomatis setelah data produksi terhubung.',
                        )
                    }}
                </p>
                <div
                    class="mt-4 flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-600 shadow-2xs dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                >
                    <Info class="size-3.5 shrink-0 text-sky-600" />
                    <span>{{
                        __(
                            'Status ERP: Standby / Menunggu Feed Produksi Harian',
                        )
                    }}</span>
                </div>
            </div>

            <!-- Scatter Plot Canvas with Linear Regression Overlay -->
            <div v-else class="space-y-4">
                <div class="h-72 w-full">
                    <BaseScatterChart
                        :datasets="chartDatasets"
                        :options="chartOptions"
                        height-class="h-72"
                    />
                </div>

                <!-- Footer Statistical Summary -->
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400"
                    data-test="scatter-regression-summary"
                >
                    <div class="flex items-center gap-4">
                        <span class="flex items-center gap-1.5">
                            <span
                                class="font-semibold text-slate-700 dark:text-slate-300"
                                >{{ __('Persamaan Garis') }}:</span
                            >
                            <span class="font-mono font-bold text-[#cc0000]">{{
                                calculatedRegression.formula
                            }}</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span
                                class="font-semibold text-slate-700 dark:text-slate-300"
                                >{{ __('Koefisien Determinasi (R²)') }}:</span
                            >
                            <span
                                class="font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                                >{{
                                    calculatedRegression.rSquared !== null
                                        ? calculatedRegression.rSquared.toFixed(
                                              3,
                                          )
                                        : '-'
                                }}</span
                            >
                        </span>
                    </div>

                    <div class="flex items-center gap-1 text-[11px]">
                        <CheckCircle2
                            class="size-3.5 shrink-0 text-emerald-600"
                        />
                        <span>{{
                            __(
                                'Data sampel: :count titik observasi (Bulan × Seksi)',
                                { count: filteredPoints.length },
                            )
                        }}</span>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
