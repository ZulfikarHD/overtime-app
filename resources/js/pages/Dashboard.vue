<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    Filter,
    Flame,
    Layers,
    PieChart,
    RotateCcw,
    Shield,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import DailyBurnLineChart, {
    type DailyBurnChartData,
} from '@/components/dashboard/DailyBurnLineChart.vue';
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
import CategoryDistributionDonut, {
    type CategoryDistributionData,
} from '@/components/dashboard/CategoryDistributionDonut.vue';
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
    dailyBurnChart?: DailyBurnChartData;
    sectionBurnComparison?: SectionBurnComparisonData;
    leaderboard?: LeaderboardData;
    categoryDistribution?: CategoryDistributionData;
    trendWorkingTime?: TrendWorkingTimeData;
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
    dailyBurnChart: undefined,
    sectionBurnComparison: undefined,
    leaderboard: undefined,
    categoryDistribution: undefined,
    trendWorkingTime: undefined,
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
const { timeString, dateString, currentShift } = useShiftInfo();
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
                'dailyBurnChart',
                'sectionBurnComparison',
                'leaderboard',
                'categoryDistribution',
                'trendWorkingTime',
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

const roleCapabilities = computed(() => {
    const role: UserRole = user.value?.role ?? 'user';

    switch (role) {
        case 'admin':
            return [
                {
                    title: 'User Management',
                    desc: 'Create, update, and manage plant accounts across all roles.',
                    allowed: true,
                },
                {
                    title: 'Overtime Submission',
                    desc: 'Plant-wide emergency and master batch overtime entry.',
                    allowed: true,
                },
                {
                    title: 'Overtime Approval',
                    desc: 'Approve or reject any timesheet line item plant-wide.',
                    allowed: true,
                },
                {
                    title: 'Department & Section Scoping',
                    desc: 'Access all production lines and cost centers.',
                    allowed: true,
                },
                {
                    title: 'ML Predictive Analytics',
                    desc: 'Monitor ML models, burn trajectories, and anomaly alerts.',
                    allowed: true,
                },
                {
                    title: 'Policy Configuration',
                    desc: 'Set monthly thresholds and SPKL compliance grace periods.',
                    allowed: true,
                },
            ];
        case 'manager':
            return [
                {
                    title: 'Department Overtime Approval',
                    desc: 'Review, partial-approve, and reject section timesheets.',
                    allowed: true,
                },
                {
                    title: 'Department Scope',
                    desc: `Supervise all sections within ${user.value?.department?.name ?? 'your department'}.`,
                    allowed: true,
                },
                {
                    title: 'ML & Budget Tracking',
                    desc: 'Track departmental burn rates and ML cost predictions.',
                    allowed: true,
                },
                {
                    title: 'Personal Report',
                    desc: 'Inspect individual hours, overtime logs, and historical summary.',
                    allowed: true,
                },
            ];
        case 'team_leader':
            return [
                {
                    title: 'Daily Overtime Submission',
                    desc: `Log daily shift overtime for ${user.value?.section?.name ?? 'your assigned section'}.`,
                    allowed: true,
                },
                {
                    title: 'SPKL Document Attachment',
                    desc: 'Upload physical SPKL sign-offs or reference codes post-shift.',
                    allowed: true,
                },
                {
                    title: 'Section Roster Management',
                    desc: 'Select active shopfloor crew members from section roster.',
                    allowed: true,
                },
                {
                    title: 'Personal Report',
                    desc: 'Inspect individual hours, overtime logs, and historical summary.',
                    allowed: true,
                },
            ];
        case 'user':
        default:
            return [
                {
                    title: 'Personal Report',
                    desc: 'View individual overtime records and verified hours.',
                    allowed: true,
                },
                {
                    title: 'Shift Verification',
                    desc: 'Confirm scheduled shift and active operational line.',
                    allowed: true,
                },
            ];
    }
});
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

                <!-- Shift Indicator Pill & Filter Bar -->
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Shift Pill -->
                    <div
                        class="inline-flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-3.5 py-2 text-sm font-semibold text-emerald-800 dark:text-emerald-300"
                    >
                        <span
                            class="size-2 animate-pulse rounded-full bg-emerald-500"
                        />
                        <span>{{ currentShift.badgeText }}</span>
                    </div>

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
        <div
            v-show="activeTab === 'pacing'"
            class="space-y-6"
            data-test="tab-panel-pacing"
        >
            <!-- Hero Section: Daily Cumulative Burn Line Chart (Story E09-02) -->
            <DailyBurnLineChart
                :data="dailyBurnChart"
                :loading="isFiltering"
                :selected-section-id="filterSection"
                @navigate-month="handleMonthNavigation"
                @select-section="handleSectionSelect"
            />

            <!-- Mid Section: Section Burn Comparison Bar Chart (Story E09-03) -->
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

        <!-- Assignment & Identity Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Assignment Card -->
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription
                        class="flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase"
                    >
                        <Building2 class="text-primary size-4" />
                        {{ __('Assignment Overview') }}
                    </CardDescription>
                    <CardTitle class="text-lg font-bold">
                        {{ user?.department?.name ?? __('Not Assigned') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-1.5 pt-1 text-sm">
                    <div
                        class="text-muted-foreground flex items-center justify-between"
                    >
                        <span class="flex items-center gap-1">
                            <Layers class="size-3.5" />
                            {{ __('Section') }}:
                        </span>
                        <span class="text-foreground font-medium">
                            {{ user?.section?.name ?? __('Not Assigned') }}
                        </span>
                    </div>
                    <div
                        class="text-muted-foreground flex items-center justify-between"
                    >
                        <span class="flex items-center gap-1">
                            <Users class="size-3.5" />
                            {{ __('NPK') }}:
                        </span>
                        <span class="text-foreground font-mono font-medium">
                            {{ user?.npk ?? '-' }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Role & Permission Status -->
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription
                        class="flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase"
                    >
                        <Shield class="text-primary size-4" />
                        {{ __('Role') }}
                    </CardDescription>
                    <CardTitle
                        class="flex items-center gap-2 text-lg font-bold"
                        data-test="user-role-card"
                    >
                        <RoleBadge
                            v-if="user?.role"
                            :role="user.role"
                            size="md"
                        />
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-1.5 pt-1 text-sm">
                    <div
                        class="text-muted-foreground flex items-center justify-between"
                    >
                        <span>{{ __('Account Status') }}:</span>
                        <span
                            class="inline-flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400"
                        >
                            <CheckCircle2 class="size-3.5" />
                            {{ user?.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div
                        class="text-muted-foreground flex items-center justify-between"
                    >
                        <span>{{ __('Timezone') }}:</span>
                        <span class="text-foreground font-mono"
                            >Asia/Jakarta (WIB)</span
                        >
                    </div>
                </CardContent>
            </Card>

            <!-- Operational Readiness -->
            <Card class="sm:col-span-2 lg:col-span-1">
                <CardHeader class="pb-2">
                    <CardDescription
                        class="flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase"
                    >
                        <Activity class="text-primary size-4" />
                        {{ __('Active Shift') }}
                    </CardDescription>
                    <CardTitle class="text-lg font-bold">
                        {{ currentShift.name }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-1.5 pt-1 text-sm">
                    <div
                        class="text-muted-foreground flex items-center justify-between"
                    >
                        <span>{{ __('Shift Hours') }}:</span>
                        <span class="text-foreground font-medium">{{
                            currentShift.hours
                        }}</span>
                    </div>
                    <div
                        class="text-muted-foreground flex items-center justify-between"
                    >
                        <span>{{ __('Next Handover') }}:</span>
                        <span class="text-foreground font-mono">
                            {{
                                currentShift.shiftNumber === 1
                                    ? '15:00 WIB'
                                    : currentShift.shiftNumber === 2
                                      ? '23:00 WIB'
                                      : '07:00 WIB'
                            }}
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Sprint 1 System Readiness Notice -->
        <div
            class="flex items-start gap-3 rounded-xl border border-blue-500/20 bg-blue-500/5 p-4 text-sm text-blue-900 sm:p-5 dark:text-blue-200"
        >
            <AlertCircle
                class="mt-0.5 size-5 shrink-0 text-blue-600 dark:text-blue-400"
            />
            <div class="space-y-1">
                <h3 class="text-foreground font-semibold">
                    {{
                        __(
                            'System Infrastructure Initialized — Sprint 1 Active. Overtime entry and SPKL workflows will activate in upcoming releases.',
                        )
                    }}
                </h3>
                <p class="text-muted-foreground text-xs leading-relaxed">
                    Authentication, database relations, and role-based scoping
                    have been fully established according to Epic E01
                    specifications. The system is operating in Asia/Jakarta
                    timezone.
                </p>
            </div>
        </div>

        <!-- Role Capability Matrix for current user -->
        <Card>
            <CardHeader>
                <CardTitle
                    class="flex items-center gap-2 text-base font-semibold"
                >
                    <FileSpreadsheet class="text-primary size-4" />
                    {{ __('Role Capabilities') }}
                </CardTitle>
                <CardDescription>
                    Authorized operational actions assigned to your account in
                    this manufacturing plant.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="cap in roleCapabilities"
                        :key="cap.title"
                        class="border-border/50 bg-background/50 flex items-start gap-2.5 rounded-lg border p-3"
                    >
                        <CheckCircle2
                            class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                        />
                        <div class="space-y-0.5">
                            <h4 class="text-foreground text-sm font-medium">
                                {{ cap.title }}
                            </h4>
                            <p
                                class="text-muted-foreground text-xs leading-relaxed"
                            >
                                {{ cap.desc }}
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
