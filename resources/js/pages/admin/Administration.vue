<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    CheckCircle2,
    Clock,
    Edit2,
    Flame,
    Info,
    Layers,
    Plus,
    Search,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    UserCheck,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmationDialog from '@/components/admin/ConfirmationDialog.vue';
import PolicyThresholdSheet, {
    type DepartmentOption,
    type PolicyThresholdRecord,
} from '@/components/admin/PolicyThresholdSheet.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/composables/useTrans';
import { dashboard } from '@/routes';
import { administration } from '@/routes/admin';
import policyThresholdsRoute from '@/routes/admin/policy-thresholds';

export type DepartmentPolicyStatus = {
    department_id: number;
    department_code: string;
    department_name: string;
    is_active: boolean;
    has_override: boolean;
    override_id: number | null;
    weekly_soft_limit_hours: number;
    consecutive_weeks_alert: number;
    spkl_grace_period_days: number;
    burn_warning_pct: number;
    burn_danger_pct: number;
    updated_at: string | null;
};

export type PolicyStats = {
    total_departments: number;
    overrides_count: number;
    inherited_count: number;
};

const props = defineProps<{
    activeTab?: string;
    plantDefault: PolicyThresholdRecord;
    departments: DepartmentPolicyStatus[];
    availableDepartments: DepartmentOption[];
    policyStats: PolicyStats;
}>();

const { __ } = useTrans();
const page = usePage();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Administration',
                href: administration(),
            },
        ],
    },
});

const currentTab = ref(props.activeTab || 'policies');

watch(
    () => props.activeTab,
    (tab) => {
        if (tab) {
            currentTab.value = tab;
        }
    },
);

function switchTab(tab: string) {
    currentTab.value = tab;
    router.get(
        administration.url({
            query: { tab },
        }),
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
}

// Search and filter state for departmental overrides table
const searchQuery = ref('');
const statusFilter = ref<'all' | 'override' | 'inherited'>('all');

const filteredDepartments = computed(() => {
    let result = props.departments;

    if (statusFilter.value === 'override') {
        result = result.filter((d) => d.has_override);
    } else if (statusFilter.value === 'inherited') {
        result = result.filter((d) => !d.has_override);
    }

    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase().trim();
        result = result.filter(
            (d) =>
                d.department_code.toLowerCase().includes(query) ||
                d.department_name.toLowerCase().includes(query),
        );
    }

    return result;
});

// Existing override department IDs to filter options in sheet
const existingOverrideDeptIds = computed(() => {
    return props.departments
        .filter((d) => d.has_override)
        .map((d) => d.department_id);
});

// Sheet Drawer State
const sheetOpen = ref(false);
const sheetIsPlantDefault = ref(true);
const selectedThreshold = ref<PolicyThresholdRecord | null>(null);

function openEditPlantDefault() {
    sheetIsPlantDefault.value = true;
    selectedThreshold.value = {
        ...props.plantDefault,
    };
    sheetOpen.value = true;
}

function openCreateOverride(preselectedDeptId?: number) {
    sheetIsPlantDefault.value = false;
    if (preselectedDeptId) {
        const dept = props.departments.find(
            (d) => d.department_id === preselectedDeptId,
        );
        selectedThreshold.value = {
            department_id: preselectedDeptId,
            department_code: dept?.department_code,
            department_name: dept?.department_name,
            weekly_soft_limit_hours:
                dept?.weekly_soft_limit_hours ??
                props.plantDefault.weekly_soft_limit_hours,
            consecutive_weeks_alert:
                dept?.consecutive_weeks_alert ??
                props.plantDefault.consecutive_weeks_alert,
            spkl_grace_period_days:
                dept?.spkl_grace_period_days ??
                props.plantDefault.spkl_grace_period_days,
            burn_warning_pct:
                dept?.burn_warning_pct ?? props.plantDefault.burn_warning_pct,
            burn_danger_pct:
                dept?.burn_danger_pct ?? props.plantDefault.burn_danger_pct,
        };
    } else {
        selectedThreshold.value = {
            weekly_soft_limit_hours: props.plantDefault.weekly_soft_limit_hours,
            consecutive_weeks_alert: props.plantDefault.consecutive_weeks_alert,
            spkl_grace_period_days: props.plantDefault.spkl_grace_period_days,
            burn_warning_pct: props.plantDefault.burn_warning_pct,
            burn_danger_pct: props.plantDefault.burn_danger_pct,
        };
    }
    sheetOpen.value = true;
}

function openEditOverride(dept: DepartmentPolicyStatus) {
    sheetIsPlantDefault.value = false;
    selectedThreshold.value = {
        id: dept.override_id ?? undefined,
        department_id: dept.department_id,
        department_code: dept.department_code,
        department_name: dept.department_name,
        weekly_soft_limit_hours: dept.weekly_soft_limit_hours,
        consecutive_weeks_alert: dept.consecutive_weeks_alert,
        spkl_grace_period_days: dept.spkl_grace_period_days,
        burn_warning_pct: dept.burn_warning_pct,
        burn_danger_pct: dept.burn_danger_pct,
    };
    sheetOpen.value = true;
}

// Delete Confirmation Dialog State
const deleteDialogOpen = ref(false);
const deptToDelete = ref<DepartmentPolicyStatus | null>(null);
const isDeleting = ref(false);

function triggerDeleteOverride(dept: DepartmentPolicyStatus) {
    deptToDelete.value = dept;
    deleteDialogOpen.value = true;
}

function executeDeleteOverride() {
    if (!deptToDelete.value || !deptToDelete.value.override_id) {
        return;
    }

    isDeleting.value = true;
    router.delete(
        policyThresholdsRoute.destroy({
            policy_threshold: deptToDelete.value.override_id,
        }).url,
        {
            preserveScroll: true,
            onFinish: () => {
                isDeleting.value = false;
                deleteDialogOpen.value = false;
                deptToDelete.value = null;
            },
        },
    );
}

// Flash notification
const flashSuccess = computed(
    () => (page.props.flash as { success?: string } | undefined)?.success,
);
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:gap-8 md:p-8">
        <Head :title="__('Administration Hub')" />

        <!-- Header Banner -->
        <div
            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ __('Administration Hub') }}
                    </h1>
                    <Badge variant="outline" class="font-mono text-xs">
                        EPIC-02
                    </Badge>
                </div>
                <p class="text-muted-foreground text-sm">
                    {{
                        __(
                            'Manage plant-wide overtime limits, SPKL submission grace period, and budget burn alert levels.',
                        )
                    }}
                </p>
            </div>
        </div>

        <!-- Success Flash Alert -->
        <div
            v-if="flashSuccess"
            class="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-950/40 dark:text-green-300"
        >
            <CheckCircle2
                class="size-5 shrink-0 text-green-600 dark:text-green-400"
            />
            <span class="font-medium">{{ flashSuccess }}</span>
        </div>

        <!-- Unified Administration Navigation Tabs (UX Constraint: single surface) -->
        <div class="border-border/80 border-b">
            <nav class="-mb-px flex space-x-6">
                <button
                    type="button"
                    :class="[
                        currentTab === 'policies'
                            ? 'border-primary text-primary font-semibold'
                            : 'text-muted-foreground hover:border-border hover:text-foreground border-transparent',
                        'flex cursor-pointer items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors',
                    ]"
                    data-test="tab-policies"
                    @click="switchTab('policies')"
                >
                    <Shield class="size-4" />
                    <span>{{ __('Policy Thresholds') }}</span>
                    <Badge variant="secondary" class="ml-1 text-xs">
                        {{ policyStats.overrides_count }} {{ __('Custom') }}
                    </Badge>
                </button>

                <button
                    type="button"
                    :class="[
                        currentTab === 'users'
                            ? 'border-primary text-primary font-semibold'
                            : 'text-muted-foreground hover:border-border hover:text-foreground border-transparent',
                        'flex cursor-pointer items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors',
                    ]"
                    data-test="tab-users"
                    @click="switchTab('users')"
                >
                    <Users class="size-4" />
                    <span>{{ __('User Accounts') }}</span>
                </button>
            </nav>
        </div>

        <!-- Tab 1: Policy Thresholds Content -->
        <div v-if="currentTab === 'policies'" class="space-y-6">
            <!-- Summary Stats Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle
                            class="text-muted-foreground text-sm font-medium"
                        >
                            {{ __('Total Departments') }}
                        </CardTitle>
                        <Building2 class="text-muted-foreground size-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ policyStats.total_departments }}
                        </div>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ __('Registered in master data') }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle
                            class="text-muted-foreground text-sm font-medium"
                        >
                            {{ __('Custom Overrides') }}
                        </CardTitle>
                        <Layers class="size-4 text-amber-500" />
                    </CardHeader>
                    <CardContent>
                        <div
                            class="text-2xl font-bold text-amber-600 dark:text-amber-400"
                        >
                            {{ policyStats.overrides_count }}
                        </div>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ __('Departments with tailored thresholds') }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle
                            class="text-muted-foreground text-sm font-medium"
                        >
                            {{ __('Inheriting Default') }}
                        </CardTitle>
                        <ShieldCheck class="text-primary size-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-primary text-2xl font-bold">
                            {{ policyStats.inherited_count }}
                        </div>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ __('Using standard plant baseline') }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Plant-wide Default Card -->
            <Card class="border-primary/20 bg-primary/5 dark:bg-primary/10">
                <CardHeader class="pb-3">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="bg-primary text-primary-foreground flex size-9 items-center justify-center rounded-lg"
                            >
                                <Shield class="size-5" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <CardTitle class="text-base font-bold">
                                        {{ __('Plant-wide Default Policy') }}
                                    </CardTitle>
                                    <Badge
                                        class="bg-primary/20 text-primary border-primary/30 text-xs"
                                    >
                                        {{ __('Plant Default') }}
                                    </Badge>
                                </div>
                                <CardDescription class="mt-0.5 text-xs">
                                    {{
                                        __(
                                            'Standard baseline policies automatically applied to all departments unless a custom override is configured.',
                                        )
                                    }}
                                </CardDescription>
                            </div>
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            class="bg-background shrink-0 self-start shadow-xs sm:self-auto"
                            data-test="btn-edit-plant-default"
                            @click="openEditPlantDefault"
                        >
                            <Edit2 class="mr-1.5 size-3.5" />
                            <span>{{ __('Edit Plant Default') }}</span>
                        </Button>
                    </div>
                </CardHeader>

                <CardContent class="pt-0">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <!-- Metric 1: Weekly Soft Limit -->
                        <div
                            class="bg-background/90 rounded-lg border p-3 shadow-2xs"
                        >
                            <div
                                class="text-muted-foreground flex items-center gap-1.5 text-xs"
                            >
                                <Clock class="size-3.5" />
                                <span>{{ __('Weekly Soft Limit') }}</span>
                            </div>
                            <div class="mt-1 font-mono text-lg font-bold">
                                {{ plantDefault.weekly_soft_limit_hours }}
                                <span
                                    class="text-muted-foreground font-sans text-xs font-normal"
                                >
                                    {{ __('hrs/wk') }}
                                </span>
                            </div>
                        </div>

                        <!-- Metric 2: Consecutive Weeks -->
                        <div
                            class="bg-background/90 rounded-lg border p-3 shadow-2xs"
                        >
                            <div
                                class="text-muted-foreground flex items-center gap-1.5 text-xs"
                            >
                                <Layers class="size-3.5" />
                                <span>{{ __('Consecutive Weeks Alert') }}</span>
                            </div>
                            <div class="mt-1 font-mono text-lg font-bold">
                                {{ plantDefault.consecutive_weeks_alert }}
                                <span
                                    class="text-muted-foreground font-sans text-xs font-normal"
                                >
                                    {{ __('weeks') }}
                                </span>
                            </div>
                        </div>

                        <!-- Metric 3: SPKL Grace Period -->
                        <div
                            class="bg-background/90 rounded-lg border p-3 shadow-2xs"
                        >
                            <div
                                class="text-muted-foreground flex items-center gap-1.5 text-xs"
                            >
                                <Clock class="size-3.5" />
                                <span>{{ __('SPKL Grace Period') }}</span>
                            </div>
                            <div class="mt-1 font-mono text-lg font-bold">
                                {{ plantDefault.spkl_grace_period_days }}
                                <span
                                    class="text-muted-foreground font-sans text-xs font-normal"
                                >
                                    {{ __('days') }}
                                </span>
                            </div>
                        </div>

                        <!-- Metric 4: Burn Warning -->
                        <div
                            class="bg-background/90 rounded-lg border p-3 shadow-2xs"
                        >
                            <div
                                class="flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400"
                            >
                                <Flame class="size-3.5" />
                                <span>{{ __('Burn Warning') }}</span>
                            </div>
                            <div
                                class="mt-1 font-mono text-lg font-bold text-amber-600 dark:text-amber-400"
                            >
                                {{ plantDefault.burn_warning_pct }}%
                            </div>
                        </div>

                        <!-- Metric 5: Burn Danger -->
                        <div
                            class="bg-background/90 col-span-2 rounded-lg border p-3 shadow-2xs sm:col-span-1"
                        >
                            <div
                                class="flex items-center gap-1.5 text-xs text-red-600 dark:text-red-400"
                            >
                                <ShieldAlert class="size-3.5" />
                                <span>{{ __('Burn Danger') }}</span>
                            </div>
                            <div
                                class="mt-1 font-mono text-lg font-bold text-red-600 dark:text-red-400"
                            >
                                {{ plantDefault.burn_danger_pct }}%
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Department Overrides Section -->
            <div class="space-y-4">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="text-lg font-bold tracking-tight">
                            {{ __('Department Overrides') }}
                        </h2>
                        <p class="text-muted-foreground text-xs">
                            {{
                                __(
                                    'Overview of department-specific thresholds and plant baseline inheritance.',
                                )
                            }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            size="sm"
                            data-test="btn-add-override"
                            @click="openCreateOverride()"
                        >
                            <Plus class="mr-1.5 size-4" />
                            <span>{{ __('+ Add Department Override') }}</span>
                        </Button>
                    </div>
                </div>

                <!-- Search and Filters Bar -->
                <div
                    class="flex flex-col items-stretch justify-between gap-3 sm:flex-row sm:items-center"
                >
                    <div class="relative max-w-sm flex-1">
                        <Search
                            class="text-muted-foreground absolute top-2.5 left-2.5 size-4"
                        />
                        <Input
                            v-model="searchQuery"
                            type="search"
                            :placeholder="__('Search department...')"
                            class="h-9 pl-8 text-xs"
                        />
                    </div>

                    <div
                        class="bg-muted/40 flex items-center gap-1 rounded-lg border p-1"
                    >
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 px-2.5 text-xs"
                            :class="{
                                'bg-background font-semibold shadow-xs':
                                    statusFilter === 'all',
                            }"
                            @click="statusFilter = 'all'"
                        >
                            {{ __('All') }} ({{ departments.length }})
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 px-2.5 text-xs"
                            :class="{
                                'bg-background font-semibold shadow-xs':
                                    statusFilter === 'override',
                            }"
                            @click="statusFilter = 'override'"
                        >
                            {{ __('Custom Overrides') }} ({{
                                policyStats.overrides_count
                            }})
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 px-2.5 text-xs"
                            :class="{
                                'bg-background font-semibold shadow-xs':
                                    statusFilter === 'inherited',
                            }"
                            @click="statusFilter = 'inherited'"
                        >
                            {{ __('Plant Default') }} ({{
                                policyStats.inherited_count
                            }})
                        </Button>
                    </div>
                </div>

                <!-- Department Policies Table -->
                <div
                    class="bg-card overflow-hidden rounded-lg border shadow-2xs"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead
                                class="bg-muted/50 text-muted-foreground border-b text-xs"
                            >
                                <tr>
                                    <th class="px-4 py-3 font-semibold">
                                        {{ __('Department') }}
                                    </th>
                                    <th class="px-4 py-3 font-semibold">
                                        {{ __('Inheritance Status') }}
                                    </th>
                                    <th
                                        class="px-4 py-3 text-center font-semibold"
                                    >
                                        {{ __('Weekly Soft Limit') }}
                                    </th>
                                    <th
                                        class="px-4 py-3 text-center font-semibold"
                                    >
                                        {{ __('SPKL Grace Period') }}
                                    </th>
                                    <th
                                        class="px-4 py-3 text-center font-semibold"
                                    >
                                        {{ __('Burn Alert Levels') }}
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-semibold"
                                    >
                                        {{ __('Actions') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-border divide-y">
                                <tr
                                    v-if="filteredDepartments.length === 0"
                                    class="text-center"
                                >
                                    <td
                                        colspan="6"
                                        class="text-muted-foreground py-8 text-sm"
                                    >
                                        {{ __('No departments found.') }}
                                    </td>
                                </tr>

                                <tr
                                    v-for="dept in filteredDepartments"
                                    :key="dept.department_id"
                                    class="hover:bg-muted/40 transition-colors"
                                    :data-test="`row-dept-${dept.department_code}`"
                                >
                                    <!-- Department Info -->
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <Badge
                                                variant="outline"
                                                class="font-mono text-xs font-semibold"
                                            >
                                                {{ dept.department_code }}
                                            </Badge>
                                            <span
                                                class="text-foreground font-medium"
                                            >
                                                {{ dept.department_name }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Status Badge -->
                                    <td class="px-4 py-3">
                                        <Badge
                                            v-if="dept.has_override"
                                            class="border-amber-200 bg-amber-100 text-xs text-amber-800 dark:bg-amber-950/60 dark:text-amber-300"
                                        >
                                            <Layers class="mr-1 size-3" />
                                            {{ __('Custom Override Active') }}
                                        </Badge>
                                        <Badge
                                            v-else
                                            variant="secondary"
                                            class="text-muted-foreground text-xs font-normal"
                                        >
                                            <ShieldCheck
                                                class="text-primary mr-1 size-3"
                                            />
                                            {{
                                                __(
                                                    'Inherited from Plant Default',
                                                )
                                            }}
                                        </Badge>
                                    </td>

                                    <!-- Weekly Limit -->
                                    <td class="px-4 py-3 text-center font-mono">
                                        <span
                                            :class="
                                                dept.has_override
                                                    ? 'font-bold text-amber-900 dark:text-amber-200'
                                                    : 'text-foreground'
                                            "
                                        >
                                            {{ dept.weekly_soft_limit_hours }}
                                        </span>
                                        <span
                                            class="text-muted-foreground ml-1 text-xs"
                                        >
                                            {{ __('hrs/wk') }}
                                        </span>
                                    </td>

                                    <!-- Grace Period -->
                                    <td class="px-4 py-3 text-center font-mono">
                                        <span
                                            :class="
                                                dept.has_override
                                                    ? 'font-bold text-amber-900 dark:text-amber-200'
                                                    : 'text-foreground'
                                            "
                                        >
                                            {{ dept.spkl_grace_period_days }}
                                        </span>
                                        <span
                                            class="text-muted-foreground ml-1 text-xs"
                                        >
                                            {{ __('days') }}
                                        </span>
                                    </td>

                                    <!-- Burn Alert Levels -->
                                    <td class="px-4 py-3 text-center">
                                        <div
                                            class="inline-flex items-center gap-1.5 font-mono text-xs"
                                        >
                                            <span
                                                class="rounded bg-amber-50 px-1.5 py-0.5 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300"
                                            >
                                                {{ dept.burn_warning_pct }}%
                                            </span>
                                            <span class="text-muted-foreground"
                                                >/</span
                                            >
                                            <span
                                                class="rounded bg-red-50 px-1.5 py-0.5 text-red-700 dark:bg-red-950/50 dark:text-red-300"
                                            >
                                                {{ dept.burn_danger_pct }}%
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-4 py-3 text-right">
                                        <div
                                            class="flex items-center justify-end gap-1.5"
                                        >
                                            <template v-if="dept.has_override">
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    :data-test="`btn-edit-override-${dept.department_code}`"
                                                    :title="
                                                        __(
                                                            'Edit Department Override',
                                                        )
                                                    "
                                                    @click="
                                                        openEditOverride(dept)
                                                    "
                                                >
                                                    <Edit2
                                                        class="text-muted-foreground hover:text-foreground size-4"
                                                    />
                                                </Button>

                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    :data-test="`btn-delete-override-${dept.department_code}`"
                                                    :title="
                                                        __('Delete Override')
                                                    "
                                                    @click="
                                                        triggerDeleteOverride(
                                                            dept,
                                                        )
                                                    "
                                                >
                                                    <Trash2
                                                        class="size-4 text-red-500 hover:text-red-600"
                                                    />
                                                </Button>
                                            </template>

                                            <Button
                                                v-else
                                                variant="outline"
                                                size="sm"
                                                class="h-7 text-xs"
                                                :data-test="`btn-configure-override-${dept.department_code}`"
                                                @click="
                                                    openCreateOverride(
                                                        dept.department_id,
                                                    )
                                                "
                                            >
                                                <Plus class="mr-1 size-3" />
                                                <span>{{
                                                    __('Override')
                                                }}</span>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: User Accounts (E02-05 Placeholder within same single surface) -->
        <div v-else-if="currentTab === 'users'" class="space-y-6">
            <Card class="border-dashed p-8 text-center">
                <div
                    class="bg-primary/10 text-primary mx-auto flex size-12 items-center justify-center rounded-full"
                >
                    <UserCheck class="size-6" />
                </div>
                <h3 class="mt-4 text-lg font-bold">
                    {{ __('User Account Management & RBAC') }}
                </h3>
                <p class="text-muted-foreground mx-auto mt-2 max-w-md text-sm">
                    {{
                        __(
                            'User provisioning, role assignments, department scoping, and security credentials management (Epic E02-05).',
                        )
                    }}
                </p>
                <div class="mt-6">
                    <Badge variant="outline" class="font-mono text-xs">
                        STORY [E02-05]
                    </Badge>
                </div>
            </Card>
        </div>

        <!-- Form Sheet Component (Drawer) -->
        <PolicyThresholdSheet
            v-model:open="sheetOpen"
            :is-plant-default="sheetIsPlantDefault"
            :threshold="selectedThreshold"
            :available-departments="availableDepartments"
            :existing-override-dept-ids="existingOverrideDeptIds"
        />

        <!-- Revert Override Confirmation Dialog -->
        <ConfirmationDialog
            v-model:open="deleteDialogOpen"
            :title="__('Delete department override?')"
            :description="
                __(
                    'This department will revert to inheriting the plant-wide default policy threshold. Existing overtime records will not be altered.',
                )
            "
            :confirm-text="__('Yes, Delete Override')"
            :cancel-text="__('Cancel')"
            variant="destructive"
            :loading="isDeleting"
            @confirm="executeDeleteOverride"
        />
    </div>
</template>
