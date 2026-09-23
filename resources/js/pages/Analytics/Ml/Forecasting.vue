<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, TrendingUp } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import BaseLineChart from '@/components/charts/BaseLineChart.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTrans } from '@/composables/useTrans';
import {
    analysis as mlAnalysis,
    forecast as mlForecast,
    training as mlTraining,
} from '@/routes/analytics/ml';
import {
    destroy as destroyForecast,
    store as storeForecast,
    update as updateForecast,
} from '@/routes/analytics/ml/forecast';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Analisis Forecasting', href: mlForecast() },
        ],
    },
});

type ForecastRow = {
    id: number | null;
    /** training = historical actuals; forecast = user-entered plan; auto = model estimate from same-month history; empty = no data at all */
    source: 'training' | 'forecast' | 'auto' | 'empty';
    n_ref?: number;
    year: number;
    month: number;
    month_name: string;
    period_label: string;
    working_days: number | null;
    production_volume: number | null;
    man_power: number | null;
    actual_overtime_index: number | null;
    index_ideal: number | null;
    index_benchmark: number | null;
    selisih_ideal_benchmark: number | null;
    deviasi_pct: number | null;
    status: 'Over' | 'Under' | 'Sama' | null;
};

const props = defineProps<{
    has_training_data: boolean;
    equation: string | null;
    r_squared?: number;
    benchmark_ratio: number;
    forecast_rows: ForecastRow[];
    n_training?: number;
    view_year: number;
}>();

const { __ } = useTrans();

// ─── Year navigation ──────────────────────────────────────────────────────────
function changeYear(year: number): void {
    router.get(mlForecast.url({ query: { year } }));
}

// ─── Sheet state ──────────────────────────────────────────────────────────────
const isOpen = ref(false);
const editingRow = ref<ForecastRow | null>(null);

const sheetTitle = computed(() =>
    editingRow.value ? __('Edit Data Forecast') : __('Tambah Data Forecast'),
);

// ─── Form ─────────────────────────────────────────────────────────────────────
const form = useForm({
    year: props.view_year,
    month: 7,
    working_days: 20,
    production_volume: 0,
    man_power: 0,
    actual_overtime_index: undefined as number | undefined,
});

function openAdd(month?: number): void {
    editingRow.value = null;
    form.reset();
    form.year = props.view_year;
    form.month = month ?? 7;
    form.working_days = 20;
    form.production_volume = 0;
    form.man_power = 0;
    form.actual_overtime_index = undefined;
    isOpen.value = true;
}

function openEdit(row: ForecastRow): void {
    editingRow.value = row;
    form.year = row.year;
    form.month = row.month;
    form.working_days = row.working_days ?? 20;
    form.production_volume = row.production_volume ?? 0;
    form.man_power = row.man_power ?? 0;
    form.actual_overtime_index = row.actual_overtime_index ?? undefined;
    isOpen.value = true;
}

function submitForm(): void {
    if (editingRow.value && editingRow.value.id) {
        form.put(updateForecast.url(editingRow.value.id), {
            onSuccess: () => {
                isOpen.value = false;
            },
        });
    } else {
        form.post(storeForecast.url(), {
            onSuccess: () => {
                isOpen.value = false;
                form.reset();
            },
        });
    }
}

function confirmDelete(row: ForecastRow): void {
    if (!row.id) return;
    if (
        !confirm(
            __('Hapus data forecast bulan :periode?').replace(
                ':periode',
                row.period_label,
            ),
        )
    )
        return;
    router.delete(destroyForecast.url(row.id));
}

watch(isOpen, (v) => {
    if (!v) form.clearErrors();
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
const months = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

function fmt(val: number | null | undefined, decimals = 0): string {
    if (val === null || val === undefined) return '–';
    return Number(val).toLocaleString('id-ID', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

function statusClass(status: string | null): string {
    if (status === 'Over')
        return 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300';
    if (status === 'Under')
        return 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300';
    if (status === 'Sama')
        return 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
    return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400';
}

// ─── Year-has-no-data detection ───────────────────────────────────────────────
// "all empty" only when EVERY row has no historical data for that month at all (very rare edge case)
const allEmpty = computed(() =>
    props.forecast_rows.every((r) => r.source === 'empty'),
);

// ─── Status insight summary ────────────────────────────────────────────────────
const statusInsight = computed(() => {
    const rows = props.forecast_rows.filter((r) => r.source === 'forecast');
    if (rows.length === 0) return null;
    const over = rows.filter((r) => r.status === 'Over').length;
    const under = rows.filter((r) => r.status === 'Under').length;
    const noAct = rows.filter((r) => r.status === null).length;
    return { over, under, noAct, total: rows.length };
});

// ─── Chart — Three visual segments ────────────────────────────────────────────
// Historical (solid black) · Plan/Forecast (dashed red) · Auto-estimate (dotted slate)
const chartLabels = computed(() =>
    props.forecast_rows.map((r) => r.month_name.substring(0, 3)),
);

const historicalIdeal = computed(() =>
    props.forecast_rows.map((r) =>
        r.source === 'training' ? r.index_ideal : null,
    ),
);
const forecastIdeal = computed(() =>
    props.forecast_rows.map((r) =>
        r.source === 'forecast' ? r.index_ideal : null,
    ),
);
// auto = model prediction using same-month historical averages (self-learning from accumulated n)
const autoIdeal = computed(() =>
    props.forecast_rows.map((r) =>
        r.source === 'auto' ? r.index_ideal : null,
    ),
);
const actualData = computed(() =>
    props.forecast_rows.map((r) =>
        r.actual_overtime_index !== null ? r.actual_overtime_index : null,
    ),
);
const benchmarkData = computed(() =>
    props.forecast_rows.map((r) => r.index_benchmark),
);

const hasAutoRows = computed(() =>
    props.forecast_rows.some((r) => r.source === 'auto'),
);

const chartDatasets = computed<ChartDataset<'line'>[]>(() => {
    const ds: ChartDataset<'line'>[] = [
        {
            label: 'Ŷ Aktual (Historis)',
            data: historicalIdeal.value as number[],
            borderColor: '#0f172a',
            backgroundColor: 'rgba(15,23,42,0.05)',
            borderWidth: 2.5,
            pointRadius: 5,
            pointBackgroundColor: '#0f172a',
            tension: 0.2,
            fill: false,
            spanGaps: false,
        },
        {
            label: 'Ŷ Model (Input Rencana)',
            data: forecastIdeal.value as number[],
            borderColor: '#cc0000',
            backgroundColor: 'rgba(204,0,0,0.06)',
            borderWidth: 2.5,
            borderDash: [6, 3],
            pointRadius: 5,
            pointStyle: 'circle',
            pointBackgroundColor: '#cc0000',
            tension: 0.2,
            fill: false,
            spanGaps: false,
        },
        {
            label: 'Ŷ Auto-Estimasi (Rata-rata historis bulan yg sama)',
            data: autoIdeal.value as number[],
            borderColor: '#64748b',
            backgroundColor: 'transparent',
            borderWidth: 1.5,
            borderDash: [3, 6],
            pointRadius: 4,
            pointStyle: 'triangle',
            pointBackgroundColor: '#64748b',
            tension: 0.2,
            fill: false,
            spanGaps: false,
        },
        {
            label: 'Benchmark Q1',
            data: benchmarkData.value as number[],
            borderColor: '#f59e0b',
            backgroundColor: 'transparent',
            borderWidth: 1.5,
            borderDash: [3, 3],
            pointRadius: 2,
            tension: 0.2,
            fill: false,
            spanGaps: true,
        },
    ];

    const hasActuals = props.forecast_rows.some(
        (r) => r.source === 'forecast' && r.actual_overtime_index !== null,
    );
    if (hasActuals) {
        ds.push({
            label: 'Y Aktual (Forecast Terealisasi)',
            data: actualData.value as number[],
            borderColor: '#10b981',
            backgroundColor: 'transparent',
            borderWidth: 2,
            pointRadius: 5,
            pointStyle: 'rectRot',
            pointBackgroundColor: '#10b981',
            tension: 0.2,
            fill: false,
            spanGaps: false,
        });
    }
    return ds;
});

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    scales: {
        x: {
            ticks: { font: { size: 10 }, color: '#64748b' },
            grid: { display: false },
        },
        y: {
            beginAtZero: false,
            ticks: {
                font: { family: 'monospace', size: 10 },
                color: '#64748b',
                callback: (v) => Number(v).toLocaleString('id-ID'),
            },
        },
    },
    plugins: {
        legend: {
            display: true,
            position: 'top',
            labels: { font: { size: 11 }, boxWidth: 12, usePointStyle: true },
        },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    if (ctx.parsed.y === null) return '';
                    return `${ctx.dataset.label}: ${Number(ctx.parsed.y).toLocaleString('id-ID')}`;
                },
            },
        },
    },
}));

// ─── Available years for navigation ───────────────────────────────────────────
const availableYears = [2024, 2025, 2026, 2027];
</script>

<template>
    <div class="space-y-6 p-6">
        <Head :title="__('Analisis Forecasting')" />

        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                :title="__('Analisis Forecasting')"
                :description="
                    __(
                        'Prediksi Index Overtime berbasis model regresi linier berganda — tampilan penuh Januari s.d. Desember.',
                    )
                "
            >
                <template #icon>
                    <TrendingUp class="size-5 text-[#cc0000]" />
                </template>
            </Heading>
            <Button
                v-if="has_training_data"
                class="bg-[#cc0000] text-white shadow-xs transition-all hover:bg-[#b30000] active:scale-95"
                @click="openAdd()"
            >
                <Plus class="mr-1.5 size-4" />
                {{ __('Tambah Data Forecast') }}
            </Button>
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
                class="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"
                >{{ __('Hasil Analisis') }}</Link
            >
            <Link
                :href="mlForecast.url()"
                class="-mb-px border-b-2 border-[#cc0000] px-4 py-2 text-sm font-medium text-[#cc0000]"
                >{{ __('Analisis Forecasting') }}</Link
            >
        </div>

        <!-- No training data state -->
        <div
            v-if="!has_training_data"
            class="rounded-xl border border-slate-200 bg-slate-50 py-16 text-center dark:border-slate-800 dark:bg-slate-900/40"
        >
            <TrendingUp class="mx-auto mb-3 size-10 text-slate-300" />
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
            <!-- Year selector (user can look at any year) -->
            <div
                class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-xs dark:border-slate-800 dark:bg-slate-900/60"
            >
                <span class="text-xs font-semibold text-slate-500"
                    >Tampilkan Tahun:</span
                >
                <div class="flex gap-1">
                    <button
                        v-for="yr in availableYears"
                        :key="yr"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-sm font-semibold transition-all',
                            yr === view_year
                                ? 'bg-[#cc0000] text-white shadow-sm'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700',
                        ]"
                        @click="changeYear(yr)"
                    >
                        {{ yr }}
                    </button>
                </div>
                <span class="ml-auto text-[10px] text-slate-400"
                    >Semakin banyak data training ditambah → estimasi otomatis
                    semakin akurat</span
                >
            </div>

            <!-- All-empty year: accumulation model explainer -->
            <div
                v-if="allEmpty"
                class="rounded-xl border border-dashed border-[#cc0000]/40 bg-red-50/30 px-5 py-5 dark:border-red-900/30 dark:bg-red-950/10"
            >
                <p
                    class="mb-1 text-[11px] font-bold tracking-widest text-[#cc0000] uppercase"
                >
                    Belum Ada Data untuk Tahun {{ view_year }}
                </p>
                <p
                    class="mb-3 max-w-2xl text-sm text-slate-600 dark:text-slate-400"
                >
                    Model regresi sudah terlatih dari
                    <strong>{{ n_training }} bulan historis</strong>. Untuk
                    melihat prediksi tahun {{ view_year }}, masukkan
                    <strong>rencana X1/X2/X3</strong> per bulan (hari kerja,
                    volume produksi, man power yang direncanakan) — model akan
                    langsung menghitung Ŷ Index Overtime.
                </p>
                <div
                    class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 dark:border-sky-900/40 dark:bg-sky-950/20"
                >
                    <p
                        class="mb-1 text-[10px] font-bold tracking-widest text-sky-700 uppercase dark:text-sky-400"
                    >
                        Cara Kerja Akumulasi (Self-Learning melalui Penambahan
                        Data)
                    </p>
                    <ol
                        class="space-y-1 text-xs text-slate-600 dark:text-slate-400"
                    >
                        <li>
                            <span
                                class="mr-1.5 inline-block h-4 w-4 rounded-full bg-sky-600 text-center text-[9px] leading-4 font-bold text-white"
                                >1</span
                            >
                            <strong>Input rencana</strong> — klik "+ Input" pada
                            bulan yang ingin diprediksi, isi X1/X2/X3 sesuai
                            target produksi.
                        </li>
                        <li>
                            <span
                                class="mr-1.5 inline-block h-4 w-4 rounded-full bg-sky-600 text-center text-[9px] leading-4 font-bold text-white"
                                >2</span
                            >
                            <strong>Model memprediksi Ŷ</strong> — menggunakan
                            koefisien yang dipelajari dari
                            {{ n_training }} bulan training (n={{
                                n_training
                            }}).
                        </li>
                        <li>
                            <span
                                class="mr-1.5 inline-block h-4 w-4 rounded-full bg-sky-600 text-center text-[9px] leading-4 font-bold text-white"
                                >3</span
                            >
                            <strong
                                >Bulan berlalu → tambah aktual ke
                                Training</strong
                            >
                            — ketika realisasi bulan tersebut sudah diketahui,
                            tambahkan ke halaman "Data Training" (n menjadi
                            {{ (n_training ?? 30) + 1 }},
                            {{ (n_training ?? 30) + 2 }}, dst). Buka ulang
                            halaman ini → model dihitung ulang dengan data lebih
                            banyak → prediksi ke depan lebih akurat.
                        </li>
                    </ol>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        class="bg-[#cc0000] text-white shadow-xs hover:bg-[#b30000]"
                        @click="openAdd(1)"
                    >
                        <Plus class="mr-1.5 size-4" />
                        Mulai Input Januari {{ view_year }}
                    </Button>
                    <Link :href="mlTraining.url()">
                        <Button variant="outline" class="gap-1.5">
                            Lihat Data Training (n={{ n_training }})
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Equation banner -->
            <Card
                v-if="!allEmpty"
                class="border-[#cc0000]/20 bg-red-50/40 dark:border-red-900/30 dark:bg-red-950/20"
            >
                <CardContent class="px-5 py-3">
                    <div
                        class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-[10px] font-bold tracking-widest text-[#cc0000] uppercase"
                            >
                                Persamaan Regresi — diestimasi sekali dari
                                <strong class="text-[#cc0000]"
                                    >n = {{ n_training }} bulan aktual</strong
                                >
                            </p>
                            <p
                                class="mt-0.5 font-mono text-sm font-bold text-slate-900 dark:text-white"
                            >
                                {{ equation }}
                            </p>
                            <p class="mt-1 text-[10px] text-slate-400">
                                Y = Index Overtime · X1 = Hari Kerja · X2 =
                                Volume Produksi · X3 = Man Power.<br />
                                <strong class="text-slate-500"
                                    >Koefisien tidak berubah selama forecast
                                    ini.</strong
                                >
                                Untuk memperbarui model, tambah data aktual baru
                                di halaman Data Training lalu buka ulang halaman
                                ini (n menjadi
                                {{ (n_training ?? 30) + 1 }},
                                {{ (n_training ?? 30) + 2 }}, dst. → model
                                baru).
                            </p>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            R² =
                            {{
                                r_squared ? (r_squared * 100).toFixed(1) : '–'
                            }}%
                            <span class="ml-1 text-slate-400"
                                >(proporsi variasi Y yang dijelaskan
                                model)</span
                            >
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Status reading guide (only when year has data) -->
            <div
                v-if="!allEmpty"
                class="rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/40"
            >
                <p
                    class="mb-2 text-[10px] font-bold tracking-widest text-slate-500 uppercase"
                >
                    Cara Membaca Tabel
                </p>
                <div
                    class="grid grid-cols-2 gap-x-6 gap-y-1.5 text-xs sm:grid-cols-4"
                >
                    <div class="flex items-start gap-2">
                        <span
                            class="mt-0.5 inline-block size-3 rounded-full bg-slate-700"
                        ></span>
                        <span class="text-slate-500"
                            ><strong>Historis</strong> — bulan dengan data
                            aktual dari training; Ŷ = prediksi model untuk bulan
                            tersebut</span
                        >
                    </div>
                    <div class="flex items-start gap-2">
                        <span
                            class="mt-0.5 inline-block size-3 rounded-full border-2 border-dashed border-[#cc0000]"
                        ></span>
                        <span class="text-slate-500"
                            ><strong>Forecast (Rencana)</strong> — Anda sudah
                            mengisi X1/X2/X3; model memprediksi Ŷ dari rencana
                            tsb</span
                        >
                    </div>
                    <div class="flex items-start gap-2">
                        <span
                            class="mt-0.5 inline-block size-3 rounded bg-slate-400/60 dark:bg-slate-600"
                        ></span>
                        <span class="text-slate-500"
                            ><strong>Auto-Estimasi</strong> — model menghitung Ŷ
                            otomatis menggunakan rata-rata X bulan yang sama
                            dari data training historis. Semakin banyak data
                            training (n↑), estimasi semakin akurat. Klik
                            "Override" untuk masukkan rencana aktual.</span
                        >
                    </div>
                    <div class="flex items-start gap-2">
                        <span
                            class="mt-0.5 rounded bg-red-100 px-1 py-0.5 text-[9px] font-bold text-red-800"
                            >Over</span
                        >
                        <span class="text-slate-500"
                            >Y Aktual <strong>lebih tinggi</strong> dari Ŷ model
                            — realisasi overtime melebihi prediksi</span
                        >
                    </div>
                    <div class="flex items-start gap-2">
                        <span
                            class="mt-0.5 rounded bg-sky-100 px-1 py-0.5 text-[9px] font-bold text-sky-800"
                            >Under</span
                        >
                        <span class="text-slate-500"
                            >Y Aktual <strong>lebih rendah</strong> dari Ŷ model
                            — realisasi overtime di bawah prediksi</span
                        >
                    </div>
                </div>
                <!-- Benchmark explanation callout -->
                <div
                    class="mt-3 rounded-lg border border-amber-200 bg-amber-50/50 px-3 py-2 dark:border-amber-900/30 dark:bg-amber-950/10"
                >
                    <p
                        class="text-[10px] font-bold tracking-wider text-amber-700 uppercase dark:text-amber-400"
                    >
                        Apa itu Benchmark (Q1)?
                    </p>
                    <p
                        class="mt-0.5 text-[11px] text-slate-600 dark:text-slate-400"
                    >
                        Dari semua bulan training historis, diambil 25% bulan
                        yang paling efisien (rasio
                        <em>Total Index Overtime ÷ Volume Produksi</em>
                        terendah). Rasio tersebut dikalikan Volume Produksi
                        bulan ini → hasilnya adalah
                        <strong>Index Overtime target efisiensi</strong> (dalam
                        satuan Index Overtime, sama dengan Y). Benchmark bukan
                        jam lembur, bukan persentase — melainkan angka Index
                        Overtime yang seharusnya bisa dicapai jika seefisien
                        bulan-bulan terbaik historis.
                    </p>
                </div>
                <!-- Insight summary -->
                <div
                    v-if="
                        statusInsight &&
                        statusInsight.total - statusInsight.noAct > 0
                    "
                    class="mt-2 flex flex-wrap gap-3 border-t border-slate-200 pt-2 dark:border-slate-700"
                >
                    <span
                        class="text-[10px] font-bold tracking-widest text-slate-400 uppercase"
                        >Ringkasan Aktual vs Prediksi:</span
                    >
                    <span
                        v-if="statusInsight.over > 0"
                        class="text-xs font-semibold text-red-600 dark:text-red-400"
                        >{{ statusInsight.over }} bulan Over</span
                    >
                    <span
                        v-if="statusInsight.under > 0"
                        class="text-xs font-semibold text-sky-600 dark:text-sky-400"
                        >{{ statusInsight.under }} bulan Under</span
                    >
                    <span
                        v-if="statusInsight.noAct > 0"
                        class="text-xs text-slate-400"
                        >{{ statusInsight.noAct }} bulan belum ada
                        realisasi</span
                    >
                </div>
            </div>

            <!-- Chart: Historical solid + Forecast dashed (industry standard: separate visual segments) -->
            <Card
                v-if="forecast_rows.length > 0"
                class="border-slate-200 dark:border-slate-800"
            >
                <CardHeader class="pb-1">
                    <CardTitle class="text-sm font-semibold">
                        Index Overtime — Tampilan Penuh {{ view_year }}
                        <span class="ml-2 font-normal text-slate-400"
                            >(Historis: solid · Rencana: putus-putus merah ·
                            Auto-Estimasi: titik-titik abu)</span
                        >
                    </CardTitle>
                </CardHeader>
                <CardContent class="pb-4">
                    <BaseLineChart
                        :labels="chartLabels"
                        :datasets="chartDatasets"
                        :options="chartOptions"
                        height-class="h-64"
                    />
                </CardContent>
            </Card>

            <!-- 12-month full-year table -->
            <Card class="border-slate-200 dark:border-slate-800">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-semibold">
                        Tabel Januari–Desember {{ view_year }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead
                                class="border-b border-slate-100 bg-slate-50/70 dark:border-slate-800 dark:bg-slate-900/60"
                            >
                                <tr>
                                    <th
                                        class="px-3 py-2.5 text-left text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Periode
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-center text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Tipe
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        X1 Hari Kerja
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        X2 Volume
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        X3 Man Power
                                    </th>
                                    <th
                                        class="bg-red-50/60 px-3 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase dark:bg-red-950/20"
                                    >
                                        Ŷ Prediksi Model
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Target Efisiensi (Q1 Historis)
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Selisih (Prediksi − Target)
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Y Aktual
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-center text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-center text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr
                                    v-for="row in forecast_rows"
                                    :key="`${row.year}-${row.month}`"
                                    :class="[
                                        'transition-colors',
                                        row.source === 'training'
                                            ? 'bg-slate-50/50 hover:bg-slate-100/60 dark:bg-slate-900/30 dark:hover:bg-slate-800/40'
                                            : row.source === 'auto'
                                              ? 'bg-slate-50/20 italic hover:bg-slate-50/60 dark:bg-slate-900/10 dark:hover:bg-slate-800/30'
                                              : row.source === 'empty'
                                                ? 'opacity-40'
                                                : 'hover:bg-slate-50/60 dark:hover:bg-slate-800/40',
                                    ]"
                                >
                                    <td
                                        class="px-3 py-2.5 font-semibold whitespace-nowrap text-slate-800 dark:text-slate-200"
                                    >
                                        {{ row.period_label }}
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <Badge
                                            v-if="row.source === 'training'"
                                            class="bg-slate-700 text-[10px] text-white"
                                            >Historis</Badge
                                        >
                                        <Badge
                                            v-else-if="
                                                row.source === 'forecast'
                                            "
                                            class="border border-dashed border-[#cc0000] bg-transparent text-[10px] text-[#cc0000]"
                                            >Forecast</Badge
                                        >
                                        <span
                                            v-else-if="row.source === 'auto'"
                                            class="inline-flex flex-col items-center gap-0.5"
                                        >
                                            <span
                                                class="rounded bg-slate-200 px-1.5 py-0.5 text-[9px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                                >Auto-Estimasi</span
                                            >
                                            <span
                                                class="text-[8px] text-slate-400"
                                                >dari
                                                {{ row.n_ref }} bulan</span
                                            >
                                        </span>
                                        <span
                                            v-else
                                            class="text-[10px] text-slate-300"
                                            >–</span
                                        >
                                    </td>
                                    <td
                                        class="px-3 py-2.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                    >
                                        {{ row.working_days ?? '–' }}
                                    </td>
                                    <td
                                        class="px-3 py-2.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                    >
                                        {{
                                            row.production_volume !== null
                                                ? row.production_volume.toLocaleString(
                                                      'id-ID',
                                                  )
                                                : '–'
                                        }}
                                    </td>
                                    <td
                                        class="px-3 py-2.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                    >
                                        {{
                                            row.man_power !== null
                                                ? row.man_power.toLocaleString(
                                                      'id-ID',
                                                  )
                                                : '–'
                                        }}
                                    </td>
                                    <td
                                        class="bg-red-50/30 px-3 py-2.5 text-right font-mono font-bold text-[#cc0000] tabular-nums dark:bg-red-950/10"
                                    >
                                        {{ fmt(row.index_ideal) }}
                                    </td>
                                    <td
                                        class="px-3 py-2.5 text-right font-mono text-slate-600 tabular-nums dark:text-slate-400"
                                    >
                                        {{ fmt(row.index_benchmark) }}
                                    </td>
                                    <td
                                        class="px-3 py-2.5 text-right font-mono tabular-nums"
                                        :class="
                                            row.selisih_ideal_benchmark !==
                                                null &&
                                            row.selisih_ideal_benchmark > 0
                                                ? 'text-amber-600'
                                                : 'text-emerald-600'
                                        "
                                    >
                                        {{ fmt(row.selisih_ideal_benchmark) }}
                                    </td>
                                    <td
                                        class="px-3 py-2.5 text-right font-mono text-slate-600 tabular-nums dark:text-slate-400"
                                    >
                                        {{ fmt(row.actual_overtime_index) }}
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <!-- Historical: show deviasi instead of Over/Under -->
                                        <span
                                            v-if="
                                                row.source === 'training' &&
                                                row.deviasi_pct !== null
                                            "
                                            :class="[
                                                'inline-block rounded px-1.5 py-0.5 font-mono text-[10px] tabular-nums',
                                                Math.abs(row.deviasi_pct) <= 5
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : Math.abs(
                                                            row.deviasi_pct,
                                                        ) <= 15
                                                      ? 'bg-amber-100 text-amber-800'
                                                      : 'bg-red-100 text-red-800',
                                            ]"
                                        >
                                            {{ row.deviasi_pct > 0 ? '+' : ''
                                            }}{{
                                                Number(row.deviasi_pct).toFixed(
                                                    1,
                                                )
                                            }}%
                                        </span>
                                        <Badge
                                            v-else-if="row.status"
                                            :class="statusClass(row.status)"
                                            >{{ row.status }}</Badge
                                        >
                                        <span
                                            v-else
                                            class="text-xs text-slate-300"
                                            >–</span
                                        >
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <div
                                            class="flex items-center justify-center gap-1"
                                        >
                                            <!-- Auto-estimate: let user override with actual plan -->
                                            <Button
                                                v-if="
                                                    row.source === 'auto' ||
                                                    row.source === 'empty'
                                                "
                                                size="sm"
                                                variant="ghost"
                                                class="h-7 gap-1 px-2 text-[10px] text-slate-500 hover:bg-slate-100 hover:text-[#cc0000]"
                                                @click="openAdd(row.month)"
                                            >
                                                <Plus class="size-3" />
                                                {{
                                                    row.source === 'auto'
                                                        ? 'Override'
                                                        : 'Input'
                                                }}
                                            </Button>
                                            <!-- Manual forecast: edit/delete -->
                                            <template
                                                v-else-if="
                                                    row.source === 'forecast'
                                                "
                                            >
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    class="h-7 w-7 p-0 hover:text-[#cc0000]"
                                                    @click="openEdit(row)"
                                                >
                                                    <Pencil class="size-3.5" />
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    class="h-7 w-7 p-0 hover:text-red-600"
                                                    @click="confirmDelete(row)"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                </Button>
                                            </template>
                                            <!-- Historical: read-only -->
                                            <span
                                                v-else
                                                class="text-[10px] text-slate-300"
                                                >–</span
                                            >
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <div
                class="space-y-1 rounded-lg border border-slate-100 bg-slate-50/50 px-4 py-3 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900/30"
            >
                <p>
                    <strong>Ŷ Prediksi Model</strong> — nilai Index Overtime
                    yang dihasilkan persamaan regresi untuk kombinasi X1/X2/X3
                    bulan tersebut. Satuannya: Index Overtime (angka total unit
                    lembur yang diakumulasi model).
                </p>
                <p>
                    <strong>Target Efisiensi (Q1 Historis)</strong> — berapa
                    seharusnya Index Overtime bulan ini jika seefisien 25% bulan
                    terbaik dari seluruh data training? Dihitung: Volume
                    Produksi bulan ini × rasio OT/Volume dari kuartil-1
                    historis. Satuan: Index Overtime.
                </p>
                <p>
                    <strong>Selisih positif</strong> (Prediksi &gt; Target) →
                    model memprediksi overtime lebih tinggi dari target
                    efisiensi historis; <strong>Selisih negatif</strong> →
                    prediksi lebih efisien dari target.
                </p>
                <p class="text-slate-400">
                    Baris <em>Historis</em> menampilkan deviasi (%) antara Y
                    aktual dan Ŷ prediksi model — seberapa jauh model meleset
                    pada data masa lalu. Baris
                    <em>Auto-Estimasi</em> menggunakan rata-rata X dari bulan
                    yang sama di tahun-tahun sebelumnya sebagai input perkiraan.
                </p>
            </div>
        </template>

        <!-- Add / Edit Sheet (only for forecast rows) -->
        <Sheet :open="isOpen" @update:open="isOpen = $event">
            <SheetContent
                side="right"
                class="w-full overflow-y-auto sm:max-w-md"
            >
                <SheetHeader>
                    <SheetTitle>{{ sheetTitle }}</SheetTitle>
                    <SheetDescription>
                        {{
                            editingRow
                                ? __(
                                      'Perbarui input bulan forecast. Isi Y Aktual jika realisasi sudah tersedia.',
                                  )
                                : __(
                                      'Tambah input bulan forecast — isi X1/X2/X3 yang direncanakan.',
                                  )
                        }}
                    </SheetDescription>
                </SheetHeader>

                <form class="mt-6 space-y-5" @submit.prevent="submitForm">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="fc_year">Tahun</Label>
                            <Input
                                id="fc_year"
                                v-model.number="form.year"
                                name="year"
                                type="number"
                                min="2000"
                                max="2100"
                                class="font-mono tabular-nums"
                            />
                            <p
                                v-if="form.errors.year"
                                class="text-xs text-red-500"
                            >
                                {{ form.errors.year }}
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="fc_month">Bulan</Label>
                            <Select v-model="form.month" name="month">
                                <SelectTrigger id="fc_month"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="m in months"
                                        :key="m.value"
                                        :value="m.value"
                                        >{{ m.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <p
                                v-if="form.errors.month"
                                class="text-xs text-red-500"
                            >
                                {{ form.errors.month }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="rounded-lg border border-sky-200 bg-sky-50/40 px-3 py-2 dark:border-sky-900/40 dark:bg-sky-950/20"
                    >
                        <p
                            class="text-[10px] font-bold text-sky-700 dark:text-sky-400"
                        >
                            Input Rencana Bulan Forecast
                        </p>
                        <p class="text-[10px] text-slate-500">
                            Isi berdasarkan jadwal produksi yang sudah
                            direncanakan untuk bulan tersebut.
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="fc_wd"
                            >X1 — Hari Kerja
                            <span class="text-xs text-slate-400"
                                >(rencana)</span
                            ></Label
                        >
                        <Input
                            id="fc_wd"
                            v-model.number="form.working_days"
                            name="working_days"
                            type="number"
                            min="1"
                            max="31"
                            class="font-mono tabular-nums"
                        />
                        <p
                            v-if="form.errors.working_days"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.working_days }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="fc_vol"
                            >X2 — Volume Produksi
                            <span class="text-xs text-slate-400"
                                >(rencana, unit/bulan)</span
                            ></Label
                        >
                        <Input
                            id="fc_vol"
                            v-model.number="form.production_volume"
                            name="production_volume"
                            type="number"
                            min="1"
                            class="font-mono tabular-nums"
                        />
                        <p
                            v-if="form.errors.production_volume"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.production_volume }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="fc_mp"
                            >X3 — Man Power
                            <span class="text-xs text-slate-400"
                                >(rencana)</span
                            ></Label
                        >
                        <Input
                            id="fc_mp"
                            v-model.number="form.man_power"
                            name="man_power"
                            type="number"
                            min="1"
                            class="font-mono tabular-nums"
                        />
                        <p
                            v-if="form.errors.man_power"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.man_power }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="fc_actual"
                            >Y Aktual
                            <span class="text-xs text-slate-400"
                                >(opsional — isi setelah realisasi bulan
                                berlalu)</span
                            ></Label
                        >
                        <Input
                            id="fc_actual"
                            v-model.number="form.actual_overtime_index"
                            name="actual_overtime_index"
                            type="number"
                            min="0"
                            class="font-mono tabular-nums"
                        />
                        <p
                            v-if="form.errors.actual_overtime_index"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.actual_overtime_index }}
                        </p>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <Button
                            type="submit"
                            class="flex-1 bg-[#cc0000] text-white hover:bg-[#b30000]"
                            :disabled="form.processing"
                        >
                            {{
                                form.processing
                                    ? __('Menyimpan…')
                                    : editingRow
                                      ? __('Perbarui')
                                      : __('Simpan')
                            }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="isOpen = false"
                            >{{ __('Batal') }}</Button
                        >
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    </div>
</template>
