<script setup lang="ts">
import {
    AlertCircle,
    Banknote,
    CircleDollarSign,
    DollarSign,
    RefreshCw,
    Wallet,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import BudgetVsActualBarChart, {
    type BudgetVsActualData,
} from '@/components/analytics/BudgetVsActualBarChart.vue';
import CostBreakdownTable from '@/components/analytics/CostBreakdownTable.vue';
import CostByDepartmentChart, {
    type DepartmentCostItem,
} from '@/components/analytics/CostByDepartmentChart.vue';
import CostTrendStackedChart, {
    type MonthlyTrendData,
} from '@/components/analytics/CostTrendStackedChart.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { cost as costRoute } from '@/routes/analytics';

export interface CostData {
    kpi: {
        total_cost: number;
        formatted_total_cost: string;
        planned_budget: number;
        formatted_planned_budget: string;
        remaining_budget: number;
        formatted_remaining_budget: string;
        budget_consumption_pct: number;
        has_budget: boolean;
        avg_cost_per_employee: number;
        formatted_avg_cost_per_employee: string;
        active_employee_count: number;
        capex_cost: number;
        formatted_capex_cost: string;
        opex_cost: number;
        formatted_opex_cost: string;
        capex_ratio_pct: number;
    };
    department_costs: DepartmentCostItem[];
    monthly_trend_6m: MonthlyTrendData;
    budget_vs_actual: BudgetVsActualData;
    scope: {
        department_id: number | null;
        department_name: string;
        start_date: string;
        end_date: string;
        fiscal_year: number;
        fiscal_month: number;
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

const costData = ref<CostData | null>((props.initialData as CostData) ?? null);
const isLoading = ref(false);
const errorMsg = ref<string | null>(null);

async function fetchCostData() {
    isLoading.value = true;
    errorMsg.value = null;

    try {
        const queryParams: Record<string, string> = {};
        if (
            props.filters?.department_id !== undefined &&
            props.filters?.department_id !== null
        ) {
            queryParams.department_id = String(props.filters.department_id);
        }
        if (props.filters?.start_date) {
            queryParams.start_date = props.filters.start_date;
        }
        if (props.filters?.end_date) {
            queryParams.end_date = props.filters.end_date;
        }

        const url = costRoute.url({ query: queryParams });
        const res = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!res.ok) {
            throw new Error(`HTTP error ${res.status}`);
        }

        const data = (await res.json()) as CostData;
        costData.value = data;
    } catch {
        errorMsg.value = __(
            'Gagal memuat data analisis biaya. Silakan coba kembali.',
        );
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.initialData,
    (newVal) => {
        if (newVal) {
            costData.value = newVal as CostData;
        }
    },
    { deep: true },
);

onMounted(() => {
    if (!costData.value) {
        fetchCostData();
    }
});

watch(
    () => props.filters,
    () => {
        fetchCostData();
    },
    { deep: true },
);

const kpi = computed(() => costData.value?.kpi);
</script>

<template>
    <div class="space-y-6" data-test="tab-cost-content">
        <!-- Error Banner with Retry Action -->
        <div
            v-if="errorMsg"
            class="flex items-center justify-between rounded-lg border border-red-200 bg-red-50 p-4 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
            data-test="cost-error-banner"
        >
            <div class="flex items-center gap-2">
                <AlertCircle class="size-4 shrink-0" />
                <span>{{ errorMsg }}</span>
            </div>
            <Button
                variant="outline"
                size="sm"
                class="border-red-300 bg-white text-xs hover:bg-red-50 dark:border-red-800 dark:bg-slate-900"
                data-test="btn-retry-cost"
                @click="fetchCostData"
            >
                <RefreshCw class="mr-1 size-3" />
                {{ __('Coba Lagi') }}
            </Button>
        </div>

        <!-- 4 Financial KPI Cards Row (E09-08) -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- 1. Total Biaya Lembur -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-total-cost"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Total Biaya Lembur') }}
                    </CardTitle>
                    <Banknote class="size-4 text-[#cc0000]" />
                </CardHeader>
                <CardContent>
                    <div
                        v-if="isLoading"
                        class="h-8 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-800"
                    />
                    <div
                        v-else
                        class="font-mono text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white"
                    >
                        {{ kpi?.formatted_total_cost ?? 'Rp 0' }}
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Akumulasi realisasi bulan berjalan') }}
                    </p>
                </CardContent>
            </Card>

            <!-- 2. Sisa Anggaran (Rp) -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-remaining-budget"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Sisa Anggaran') }}
                    </CardTitle>
                    <Wallet
                        class="size-4"
                        :class="
                            (kpi?.budget_consumption_pct ?? 0) > 100
                                ? 'text-[#cc0000]'
                                : 'text-emerald-600'
                        "
                    />
                </CardHeader>
                <CardContent>
                    <div
                        v-if="isLoading"
                        class="h-8 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-800"
                    />
                    <div
                        v-else
                        class="font-mono text-2xl font-bold tracking-tight tabular-nums"
                        :class="
                            (kpi?.budget_consumption_pct ?? 0) > 100
                                ? 'text-[#cc0000] dark:text-red-400'
                                : 'text-emerald-600 dark:text-emerald-400'
                        "
                    >
                        {{ kpi?.formatted_remaining_budget ?? 'Rp 0' }}
                    </div>

                    <!-- Mini Progress Bar (% Consumed) -->
                    <div
                        class="mt-2 h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800"
                    >
                        <div
                            class="h-1.5 rounded-full transition-all duration-500"
                            :class="
                                (kpi?.budget_consumption_pct ?? 0) > 100
                                    ? 'bg-[#cc0000]'
                                    : (kpi?.budget_consumption_pct ?? 0) > 85
                                      ? 'bg-amber-500'
                                      : 'bg-emerald-500'
                            "
                            :style="{
                                width: `${Math.min(100, kpi?.budget_consumption_pct ?? 0)}%`,
                            }"
                        />
                    </div>
                    <p
                        class="mt-1 font-mono text-[11px] text-slate-500 tabular-nums"
                    >
                        {{ kpi?.budget_consumption_pct ?? 0 }}%
                        {{ __('anggaran terkonsumsi') }}
                    </p>
                </CardContent>
            </Card>

            <!-- 3. Rata-rata Biaya per Karyawan -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-avg-cost-per-employee"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Rata-rata Biaya / Karyawan') }}
                    </CardTitle>
                    <CircleDollarSign class="size-4 text-sky-600" />
                </CardHeader>
                <CardContent>
                    <div
                        v-if="isLoading"
                        class="h-8 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-800"
                    />
                    <div
                        v-else
                        class="font-mono text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white"
                    >
                        {{ kpi?.formatted_avg_cost_per_employee ?? 'Rp 0' }}
                    </div>
                    <p
                        class="mt-1 font-mono text-xs text-slate-500 tabular-nums"
                    >
                        {{ __('Berdasarkan') }}
                        {{ kpi?.active_employee_count ?? 0 }}
                        {{ __('headcount lembur aktif') }}
                    </p>
                </CardContent>
            </Card>

            <!-- 4. Rasio Biaya CapEx (Replacing ROI per BA Spec) -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-capex-ratio"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Rasio Biaya CapEx') }}
                    </CardTitle>
                    <DollarSign class="size-4 text-violet-600" />
                </CardHeader>
                <CardContent>
                    <div
                        v-if="isLoading"
                        class="h-8 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-800"
                    />
                    <div
                        v-else
                        class="font-mono text-2xl font-bold tracking-tight text-violet-600 tabular-nums dark:text-violet-400"
                    >
                        {{ kpi?.capex_ratio_pct ?? 0 }}%
                    </div>
                    <p
                        class="mt-1 flex items-center gap-1 text-xs text-slate-500"
                    >
                        <Badge
                            variant="secondary"
                            class="bg-violet-50 text-[10px] font-semibold text-violet-700 dark:bg-violet-950/40 dark:text-violet-300"
                        >
                            {{ __('Terkapitalisasi') }}
                        </Badge>
                        <span class="font-mono text-[11px] tabular-nums">{{
                            kpi?.formatted_capex_cost ?? 'Rp 0'
                        }}</span>
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- 3 Financial Charts Grid (E09-08) -->
        <div class="space-y-6">
            <!-- Row 1: Biaya Lembur per Departemen (Horizontal Bar) -->
            <CostByDepartmentChart
                :department-costs="costData?.department_costs ?? []"
                :loading="isLoading"
                :empty="
                    !costData?.department_costs ||
                    costData.department_costs.length === 0
                "
            />

            <!-- Row 2: Two-column grid (Tren Biaya 6 Bulan & Anggaran vs Realisasi) -->
            <div class="grid gap-6 lg:grid-cols-2">
                <CostTrendStackedChart
                    :trend-data="costData?.monthly_trend_6m"
                    :loading="isLoading"
                    :empty="
                        !costData?.monthly_trend_6m?.labels ||
                        costData.monthly_trend_6m.labels.length === 0
                    "
                />

                <BudgetVsActualBarChart
                    :data="costData?.budget_vs_actual"
                    :loading="isLoading"
                    :empty="
                        !costData?.budget_vs_actual?.labels ||
                        costData.budget_vs_actual.labels.length === 0
                    "
                />
            </div>

            <!-- Row 3: High-Density Cost Breakdown Audit Table -->
            <CostBreakdownTable
                :department-costs="costData?.department_costs ?? []"
                :loading="isLoading"
            />
        </div>
    </div>
</template>
