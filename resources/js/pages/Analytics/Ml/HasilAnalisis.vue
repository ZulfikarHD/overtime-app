<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import { Link } from '@inertiajs/vue3';
import { BarChart3, CheckCircle2, XCircle, AlertCircle } from '@lucide/vue';
import { computed, ref } from 'vue';
import BaseBarChart from '@/components/charts/BaseBarChart.vue';
import BaseLineChart from '@/components/charts/BaseLineChart.vue';
import BaseScatterChart from '@/components/charts/BaseScatterChart.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import {
    analysis as mlAnalysis,
    forecast as mlForecast,
    training as mlTraining,
} from '@/routes/analytics/ml';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Hasil Analisis', href: mlAnalysis() },
        ],
    },
});

type DescriptiveRow = {
    variable: string;
    key: string;
    n: number;
    mean: number;
    std_dev: number;
    min: number;
    max: number;
    cv: number;
};

type PearsonSig = {
    pair: string;
    var_a: string;
    var_b: string;
    r: number;
    r_squared: number;
    t_stat: number;
    p_value: number;
    significant: boolean;
    strength: string;
};

type Coefficients = { b0: number; b1: number; b2: number; b3: number };

type RegressionResult = {
    n: number;
    coefficients: Coefficients;
    std_errors: Coefficients;
    t_stats: Coefficients;
    p_values: Coefficients;
    ci95_lower: Coefficients;
    ci95_upper: Coefficients;
    r_squared: number;
    adjusted_r_squared: number;
    r_multiple: number;
    see: number;
    mape: number;
    ss_regression: number;
    ss_residual: number;
    ss_total: number;
    ms_regression: number;
    ms_residual: number;
    f_statistic: number;
    p_value_f: number;
    df_regression: number;
    df_residual: number;
    equation: string;
};

type AssumptionTest = {
    jarque_bera: {
        jb: number;
        skewness: number;
        excess_kurtosis: number;
        p_value: number;
        passed: boolean;
        decision: string;
    };
    durbin_watson: {
        dw: number;
        dl: number;
        du: number;
        decision: string;
        r_lag1: number;
    };
    glejser: {
        intercept: { p_value: number; significant: boolean };
        x1: { p_value: number; significant: boolean };
        x2: { p_value: number; significant: boolean };
        x3: { p_value: number; significant: boolean };
        passed: boolean;
        decision: string;
    };
    vif: {
        x1: { value: number; passed: boolean; label: string };
        x2: { value: number; passed: boolean; label: string };
        x3: { value: number; passed: boolean; label: string };
    };
    f_linearity: {
        f_stat: number;
        p_value: number;
        passed: boolean;
        decision: string;
    };
};

const props = defineProps<{
    has_training_data: boolean;
    descriptive?: DescriptiveRow[];
    pearson?: {
        matrix: Record<string, Record<string, number>>;
        significance: PearsonSig[];
    };
    regression?: RegressionResult;
    assumptions?: AssumptionTest;
    n?: number;
    chart_data?: {
        labels: string[];
        actual: number[];
        fitted: number[];
        residuals: number[];
        scatter_pairs: { x: number; y: number }[];
        qq_pairs: { x: number; y: number }[];
    };
}>();

const { __ } = useTrans();
const activeTab = ref<'descriptive' | 'pearson' | 'regression' | 'assumptions'>(
    'descriptive',
);

// ─── Actual vs Predicted scatter chart (industry standard regression diagnostic) ─
// Points on the 45° line = perfect prediction; spread indicates residuals.
const scatterDatasets = computed<ChartDataset<'scatter'>[]>(() => {
    if (!props.chart_data?.scatter_pairs?.length) return [];

    // Build 45° reference line: min to max of fitted values
    const fitteds = props.chart_data.scatter_pairs.map((p) => p.x);
    const minF = Math.min(...fitteds);
    const maxF = Math.max(...fitteds);
    const refLine: { x: number; y: number }[] = [
        { x: minF, y: minF },
        { x: maxF, y: maxF },
    ];

    return [
        {
            label: 'Observasi (Ŷ vs Y)',
            data: props.chart_data.scatter_pairs,
            backgroundColor: 'rgba(204,0,0,0.70)',
            borderColor: '#cc0000',
            borderWidth: 1,
            pointRadius: 5,
            pointHoverRadius: 7,
        },
        {
            label: 'Garis 45° (Prediksi Sempurna)',
            data: refLine,
            backgroundColor: 'transparent',
            borderColor: '#0f172a',
            borderWidth: 1.5,
            pointRadius: 0,
            showLine: true,
            type: 'line' as const,
        } as unknown as ChartDataset<'scatter'>,
    ];
});

const scatterOptions = computed<ChartOptions<'scatter'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'nearest', intersect: true },
    scales: {
        x: {
            title: {
                display: true,
                text: 'Ŷ Nilai Prediksi (Fitted)',
                font: { size: 10 },
                color: '#64748b',
            },
            ticks: {
                font: { family: 'monospace', size: 9 },
                color: '#64748b',
                callback: (v) => Number(v).toLocaleString('id-ID'),
            },
        },
        y: {
            title: {
                display: true,
                text: 'Y Nilai Aktual',
                font: { size: 10 },
                color: '#64748b',
            },
            ticks: {
                font: { family: 'monospace', size: 9 },
                color: '#64748b',
                callback: (v) => Number(v).toLocaleString('id-ID'),
            },
        },
    },
    plugins: {
        legend: {
            display: true,
            position: 'top',
            labels: { font: { size: 10 }, boxWidth: 10, usePointStyle: true },
        },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    if (ctx.datasetIndex === 0) {
                        return `Prediksi: ${Number(ctx.parsed.x).toLocaleString('id-ID')} → Aktual: ${Number(ctx.parsed.y).toLocaleString('id-ID')}`;
                    }
                    return '';
                },
            },
        },
    },
}));

// ─── Q-Q plot scatter (normality diagnostic) ─────────────────────────────────
const qqDatasets = computed<ChartDataset<'scatter'>[]>(() => {
    if (!props.chart_data?.qq_pairs?.length) return [];

    const xs = props.chart_data.qq_pairs.map((p) => p.x);
    const minX = Math.min(...xs);
    const maxX = Math.max(...xs);

    return [
        {
            label: 'Kuantil Residual Terstandarisasi',
            data: props.chart_data.qq_pairs,
            backgroundColor: 'rgba(59,130,246,0.65)',
            borderColor: '#3b82f6',
            borderWidth: 1,
            pointRadius: 4.5,
            pointHoverRadius: 6,
        },
        {
            label: 'Garis Normal Ideal',
            data: [
                { x: minX, y: minX },
                { x: maxX, y: maxX },
            ],
            backgroundColor: 'transparent',
            borderColor: '#f59e0b',
            borderWidth: 1.5,
            pointRadius: 0,
            showLine: true,
            type: 'line' as const,
        } as unknown as ChartDataset<'scatter'>,
    ];
});

const qqOptions = computed<ChartOptions<'scatter'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        x: {
            title: {
                display: true,
                text: 'Kuantil Teoritis (Normal)',
                font: { size: 10 },
                color: '#64748b',
            },
            ticks: { font: { size: 9 }, color: '#64748b' },
        },
        y: {
            title: {
                display: true,
                text: 'Kuantil Residual Terstandarisasi',
                font: { size: 10 },
                color: '#64748b',
            },
            ticks: { font: { size: 9 }, color: '#64748b' },
        },
    },
    plugins: {
        legend: {
            display: true,
            position: 'top',
            labels: { font: { size: 10 }, boxWidth: 10, usePointStyle: true },
        },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    if (ctx.datasetIndex === 0) {
                        return `Teoritis: ${Number(ctx.parsed.x).toFixed(2)}, Residual: ${Number(ctx.parsed.y).toFixed(2)}`;
                    }
                    return '';
                },
            },
        },
    },
}));

// ─── Residuals chart ──────────────────────────────────────────────────────────
const residualDatasets = computed<ChartDataset<'line'>[]>(() => {
    if (!props.chart_data) return [];
    return [
        {
            label: 'Residual (eᵢ)',
            data: props.chart_data.residuals,
            borderColor: '#3b82f6',
            backgroundColor: (ctx) => {
                const v = props.chart_data!.residuals[ctx.dataIndex] ?? 0;
                return v >= 0
                    ? 'rgba(59,130,246,0.15)'
                    : 'rgba(220,38,38,0.12)';
            },
            borderWidth: 1.5,
            pointRadius: 3,
            tension: 0,
            fill: 'origin',
        },
    ];
});

// ─── VIF bar chart (multicollinearity visualization — standard diagnostic) ────
const vifBarDatasets = computed<ChartDataset<'bar'>[]>(() => {
    if (!props.assumptions?.vif) return [];
    const vals = [
        props.assumptions.vif.x1?.value ?? 0,
        props.assumptions.vif.x2?.value ?? 0,
        props.assumptions.vif.x3?.value ?? 0,
    ];
    const colors = vals.map((v) =>
        v < 5
            ? 'rgba(16,185,129,0.8)'
            : v < 10
              ? 'rgba(245,158,11,0.8)'
              : 'rgba(204,0,0,0.8)',
    );
    return [
        {
            label: 'VIF',
            data: vals,
            backgroundColor: colors,
            borderColor: colors.map((c) => c.replace('0.8', '1')),
            borderWidth: 1,
            borderRadius: 4,
        },
    ];
});

const vifBarOptions = computed<ChartOptions<'bar'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        x: {
            ticks: {
                font: { size: 11, weight: 'bold' as const },
                color: '#0f172a',
            },
            grid: { display: false },
        },
        y: {
            beginAtZero: true,
            ticks: { font: { size: 10 }, color: '#64748b' },
            grid: { color: 'rgba(100,116,139,0.08)' },
        },
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => `VIF = ${Number(ctx.parsed.y).toFixed(3)}`,
            },
        },
        annotation: {} as Record<string, unknown>,
    },
}));

// ─── Model Summary (Plain-language verdict for Law of UX: Recognition over Recall) ──
const modelSummary = computed(() => {
    if (!props.regression || !props.assumptions) return null;
    const r2 = props.regression.r_squared ?? 0;
    const fPassed = (props.regression.p_value_f ?? 1) < 0.05;
    const jbPassed = props.assumptions.jarque_bera?.passed ?? false;
    const dwDec = props.assumptions.durbin_watson?.decision ?? '';
    const dwPassed = dwDec.includes('Tidak ada');
    const glejserPassed = props.assumptions.glejser?.passed ?? false;
    const vif = props.assumptions.vif;
    const vifPassed =
        (vif?.x1?.value ?? 99) < 10 &&
        (vif?.x2?.value ?? 99) < 10 &&
        (vif?.x3?.value ?? 99) < 10;
    const fLinearPassed = props.assumptions.f_linearity?.passed ?? false;

    const passedCount = [
        jbPassed,
        dwPassed,
        glejserPassed,
        vifPassed,
        fLinearPassed,
    ].filter(Boolean).length;
    const r2Pct = (r2 * 100).toFixed(1);

    let verdict: 'layak' | 'perhatian' | 'tidak_layak';
    let verdictText: string;
    let verdictSub: string;

    if (r2 >= 0.6 && fPassed && passedCount >= 4) {
        verdict = 'layak';
        verdictText = 'Model Regresi LAYAK Digunakan';
        verdictSub = `Model menjelaskan ${r2Pct}% variasi Index Overtime dan hubungannya terbukti signifikan. Anda dapat menggunakan halaman Forecasting untuk prediksi.`;
    } else if (r2 >= 0.4 && fPassed) {
        verdict = 'perhatian';
        verdictText = 'Model Regresi CUKUP — Perlu Perhatian';
        verdictSub = `Model menjelaskan ${r2Pct}% variasi Index Overtime. Beberapa asumsi tidak terpenuhi — hasil prediksi sebaiknya dikonfirmasi dengan data lapangan.`;
    } else {
        verdict = 'tidak_layak';
        verdictText = 'Model Regresi BELUM LAYAK';
        verdictSub = `R² = ${r2Pct}% — model belum cukup kuat. Tambah data training lebih banyak atau periksa kualitas data agar model lebih akurat.`;
    }

    return {
        verdict,
        verdictText,
        verdictSub,
        r2Pct,
        fPassed,
        passedCount,
        totalAssumptions: 5,
    };
});

// ── Helpers ──────────────────────────────────────────────────────────────────
function fmt(val: number | undefined | null, decimals = 4): string {
    if (val === undefined || val === null) {
        return '–';
    }
    return Number(val).toLocaleString('id-ID', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

function fmtPct(val: number | undefined | null): string {
    if (val === undefined || val === null) {
        return '–';
    }
    return (Number(val) * 100).toFixed(2) + '%';
}

function pValueBadge(p: number): string {
    return p < 0.05
        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400';
}

function matrixCellClass(r: number): string {
    const abs = Math.abs(r);
    if (abs >= 0.8) {
        return 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300 font-semibold';
    }
    if (abs >= 0.6) {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300';
    }
    if (abs >= 0.4) {
        return 'bg-sky-50 text-sky-800 dark:bg-sky-950/40 dark:text-sky-300';
    }
    return 'text-slate-600 dark:text-slate-400';
}

const coefRows = [
    { key: 'b0', label: 'Intercept (b0)' },
    { key: 'b1', label: 'X1 - Jumlah Hari Kerja (b1)' },
    { key: 'b2', label: 'X2 - Volume Produksi (b2)' },
    { key: 'b3', label: 'X3 - Total Man Power (b3)' },
] as const;

const matrixVars = ['X1', 'X2', 'X3', 'Y'];
</script>

<template>
    <div class="space-y-6 p-6">
        <Head :title="__('Hasil Analisis')" />

        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                :title="__('Hasil Analisis')"
                :description="
                    __(
                        'Statistik deskriptif, korelasi Pearson, dan regresi linier berganda.',
                    )
                "
            >
                <template #icon>
                    <BarChart3 class="size-5 text-[#cc0000]" />
                </template>
            </Heading>
        </div>

        <!-- Sub-nav tabs -->
        <div class="flex gap-1 border-b border-slate-200 dark:border-slate-800">
            <Link
                :href="mlTraining.url()"
                class="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"
                >{{ __('Data Training') }}</Link
            >
            <Link
                :href="mlAnalysis.url()"
                class="-mb-px border-b-2 border-[#cc0000] px-4 py-2 text-sm font-medium text-[#cc0000]"
                >{{ __('Hasil Analisis') }}</Link
            >
            <Link
                :href="mlForecast.url()"
                class="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"
                >{{ __('Analisis Forecasting') }}</Link
            >
        </div>

        <!-- No data state -->
        <div
            v-if="!has_training_data"
            class="rounded-xl border border-slate-200 bg-slate-50 py-16 text-center dark:border-slate-800 dark:bg-slate-900/40"
        >
            <BarChart3 class="mx-auto mb-3 size-10 text-slate-300" />
            <p class="text-sm font-medium text-slate-600 dark:text-slate-400">
                {{ __('Data training belum tersedia.') }}
            </p>
            <Link
                :href="mlTraining.url()"
                class="mt-2 inline-block text-sm font-semibold text-[#cc0000] hover:underline"
                >← {{ __('Tambah Data Training') }}</Link
            >
        </div>

        <template v-else>
            <!-- ── Model Summary card (Aesthetic-Usability + Miller's Law: verdict first) ── -->
            <div
                v-if="modelSummary"
                :class="[
                    'rounded-xl border px-5 py-4',
                    modelSummary.verdict === 'layak'
                        ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/40 dark:bg-emerald-950/20'
                        : modelSummary.verdict === 'perhatian'
                          ? 'border-amber-200 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-950/20'
                          : 'border-red-200 bg-red-50/60 dark:border-red-900/40 dark:bg-red-950/20',
                ]"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <div
                            :class="[
                                'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full text-base font-bold',
                                modelSummary.verdict === 'layak'
                                    ? 'bg-emerald-500 text-white'
                                    : modelSummary.verdict === 'perhatian'
                                      ? 'bg-amber-400 text-white'
                                      : 'bg-[#cc0000] text-white',
                            ]"
                        >
                            {{
                                modelSummary.verdict === 'layak'
                                    ? '✓'
                                    : modelSummary.verdict === 'perhatian'
                                      ? '!'
                                      : '✗'
                            }}
                        </div>
                        <div>
                            <p
                                :class="[
                                    'text-sm font-bold',
                                    modelSummary.verdict === 'layak'
                                        ? 'text-emerald-800 dark:text-emerald-300'
                                        : modelSummary.verdict === 'perhatian'
                                          ? 'text-amber-800 dark:text-amber-300'
                                          : 'text-[#cc0000]',
                                ]"
                            >
                                {{ modelSummary.verdictText }}
                            </p>
                            <p
                                class="mt-1 text-xs text-slate-600 dark:text-slate-400"
                            >
                                {{ modelSummary.verdictSub }}
                            </p>
                        </div>
                    </div>
                    <!-- Key metric pills -->
                    <div
                        class="flex shrink-0 flex-wrap gap-2 sm:flex-col sm:items-end"
                    >
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-slate-700 shadow-xs ring-1 ring-slate-200 dark:bg-slate-800/60 dark:text-slate-300 dark:ring-slate-700"
                        >
                            R² = {{ modelSummary.r2Pct }}%
                        </span>
                        <span
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold shadow-xs ring-1',
                                modelSummary.fPassed
                                    ? 'bg-emerald-100 text-emerald-800 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-900'
                                    : 'bg-red-100 text-red-800 ring-red-200 dark:bg-red-950/60 dark:text-red-300 dark:ring-red-900',
                            ]"
                        >
                            F-test
                            {{
                                modelSummary.fPassed
                                    ? 'Signifikan'
                                    : 'Tidak Signifikan'
                            }}
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-slate-700 shadow-xs ring-1 ring-slate-200 dark:bg-slate-800/60 dark:text-slate-300 dark:ring-slate-700"
                        >
                            Asumsi {{ modelSummary.passedCount }}/{{
                                modelSummary.totalAssumptions
                            }}
                            Terpenuhi
                        </span>
                    </div>
                </div>
            </div>

            <!-- Analysis tabs -->
            <div
                class="flex flex-wrap gap-1 rounded-lg border border-slate-200 bg-slate-50 p-1 dark:border-slate-800 dark:bg-slate-900/60"
            >
                <button
                    v-for="tab in [
                        {
                            key: 'descriptive',
                            label: __('Statistik Deskriptif'),
                        },
                        { key: 'pearson', label: __('Korelasi Pearson') },
                        {
                            key: 'regression',
                            label: __('Regresi Linier Berganda'),
                        },
                        { key: 'assumptions', label: __('Uji Asumsi Klasik') },
                    ]"
                    :key="tab.key"
                    class="min-w-max flex-1 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
                    :class="
                        activeTab === tab.key
                            ? 'bg-white text-[#cc0000] shadow-xs dark:bg-slate-800 dark:text-red-300'
                            : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'
                    "
                    @click="activeTab = tab.key as typeof activeTab"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- ── Tab A: Statistik Deskriptif ──────────────────────────────── -->
            <template v-if="activeTab === 'descriptive'">
                <!-- Reading guide -->
                <div
                    class="rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <p
                        class="mb-1 text-[10px] font-bold tracking-widest text-slate-500 uppercase"
                    >
                        Cara Membaca Tabel Ini
                    </p>
                    <div
                        class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-slate-500"
                    >
                        <span
                            ><strong>Mean</strong> — rata-rata nilai selama
                            periode data training</span
                        >
                        <span
                            ><strong>Std Dev</strong> — seberapa jauh nilai
                            berfluktuasi dari rata-rata</span
                        >
                        <span
                            ><strong>CV%</strong> — variasi relatif: CV &gt; 30%
                            = data sangat fluktuatif; &lt; 15% = data
                            stabil</span
                        >
                        <span
                            ><strong>Min / Max</strong> — nilai terendah dan
                            tertinggi yang pernah terjadi</span
                        >
                    </div>
                </div>
                <Card class="border-slate-200 dark:border-slate-800">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-semibold"
                            >{{ __('Statistik Deskriptif') }} (n =
                            {{ n }})</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="overflow-x-auto p-0">
                        <table class="w-full text-sm">
                            <thead
                                class="border-b border-slate-100 bg-slate-50/70 dark:border-slate-800 dark:bg-slate-900/60"
                            >
                                <tr>
                                    <th
                                        class="px-4 py-2.5 text-left text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Variabel
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        N
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Mean
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Std Dev
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Min
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Max
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        CV (%)
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr
                                    v-for="row in descriptive"
                                    :key="row.key"
                                    class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30"
                                >
                                    <td
                                        class="px-4 py-2.5 font-semibold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ row.variable }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-slate-600 tabular-nums dark:text-slate-400"
                                    >
                                        {{ row.n }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                    >
                                        {{ fmt(row.mean, 2) }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                    >
                                        {{ fmt(row.std_dev, 2) }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                    >
                                        {{ fmt(row.min, 0) }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                    >
                                        {{ fmt(row.max, 0) }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono font-semibold tabular-nums"
                                        :class="
                                            row.cv > 30
                                                ? 'text-amber-600'
                                                : 'text-slate-700 dark:text-slate-300'
                                        "
                                    >
                                        {{ fmt(row.cv, 2) }}%
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
                <p class="text-xs text-slate-400">
                    CV &gt; 30% = data heterogen / fluktuasi tinggi. CV &lt; 15%
                    = data homogen / stabil.
                </p>
            </template>

            <!-- ── Tab B: Korelasi Pearson ───────────────────────────────────── -->
            <template v-if="activeTab === 'pearson'">
                <!-- Legend: reading guide (Hick's Law — reduce mental effort) -->
                <div
                    class="rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <p
                        class="mb-2 text-[10px] font-bold tracking-widest text-slate-500 uppercase"
                    >
                        Cara Membaca Korelasi Pearson (r)
                    </p>
                    <div class="flex flex-wrap gap-3 text-xs">
                        <span class="flex items-center gap-1.5"
                            ><span
                                class="inline-block h-3 w-8 rounded bg-red-200"
                            ></span
                            ><strong>|r| ≥ 0.8</strong> — Sangat Kuat</span
                        >
                        <span class="flex items-center gap-1.5"
                            ><span
                                class="inline-block h-3 w-8 rounded bg-amber-200"
                            ></span
                            ><strong>0.6 ≤ |r| &lt; 0.8</strong> — Kuat</span
                        >
                        <span class="flex items-center gap-1.5"
                            ><span
                                class="inline-block h-3 w-8 rounded bg-sky-200"
                            ></span
                            ><strong>0.4 ≤ |r| &lt; 0.6</strong> — Sedang</span
                        >
                        <span class="flex items-center gap-1.5"
                            ><span
                                class="inline-block h-3 w-8 rounded bg-slate-200"
                            ></span
                            ><strong>|r| &lt; 0.4</strong> — Lemah</span
                        >
                        <span class="ml-2 text-slate-400"
                            >· Nilai <strong>positif</strong> = naik bersama ·
                            Nilai <strong>negatif</strong> = berlawanan arah ·
                            <strong>p &lt; 0.05</strong> = signifikan secara
                            statistik</span
                        >
                    </div>
                </div>

                <!-- Correlation matrix -->
                <Card class="border-slate-200 dark:border-slate-800">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-semibold"
                            >{{ __('Matriks Korelasi') }} (r)</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="overflow-x-auto p-0">
                        <table class="w-full text-sm">
                            <thead
                                class="border-b border-slate-100 bg-slate-50/70 dark:border-slate-800 dark:bg-slate-900/60"
                            >
                                <tr>
                                    <th
                                        class="px-4 py-2.5 text-left text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Variabel
                                    </th>
                                    <th
                                        v-for="v in matrixVars"
                                        :key="v"
                                        class="px-4 py-2.5 text-center text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        {{ v }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr
                                    v-for="row in matrixVars"
                                    :key="row"
                                    class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30"
                                >
                                    <td
                                        class="px-4 py-2.5 font-semibold text-slate-700 dark:text-slate-300"
                                    >
                                        {{ row }}
                                    </td>
                                    <td
                                        v-for="col in matrixVars"
                                        :key="col"
                                        class="rounded px-4 py-2.5 text-center font-mono text-sm tabular-nums"
                                        :class="
                                            matrixCellClass(
                                                pearson?.matrix?.[row]?.[col] ??
                                                    0,
                                            )
                                        "
                                    >
                                        {{
                                            fmt(
                                                pearson?.matrix?.[row]?.[col],
                                                4,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <!-- Significance table -->
                <Card class="mt-4 border-slate-200 dark:border-slate-800">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-semibold"
                            >{{ __('Uji Signifikansi') }} (α = 5%)</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="overflow-x-auto p-0">
                        <table class="w-full text-sm">
                            <thead
                                class="border-b border-slate-100 bg-slate-50/70 dark:border-slate-800 dark:bg-slate-900/60"
                            >
                                <tr>
                                    <th
                                        class="px-4 py-2.5 text-left text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Pasangan
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        r
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        r²
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        t-hitung
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        P-Value
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-center text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Keputusan
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-left text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        {{ __('Kekuatan Hubungan') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr
                                    v-for="sig in pearson?.significance"
                                    :key="sig.pair"
                                    class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30"
                                >
                                    <td
                                        class="px-4 py-2.5 text-xs font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        {{ sig.pair }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono font-semibold tabular-nums"
                                        :class="
                                            Math.abs(sig.r) >= 0.6
                                                ? 'text-[#cc0000]'
                                                : 'text-slate-700 dark:text-slate-300'
                                        "
                                    >
                                        {{ fmt(sig.r, 4) }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-slate-600 tabular-nums dark:text-slate-400"
                                    >
                                        {{ fmt(sig.r_squared, 4) }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-slate-600 tabular-nums dark:text-slate-400"
                                    >
                                        {{ fmt(sig.t_stat, 4) }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono tabular-nums"
                                        :class="
                                            sig.p_value < 0.05
                                                ? 'font-semibold text-emerald-600'
                                                : 'text-slate-500'
                                        "
                                    >
                                        {{ fmt(sig.p_value, 4) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <Badge
                                            :class="
                                                sig.significant
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                            "
                                        >
                                            {{
                                                sig.significant
                                                    ? 'Signifikan'
                                                    : 'Tidak Signifikan'
                                            }}
                                        </Badge>
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-xs text-slate-600 dark:text-slate-400"
                                    >
                                        {{ sig.strength }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
                <p class="text-xs text-slate-400">
                    H₀: r = 0 (tidak ada hubungan linier). Tolak H₀ bila P-Value
                    &lt; 0,05. r &gt; 0,8 antar prediktor → indikasi
                    multikolinearitas.
                </p>
            </template>

            <!-- ── Tab C: Regresi Linier Berganda ───────────────────────────── -->
            <template v-if="activeTab === 'regression'">
                <!-- Equation banner -->
                <Card
                    class="border-[#cc0000]/20 bg-red-50/40 dark:border-red-900/30 dark:bg-red-950/20"
                >
                    <CardContent class="px-5 py-4">
                        <p
                            class="text-[10px] font-bold tracking-widest text-[#cc0000] uppercase"
                        >
                            {{ __('Persamaan Regresi') }}
                        </p>
                        <p
                            class="mt-1 font-mono text-base font-bold text-slate-900 dark:text-white"
                        >
                            {{ regression?.equation }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            n = {{ regression?.n }} | R² =
                            {{ fmt(regression?.r_squared, 4) }} | Adj. R² =
                            {{ fmt(regression?.adjusted_r_squared, 4) }} | MAPE
                            = {{ fmt(regression?.mape, 2) }}%
                        </p>
                        <!-- Plain-language interpretation of R² (Recognition over Recall) -->
                        <div
                            class="mt-3 rounded-lg bg-white/60 px-3 py-2 text-xs text-slate-600 dark:bg-slate-900/40 dark:text-slate-400"
                        >
                            <strong>Artinya:</strong> Model ini mampu
                            menjelaskan
                            <strong class="text-[#cc0000]"
                                >{{
                                    (
                                        (regression?.r_squared ?? 0) * 100
                                    ).toFixed(1)
                                }}%</strong
                            >
                            dari perubahan Index Overtime berdasarkan Hari
                            Kerja, Volume Produksi, dan Man Power. Semakin dekat
                            ke 100%, semakin akurat prediksi model. MAPE sebesar
                            <strong>{{ fmt(regression?.mape, 2) }}%</strong>
                            berarti rata-rata kesalahan prediksi adalah sebesar
                            itu dari nilai aktual.
                        </div>
                    </CardContent>
                </Card>

                <!-- Coefficients table -->
                <Card class="border-slate-200 dark:border-slate-800">
                    <CardHeader class="pb-2"
                        ><CardTitle class="text-sm font-semibold"
                            >Koefisien, Uji t &amp; P-Value</CardTitle
                        ></CardHeader
                    >
                    <CardContent class="overflow-x-auto p-0">
                        <table class="w-full text-sm">
                            <thead
                                class="border-b border-slate-100 bg-slate-50/70 dark:border-slate-800 dark:bg-slate-900/60"
                            >
                                <tr>
                                    <th
                                        class="px-4 py-2.5 text-left text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Variabel
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Koefisien
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Std Error
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        t-hitung
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        P-Value
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-center text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Keputusan
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        CI 95% Bawah
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        CI 95% Atas
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr
                                    v-for="row in coefRows"
                                    :key="row.key"
                                    class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30"
                                >
                                    <td
                                        class="px-4 py-2.5 text-xs font-semibold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ row.label }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                                    >
                                        {{
                                            fmt(
                                                regression?.coefficients?.[
                                                    row.key
                                                ],
                                                2,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-slate-600 tabular-nums dark:text-slate-400"
                                    >
                                        {{
                                            fmt(
                                                regression?.std_errors?.[
                                                    row.key
                                                ],
                                                4,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-slate-600 tabular-nums dark:text-slate-400"
                                    >
                                        {{
                                            fmt(
                                                regression?.t_stats?.[row.key],
                                                4,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono tabular-nums"
                                        :class="
                                            (regression?.p_values?.[row.key] ??
                                                1) < 0.05
                                                ? 'font-semibold text-emerald-600'
                                                : 'text-slate-500'
                                        "
                                    >
                                        {{
                                            fmt(
                                                regression?.p_values?.[row.key],
                                                6,
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <Badge
                                            :class="
                                                (regression?.p_values?.[
                                                    row.key
                                                ] ?? 1) < 0.05
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                    : 'bg-slate-100 text-slate-500'
                                            "
                                        >
                                            {{
                                                (regression?.p_values?.[
                                                    row.key
                                                ] ?? 1) < 0.05
                                                    ? 'Signifikan'
                                                    : 'Tidak Signifikan'
                                            }}
                                        </Badge>
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-slate-500 tabular-nums"
                                    >
                                        {{
                                            fmt(
                                                regression?.ci95_lower?.[
                                                    row.key
                                                ],
                                                2,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-slate-500 tabular-nums"
                                    >
                                        {{
                                            fmt(
                                                regression?.ci95_upper?.[
                                                    row.key
                                                ],
                                                2,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <!-- ANOVA & Goodness of Fit side by side -->
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <!-- F-ANOVA -->
                    <Card class="border-slate-200 dark:border-slate-800">
                        <CardHeader class="pb-2"
                            ><CardTitle class="text-sm font-semibold"
                                >Uji F (ANOVA) — Kelayakan Model
                                Simultan</CardTitle
                            ></CardHeader
                        >
                        <CardContent class="space-y-2 text-sm">
                            <div
                                class="flex justify-between border-b border-slate-100 pb-1.5 dark:border-slate-800"
                            >
                                <span class="text-slate-500">SS Regresi</span>
                                <span
                                    class="font-mono font-semibold text-slate-800 tabular-nums dark:text-slate-200"
                                    >{{
                                        fmt(regression?.ss_regression, 0)
                                    }}</span
                                >
                            </div>
                            <div
                                class="flex justify-between border-b border-slate-100 pb-1.5 dark:border-slate-800"
                            >
                                <span class="text-slate-500">SS Residual</span>
                                <span
                                    class="font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                    >{{ fmt(regression?.ss_residual, 0) }}</span
                                >
                            </div>
                            <div
                                class="flex justify-between border-b border-slate-100 pb-1.5 dark:border-slate-800"
                            >
                                <span class="text-slate-500"
                                    >MS Regresi / MS Residual</span
                                >
                                <span
                                    class="font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                    >{{ fmt(regression?.ms_regression, 0) }} /
                                    {{ fmt(regression?.ms_residual, 0) }}</span
                                >
                            </div>
                            <div
                                class="flex justify-between border-b border-slate-100 pb-1.5 dark:border-slate-800"
                            >
                                <span class="text-slate-500"
                                    >df (k ; n-k-1)</span
                                >
                                <span
                                    class="font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                    >{{ regression?.df_regression }} ;
                                    {{ regression?.df_residual }}</span
                                >
                            </div>
                            <div
                                class="flex justify-between border-b border-slate-100 pb-1.5 dark:border-slate-800"
                            >
                                <span
                                    class="font-semibold text-slate-700 dark:text-slate-200"
                                    >F-hitung</span
                                >
                                <span
                                    class="font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                                    >{{ fmt(regression?.f_statistic, 4) }}</span
                                >
                            </div>
                            <div
                                class="flex justify-between border-b border-slate-100 pb-1.5 dark:border-slate-800"
                            >
                                <span
                                    class="font-semibold text-slate-700 dark:text-slate-200"
                                    >P-Value (Sig. F)</span
                                >
                                <span
                                    class="font-mono font-bold tabular-nums"
                                    :class="
                                        (regression?.p_value_f ?? 1) < 0.05
                                            ? 'text-emerald-600'
                                            : 'text-red-600'
                                    "
                                    >{{ fmt(regression?.p_value_f, 6) }}</span
                                >
                            </div>
                            <div class="pt-1">
                                <Badge
                                    :class="
                                        (regression?.p_value_f ?? 1) < 0.05
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                            : 'bg-red-100 text-red-800'
                                    "
                                >
                                    {{
                                        (regression?.p_value_f ?? 1) < 0.05
                                            ? 'Model SIGNIFIKAN / layak digunakan'
                                            : 'Model tidak signifikan'
                                    }}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Goodness of Fit -->
                    <Card class="border-slate-200 dark:border-slate-800">
                        <CardHeader class="pb-2"
                            ><CardTitle class="text-sm font-semibold">{{
                                __('Goodness of Fit')
                            }}</CardTitle></CardHeader
                        >
                        <CardContent class="space-y-2 text-sm">
                            <div
                                v-for="gof in [
                                    {
                                        label: 'R (Korelasi Berganda)',
                                        value: fmt(regression?.r_multiple, 4),
                                    },
                                    {
                                        label: 'R² (Determinasi)',
                                        value: fmt(regression?.r_squared, 4),
                                    },
                                    {
                                        label: 'R² (%)',
                                        value:
                                            fmt(
                                                (regression?.r_squared ?? 0) *
                                                    100,
                                                2,
                                            ) + '%',
                                    },
                                    {
                                        label: 'Adjusted R²',
                                        value: fmt(
                                            regression?.adjusted_r_squared,
                                            4,
                                        ),
                                    },
                                    {
                                        label: 'SEE',
                                        value: fmt(regression?.see, 2),
                                    },
                                    {
                                        label: 'MAPE',
                                        value: fmt(regression?.mape, 2) + '%',
                                    },
                                ]"
                                :key="gof.label"
                                class="flex justify-between border-b border-slate-100 pb-1.5 dark:border-slate-800"
                            >
                                <span class="text-slate-500">{{
                                    gof.label
                                }}</span>
                                <span
                                    class="font-mono font-semibold text-slate-800 tabular-nums dark:text-slate-200"
                                    >{{ gof.value }}</span
                                >
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Scatter: Actual vs Predicted (industry-standard regression diagnostic) -->
                <Card
                    v-if="chart_data && chart_data.scatter_pairs?.length > 0"
                    class="border-slate-200 dark:border-slate-800"
                >
                    <CardHeader class="pb-1">
                        <CardTitle class="text-sm font-semibold">
                            Scatter: Aktual (Y) vs Prediksi (Ŷ) — Goodness of
                            Fit
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="pb-4">
                        <BaseScatterChart
                            :datasets="scatterDatasets"
                            :options="scatterOptions"
                            height-class="h-64"
                        />
                        <p class="mt-2 text-xs text-slate-400">
                            Titik yang
                            <strong>berada di sekitar garis 45°</strong>
                            menunjukkan prediksi mendekati nilai aktual.
                            Penyimpangan besar dari garis menunjukkan observasi
                            yang sulit diprediksi model. R² =
                            <strong
                                >{{
                                    (
                                        (regression?.r_squared ?? 0) * 100
                                    ).toFixed(1)
                                }}%</strong
                            >
                            — proporsi variasi Y yang dijelaskan model.
                        </p>
                    </CardContent>
                </Card>

                <!-- Residuals over time (homoscedasticity check) -->
                <Card
                    v-if="chart_data && chart_data.labels.length > 0"
                    class="border-slate-200 dark:border-slate-800"
                >
                    <CardHeader class="pb-1">
                        <CardTitle class="text-sm font-semibold">
                            Plot Residual per Periode (eᵢ = Yᵢ − Ŷᵢ)
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="pb-4">
                        <BaseLineChart
                            :labels="chart_data.labels"
                            :datasets="residualDatasets"
                            height-class="h-48"
                        />
                        <p class="mt-2 text-xs text-slate-400">
                            Residual yang
                            <strong>tersebar acak di sekitar nol</strong> (tanpa
                            pola corong / gelombang / tren) mengindikasikan
                            asumsi homoskedastisitas dan independensi residual
                            terpenuhi.
                        </p>
                    </CardContent>
                </Card>

                <!-- Q-Q Plot: Normality diagnostic -->
                <Card
                    v-if="chart_data && chart_data.qq_pairs?.length > 0"
                    class="border-slate-200 dark:border-slate-800"
                >
                    <CardHeader class="pb-1">
                        <CardTitle class="text-sm font-semibold">
                            Q-Q Plot Residual — Uji Normalitas Visual
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="pb-4">
                        <BaseScatterChart
                            :datasets="qqDatasets"
                            :options="qqOptions"
                            height-class="h-56"
                        />
                        <p class="mt-2 text-xs text-slate-400">
                            Titik yang
                            <strong
                                >mengikuti garis kuning (ideal normal)</strong
                            >
                            berarti residual berdistribusi normal. Penyimpangan
                            di ujung-ujung (ekor) bisa mengindikasikan
                            distribusi berekor berat — konfirmasi dengan uji
                            Jarque-Bera di tab Asumsi.
                        </p>
                    </CardContent>
                </Card>
            </template>

            <!-- ── Tab D: Uji Asumsi Klasik ──────────────────────────────────── -->
            <template v-if="activeTab === 'assumptions'">
                <Card class="border-slate-200 dark:border-slate-800">
                    <CardHeader class="pb-2"
                        ><CardTitle class="text-sm font-semibold">{{
                            __('Rekap Uji Asumsi Klasik')
                        }}</CardTitle></CardHeader
                    >
                    <CardContent class="overflow-x-auto p-0">
                        <table class="w-full text-sm">
                            <thead
                                class="border-b border-slate-100 bg-slate-50/70 dark:border-slate-800 dark:bg-slate-900/60"
                            >
                                <tr>
                                    <th
                                        class="px-4 py-2.5 text-left text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Asumsi
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-left text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Alat Uji
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Nilai Statistik
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        P-Value / Batas
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-center text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <!-- Normalitas -->
                                <tr
                                    class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30"
                                >
                                    <td
                                        class="px-4 py-3 text-slate-800 dark:text-slate-200"
                                    >
                                        <p class="font-semibold">
                                            Normalitas Residual
                                        </p>
                                        <p
                                            class="mt-0.5 text-[10px] text-slate-400"
                                        >
                                            Apakah kesalahan prediksi (error)
                                            terdistribusi secara normal? Wajib
                                            agar p-value statistik dapat
                                            dipercaya.
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">
                                        Jarque-Bera
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                    >
                                        JB =
                                        {{ fmt(assumptions?.jarque_bera?.jb, 4)
                                        }}<br />
                                        <span class="text-xs text-slate-400"
                                            >S =
                                            {{
                                                fmt(
                                                    assumptions?.jarque_bera
                                                        ?.skewness,
                                                    4,
                                                )
                                            }}, K =
                                            {{
                                                fmt(
                                                    assumptions?.jarque_bera
                                                        ?.excess_kurtosis,
                                                    4,
                                                )
                                            }}</span
                                        >
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-600 tabular-nums"
                                    >
                                        {{
                                            fmt(
                                                assumptions?.jarque_bera
                                                    ?.p_value,
                                                4,
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="inline-flex items-center gap-1"
                                        >
                                            <CheckCircle2
                                                v-if="
                                                    assumptions?.jarque_bera
                                                        ?.passed
                                                "
                                                class="size-4 text-emerald-500"
                                            />
                                            <XCircle
                                                v-else
                                                class="size-4 text-red-500"
                                            />
                                            <Badge
                                                :class="
                                                    assumptions?.jarque_bera
                                                        ?.passed
                                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                        : 'bg-red-100 text-red-800'
                                                "
                                            >
                                                {{
                                                    assumptions?.jarque_bera
                                                        ?.passed
                                                        ? 'TERPENUHI'
                                                        : 'TIDAK TERPENUHI'
                                                }}
                                            </Badge>
                                        </span>
                                    </td>
                                </tr>
                                <!-- Autokorelasi -->
                                <tr
                                    class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30"
                                >
                                    <td
                                        class="px-4 py-3 text-slate-800 dark:text-slate-200"
                                    >
                                        <p class="font-semibold">
                                            Non-Autokorelasi
                                        </p>
                                        <p
                                            class="mt-0.5 text-[10px] text-slate-400"
                                        >
                                            Apakah error antar bulan saling
                                            independen? Pola berulang dalam
                                            error akan membiaskan prediksi deret
                                            waktu.
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">
                                        Durbin-Watson
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                    >
                                        DW =
                                        {{
                                            fmt(
                                                assumptions?.durbin_watson?.dw,
                                                4,
                                            )
                                        }}<br />
                                        <span class="text-xs text-slate-400"
                                            >dL={{
                                                assumptions?.durbin_watson?.dl
                                            }}
                                            dU={{
                                                assumptions?.durbin_watson?.du
                                            }}</span
                                        >
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-xs text-slate-500"
                                    >
                                        dU &lt; DW &lt; 4-dU
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="inline-flex flex-col items-center gap-1"
                                        >
                                            <span>
                                                <CheckCircle2
                                                    v-if="
                                                        assumptions?.durbin_watson?.decision?.includes(
                                                            'TERPENUHI',
                                                        )
                                                    "
                                                    class="inline size-4 text-emerald-500"
                                                />
                                                <AlertCircle
                                                    v-else-if="
                                                        assumptions?.durbin_watson?.decision?.includes(
                                                            'ragu',
                                                        )
                                                    "
                                                    class="inline size-4 text-amber-500"
                                                />
                                                <XCircle
                                                    v-else
                                                    class="inline size-4 text-red-500"
                                                />
                                            </span>
                                            <Badge
                                                :class="
                                                    assumptions?.durbin_watson?.decision?.includes(
                                                        'TERPENUHI',
                                                    )
                                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                        : assumptions?.durbin_watson?.decision?.includes(
                                                                'ragu',
                                                            )
                                                          ? 'bg-amber-100 text-amber-800'
                                                          : 'bg-red-100 text-red-800'
                                                "
                                            >
                                                {{
                                                    assumptions?.durbin_watson?.decision?.includes(
                                                        'TERPENUHI',
                                                    )
                                                        ? 'TERPENUHI'
                                                        : assumptions?.durbin_watson?.decision?.includes(
                                                                'ragu',
                                                            )
                                                          ? 'RAGU-RAGU'
                                                          : 'TIDAK TERPENUHI'
                                                }}
                                            </Badge>
                                        </span>
                                    </td>
                                </tr>
                                <!-- Heteroskedastisitas -->
                                <tr
                                    class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30"
                                >
                                    <td
                                        class="px-4 py-3 text-slate-800 dark:text-slate-200"
                                    >
                                        <p class="font-semibold">
                                            Homoskedastisitas
                                        </p>
                                        <p
                                            class="mt-0.5 text-[10px] text-slate-400"
                                        >
                                            Apakah presisi prediksi konsisten di
                                            semua rentang data? Jika tidak,
                                            model lebih akurat di satu kondisi
                                            daripada kondisi lain.
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">
                                        Glejser
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-xs text-slate-500"
                                    >
                                        P(X1)={{
                                            fmt(
                                                assumptions?.glejser?.x1
                                                    ?.p_value,
                                                4,
                                            )
                                        }}<br />
                                        P(X2)={{
                                            fmt(
                                                assumptions?.glejser?.x2
                                                    ?.p_value,
                                                4,
                                            )
                                        }}<br />
                                        P(X3)={{
                                            fmt(
                                                assumptions?.glejser?.x3
                                                    ?.p_value,
                                                4,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-xs text-slate-500"
                                    >
                                        P-Value &gt; 0,05
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="inline-flex items-center gap-1"
                                        >
                                            <CheckCircle2
                                                v-if="
                                                    assumptions?.glejser?.passed
                                                "
                                                class="size-4 text-emerald-500"
                                            />
                                            <XCircle
                                                v-else
                                                class="size-4 text-red-500"
                                            />
                                            <Badge
                                                :class="
                                                    assumptions?.glejser?.passed
                                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                        : 'bg-red-100 text-red-800'
                                                "
                                            >
                                                {{
                                                    assumptions?.glejser?.passed
                                                        ? 'TERPENUHI'
                                                        : 'TIDAK TERPENUHI'
                                                }}
                                            </Badge>
                                        </span>
                                    </td>
                                </tr>
                                <!-- VIF -->
                                <tr
                                    class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30"
                                >
                                    <td
                                        class="px-4 py-3 text-slate-800 dark:text-slate-200"
                                    >
                                        <p class="font-semibold">
                                            Non-Multikolinearitas
                                        </p>
                                        <p
                                            class="mt-0.5 text-[10px] text-slate-400"
                                        >
                                            Apakah X1/X2/X3 saling tidak tumpang
                                            tindih? Jika tumpang tindih,
                                            kontribusi tiap variabel tidak dapat
                                            dipercaya secara individual.
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">
                                        VIF
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-xs text-slate-500"
                                    >
                                        VIF(X1)={{
                                            fmt(assumptions?.vif?.x1?.value, 4)
                                        }}<br />
                                        VIF(X2)={{
                                            fmt(assumptions?.vif?.x2?.value, 4)
                                        }}<br />
                                        VIF(X3)={{
                                            fmt(assumptions?.vif?.x3?.value, 4)
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-xs text-slate-500"
                                    >
                                        VIF &lt; 10
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="inline-flex items-center gap-1"
                                        >
                                            <CheckCircle2
                                                v-if="
                                                    assumptions?.vif?.x1
                                                        ?.passed &&
                                                    assumptions?.vif?.x2
                                                        ?.passed &&
                                                    assumptions?.vif?.x3?.passed
                                                "
                                                class="size-4 text-emerald-500"
                                            />
                                            <XCircle
                                                v-else
                                                class="size-4 text-red-500"
                                            />
                                            <Badge
                                                :class="
                                                    assumptions?.vif?.x1
                                                        ?.passed &&
                                                    assumptions?.vif?.x2
                                                        ?.passed &&
                                                    assumptions?.vif?.x3?.passed
                                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                        : 'bg-red-100 text-red-800'
                                                "
                                            >
                                                {{
                                                    assumptions?.vif?.x1
                                                        ?.passed &&
                                                    assumptions?.vif?.x2
                                                        ?.passed &&
                                                    assumptions?.vif?.x3?.passed
                                                        ? 'TERPENUHI'
                                                        : 'TIDAK TERPENUHI'
                                                }}
                                            </Badge>
                                        </span>
                                    </td>
                                </tr>
                                <!-- Linearitas -->
                                <tr
                                    class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30"
                                >
                                    <td
                                        class="px-4 py-3 text-slate-800 dark:text-slate-200"
                                    >
                                        <p class="font-semibold">
                                            Linearitas Model
                                        </p>
                                        <p
                                            class="mt-0.5 text-[10px] text-slate-400"
                                        >
                                            Apakah kombinasi X1/X2/X3 secara
                                            bersama-sama memang berpengaruh
                                            terhadap Y? Diperlukan agar regresi
                                            linier tepat digunakan.
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">
                                        Uji F Model
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                    >
                                        F =
                                        {{
                                            fmt(
                                                assumptions?.f_linearity
                                                    ?.f_stat,
                                                4,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono tabular-nums"
                                        :class="
                                            (assumptions?.f_linearity
                                                ?.p_value ?? 1) < 0.05
                                                ? 'text-emerald-600'
                                                : 'text-red-600'
                                        "
                                    >
                                        {{
                                            fmt(
                                                assumptions?.f_linearity
                                                    ?.p_value,
                                                4,
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="inline-flex items-center gap-1"
                                        >
                                            <CheckCircle2
                                                v-if="
                                                    assumptions?.f_linearity
                                                        ?.passed
                                                "
                                                class="size-4 text-emerald-500"
                                            />
                                            <XCircle
                                                v-else
                                                class="size-4 text-red-500"
                                            />
                                            <Badge
                                                :class="
                                                    assumptions?.f_linearity
                                                        ?.passed
                                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                        : 'bg-red-100 text-red-800'
                                                "
                                            >
                                                {{
                                                    assumptions?.f_linearity
                                                        ?.passed
                                                        ? 'TERPENUHI'
                                                        : 'TIDAK TERPENUHI'
                                                }}
                                            </Badge>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
                <!-- VIF bar chart: multicollinearity visualization -->
                <Card
                    v-if="assumptions?.vif"
                    class="border-slate-200 dark:border-slate-800"
                >
                    <CardHeader class="pb-1">
                        <CardTitle class="text-sm font-semibold">
                            VIF per Variabel — Uji Multikolinearitas
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="pb-4">
                        <BaseBarChart
                            :labels="[
                                'X1 — Hari Kerja',
                                'X2 — Volume Produksi',
                                'X3 — Man Power',
                            ]"
                            :datasets="vifBarDatasets"
                            :options="vifBarOptions"
                            height-class="h-48"
                        />
                        <div
                            class="mt-2 flex flex-wrap gap-4 text-xs text-slate-500"
                        >
                            <span class="flex items-center gap-1.5"
                                ><span
                                    class="inline-block h-3 w-3 rounded-sm bg-emerald-400"
                                ></span
                                >VIF &lt; 5 — Tidak ada multikolinearitas</span
                            >
                            <span class="flex items-center gap-1.5"
                                ><span
                                    class="inline-block h-3 w-3 rounded-sm bg-amber-400"
                                ></span
                                >VIF 5–10 — Perlu diperhatikan</span
                            >
                            <span class="flex items-center gap-1.5"
                                ><span
                                    class="inline-block h-3 w-3 rounded-sm bg-[#cc0000]"
                                ></span
                                >VIF &gt; 10 — Multikolinearitas serius</span
                            >
                        </div>
                    </CardContent>
                </Card>

                <p class="text-xs text-slate-400">
                    Catatan: Autokorelasi perlu dicek karena data deret waktu
                    bulanan. Durbin-Watson (n=30, k=3, α=5%): dL=1,2138
                    dU=1,6498. Asumsi aman bila dU &lt; DW &lt; 4-dU.
                </p>
            </template>
        </template>
    </div>
</template>
