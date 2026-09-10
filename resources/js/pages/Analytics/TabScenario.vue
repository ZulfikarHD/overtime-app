<script setup lang="ts">
import {
    Activity,
    AlertCircle,
    BookmarkPlus,
    CheckCircle2,
    Clock,
    DollarSign,
    FolderKanban,
    Layers,
    RefreshCw,
    ShieldAlert,
    Sliders,
    Sparkles,
    TrendingUp,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import ProductionCalculatorPanel, {
    type PlanningCalculationResult,
    type SectionOption,
} from '@/components/analytics/ProductionCalculatorPanel.vue';
import SavedScenariosDrawer, {
    type SavedScenarioItem,
} from '@/components/analytics/SavedScenariosDrawer.vue';
import ScenarioBuilderPanel, {
    type DepartmentOption,
    type ScenarioBuilderResult,
} from '@/components/analytics/ScenarioBuilderPanel.vue';
import ScenarioComparisonChart from '@/components/analytics/ScenarioComparisonChart.vue';
import ChartSkeleton from '@/components/charts/ChartSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import { scenario as scenarioRoute } from '@/routes/analytics';

export interface ScenarioData {
    baseline: {
        department_id: number | null;
        department_name: string;
        actual_hours: number;
        actual_cost: number;
        formatted_actual_cost: string;
        budget_cost: number;
        formatted_budget_cost: string;
        budget_hours: number;
        burn_index_pct: number;
        active_headcount: number;
        avg_hourly_rate: number;
        formatted_avg_hourly_rate: string;
        safety_risk_score: number;
    };
    sections: SectionOption[];
    departments: DepartmentOption[];
    policy: {
        weekly_soft_limit_hours: number;
        consecutive_weeks_alert: number;
    };
    correlation_r: number;
    saved_scenarios: SavedScenarioItem[];
    initial_calculator_result: PlanningCalculationResult | null;
    initial_builder_result: ScenarioBuilderResult | null;
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

const defaultScenarioData: ScenarioData = {
    baseline: {
        department_id: null,
        department_name: 'Semua Departemen',
        actual_hours: 0,
        actual_cost: 0,
        formatted_actual_cost: 'Rp 0',
        budget_cost: 0,
        formatted_budget_cost: 'Rp 0',
        budget_hours: 0,
        burn_index_pct: 0,
        active_headcount: 0,
        avg_hourly_rate: 50000,
        formatted_avg_hourly_rate: 'Rp 50.000',
        safety_risk_score: 0,
    },
    sections: [],
    departments: [],
    policy: {
        weekly_soft_limit_hours: 20,
        consecutive_weeks_alert: 3,
    },
    correlation_r: 0.78,
    saved_scenarios: [],
    initial_calculator_result: null,
    initial_builder_result: null,
    scope: {
        department_id: null,
        department_name: 'Semua',
        start_date: '',
        end_date: '',
        fiscal_year: 2026,
        fiscal_month: 9,
    },
};

const scenarioData = ref<ScenarioData>(
    (props.initialData as ScenarioData) ?? defaultScenarioData,
);
const isLoading = ref(false);
const errorMsg = ref<string | null>(null);

// Active Builder & Calculator State
const activeBuilderResult = ref<ScenarioBuilderResult | null>(
    scenarioData.value.initial_builder_result ?? null,
);
const activeCalculatorResult = ref<PlanningCalculationResult | null>(
    scenarioData.value.initial_calculator_result ?? null,
);

// Saved Scenarios Drawer State
const savedDrawerOpen = ref(false);
const savedScenariosList = ref<SavedScenarioItem[]>(
    scenarioData.value.saved_scenarios ?? [],
);

// Sync with initialData changes
watch(
    () => props.initialData,
    (newVal) => {
        if (newVal) {
            scenarioData.value = newVal as ScenarioData;
            activeBuilderResult.value =
                (newVal as ScenarioData).initial_builder_result ?? null;
            activeCalculatorResult.value =
                (newVal as ScenarioData).initial_calculator_result ?? null;
            savedScenariosList.value =
                (newVal as ScenarioData).saved_scenarios ?? [];
        }
    },
);

async function fetchScenarioData() {
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

        const url = scenarioRoute.url({ query: queryParams });
        const res = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!res.ok) {
            throw new Error(`HTTP error ${res.status}`);
        }

        const data = (await res.json()) as ScenarioData;
        scenarioData.value = data;
        activeBuilderResult.value = data.initial_builder_result;
        activeCalculatorResult.value = data.initial_calculator_result;
        savedScenariosList.value = data.saved_scenarios ?? [];
    } catch {
        errorMsg.value = __(
            'Gagal memuat data simulasi skenario. Silakan coba kembali.',
        );
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => [
        props.filters?.department_id,
        props.filters?.start_date,
        props.filters?.end_date,
    ],
    () => {
        fetchScenarioData();
    },
);

onMounted(() => {
    if (!scenarioData.value) {
        fetchScenarioData();
    }
});

function handleBuilderUpdated(res: ScenarioBuilderResult) {
    activeBuilderResult.value = res;
}

function handleCalculatorCalculated(res: PlanningCalculationResult) {
    activeCalculatorResult.value = res;
}

function handleApplySavedScenario(scenario: SavedScenarioItem) {
    if (activeBuilderResult.value) {
        activeBuilderResult.value = {
            ...activeBuilderResult.value,
            overtime_change_pct: scenario.overtime_change_pct,
            budget_allocation: scenario.budget_allocation,
            projected_hours: scenario.projected_hours,
            projected_cost: scenario.projected_cost,
            projected_burn_index: scenario.projected_burn_index,
            burn_zone: scenario.burn_zone,
            safety_risk_score: scenario.safety_risk_score,
            production_volume_impact_pct: scenario.production_volume_impact_pct,
        };
    }
}

function handleScenarioSaved(updatedList: Array<Record<string, unknown>>) {
    savedScenariosList.value = updatedList as unknown as SavedScenarioItem[];
}

function handleScenarioDeleted(deletedId: string) {
    savedScenariosList.value = savedScenariosList.value.filter(
        (s) => s.id !== deletedId,
    );
}

// Comparison parameters computed for the chart
const baselineComparison = computed(() => {
    const base = scenarioData.value?.baseline;
    return {
        hours: base?.actual_hours ?? 450,
        cost: base?.actual_cost ?? 22500000,
        burn_index: base?.burn_index_pct ?? 90.0,
        safety_risk: base?.safety_risk_score ?? 12.0,
    };
});

const currentComparison = computed(() => {
    const curr = activeBuilderResult.value;
    return {
        name: `${__('Skenario')} (${(curr?.overtime_change_pct ?? 0) > 0 ? '+' : ''}${curr?.overtime_change_pct ?? 0}%)`,
        hours: curr?.projected_hours ?? 450,
        cost: curr?.projected_cost ?? 22500000,
        burn_index: curr?.projected_burn_index ?? 90.0,
        safety_risk: curr?.safety_risk_score ?? 12.0,
    };
});
</script>

<template>
    <div class="space-y-6" data-test="tab-scenario-content">
        <!-- Error Banner -->
        <div
            v-if="errorMsg"
            class="flex items-center justify-between rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700 shadow-2xs dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300"
        >
            <div class="flex items-center gap-2">
                <AlertCircle
                    class="size-4 shrink-0 text-red-600 dark:text-red-400"
                />
                <span>{{ errorMsg }}</span>
            </div>
            <Button
                variant="outline"
                size="sm"
                class="h-7 cursor-pointer border-red-300 text-xs font-semibold text-red-700 hover:bg-red-100 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950"
                @click="fetchScenarioData"
            >
                <RefreshCw class="mr-1.5 size-3" />
                <span>{{ __('Coba Lagi') }}</span>
            </Button>
        </div>

        <!-- Main Simulation Cockpit Grid (E09-10) -->
        <div class="space-y-6">
            <!-- Top Dual Panel Grid -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Left Panel: Production Volume Planning Calculator -->
                <ProductionCalculatorPanel
                    :sections="scenarioData.sections"
                    :initial-result="activeCalculatorResult"
                    @calculated="handleCalculatorCalculated"
                />

                <!-- Right Panel: Scenario Builder Cockpit -->
                <ScenarioBuilderPanel
                    :departments="scenarioData.departments"
                    :initial-result="activeBuilderResult"
                    :selected-department-id="props.filters?.department_id"
                    :saved-count="savedScenariosList.length"
                    @updated="handleBuilderUpdated"
                    @open-saved-drawer="savedDrawerOpen = true"
                    @scenario-saved="handleScenarioSaved"
                />
            </div>

            <!-- Bottom Panel: Scenario Comparison Chart -->
            <ScenarioComparisonChart
                :baseline="baselineComparison"
                :current="currentComparison"
                :saved-scenarios="savedScenariosList"
                :loading="isLoading"
            />
        </div>

        <!-- Slide-in Saved Scenarios Management Drawer -->
        <SavedScenariosDrawer
            v-model:open="savedDrawerOpen"
            :scenarios="savedScenariosList"
            @apply="handleApplySavedScenario"
            @deleted="handleScenarioDeleted"
        />
    </div>
</template>
