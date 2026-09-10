<script setup lang="ts">
import {
    AlertCircle,
    ArrowRight,
    Calculator,
    CheckCircle2,
    Clock,
    Factory,
    Gauge,
    Loader2,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { calculate as calculateRoute } from '@/routes/analytics/scenario';

export interface CategoryBreakdownItem {
    key: string;
    label: string;
    hours: number;
    cost: number;
    formatted_cost: string;
    percentage: number;
}

export interface PlanningCalculationResult {
    target_volume: number;
    period: 'weekly' | 'monthly' | 'quarterly';
    period_label: string;
    section_id: number;
    section_code: string;
    section_name: string;
    department_id: number | null;
    department_name: string;
    labor_factor: number;
    estimated_hours: number;
    estimated_cost: number;
    formatted_estimated_cost: string;
    headcount_needed: number;
    efficiency_pct: number;
    hourly_rate: number;
    categories: CategoryBreakdownItem[];
}

export interface SectionOption {
    id: number;
    code: string;
    name: string;
    department_id: number;
    department_name: string;
    labor_factor: number;
    historical_hours: number;
    historical_units: number;
    category_ratios: {
        production: number;
        tpm: number;
        project: number;
        others: number;
    };
}

interface Props {
    sections?: SectionOption[];
    initialResult?: PlanningCalculationResult | null;
}

const props = withDefaults(defineProps<Props>(), {
    sections: () => [],
    initialResult: null,
});

const emit = defineEmits<{
    (e: 'calculated', result: PlanningCalculationResult): void;
}>();

const { __ } = useTrans();

// Form state
const targetVolume = ref<number>(props.initialResult?.target_volume ?? 1500);
const period = ref<'weekly' | 'monthly' | 'quarterly'>(
    props.initialResult?.period ?? 'monthly',
);
const selectedSectionId = ref<number>(
    props.initialResult?.section_id ?? props.sections[0]?.id ?? 0,
);

const isCalculating = ref(false);
const errorMsg = ref<string | null>(null);

const result = ref<PlanningCalculationResult | null>(
    props.initialResult ?? null,
);

// Active section details
const activeSection = computed(() => {
    return (
        props.sections.find((s) => s.id === selectedSectionId.value) ??
        props.sections[0] ??
        null
    );
});

// Update selected section if prop changes and none is selected
watch(
    () => props.sections,
    (newSections) => {
        if (
            newSections.length > 0 &&
            (!selectedSectionId.value ||
                !newSections.some((s) => s.id === selectedSectionId.value))
        ) {
            selectedSectionId.value = newSections[0].id;
        }
    },
    { immediate: true },
);

// Sync initialResult if passed later
watch(
    () => props.initialResult,
    (newVal) => {
        if (newVal) {
            result.value = newVal;
            targetVolume.value = newVal.target_volume;
            period.value = newVal.period;
            selectedSectionId.value = newVal.section_id;
        }
    },
);

async function runCalculation() {
    if (
        !selectedSectionId.value &&
        props.sections.length > 0 &&
        props.sections[0]
    ) {
        selectedSectionId.value = props.sections[0].id;
    }

    if (!selectedSectionId.value) {
        return;
    }

    isCalculating.value = true;
    errorMsg.value = null;

    try {
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const res = await fetch(calculateRoute.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                type: 'planning',
                target_volume: Number(targetVolume.value),
                period: period.value,
                section_id: Number(selectedSectionId.value),
            }),
        });

        if (!res.ok) {
            const errJson = await res.json().catch(() => null);
            throw new Error(
                errJson?.message ??
                    `HTTP error ${res.status}: Gagal melakukan perhitungan.`,
            );
        }

        const json = await res.json();
        if (json.status === 'success' && json.data) {
            result.value = json.data as PlanningCalculationResult;
            emit('calculated', result.value);
        }
    } catch (err: unknown) {
        const error = err as Error;
        errorMsg.value =
            error?.message ??
            __('Terjadi kesalahan saat memproses perhitungan skenario.');
    } finally {
        isCalculating.value = false;
    }
}

function setQuickVolume(vol: number) {
    targetVolume.value = vol;
}
</script>

<template>
    <div class="space-y-5" data-test="production-calculator-panel">
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader class="pb-4">
                <div class="flex items-center justify-between gap-2">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <Calculator class="size-4 text-[#cc0000]" />
                        <span>{{
                            __('Kalkulator Perencanaan Volume Produksi')
                        }}</span>
                    </CardTitle>
                    <Badge
                        variant="outline"
                        class="border-slate-200 font-mono text-[11px] text-slate-600 dark:border-slate-700 dark:text-slate-300"
                    >
                        {{ __('Model Empiris OT-CapEx') }}
                    </Badge>
                </div>
                <CardDescription class="text-xs text-slate-500">
                    {{
                        __(
                            'Estimasi kebutuhan jam lembur dan penambahan tenaga kerja berdasarkan target unit kendaraan dan rasio historis seksi.',
                        )
                    }}
                </CardDescription>
            </CardHeader>

            <CardContent class="space-y-5">
                <!-- Form Inputs -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <!-- Target Volume -->
                    <div class="space-y-1.5 sm:col-span-1">
                        <label
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            {{ __('Target Volume Produksi') }}
                        </label>
                        <div class="relative">
                            <input
                                v-model.number="targetVolume"
                                type="number"
                                min="1"
                                max="50000"
                                step="50"
                                class="h-9 w-full rounded-md border border-slate-300 bg-white px-3 pr-12 font-mono text-xs font-bold text-slate-900 tabular-nums shadow-2xs focus:border-[#cc0000] focus:ring-1 focus:ring-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                data-test="input-target-volume"
                            />
                            <span
                                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[11px] font-semibold text-slate-400"
                            >
                                {{ __('Unit') }}
                            </span>
                        </div>
                        <!-- Quick volume chips -->
                        <div class="flex flex-wrap gap-1 pt-1">
                            <button
                                v-for="qVol in [500, 1000, 1500, 2000]"
                                :key="qVol"
                                type="button"
                                :data-test="'btn-quick-volume-' + qVol"
                                class="cursor-pointer rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-mono text-[10px] text-slate-600 tabular-nums hover:border-[#cc0000] hover:text-[#cc0000] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                @click="setQuickVolume(qVol)"
                            >
                                {{ qVol }}
                            </button>
                        </div>
                    </div>

                    <!-- Production Period -->
                    <div class="space-y-1.5 sm:col-span-1">
                        <label
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            {{ __('Periode Produksi') }}
                        </label>
                        <select
                            v-model="period"
                            class="h-9 w-full rounded-md border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 shadow-2xs focus:border-[#cc0000] focus:ring-1 focus:ring-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            data-test="select-production-period"
                        >
                            <option value="weekly">
                                {{ __('Mingguan (1 Minggu)') }}
                            </option>
                            <option value="monthly">
                                {{ __('Bulanan (1 Bulan)') }}
                            </option>
                            <option value="quarterly">
                                {{ __('Kuartalan (3 Bulan)') }}
                            </option>
                        </select>
                        <p class="text-[10px] text-slate-400">
                            {{
                                period === 'weekly'
                                    ? __('Limit mingguan: 20 jam / orang')
                                    : period === 'quarterly'
                                      ? __('Proyeksi agregat kuartal berjalan')
                                      : __('Basis 4.33 minggu per siklus bulan')
                            }}
                        </p>
                    </div>

                    <!-- Production Section -->
                    <div class="space-y-1.5 sm:col-span-1">
                        <label
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            {{ __('Seksi Produksi') }}
                        </label>
                        <select
                            v-model="selectedSectionId"
                            class="h-9 w-full rounded-md border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 shadow-2xs focus:border-[#cc0000] focus:ring-1 focus:ring-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            data-test="select-production-section"
                        >
                            <option
                                v-for="sec in sections"
                                :key="sec.id"
                                :value="sec.id"
                            >
                                {{ sec.code }} - {{ sec.name }}
                            </option>
                        </select>
                        <p
                            v-if="activeSection"
                            class="font-mono text-[10px] text-slate-500"
                        >
                            {{ __('Faktor Tenaga Kerja:') }}
                            <span
                                class="font-bold text-slate-700 dark:text-slate-300"
                                >{{
                                    activeSection.labor_factor.toFixed(4)
                                }}
                                jam/unit</span
                            >
                        </p>
                    </div>
                </div>

                <!-- Submit / Calculate Trigger -->
                <div
                    class="flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800"
                >
                    <div class="text-[11px] text-slate-500">
                        <span class="inline-flex items-center gap-1">
                            <Clock class="size-3 text-slate-400" />
                            {{ __('Berdasarkan riwayat 12 bulan terakhir') }}
                        </span>
                    </div>
                    <Button
                        type="button"
                        class="h-9 cursor-pointer bg-[#cc0000] px-4 text-xs font-semibold text-white shadow-xs hover:bg-[#b30000] active:scale-98"
                        :disabled="isCalculating || !selectedSectionId"
                        data-test="btn-run-calculator"
                        @click="runCalculation"
                    >
                        <Loader2
                            v-if="isCalculating"
                            class="mr-2 size-3.5 animate-spin"
                        />
                        <Calculator v-else class="mr-2 size-3.5" />
                        <span>{{ __('Hitung Kebutuhan') }}</span>
                    </Button>
                </div>

                <!-- Error Notification -->
                <div
                    v-if="errorMsg"
                    class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                >
                    <AlertCircle class="size-4 shrink-0 text-red-600" />
                    <span>{{ errorMsg }}</span>
                </div>

                <!-- Results Section (Visible after calculation) -->
                <div v-if="result" class="space-y-4 pt-1">
                    <div class="flex items-center justify-between">
                        <h4
                            class="text-xs font-bold tracking-wider text-slate-600 uppercase dark:text-slate-400"
                        >
                            {{ __('Hasil Estimasi & Kebutuhan Sumber Daya') }}
                        </h4>
                        <span
                            class="font-mono text-[11px] text-slate-500"
                            data-test="calc-section-label"
                        >
                            {{ result.section_name }} ({{
                                result.period_label
                            }})
                        </span>
                    </div>

                    <!-- 4 KPI Summary Cards -->
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <!-- Total Overtime Hours -->
                        <div
                            class="dark:bg-slate-850/60 rounded-lg border border-slate-200 bg-slate-50/70 p-3 shadow-2xs dark:border-slate-800"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-[11px] font-medium text-slate-500"
                                    >{{ __('Total Jam Lembur') }}</span
                                >
                                <Clock class="size-3.5 text-sky-600" />
                            </div>
                            <div
                                class="mt-1.5 font-mono text-lg font-bold text-slate-900 tabular-nums dark:text-white"
                                data-test="calc-total-hours"
                            >
                                {{
                                    result.estimated_hours.toLocaleString(
                                        'id-ID',
                                        {
                                            minimumFractionDigits: 1,
                                            maximumFractionDigits: 1,
                                        },
                                    )
                                }}
                                <span
                                    class="text-xs font-normal text-slate-500"
                                    >{{ __('Jam') }}</span
                                >
                            </div>
                            <div class="mt-1 text-[10px] text-slate-400">
                                {{ __('Labor factor:') }}
                                {{ result.labor_factor.toFixed(4) }}
                            </div>
                        </div>

                        <!-- Estimated Cost -->
                        <div
                            class="dark:bg-slate-850/60 rounded-lg border border-slate-200 bg-slate-50/70 p-3 shadow-2xs dark:border-slate-800"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-[11px] font-medium text-slate-500"
                                    >{{ __('Estimasi Biaya') }}</span
                                >
                                <TrendingUp class="size-3.5 text-emerald-600" />
                            </div>
                            <div
                                class="mt-1.5 font-mono text-lg font-bold text-slate-900 tabular-nums dark:text-white"
                                data-test="calc-estimated-cost"
                            >
                                {{ result.formatted_estimated_cost }}
                            </div>
                            <div
                                class="mt-1 font-mono text-[10px] text-slate-400"
                            >
                                @ Rp
                                {{
                                    result.hourly_rate.toLocaleString('id-ID')
                                }}/jam
                            </div>
                        </div>

                        <!-- Headcount Needed -->
                        <div
                            class="dark:bg-slate-850/60 rounded-lg border border-slate-200 bg-slate-50/70 p-3 shadow-2xs dark:border-slate-800"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-[11px] font-medium text-slate-500"
                                    >{{ __('Karyawan Dibutuhkan') }}</span
                                >
                                <Users class="size-3.5 text-[#cc0000]" />
                            </div>
                            <div
                                class="mt-1.5 font-mono text-lg font-bold text-[#cc0000] tabular-nums dark:text-red-400"
                                data-test="calc-headcount-needed"
                            >
                                {{ result.headcount_needed }}
                                <span
                                    class="text-xs font-normal text-slate-500"
                                    >{{ __('Orang') }}</span
                                >
                            </div>
                            <div class="mt-1 text-[10px] text-slate-400">
                                {{ __('Sesuai limit kebijakan') }}
                            </div>
                        </div>

                        <!-- Efficiency -->
                        <div
                            class="dark:bg-slate-850/60 rounded-lg border border-slate-200 bg-slate-50/70 p-3 shadow-2xs dark:border-slate-800"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-[11px] font-medium text-slate-500"
                                    >{{ __('Efisiensi Relatif') }}</span
                                >
                                <Gauge class="size-3.5 text-amber-500" />
                            </div>
                            <div
                                class="mt-1.5 font-mono text-lg font-bold text-emerald-600 tabular-nums dark:text-emerald-400"
                                data-test="calc-efficiency-pct"
                            >
                                {{ result.efficiency_pct.toFixed(1) }}%
                            </div>
                            <div class="mt-1 text-[10px] text-slate-400">
                                {{ __('vs Baseline historis') }}
                            </div>
                        </div>
                    </div>

                    <!-- Category Breakdown Table -->
                    <div class="space-y-2">
                        <div
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            {{
                                __(
                                    'Rincian Estimasi Alokasi Berdasarkan Kategori Beban',
                                )
                            }}
                        </div>
                        <div
                            class="overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                        >
                            <table
                                class="w-full border-collapse text-left text-xs"
                                data-test="category-breakdown-table"
                            >
                                <thead>
                                    <tr
                                        class="dark:bg-slate-850 border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold tracking-wider text-slate-500 uppercase dark:border-slate-800"
                                    >
                                        <th class="p-2.5">
                                            {{ __('Kategori Beban') }}
                                        </th>
                                        <th class="p-2.5 text-right">
                                            {{ __('Proporsi') }}
                                        </th>
                                        <th class="p-2.5 text-right">
                                            {{ __('Estimasi Jam') }}
                                        </th>
                                        <th class="p-2.5 text-right">
                                            {{ __('Estimasi Biaya') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-slate-800"
                                >
                                    <tr
                                        v-for="cat in result.categories"
                                        :key="cat.key"
                                        class="transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-800/40"
                                    >
                                        <td class="p-2.5">
                                            <div
                                                class="flex items-center gap-1.5"
                                            >
                                                <span
                                                    v-if="cat.key === 'project'"
                                                    class="inline-flex items-center rounded-full border border-sky-300 bg-sky-100 px-2 py-0.5 text-[10px] font-bold text-sky-800 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-300"
                                                >
                                                    CapEx
                                                </span>
                                                <span
                                                    v-else
                                                    class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                >
                                                    OpEx
                                                </span>
                                                <span
                                                    class="font-medium text-slate-800 dark:text-slate-200"
                                                >
                                                    {{ cat.label }}
                                                </span>
                                            </div>
                                        </td>
                                        <td
                                            class="p-2.5 text-right font-mono text-slate-600 tabular-nums dark:text-slate-300"
                                        >
                                            {{ cat.percentage.toFixed(1) }}%
                                        </td>
                                        <td
                                            class="p-2.5 text-right font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                                        >
                                            {{ cat.hours.toFixed(1) }}
                                            {{ __('Jam') }}
                                        </td>
                                        <td
                                            class="p-2.5 text-right font-mono font-semibold text-slate-900 tabular-nums dark:text-white"
                                        >
                                            {{ cat.formatted_cost }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
