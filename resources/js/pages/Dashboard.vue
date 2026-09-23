<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Calendar,
    Clock,
    Filter,
    Flame,
    PieChart,
    RotateCcw,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import BurnUpIndexChart, {
    type DailyBurnUpIndexData,
} from '@/components/dashboard/BurnUpIndexChart.vue';
import CategoryOvertimeChart, {
    type CategoryOvertimeInputData,
} from '@/components/dashboard/CategoryOvertimeChart.vue';
import DailyBurnLineChart, {
    type DailyBurnChartData,
} from '@/components/dashboard/DailyBurnLineChart.vue';
import WeeklyPlanningActualChart, {
    type WeeklyPlanningActualData,
} from '@/components/dashboard/WeeklyPlanningActualChart.vue';
import YtdOvertimeIndexChart, {
    type YtdOvertimeIndexData,
} from '@/components/dashboard/YtdOvertimeIndexChart.vue';
import KpiCardBurnIndex, {
    type BurnIndexCardData,
} from '@/components/dashboard/KpiCardBurnIndex.vue';
import KpiCardManPower, {
    type ManpowerData,
} from '@/components/dashboard/KpiCardManPower.vue';
import KpiCardProduction, {
    type ProductionVolumeData,
} from '@/components/dashboard/KpiCardProduction.vue';
import KpiCardWorkingDays, {
    type WorkingDaysData,
} from '@/components/dashboard/KpiCardWorkingDays.vue';
import SectionBurnComparisonChart, {
    type SectionBurnComparisonData,
} from '@/components/dashboard/SectionBurnComparisonChart.vue';
import CategoryDistributionDonut from '@/components/dashboard/CategoryDistributionDonut.vue';
import DailyIndexTrendChart, {
    type DailyIndexTrendData,
} from '@/components/dashboard/DailyIndexTrendChart.vue';
import DayTypeBreakdownChart, {
    type DayTypeBreakdownData,
} from '@/components/dashboard/DayTypeBreakdownChart.vue';
import EmployeeSummaryTable, {
    type EmployeeSummaryData,
} from '@/components/dashboard/EmployeeSummaryTable.vue';
import OvertimeLeaderboardChart, {
    type LeaderboardData,
} from '@/components/dashboard/OvertimeLeaderboardChart.vue';
import TrendWorkingTimeChart, {
    type TrendWorkingTimeData,
} from '@/components/dashboard/TrendWorkingTimeChart.vue';
import RoleBadge from '@/components/RoleBadge.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useShiftInfo } from '@/composables/useShiftInfo';
import { useTrans } from '@/composables/useTrans';
import { dashboard } from '@/routes';
import type { User, UserRole } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

interface DepartmentItem {
    id: number;
    code: string;
    name: string;
}

interface KpiCardsPayload {
    production_volume: ProductionVolumeData;
    working_days: WorkingDaysData;
    man_power: ManpowerData;
    burn_index: BurnIndexCardData;
    scope: {
        department_id: number | null;
        department_name: string | null;
        section_id: number | null;
        selected_date: string;
        fiscal_year: number;
        fiscal_month: number;
    };
}

interface Props {
    currentTab?: string;
    kpiCards?: KpiCardsPayload;
    weeklyPlanningVsActual?: WeeklyPlanningActualData;
    dailyBurnChart?: DailyBurnChartData;
    dailyBurnUpIndex?: DailyBurnUpIndexData;
    sectionBurnComparison?: SectionBurnComparisonData;
    leaderboard?: LeaderboardData;
    categoryDistribution?: CategoryOvertimeInputData;
    trendWorkingTime?: TrendWorkingTimeData;
    ytdOvertimeIndex?: YtdOvertimeIndexData;
    dailyIndexTrend?: DailyIndexTrendData;
    dayTypeBreakdown?: DayTypeBreakdownData;
    employeeSummary?: EmployeeSummaryData;
    departments?: DepartmentItem[];
    selectedDepartmentId?: number | null;
    selectedSectionId?: number | null;
    selectedDate?: string;
}

const props = withDefaults(defineProps<Props>(), {
    currentTab: 'pacing',
    kpiCards: undefined,
    weeklyPlanningVsActual: undefined,
    dailyBurnChart: undefined,
    dailyBurnUpIndex: undefined,
    sectionBurnComparison: undefined,
    leaderboard: undefined,
    categoryDistribution: undefined,
    trendWorkingTime: undefined,
    ytdOvertimeIndex: undefined,
    dailyIndexTrend: undefined,
    dayTypeBreakdown: undefined,
    employeeSummary: undefined,
    departments: () => [],
    selectedDepartmentId: null,
    selectedSectionId: null,
    selectedDate: '',
});

export type DashboardTab = 'pacing' | 'distribution' | 'employees';

const activeTab = ref<DashboardTab>('pacing');

function getInitialTab(): DashboardTab {
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab === 'distribution' || tab === 'employees') {
            return tab;
        }
    }
    if (
        props.currentTab === 'distribution' ||
        props.currentTab === 'employees'
    ) {
        return props.currentTab;
    }
    return 'pacing';
}

activeTab.value = getInitialTab();

function handleTabChange(tab: DashboardTab) {
    activeTab.value = tab;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        if (tab === 'pacing') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', tab);
        }
        window.history.replaceState({}, '', url.pathname + url.search);
    }
}

const { __ } = useTrans();
const { timeString, dateString } = useShiftInfo();
const page = usePage();
const user = computed(() => page.props.auth?.user as User | undefined);

const isFiltering = ref(false);
const selectedCategoryFilter = ref<string | null>(null);

function handleCategorySelect(catKey: string | null) {
    selectedCategoryFilter.value = catKey;
    if (catKey) {
        handleTabChange('employees');
    }
}

const filterDept = ref(
    props.selectedDepartmentId ? String(props.selectedDepartmentId) : 'all',
);
const filterSection = ref<number | null>(props.selectedSectionId ?? null);
const filterDate = ref(
    props.selectedDate ||
        props.kpiCards?.scope?.selected_date ||
        new Date().toISOString().slice(0, 10),
);

// Sync with props if updated via external visit
watch(
    () => props.selectedDepartmentId,
    (val) => {
        filterDept.value = val ? String(val) : 'all';
    },
);

watch(
    () => props.selectedSectionId,
    (val) => {
        filterSection.value = val ?? null;
    },
);

watch(
    () => props.selectedDate,
    (val) => {
        if (val) {
            filterDate.value = val;
        }
    },
);

function applyFilters(overrideSection?: number | null | Event) {
    isFiltering.value = true;
    const targetSection =
        typeof overrideSection === 'number' || overrideSection === null
            ? overrideSection
            : filterSection.value;

    router.get(
        dashboard.url(),
        {
            date: filterDate.value,
            department_id:
                filterDept.value === 'all' ? undefined : filterDept.value,
            section_id: targetSection ? targetSection : undefined,
            tab: activeTab.value !== 'pacing' ? activeTab.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: [
                'kpiCards',
                'weeklyPlanningVsActual',
                'dailyBurnChart',
                'dailyBurnUpIndex',
                'sectionBurnComparison',
                'leaderboard',
                'categoryDistribution',
                'trendWorkingTime',
                'ytdOvertimeIndex',
                'dailyIndexTrend',
                'dayTypeBreakdown',
                'employeeSummary',
                'selectedDepartmentId',
                'selectedSectionId',
                'selectedDate',
            ],
            onFinish: () => {
                isFiltering.value = false;
            },
        },
    );
}

function handleMonthNavigation(direction: 'prev' | 'next') {
    const current = new Date(
        filterDate.value || new Date().toISOString().slice(0, 10),
    );
    if (direction === 'prev') {
        current.setMonth(current.getMonth() - 1);
    } else {
        current.setMonth(current.getMonth() + 1);
    }
    const year = current.getFullYear();
    const month = String(current.getMonth() + 1).padStart(2, '0');
    filterDate.value = `${year}-${month}-01`;
    applyFilters();
}

function handleSectionSelect(secId: number | null) {
    filterSection.value = secId;
    applyFilters(secId);
}

function resetFilters() {
    filterDate.value = new Date().toISOString().slice(0, 10);
    filterDept.value =
        user.value?.role === 'admin'
            ? 'all'
            : user.value?.department_id
              ? String(user.value.department_id)
              : 'all';
    filterSection.value = null;
    applyFilters(null);
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <Head :title="__('Operational Dashboard')" />

        <!-- Executive Operational Header Banner with Live WIB Clock & Filters -->
        <div
            class="border-border/70 bg-card rounded-xl border p-5 shadow-xs sm:p-6"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <h1
                            class="text-foreground text-2xl font-bold tracking-tight sm:text-3xl"
                            data-test="welcome-heading"
                        >
                            {{ __('Executive Operational Dashboard') }}
                        </h1>
                    </div>
                    <p
                        class="text-muted-foreground mt-1 flex flex-wrap items-center gap-2 text-sm"
                    >
                        <span>{{
                            __('Welcome back, :name!', {
                                name: user?.name ?? 'Operator',
                            })
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

                <!-- Filter Bar -->
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Filter Controls -->
                    <div
                        class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-slate-50/70 p-1.5 dark:border-slate-800 dark:bg-slate-900/60"
                        data-test="dashboard-filter-bar"
                    >
                        <!-- Department Selector (Admin or display current) -->
                        <div class="flex items-center gap-1.5">
                            <Filter class="ml-1 size-3.5 text-slate-400" />
                            <select
                                v-if="user?.role === 'admin'"
                                v-model="filterDept"
                                class="h-8 rounded-md border border-slate-300 bg-white px-2.5 text-xs font-medium text-slate-700 shadow-2xs focus:border-blue-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                data-test="department-filter-select"
                                @change="applyFilters"
                            >
                                <option value="all">
                                    {{ __('All Departments (Plant-wide)') }}
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
                                class="inline-flex h-8 items-center rounded-md bg-slate-200/60 px-2.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                {{
                                    user?.department?.name ??
                                    __('All Departments (Plant-wide)')
                                }}
                            </span>
                        </div>

                        <!-- Date Picker -->
                        <div class="flex items-center gap-1">
                            <input
                                v-model="filterDate"
                                type="date"
                                class="h-8 rounded-md border border-slate-300 bg-white px-2.5 font-mono text-xs font-medium text-slate-700 tabular-nums shadow-2xs focus:border-blue-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                data-test="date-filter-input"
                                @change="applyFilters"
                            />
                        </div>

                        <!-- Reset Button -->
                        <button
                            type="button"
                            class="inline-flex h-8 items-center gap-1 rounded-md px-2 text-xs font-medium text-slate-500 hover:bg-slate-200/60 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            title="Reset Filter"
                            @click="resetFilters"
                        >
                            <RotateCcw class="size-3" />
                            <span class="sr-only">Reset</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header KPI Cards Row with Sparklines (Story E09-01) -->
        <div
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
            data-test="kpi-cards-grid"
        >
            <!-- Card 1: Production Volume -->
            <KpiCardProduction
                :data="kpiCards?.production_volume"
                :loading="isFiltering"
            />

            <!-- Card 2: Working Days -->
            <KpiCardWorkingDays
                :data="kpiCards?.working_days"
                :loading="isFiltering"
            />

            <!-- Card 3: Man Power -->
            <KpiCardManPower
                :data="kpiCards?.man_power"
                :loading="isFiltering"
            />

            <!-- Card 4: Burn Chart Index -->
            <KpiCardBurnIndex
                :data="kpiCards?.burn_index"
                :loading="isFiltering"
            />
        </div>

        <!-- Operational Navigation Tabs (UX Plan Progressive Disclosure) -->
        <div
            class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-0 dark:border-slate-800"
            data-test="dashboard-tabs-nav"
        >
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-semibold transition-all"
                    :class="
                        activeTab === 'pacing'
                            ? 'border-[#cc0000] text-[#cc0000] dark:text-red-400'
                            : 'text-muted-foreground border-transparent hover:text-slate-900 dark:hover:text-white'
                    "
                    @click="handleTabChange('pacing')"
                    data-test="tab-pacing"
                >
                    <Flame class="size-3.5" />
                    <span>{{ __('Overtime Pacing & Sections') }}</span>
                </button>

                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-semibold transition-all"
                    :class="
                        activeTab === 'distribution'
                            ? 'border-[#cc0000] text-[#cc0000] dark:text-red-400'
                            : 'text-muted-foreground border-transparent hover:text-slate-900 dark:hover:text-white'
                    "
                    @click="handleTabChange('distribution')"
                    data-test="tab-distribution"
                >
                    <PieChart class="size-3.5" />
                    <span>{{ __('Distribution & Trends') }}</span>
                </button>

                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-semibold transition-all"
                    :class="
                        activeTab === 'employees'
                            ? 'border-[#cc0000] text-[#cc0000] dark:text-red-400'
                            : 'text-muted-foreground border-transparent hover:text-slate-900 dark:hover:text-white'
                    "
                    @click="handleTabChange('employees')"
                    data-test="tab-employees"
                >
                    <Users class="size-3.5" />
                    <span>{{ __('Employee Overtime Roster') }}</span>
                    <Badge
                        v-if="employeeSummary?.total_count"
                        variant="secondary"
                        class="ml-1 px-1.5 py-0 font-mono text-[10px]"
                    >
                        {{ employeeSummary.total_count }}
                    </Badge>
                </button>
            </div>
        </div>

        <!-- TAB 1: PACING & SECTION HEALTH (Stories E09-02, E09-03) -->
        <!-- Layout mirrors Excel dashboard_ot.xlsx visual structure -->
        <div
            v-show="activeTab === 'pacing'"
            class="space-y-6"
            data-test="tab-panel-pacing"
        >
            <!-- ZONE 1 (TOP): Daily Burn-Up Index Chart — Cumulative INDEX per Day (Plan × Actual) -->
            <!-- Excel: "Burn-Up Chart Index Overtime Plan × Actual (Day to Date)" — grouped bars -->
            <BurnUpIndexChart :data="dailyBurnUpIndex" :loading="isFiltering" />

            <!-- ZONE 2A: Leaderboard LEFT (3fr) + Category Overtime RIGHT (2fr) — same visual level -->
            <div class="grid gap-6 lg:grid-cols-[3fr_2fr] lg:items-start">
                <OvertimeLeaderboardChart
                    :data="leaderboard"
                    :loading="isFiltering"
                />
                <CategoryOvertimeChart
                    :data="categoryDistribution"
                    :loading="isFiltering"
                />
            </div>

            <!-- ZONE 2B: Weekly Planning full-width — needs horizontal space to show W1–W5 clearly -->
            <WeeklyPlanningActualChart
                :data="weeklyPlanningVsActual"
                :loading="isFiltering"
            />

            <!-- ZONE 3: YTD Index Trend — Monthly Plan vs Actual index for the full fiscal year -->
            <!-- Excel: "Total Index Overtime Year to Date (YTD) 2026" -->
            <YtdOvertimeIndexChart
                :data="ytdOvertimeIndex"
                :loading="isFiltering"
            />

            <!-- ZONE 4 (BOTTOM): Section Burn Comparison Bar Chart -->
            <SectionBurnComparisonChart
                :data="sectionBurnComparison"
                :loading="isFiltering"
            />
        </div>

        <!-- TAB 2: OVERTIME DISTRIBUTION & TRENDS (Story E09-04) -->
        <div
            v-show="activeTab === 'distribution'"
            class="space-y-4"
            data-test="tab-panel-distribution"
        >
            <!-- Band 4: Multi-Chart Analytics Grid (Story E09-04) -->
            <div class="space-y-4" data-test="multi-chart-grid-band">
                <!-- Top Row: Leaderboard, Category Donut, 12-Month Trend (3 columns on lg) -->
                <div class="grid gap-4 lg:grid-cols-3">
                    <OvertimeLeaderboardChart
                        :data="leaderboard"
                        :loading="isFiltering"
                    />

                    <CategoryDistributionDonut
                        :data="categoryDistribution"
                        :loading="isFiltering"
                        :selected-category="selectedCategoryFilter"
                        @select-category="handleCategorySelect"
                    />

                    <TrendWorkingTimeChart
                        :data="trendWorkingTime"
                        :loading="isFiltering"
                    />
                </div>

                <!-- Bottom Row: Daily Index Trend & Day Type Breakdown (2 columns on lg) -->
                <div class="grid gap-4 lg:grid-cols-2">
                    <DailyIndexTrendChart
                        :data="dailyIndexTrend"
                        :loading="isFiltering"
                    />

                    <DayTypeBreakdownChart
                        :data="dayTypeBreakdown"
                        :loading="isFiltering"
                    />
                </div>
            </div>
        </div>

        <!-- TAB 3: SUMMARY EMPLOYEE OVERTIME TABLE (Story E09-05) -->
        <div
            v-show="activeTab === 'employees'"
            class="space-y-4"
            data-test="tab-panel-employees"
        >
            <EmployeeSummaryTable
                :data="employeeSummary"
                :loading="isFiltering"
                :selected-category-filter="selectedCategoryFilter"
                @clear-category-filter="selectedCategoryFilter = null"
            />
        </div>
    </div>
</template>
