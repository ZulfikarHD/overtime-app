<script setup lang="ts">
import {
    Activity,
    AlertCircle,
    ArrowDownRight,
    ArrowUpRight,
    Calendar,
    Clock,
    Filter,
    GitCompare,
    Minus,
    RefreshCw,
    TrendingDown,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import BestPracticeCards, {
    type BestPracticeCardItem,
    type PerformerInfo,
} from '@/components/analytics/BestPracticeCards.vue';
import DepartmentBenchmarkChart, {
    type DepartmentBenchmarkItem,
} from '@/components/analytics/DepartmentBenchmarkChart.vue';
import PeriodComparisonBarChart, {
    type PeriodComparisonChartData,
} from '@/components/analytics/PeriodComparisonBarChart.vue';
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
import { comparison as comparisonRoute } from '@/routes/analytics';

export interface ComparisonDataset {
    comparison_type: 'yoy' | 'mom' | 'qoq' | 'department';
    base_period: string;
    compare_period: string;
    base_period_label: string;
    compare_period_label: string;
    kpi: {
        total_hours: {
            base_value: number;
            compare_value: number;
            diff_value: number;
            pct_change: number;
            direction: 'up' | 'down' | 'stable';
            formatted_diff: string;
            is_zero_baseline: boolean;
        };
        total_cost: {
            base_value: number;
            compare_value: number;
            diff_value: number;
            pct_change: number;
            direction: 'up' | 'down' | 'stable';
            formatted_diff: string;
            formatted_base: string;
            formatted_compare: string;
            is_zero_baseline: boolean;
        };
        efficiency: {
            base_hours_per_unit: number;
            compare_hours_per_unit: number;
            diff_efficiency: number;
            pct_change: number;
            direction: 'up' | 'down' | 'stable';
            formatted_diff: string;
            base_units_per_hour: number;
            compare_units_per_hour: number;
            erp_connected: boolean;
        };
        headcount: {
            base_value: number;
            compare_value: number;
            diff_value: number;
            pct_change: number;
            direction: 'up' | 'down' | 'stable';
            formatted_diff: string;
        };
    };
    period_comparison_chart: PeriodComparisonChartData;
    department_benchmarks: DepartmentBenchmarkItem[];
    performers: {
        best: PerformerInfo | null;
        average: PerformerInfo | null;
        worst: PerformerInfo | null;
    };
    best_practice_cards: BestPracticeCardItem[];
    scope: {
        department_id: number | null;
        department_name: string;
        is_manager_scoped: boolean;
    };
}

interface Props {
    filters?: {
        department_id?: string | number | null;
        start_date?: string;
        end_date?: string;
    };
    initialData?: unknown;
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({
        department_id: 'all',
        start_date: '',
        end_date: '',
    }),
    initialData: undefined,
});

const { __ } = useTrans();

const comparisonType = ref<'yoy' | 'mom' | 'qoq' | 'department'>('yoy');

// Initial periods based on filters or current date
const now = new Date();
const currentYm = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
const lastYearYm = `${now.getFullYear() - 1}-${String(now.getMonth() + 1).padStart(2, '0')}`;

const basePeriod = ref<string>(
    props.filters.start_date ? props.filters.start_date.slice(0, 7) : currentYm,
);
const comparePeriod = ref<string>(lastYearYm);

const comparisonData = ref<ComparisonDataset | null>(null);
const isLoading = ref(false);
const errorMsg = ref<string | null>(null);

const parsedInitialData = computed<ComparisonDataset | null>(() => {
    if (!props.initialData || typeof props.initialData !== 'object') {
        return null;
    }
    const d = props.initialData as Partial<ComparisonDataset>;
    if (
        d.kpi &&
        d.period_comparison_chart &&
        Array.isArray(d.department_benchmarks)
    ) {
        return d as ComparisonDataset;
    }
    return null;
});

function adjustComparePeriodForType(
    newType: 'yoy' | 'mom' | 'qoq' | 'department',
) {
    if (!basePeriod.value) return;

    const [yearStr, monthStr] = basePeriod.value.split('-');
    const year = parseInt(yearStr, 10);
    const month = parseInt(monthStr, 10);

    if (isNaN(year) || isNaN(month)) return;

    if (newType === 'yoy') {
        comparePeriod.value = `${year - 1}-${String(month).padStart(2, '0')}`;
    } else if (newType === 'mom' || newType === 'department') {
        const prevMonth = month === 1 ? 12 : month - 1;
        const prevYear = month === 1 ? year - 1 : year;
        comparePeriod.value = `${prevYear}-${String(prevMonth).padStart(2, '0')}`;
    } else if (newType === 'qoq') {
        let qMonth = month - 3;
        let qYear = year;
        if (qMonth <= 0) {
            qMonth += 12;
            qYear -= 1;
        }
        comparePeriod.value = `${qYear}-${String(qMonth).padStart(2, '0')}`;
    }
}

async function fetchComparisonData() {
    isLoading.value = true;
    errorMsg.value = null;

    try {
        const queryParams: Record<string, string | number> = {
            type: comparisonType.value,
            base_period: basePeriod.value,
            compare_period: comparePeriod.value,
        };

        if (
            props.filters.department_id &&
            props.filters.department_id !== 'all'
        ) {
            queryParams.department_id = props.filters.department_id;
        }

        const url =
            typeof comparisonRoute !== 'undefined' && comparisonRoute.url
                ? comparisonRoute.url({ query: queryParams })
                : `/analytics/comparison?${new URLSearchParams(queryParams as any).toString()}`;

        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(
                `HTTP error ${response.status}: ${__('Gagal memuat data perbandingan periode.')}`,
            );
        }

        const data = (await response.json()) as ComparisonDataset;
        comparisonData.value = data;
    } catch (err: unknown) {
        const e = err as Error;
        errorMsg.value =
            e.message ||
            __('Terjadi kesalahan saat memuat data perbandingan periode.');
    } finally {
        isLoading.value = false;
    }
}

watch(comparisonType, (newType) => {
    adjustComparePeriodForType(newType);
    fetchComparisonData();
});

watch(
    () => props.filters.department_id,
    () => {
        fetchComparisonData();
    },
);

onMounted(() => {
    if (parsedInitialData.value) {
        comparisonData.value = parsedInitialData.value;
        comparisonType.value = parsedInitialData.value.comparison_type;
        basePeriod.value = parsedInitialData.value.base_period;
        comparePeriod.value = parsedInitialData.value.compare_period;
    } else {
        fetchComparisonData();
    }
});
</script>

<template>
    <div class="space-y-6" data-test="tab-comparison-content">
        <!-- Configuration Control Bar -->
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
            data-test="comparison-controls-card"
        >
            <CardHeader class="pb-3">
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                        >
                            <GitCompare class="size-4 text-[#cc0000]" />
                            <span>{{
                                __('Konfigurasi Perbandingan Periode')
                            }}</span>
                        </CardTitle>
                        <CardDescription>
                            {{
                                __(
                                    'Pilih metode komparasi dan tentukan rentang bulan basis terhadap pembanding.',
                                )
                            }}
                        </CardDescription>
                    </div>
                    <Badge
                        variant="outline"
                        class="w-fit text-xs text-slate-600 dark:text-slate-300"
                    >
                        {{
                            comparisonData?.scope?.department_name ||
                            __('Pabrik Konsolidasi')
                        }}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Comparison Type Dropdown -->
                    <div class="flex flex-col gap-1">
                        <label
                            class="text-2xs font-semibold tracking-wider text-slate-500 uppercase"
                        >
                            {{ __('Metode Komparasi') }}
                        </label>
                        <select
                            v-model="comparisonType"
                            class="h-9 rounded-md border border-slate-300 bg-white px-3 text-xs font-medium text-slate-800 shadow-2xs focus:border-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            data-test="select-comparison-type"
                        >
                            <option value="yoy">
                                {{ __('Year-over-Year (YoY) — Tahunan') }}
                            </option>
                            <option value="mom">
                                {{ __('Month-over-Month (MoM) — Bulanan') }}
                            </option>
                            <option value="qoq">
                                {{
                                    __('Quarter-over-Quarter (QoQ) — Kuartalan')
                                }}
                            </option>
                            <option value="department">
                                {{ __('Benchmarking Antar Departemen') }}
                            </option>
                        </select>
                    </div>

                    <!-- Base Period Month Picker -->
                    <div class="flex flex-col gap-1">
                        <label
                            class="text-2xs font-semibold tracking-wider text-slate-500 uppercase"
                        >
                            {{ __('Periode Basis') }}
                        </label>
                        <input
                            v-model="basePeriod"
                            type="month"
                            class="h-9 rounded-md border border-slate-300 bg-white px-3 font-mono text-xs font-medium text-slate-800 tabular-nums shadow-2xs focus:border-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            data-test="input-base-period"
                            @change="fetchComparisonData"
                        />
                    </div>

                    <!-- Compare With Month Picker -->
                    <div class="flex flex-col gap-1">
                        <label
                            class="text-2xs font-semibold tracking-wider text-slate-500 uppercase"
                        >
                            {{ __('Bandingkan Dengan') }}
                        </label>
                        <input
                            v-model="comparePeriod"
                            type="month"
                            class="h-9 rounded-md border border-slate-300 bg-white px-3 font-mono text-xs font-medium text-slate-800 tabular-nums shadow-2xs focus:border-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            data-test="input-compare-period"
                            @change="fetchComparisonData"
                        />
                    </div>

                    <!-- Refresh Button -->
                    <div class="flex flex-col justify-end pt-5">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 cursor-pointer gap-1.5 text-xs text-slate-700 hover:text-slate-900 dark:text-slate-300"
                            :disabled="isLoading"
                            @click="fetchComparisonData"
                            data-test="btn-refresh-comparison"
                        >
                            <RefreshCw
                                class="size-3.5"
                                :class="{ 'animate-spin': isLoading }"
                            />
                            <span>{{ __('Perbarui') }}</span>
                        </Button>
                    </div>
                </div>

                <!-- Error Notice -->
                <div
                    v-if="errorMsg"
                    class="mt-3 flex items-center gap-2 rounded-md border border-red-200 bg-red-50 p-2.5 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                    data-test="comparison-error-banner"
                >
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{{ errorMsg }}</span>
                </div>
            </CardContent>
        </Card>

        <!-- Comparison KPI Cards Row (E09-12 Acceptance Criteria) -->
        <div
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
            data-test="comparison-kpi-grid"
        >
            <!-- 1. Total Hours Change -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-total-hours"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Perubahan Jam Lembur') }}
                    </CardTitle>
                    <component
                        :is="
                            comparisonData?.kpi?.total_hours?.direction ===
                            'down'
                                ? TrendingDown
                                : comparisonData?.kpi?.total_hours
                                        ?.direction === 'up'
                                  ? TrendingUp
                                  : Minus
                        "
                        class="size-4"
                        :class="
                            comparisonData?.kpi?.total_hours?.direction ===
                            'down'
                                ? 'text-emerald-600'
                                : comparisonData?.kpi?.total_hours
                                        ?.direction === 'up'
                                  ? 'text-red-600'
                                  : 'text-slate-400'
                        "
                    />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight tabular-nums"
                        :class="
                            comparisonData?.kpi?.total_hours?.direction ===
                            'down'
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : comparisonData?.kpi?.total_hours
                                        ?.direction === 'up'
                                  ? 'text-red-600 dark:text-red-400'
                                  : 'text-slate-700 dark:text-slate-200'
                        "
                    >
                        {{
                            (comparisonData?.kpi?.total_hours?.pct_change ??
                                0) >= 0
                                ? '+'
                                : ''
                        }}{{
                            comparisonData?.kpi?.total_hours?.pct_change ?? 0
                        }}%
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{
                            comparisonData?.kpi?.total_hours?.formatted_diff ||
                            __('0.0 jam vs pembanding')
                        }}
                    </p>
                    <div
                        class="text-2xs mt-2 flex items-center justify-between border-t border-slate-100 pt-1.5 font-mono text-slate-400 dark:border-slate-800"
                    >
                        <span
                            >{{ comparisonData?.base_period_label }}:
                            {{
                                comparisonData?.kpi?.total_hours?.base_value?.toFixed(
                                    1,
                                ) ?? '0.0'
                            }}
                            jam</span
                        >
                        <span
                            >{{ comparisonData?.compare_period_label }}:
                            {{
                                comparisonData?.kpi?.total_hours?.compare_value?.toFixed(
                                    1,
                                ) ?? '0.0'
                            }}
                            jam</span
                        >
                    </div>
                </CardContent>
            </Card>

            <!-- 2. Total Cost Change -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-total-cost"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Perubahan Biaya (Rp)') }}
                    </CardTitle>
                    <component
                        :is="
                            comparisonData?.kpi?.total_cost?.direction ===
                            'down'
                                ? TrendingDown
                                : comparisonData?.kpi?.total_cost?.direction ===
                                    'up'
                                  ? TrendingUp
                                  : Minus
                        "
                        class="size-4"
                        :class="
                            comparisonData?.kpi?.total_cost?.direction ===
                            'down'
                                ? 'text-emerald-600'
                                : comparisonData?.kpi?.total_cost?.direction ===
                                    'up'
                                  ? 'text-red-600'
                                  : 'text-slate-400'
                        "
                    />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight tabular-nums"
                        :class="
                            comparisonData?.kpi?.total_cost?.direction ===
                            'down'
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : comparisonData?.kpi?.total_cost?.direction ===
                                    'up'
                                  ? 'text-red-600 dark:text-red-400'
                                  : 'text-slate-700 dark:text-slate-200'
                        "
                    >
                        {{
                            (comparisonData?.kpi?.total_cost?.pct_change ??
                                0) >= 0
                                ? '+'
                                : ''
                        }}{{
                            comparisonData?.kpi?.total_cost?.pct_change ?? 0
                        }}%
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{
                            comparisonData?.kpi?.total_cost?.formatted_diff ||
                            __('Selisih Rp 0')
                        }}
                    </p>
                    <div
                        class="text-2xs mt-2 flex items-center justify-between border-t border-slate-100 pt-1.5 font-mono text-slate-400 dark:border-slate-800"
                    >
                        <span
                            >{{ comparisonData?.base_period_label }}:
                            {{
                                comparisonData?.kpi?.total_cost
                                    ?.formatted_base ?? 'Rp 0'
                            }}</span
                        >
                        <span
                            >{{ comparisonData?.compare_period_label }}:
                            {{
                                comparisonData?.kpi?.total_cost
                                    ?.formatted_compare ?? 'Rp 0'
                            }}</span
                        >
                    </div>
                </CardContent>
            </Card>

            <!-- 3. Efficiency Change -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-efficiency"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Perubahan Efisiensi') }}
                    </CardTitle>
                    <component
                        :is="
                            comparisonData?.kpi?.efficiency?.direction === 'up'
                                ? TrendingUp
                                : comparisonData?.kpi?.efficiency?.direction ===
                                    'down'
                                  ? TrendingDown
                                  : Minus
                        "
                        class="size-4"
                        :class="
                            comparisonData?.kpi?.efficiency?.direction === 'up'
                                ? 'text-sky-600'
                                : comparisonData?.kpi?.efficiency?.direction ===
                                    'down'
                                  ? 'text-amber-600'
                                  : 'text-slate-400'
                        "
                    />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-sky-600 tabular-nums dark:text-sky-400"
                    >
                        {{
                            (comparisonData?.kpi?.efficiency?.pct_change ??
                                0) <= 0
                                ? '-'
                                : '+'
                        }}{{
                            Math.abs(
                                comparisonData?.kpi?.efficiency?.pct_change ??
                                    0,
                            )
                        }}%
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{
                            comparisonData?.kpi?.efficiency?.formatted_diff ||
                            __('Rasio jam lembur per unit kendaraan')
                        }}
                    </p>
                    <div
                        class="text-2xs mt-2 flex items-center justify-between border-t border-slate-100 pt-1.5 font-mono text-slate-400 dark:border-slate-800"
                    >
                        <span
                            >{{
                                comparisonData?.kpi?.efficiency?.base_hours_per_unit?.toFixed(
                                    3,
                                ) ?? '0'
                            }}
                            jam/unit</span
                        >
                        <span class="text-slate-400"
                            >vs
                            {{
                                comparisonData?.kpi?.efficiency?.compare_hours_per_unit?.toFixed(
                                    3,
                                ) ?? '0'
                            }}
                            jam/unit</span
                        >
                    </div>
                </CardContent>
            </Card>

            <!-- 4. Employee Count Change -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-headcount"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Headcount Lembur') }}
                    </CardTitle>
                    <Users class="size-4 text-slate-500" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white"
                    >
                        {{
                            comparisonData?.kpi?.headcount?.formatted_diff ||
                            '0 orang'
                        }}
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Perubahan jumlah teknisi aktif lembur') }}
                    </p>
                    <div
                        class="text-2xs mt-2 flex items-center justify-between border-t border-slate-100 pt-1.5 font-mono text-slate-400 dark:border-slate-800"
                    >
                        <span
                            >{{ comparisonData?.base_period_label }}:
                            {{
                                comparisonData?.kpi?.headcount?.base_value ?? 0
                            }}
                            org</span
                        >
                        <span
                            >{{ comparisonData?.compare_period_label }}:
                            {{
                                comparisonData?.kpi?.headcount?.compare_value ??
                                0
                            }}
                            org</span
                        >
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Period Comparison Bar Chart (Dual Bar + Line Overlay) -->
        <PeriodComparisonBarChart
            :data="comparisonData?.period_comparison_chart"
            :comparison-type="comparisonType"
            :loading="isLoading"
            :empty="!comparisonData?.period_comparison_chart?.labels?.length"
        />

        <!-- Department Benchmarking Chart -->
        <DepartmentBenchmarkChart
            :benchmarks="comparisonData?.department_benchmarks"
            :base-label="comparisonData?.base_period_label"
            :compare-label="comparisonData?.compare_period_label"
            :is-manager-scoped="comparisonData?.scope?.is_manager_scoped"
            :loading="isLoading"
            :empty="!comparisonData?.department_benchmarks?.length"
        />

        <!-- Best Practices & Performer Summaries -->
        <BestPracticeCards
            :performers="comparisonData?.performers"
            :best-practice-cards="comparisonData?.best_practice_cards"
            :loading="isLoading"
        />
    </div>
</template>
