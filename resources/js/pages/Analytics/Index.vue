<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Banknote,
    BrainCircuit,
    Calendar,
    Clock,
    Filter,
    GitCompare,
    Lightbulb,
    RotateCcw,
    ShieldAlert,
    Sliders,
    Sparkles,
    TrendingUp,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ExportReportPopover from '@/components/analytics/ExportReportPopover.vue';
import { useShiftInfo } from '@/composables/useShiftInfo';
import { useTrans } from '@/composables/useTrans';
import { dashboard } from '@/routes';
import { index as analyticsIndex } from '@/routes/analytics';
import type { User } from '@/types';
import TabComparison from './TabComparison.vue';
import TabCorrelation from './TabCorrelation.vue';
import TabCostAnalysis from './TabCostAnalysis.vue';
import TabInsights from './TabInsights.vue';
import TabPredictive from './TabPredictive.vue';
import TabScenario from './TabScenario.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Analitik & Keputusan',
                href: analyticsIndex(),
            },
        ],
    },
});

interface DepartmentItem {
    id: number;
    code: string;
    name: string;
}

interface Props {
    currentTab?: string;
    departments?: DepartmentItem[];
    filters?: {
        department_id?: string | number | null;
        start_date?: string;
        end_date?: string;
    };
    predictiveData?: unknown;
    costData?: unknown;
    correlationData?: unknown;
    scenarioData?: unknown;
    insightsData?: unknown;
    comparisonData?: unknown;
    userRole?: string;
    userDepartmentId?: number | null;
}

const props = withDefaults(defineProps<Props>(), {
    currentTab: 'predictive',
    departments: () => [],
    filters: () => ({
        department_id: 'all',
        start_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1)
            .toISOString()
            .slice(0, 10),
        end_date: new Date(
            new Date().getFullYear(),
            new Date().getMonth() + 1,
            0,
        )
            .toISOString()
            .slice(0, 10),
    }),
    userRole: 'manager',
    userDepartmentId: null,
});

const { __ } = useTrans();
const { timeString, dateString } = useShiftInfo();
const page = usePage();
const user = computed(() => page.props.auth?.user as User | undefined);

type AnalyticsTabKey =
    | 'predictive'
    | 'cost'
    | 'correlation'
    | 'scenario'
    | 'insights'
    | 'comparison';

const validTabs: AnalyticsTabKey[] = [
    'predictive',
    'cost',
    'correlation',
    'scenario',
    'insights',
    'comparison',
];

function getInitialTab(): AnalyticsTabKey {
    if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab') as AnalyticsTabKey;
        if (validTabs.includes(tab)) {
            return tab;
        }
    }
    if (validTabs.includes(props.currentTab as AnalyticsTabKey)) {
        return props.currentTab as AnalyticsTabKey;
    }
    return 'predictive';
}

const activeTab = ref<AnalyticsTabKey>(getInitialTab());

const tabs = computed<
    Array<{
        key: AnalyticsTabKey;
        label: string;
        icon: unknown;
        component: unknown;
        testId: string;
    }>
>(() => [
    {
        key: 'predictive',
        label: __('Prediksi Lembur'),
        icon: BrainCircuit,
        component: TabPredictive,
        testId: 'tab-predictive',
    },
    {
        key: 'cost',
        label: __('Analisis Biaya'),
        icon: Banknote,
        component: TabCostAnalysis,
        testId: 'tab-cost',
    },
    {
        key: 'correlation',
        label: __('Korelasi & Pola'),
        icon: Activity,
        component: TabCorrelation,
        testId: 'tab-correlation',
    },
    {
        key: 'scenario',
        label: __('Simulasi Skenario'),
        icon: Sliders,
        component: TabScenario,
        testId: 'tab-scenario',
    },
    {
        key: 'insights',
        label: __('Wawasan Kunci'),
        icon: Lightbulb,
        component: TabInsights,
        testId: 'tab-insights',
    },
    {
        key: 'comparison',
        label: __('Perbandingan Periode'),
        icon: GitCompare,
        component: TabComparison,
        testId: 'tab-comparison',
    },
]);

const activeTabComponent = computed(() => {
    const found = tabs.value.find((t) => t.key === activeTab.value);
    return found ? found.component : TabPredictive;
});

function handleTabChange(tabKey: AnalyticsTabKey) {
    activeTab.value = tabKey;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabKey);
        window.history.replaceState({}, '', url.pathname + url.search);
    }
}

// Filter State
const filterDept = ref<string>(
    props.filters?.department_id !== undefined &&
        props.filters?.department_id !== null
        ? String(props.filters.department_id)
        : 'all',
);

const filterStartDate = ref<string>(
    props.filters?.start_date ||
        new Date(new Date().getFullYear(), new Date().getMonth(), 1)
            .toISOString()
            .slice(0, 10),
);

const filterEndDate = ref<string>(
    props.filters?.end_date ||
        new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0)
            .toISOString()
            .slice(0, 10),
);

const isApplyingFilter = ref(false);

const selectedDepartmentObj = computed<DepartmentItem | null>(() => {
    if (filterDept.value === 'all') {
        return null;
    }
    return (
        props.departments.find((d) => String(d.id) === filterDept.value) ?? null
    );
});

function applyFilters() {
    isApplyingFilter.value = true;
    router.get(
        analyticsIndex.url(),
        {
            tab: activeTab.value,
            department_id: filterDept.value,
            start_date: filterStartDate.value,
            end_date: filterEndDate.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                isApplyingFilter.value = false;
            },
        },
    );
}

function resetFilters() {
    const now = new Date();
    const defaultStart = new Date(now.getFullYear(), now.getMonth(), 1)
        .toISOString()
        .slice(0, 10);
    const defaultEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0)
        .toISOString()
        .slice(0, 10);

    filterDept.value =
        user.value?.role === 'manager' && props.userDepartmentId
            ? String(props.userDepartmentId)
            : 'all';
    filterStartDate.value = defaultStart;
    filterEndDate.value = defaultEnd;

    applyFilters();
}

watch(
    () => props.currentTab,
    (newTab) => {
        if (newTab && validTabs.includes(newTab as AnalyticsTabKey)) {
            activeTab.value = newTab as AnalyticsTabKey;
        }
    },
);
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <Head :title="__('Analitik & Keputusan Lembur')" />

        <!-- Master Page Header with Live WIB Clock & Filter Toolbar -->
        <div
            class="border-border/70 bg-card rounded-xl border p-5 shadow-xs sm:p-6"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <TrendingUp class="size-6 text-[#cc0000]" />
                        <h1
                            class="text-foreground text-2xl font-bold tracking-tight sm:text-3xl"
                            data-test="analytics-page-heading"
                        >
                            {{ __('Analitik & Keputusan Lembur') }}
                        </h1>
                    </div>
                    <p
                        class="text-muted-foreground mt-1 flex flex-wrap items-center gap-2 text-sm"
                    >
                        <span>{{
                            __(
                                'Pusat intelijen analitik dan peramalan strategis PT Isuzu Astra Motor Indonesia.',
                            )
                        }}</span>
                        <span>•</span>
                        <Calendar class="size-4 shrink-0 text-slate-400" />
                        <span>{{ dateString }}</span>
                        <span>•</span>
                        <Clock class="size-4 shrink-0 text-slate-400" />
                        <span class="font-mono tabular-nums"
                            >{{ timeString }} WIB</span
                        >
                    </p>
                </div>

                <!-- Global Filter Bar & Export Action Popover -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Department Selector -->
                    <div class="flex items-center gap-1.5">
                        <Filter class="ml-1 size-3.5 text-slate-400" />
                        <select
                            v-if="user?.role === 'admin'"
                            v-model="filterDept"
                            class="h-9 rounded-md border border-slate-300 bg-white px-2.5 text-xs font-medium text-slate-700 shadow-2xs focus:border-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            data-test="filter-department"
                            @change="applyFilters"
                        >
                            <option value="all">
                                {{ __('Semua Departemen (Lintas Pabrik)') }}
                            </option>
                            <option
                                v-for="dept in departments"
                                :key="dept.id"
                                :value="String(dept.id)"
                            >
                                {{ dept.name }}
                            </option>
                        </select>

                        <span
                            v-else
                            class="inline-flex h-9 items-center rounded-md border border-slate-200 bg-slate-100/70 px-3 text-xs font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-300"
                            data-test="filter-department-locked"
                        >
                            {{
                                user?.department?.name ?? __('Departemen Anda')
                            }}
                        </span>
                    </div>

                    <!-- Date Range Pickers -->
                    <div class="flex items-center gap-1">
                        <input
                            v-model="filterStartDate"
                            type="date"
                            class="h-9 rounded-md border border-slate-300 bg-white px-2.5 font-mono text-xs font-medium text-slate-700 tabular-nums shadow-2xs focus:border-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            data-test="filter-start-date"
                            @change="applyFilters"
                        />
                        <span class="text-xs text-slate-400">~</span>
                        <input
                            v-model="filterEndDate"
                            type="date"
                            class="h-9 rounded-md border border-slate-300 bg-white px-2.5 font-mono text-xs font-medium text-slate-700 tabular-nums shadow-2xs focus:border-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            data-test="filter-end-date"
                            @change="applyFilters"
                        />
                    </div>

                    <!-- Reset Filter Button -->
                    <button
                        type="button"
                        class="inline-flex h-9 cursor-pointer items-center gap-1 rounded-md border border-slate-200 px-2.5 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        title="Reset Filter"
                        @click="resetFilters"
                        data-test="btn-filter-reset"
                    >
                        <RotateCcw class="size-3.5" />
                        <span class="sr-only">Reset</span>
                    </button>

                    <!-- Export Report Action Popover (E09-06) -->
                    <ExportReportPopover
                        :active-tab="activeTab"
                        :selected-department="selectedDepartmentObj"
                        :start-date="filterStartDate"
                        :end-date="filterEndDate"
                    />
                </div>
            </div>
        </div>

        <!-- 6-Tab Switcher Navigation (UX Plan Progressive Disclosure) -->
        <div
            class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-0 dark:border-slate-800"
            data-test="analytics-tabs-nav"
        >
            <div class="flex flex-wrap items-center gap-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="flex cursor-pointer items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-semibold transition-all"
                    :class="
                        activeTab === tab.key
                            ? 'border-[#cc0000] text-[#cc0000] dark:text-red-400'
                            : 'text-muted-foreground border-transparent hover:text-slate-900 dark:hover:text-white'
                    "
                    @click="handleTabChange(tab.key)"
                    :data-test="tab.testId"
                >
                    <component :is="tab.icon" class="size-3.5" />
                    <span>{{ tab.label }}</span>
                </button>
            </div>
        </div>

        <!-- Dynamic Sub-Tab Component Container -->
        <div class="min-h-[400px]">
            <Transition name="fade" mode="out-in">
                <component
                    :is="activeTabComponent"
                    :filters="{
                        department_id: filterDept,
                        start_date: filterStartDate,
                        end_date: filterEndDate,
                    }"
                    :initial-data="
                        activeTab === 'predictive'
                            ? predictiveData
                            : activeTab === 'cost'
                              ? costData
                              : activeTab === 'correlation'
                                ? correlationData
                                : activeTab === 'scenario'
                                  ? scenarioData
                                  : activeTab === 'insights'
                                    ? insightsData
                                    : activeTab === 'comparison'
                                      ? comparisonData
                                      : undefined
                    "
                />
            </Transition>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease-in-out;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
