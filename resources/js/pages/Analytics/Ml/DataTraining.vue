<script setup lang="ts">
import type { ChartDataset, ChartOptions } from 'chart.js';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Database, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import BaseLineChart from '@/components/charts/BaseLineChart.vue';
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
    destroy as destroyTraining,
    store as storeTraining,
    update as updateTraining,
} from '@/routes/analytics/ml/training';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Analisis Overtime – Data Training', href: mlTraining() },
        ],
    },
});

type TrainingRow = {
    id: number;
    year: number;
    month: number;
    month_name: string;
    period_label: string;
    working_days: number;
    production_volume: number;
    man_power: number;
    overtime_index: number;
};

const props = defineProps<{
    rows: TrainingRow[];
}>();

const { __ } = useTrans();

// ─── Sheet state ──────────────────────────────────────────────────────────────
const isOpen = ref(false);
const editingRow = ref<TrainingRow | null>(null);

const sheetTitle = computed(() =>
    editingRow.value ? __('Edit Data Training') : __('Tambah Data Training'),
);

// ─── Forms ────────────────────────────────────────────────────────────────────
const form = useForm({
    year: new Date().getFullYear(),
    month: new Date().getMonth() + 1,
    working_days: 20,
    production_volume: 0,
    man_power: 0,
    overtime_index: 0,
});

function openAdd(): void {
    editingRow.value = null;
    form.reset();
    form.year = new Date().getFullYear();
    form.month = new Date().getMonth() + 1;
    form.working_days = 20;
    form.production_volume = 0;
    form.man_power = 0;
    form.overtime_index = 0;
    isOpen.value = true;
}

function openEdit(row: TrainingRow): void {
    editingRow.value = row;
    form.year = row.year;
    form.month = row.month;
    form.working_days = row.working_days;
    form.production_volume = row.production_volume;
    form.man_power = row.man_power;
    form.overtime_index = row.overtime_index;
    isOpen.value = true;
}

function submitForm(): void {
    if (editingRow.value) {
        form.put(updateTraining.url(editingRow.value.id), {
            onSuccess: () => {
                isOpen.value = false;
            },
        });
    } else {
        form.post(storeTraining.url(), {
            onSuccess: () => {
                isOpen.value = false;
                form.reset();
            },
        });
    }
}

function confirmDelete(row: TrainingRow): void {
    if (
        !confirm(
            __(
                'Hapus data observasi bulan :periode? Tindakan ini tidak dapat dibatalkan.',
            ).replace(':periode', row.period_label),
        )
    ) {
        return;
    }
    router.delete(destroyTraining.url(row.id));
}

// Close sheet resets errors
watch(isOpen, (v) => {
    if (!v) {
        form.clearErrors();
    }
});

// ─── KPI stats ────────────────────────────────────────────────────────────────
const n = computed(() => props.rows.length);
const periodRange = computed(() => {
    if (props.rows.length === 0) return '–';
    return `${props.rows[0].period_label} – ${props.rows[props.rows.length - 1].period_label}`;
});
const meanY = computed(() => {
    if (props.rows.length === 0) return 0;
    return Math.round(
        props.rows.reduce((s, r) => s + r.overtime_index, 0) /
            props.rows.length,
    );
});

// ─── Month select options ─────────────────────────────────────────────────────
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

// ─── Chart data ───────────────────────────────────────────────────────────────
const chartLabels = computed(() => props.rows.map((r) => r.period_label));

const chartDatasets = computed<ChartDataset<'line'>[]>(() => [
    {
        label: 'Y – Index Overtime',
        data: props.rows.map((r) => r.overtime_index),
        borderColor: '#cc0000',
        backgroundColor: 'rgba(204,0,0,0.08)',
        borderWidth: 2,
        pointRadius: 4,
        pointBackgroundColor: '#cc0000',
        tension: 0.3,
        fill: true,
    },
    {
        label: 'X2 – Volume Produksi',
        data: props.rows.map((r) => r.production_volume),
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59,130,246,0.04)',
        borderWidth: 1.5,
        pointRadius: 3,
        pointBackgroundColor: '#3b82f6',
        tension: 0.3,
        borderDash: [4, 3],
        fill: false,
        yAxisID: 'y2',
    },
]);

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    scales: {
        x: {
            ticks: { font: { size: 10 }, maxRotation: 45, color: '#64748b' },
            grid: { display: false },
        },
        y: {
            beginAtZero: false,
            position: 'left',
            title: {
                display: true,
                text: 'Index OT',
                font: { size: 10 },
                color: '#cc0000',
            },
            ticks: {
                font: { family: 'monospace', size: 10 },
                color: '#64748b',
                callback: (v) => Number(v).toLocaleString('id-ID'),
            },
        },
        y2: {
            beginAtZero: false,
            position: 'right',
            title: {
                display: true,
                text: 'Volume (unit)',
                font: { size: 10 },
                color: '#3b82f6',
            },
            ticks: {
                font: { family: 'monospace', size: 10 },
                color: '#64748b',
            },
            grid: { drawOnChartArea: false },
        },
    },
    plugins: {
        legend: {
            display: true,
            position: 'top',
            labels: { font: { size: 11 }, boxWidth: 12 },
        },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const v = Number(ctx.parsed.y).toLocaleString('id-ID');
                    return `${ctx.dataset.label}: ${v}`;
                },
            },
        },
    },
}));
</script>

<template>
    <div class="space-y-6 p-6">
        <Head :title="__('Data Training')" />

        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                :title="__('Data Training')"
                :description="
                    __(
                        'Kelola data historis bulanan — variabel X1/X2/X3 dan Y — untuk melatih model regresi linier berganda.',
                    )
                "
            >
                <template #icon
                    ><Database class="size-5 text-[#cc0000]"
                /></template>
            </Heading>
            <Button
                class="bg-[#cc0000] text-white shadow-xs transition-all hover:bg-[#b30000] active:scale-95"
                @click="openAdd"
            >
                <Plus class="mr-1.5 size-4" />
                {{ __('Tambah Data') }}
            </Button>
        </div>

        <!-- 3-step workflow indicator (Law of UX: Progressive Disclosure + Jakob's Law) -->
        <div
            class="flex items-center gap-0 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-xs dark:border-slate-800 dark:bg-slate-900/60"
        >
            <div class="flex flex-1 items-center gap-3">
                <div
                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#cc0000] text-xs font-bold text-white shadow-sm"
                >
                    1
                </div>
                <div>
                    <p class="text-xs font-bold text-[#cc0000]">
                        Data Training
                    </p>
                    <p class="text-[10px] text-slate-400">
                        Input data historis bulanan
                    </p>
                </div>
            </div>
            <div
                class="mx-3 h-px w-6 shrink-0 bg-slate-300 dark:bg-slate-700"
            ></div>
            <div class="flex flex-1 items-center gap-3 opacity-40">
                <div
                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-400"
                >
                    2
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500">
                        Hasil Analisis
                    </p>
                    <p class="text-[10px] text-slate-400">
                        Lihat performa model regresi
                    </p>
                </div>
            </div>
            <div
                class="mx-3 h-px w-6 shrink-0 bg-slate-300 dark:bg-slate-700"
            ></div>
            <div class="flex flex-1 items-center gap-3 opacity-40">
                <div
                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-400"
                >
                    3
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500">
                        Forecasting
                    </p>
                    <p class="text-[10px] text-slate-400">
                        Prediksi bulan mendatang
                    </p>
                </div>
            </div>
        </div>

        <!-- Sub-nav tabs -->
        <div class="flex gap-1 border-b border-slate-200 dark:border-slate-800">
            <Link
                :href="mlTraining.url()"
                class="-mb-px border-b-2 border-[#cc0000] px-4 py-2 text-sm font-medium text-[#cc0000]"
                >{{ __('Data Training') }}</Link
            >
            <Link
                :href="mlAnalysis.url()"
                class="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"
                >{{ __('Hasil Analisis') }}</Link
            >
            <Link
                :href="mlForecast.url()"
                class="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"
                >{{ __('Analisis Forecasting') }}</Link
            >
        </div>

        <!-- Variable guide callout (Recognition over Recall — users see labels, not codes) -->
        <div
            class="rounded-xl border border-sky-200 bg-sky-50/60 px-4 py-3 dark:border-sky-900/50 dark:bg-sky-950/20"
        >
            <p
                class="mb-2 text-[10px] font-bold tracking-widest text-sky-700 uppercase dark:text-sky-400"
            >
                Panduan Variabel — Apa yang harus diisi?
            </p>
            <div class="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-4">
                <div>
                    <p
                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                    >
                        <span class="font-mono">X1</span> — Hari Kerja
                    </p>
                    <p class="text-[10px] text-slate-500">
                        Jumlah hari kerja efektif dalam bulan itu (biasanya
                        16–23 hari)
                    </p>
                </div>
                <div>
                    <p
                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                    >
                        <span class="font-mono">X2</span> — Volume Produksi
                    </p>
                    <p class="text-[10px] text-slate-500">
                        Total unit yang diproduksi dalam bulan tersebut
                    </p>
                </div>
                <div>
                    <p
                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                    >
                        <span class="font-mono">X3</span> — Man Power
                    </p>
                    <p class="text-[10px] text-slate-500">
                        Jumlah tenaga kerja aktif dalam bulan tersebut
                    </p>
                </div>
                <div>
                    <p class="text-xs font-bold text-[#cc0000]">
                        <span class="font-mono">Y</span> — Index Overtime
                        (Target Prediksi)
                    </p>
                    <p class="text-[10px] text-slate-500">
                        Nilai aktual Index OT — inilah yang akan diprediksi oleh
                        model
                    </p>
                </div>
            </div>
        </div>

        <!-- KPI bar -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Card class="border-slate-200 dark:border-slate-800">
                <CardContent class="pt-4 pb-3">
                    <p
                        class="text-xs font-semibold tracking-widest text-slate-500 uppercase"
                    >
                        {{ __('Observasi (n)') }}
                    </p>
                    <p
                        class="mt-1 font-mono text-3xl font-bold text-slate-900 tabular-nums dark:text-white"
                    >
                        {{ n }}
                    </p>
                </CardContent>
            </Card>
            <Card class="border-slate-200 dark:border-slate-800">
                <CardContent class="pt-4 pb-3">
                    <p
                        class="text-xs font-semibold tracking-widest text-slate-500 uppercase"
                    >
                        {{ __('Rentang Data') }}
                    </p>
                    <p
                        class="mt-1 text-sm font-semibold text-slate-900 dark:text-white"
                    >
                        {{ periodRange }}
                    </p>
                </CardContent>
            </Card>
            <Card class="border-slate-200 dark:border-slate-800">
                <CardContent class="pt-4 pb-3">
                    <p
                        class="text-xs font-semibold tracking-widest text-slate-500 uppercase"
                    >
                        Ȳ {{ __('Rata-rata Index OT') }}
                    </p>
                    <p
                        class="mt-1 font-mono text-2xl font-bold text-slate-900 tabular-nums dark:text-white"
                    >
                        {{ meanY.toLocaleString('id-ID') }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Time-series chart -->
        <Card
            v-if="rows.length > 0"
            class="border-slate-200 dark:border-slate-800"
        >
            <CardHeader class="pb-1">
                <CardTitle class="text-sm font-semibold"
                    >Y Index Overtime &amp; X2 Volume — Time Series ({{
                        rows[0].period_label
                    }}
                    s.d. {{ rows[rows.length - 1].period_label }})</CardTitle
                >
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

        <!-- Data table -->
        <Card class="border-slate-200 dark:border-slate-800">
            <CardHeader class="pb-2">
                <CardTitle
                    class="text-sm font-semibold text-slate-700 dark:text-slate-300"
                >
                    {{ __('Rekap Data Observasi Bulanan') }} (n = {{ n }})
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-if="rows.length === 0"
                    class="py-12 text-center text-sm text-slate-500"
                >
                    {{
                        __(
                            'Data training belum tersedia. Klik "Tambah Data" untuk memulai.',
                        )
                    }}
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="border-b border-slate-100 bg-slate-50/70 dark:border-slate-800 dark:bg-slate-900/60"
                        >
                            <tr>
                                <th
                                    class="px-4 py-2.5 text-left text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                >
                                    t
                                </th>
                                <th
                                    class="px-4 py-2.5 text-left text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                >
                                    {{ __('Periode') }}
                                </th>
                                <th
                                    class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                >
                                    X1 Hari Kerja
                                </th>
                                <th
                                    class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                >
                                    X2 Volume (unit)
                                </th>
                                <th
                                    class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                >
                                    X3 Man Power
                                </th>
                                <th
                                    class="px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                >
                                    Y Index OT
                                </th>
                                <th
                                    class="px-4 py-2.5 text-center text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
                                >
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr
                                v-for="(row, idx) in rows"
                                :key="row.id"
                                class="transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-800/40"
                            >
                                <td
                                    class="px-4 py-2.5 font-mono text-xs text-slate-400 tabular-nums"
                                >
                                    {{ idx + 1 }}
                                </td>
                                <td
                                    class="px-4 py-2.5 font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    {{ row.period_label }}
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                >
                                    {{ row.working_days }}
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                >
                                    {{
                                        row.production_volume.toLocaleString(
                                            'id-ID',
                                        )
                                    }}
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                >
                                    {{ row.man_power.toLocaleString('id-ID') }}
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                                >
                                    {{
                                        row.overtime_index.toLocaleString(
                                            'id-ID',
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <div
                                        class="flex items-center justify-center gap-1.5"
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
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Add / Edit Sheet -->
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
                                ? __('Perbarui data observasi bulanan.')
                                : __(
                                      'Tambah observasi bulanan baru ke dataset training.',
                                  )
                        }}
                    </SheetDescription>
                </SheetHeader>

                <form class="mt-6 space-y-5" @submit.prevent="submitForm">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="td_year">Tahun</Label>
                            <Input
                                id="td_year"
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
                            <Label for="td_month">Bulan</Label>
                            <Select v-model="form.month" name="month">
                                <SelectTrigger id="td_month"
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

                    <div class="space-y-1.5">
                        <Label for="td_wd"
                            >X1 — {{ __('Jumlah Hari Kerja') }}</Label
                        >
                        <Input
                            id="td_wd"
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
                        <Label for="td_vol"
                            >X2 — {{ __('Volume Produksi') }}
                            <span class="text-xs text-slate-400"
                                >(unit/bulan)</span
                            ></Label
                        >
                        <Input
                            id="td_vol"
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
                        <Label for="td_mp"
                            >X3 — {{ __('Total Man Power') }}</Label
                        >
                        <Input
                            id="td_mp"
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
                        <Label for="td_ot"
                            >Y — {{ __('Total Index Overtime') }}</Label
                        >
                        <Input
                            id="td_ot"
                            v-model.number="form.overtime_index"
                            name="overtime_index"
                            type="number"
                            min="0"
                            class="font-mono tabular-nums"
                        />
                        <p
                            v-if="form.errors.overtime_index"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.overtime_index }}
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
