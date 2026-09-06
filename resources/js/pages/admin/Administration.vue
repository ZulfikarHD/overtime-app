<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Edit2,
    Flame,
    Info,
    KeyRound,
    Layers,
    Lock,
    Plus,
    RotateCcw,
    Search,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    UserCheck,
    UserPlus,
    Users,
    UserX,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmationDialog from '@/components/admin/ConfirmationDialog.vue';
import PolicyThresholdSheet, {
    type DepartmentOption,
    type PolicyThresholdRecord,
} from '@/components/admin/PolicyThresholdSheet.vue';
import UserFormSheet, {
    type DepartmentWithSections,
    type RoleOption,
    type UserRecord,
} from '@/components/admin/UserFormSheet.vue';
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
import usersRoute from '@/routes/admin/users';

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

export type UserPagination = {
    data: UserRecord[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type UserStats = {
    total: number;
    active: number;
    inactive: number;
    by_role: {
        admin: number;
        manager: number;
        team_leader: number;
        user: number;
    };
};

export type UserFilters = {
    user_search?: string;
    user_role?: string;
    user_status?: string;
    user_department_id?: number | null;
};

const props = defineProps<{
    activeTab?: string;
    plantDefault: PolicyThresholdRecord;
    departments: DepartmentPolicyStatus[];
    availableDepartments: DepartmentOption[];
    policyStats: PolicyStats;
    users?: UserPagination;
    userStats?: UserStats;
    userFilters?: UserFilters;
    availableRoles?: RoleOption[];
    departmentsWithSections?: DepartmentWithSections[];
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

// -------------------------------------------------------------
// TAB 1: POLICY THRESHOLDS LOGIC
// -------------------------------------------------------------
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

const existingOverrideDeptIds = computed(() => {
    return props.departments
        .filter((d) => d.has_override)
        .map((d) => d.department_id);
});

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

// -------------------------------------------------------------
// TAB 2: USER ACCOUNTS LOGIC (E02-05)
// -------------------------------------------------------------
const userSearchInput = ref(props.userFilters?.user_search || '');
const userRoleFilter = ref(props.userFilters?.user_role || 'all');
const userStatusFilter = ref(props.userFilters?.user_status || 'all');
const userDeptFilter = ref<string | number>(
    props.userFilters?.user_department_id || '',
);

function applyUserFilters() {
    router.get(
        administration.url({
            query: {
                tab: 'users',
                user_search: userSearchInput.value.trim() || undefined,
                user_role:
                    userRoleFilter.value !== 'all'
                        ? userRoleFilter.value
                        : undefined,
                user_status:
                    userStatusFilter.value !== 'all'
                        ? userStatusFilter.value
                        : undefined,
                user_department_id: userDeptFilter.value || undefined,
            },
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function handleUserPagination(url: string | null) {
    if (!url) {
        return;
    }
    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
}

function selectUserRoleFilter(role: string) {
    userRoleFilter.value = role;
    applyUserFilters();
}

function selectUserStatusFilter(status: string) {
    userStatusFilter.value = status;
    applyUserFilters();
}

// User Form Sheet State
const userSheetOpen = ref(false);
const selectedUser = ref<UserRecord | null>(null);

function openCreateUser() {
    selectedUser.value = null;
    userSheetOpen.value = true;
}

function openEditUser(user: UserRecord) {
    selectedUser.value = { ...user };
    userSheetOpen.value = true;
}

// Status Toggle Confirmation Dialog State
const statusDialogOpen = ref(false);
const userToToggle = ref<UserRecord | null>(null);
const isTogglingStatus = ref(false);

function triggerToggleStatus(user: UserRecord) {
    if (user.is_self) {
        return;
    }
    userToToggle.value = user;
    statusDialogOpen.value = true;
}

function executeToggleStatus() {
    if (!userToToggle.value) {
        return;
    }
    isTogglingStatus.value = true;
    router.put(
        usersRoute.update.url(userToToggle.value.id),
        {
            name: userToToggle.value.name,
            email: userToToggle.value.email,
            role: userToToggle.value.role,
            department_id: userToToggle.value.department_id,
            section_id: userToToggle.value.section_id,
            npk: userToToggle.value.npk,
            is_active: !userToToggle.value.is_active,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isTogglingStatus.value = false;
                statusDialogOpen.value = false;
                userToToggle.value = null;
            },
        },
    );
}

// Password Reset Dialog State
const resetDialogOpen = ref(false);
const userToReset = ref<UserRecord | null>(null);
const isSendingReset = ref(false);

function triggerResetPassword(user: UserRecord) {
    userToReset.value = user;
    resetDialogOpen.value = true;
}

function executeResetPassword() {
    if (!userToReset.value) {
        return;
    }
    isSendingReset.value = true;
    router.post(
        usersRoute.resetPassword.url(userToReset.value.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isSendingReset.value = false;
                resetDialogOpen.value = false;
                userToReset.value = null;
            },
        },
    );
}

// Delete User Dialog State
const deleteUserDialogOpen = ref(false);
const userToDelete = ref<UserRecord | null>(null);
const isDeletingUser = ref(false);

function triggerDeleteUser(user: UserRecord) {
    if (user.is_self || !user.can_delete) {
        return;
    }
    userToDelete.value = user;
    deleteUserDialogOpen.value = true;
}

function executeDeleteUser() {
    if (!userToDelete.value) {
        return;
    }
    isDeletingUser.value = true;
    router.delete(usersRoute.destroy.url(userToDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            isDeletingUser.value = false;
            deleteUserDialogOpen.value = false;
            userToDelete.value = null;
        },
    });
}

function getRoleBadgeStyle(role: string): string {
    switch (role) {
        case 'admin':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border-purple-200 dark:border-purple-800';
        case 'manager':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800';
        case 'team_leader':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
        default:
            return 'bg-slate-100 text-slate-800 dark:bg-slate-800/60 dark:text-slate-300 border-slate-200 dark:border-slate-700';
    }
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
                    <Badge
                        v-if="userStats"
                        variant="secondary"
                        class="ml-1 text-xs"
                    >
                        {{ userStats.total }}
                    </Badge>
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

        <!-- Tab 2: User Accounts (E02-05 Full Implementation) -->
        <div v-else-if="currentTab === 'users'" class="space-y-6">
            <!-- User KPI Summary Cards -->
            <div
                v-if="userStats"
                class="grid grid-cols-2 gap-4 sm:grid-cols-4"
                data-test="user-stats-grid"
            >
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle
                            class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                        >
                            {{ __('Total Accounts') }}
                        </CardTitle>
                        <Users class="text-muted-foreground size-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ userStats.total }}
                        </div>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ __('System user directory') }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle
                            class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                        >
                            {{ __('Active Logins') }}
                        </CardTitle>
                        <UserCheck class="size-4 text-emerald-500" />
                    </CardHeader>
                    <CardContent>
                        <div
                            class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"
                        >
                            {{ userStats.active }}
                        </div>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ __('Can authenticate') }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle
                            class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                        >
                            {{ __('Deactivated') }}
                        </CardTitle>
                        <UserX class="size-4 text-rose-500" />
                    </CardHeader>
                    <CardContent>
                        <div
                            class="text-2xl font-bold text-rose-600 dark:text-rose-400"
                        >
                            {{ userStats.inactive }}
                        </div>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ __('Blocked from access') }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle
                            class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                        >
                            {{ __('Role Breakdown') }}
                        </CardTitle>
                        <Shield class="text-primary size-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-1.5 pt-1">
                            <Badge
                                variant="outline"
                                class="bg-purple-50 text-xs font-semibold text-purple-700 dark:bg-purple-950/60 dark:text-purple-300"
                                :title="__('Administrators')"
                            >
                                {{ userStats.by_role.admin }} Adm
                            </Badge>
                            <Badge
                                variant="outline"
                                class="bg-blue-50 text-xs font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300"
                                :title="__('Managers')"
                            >
                                {{ userStats.by_role.manager }} Mgr
                            </Badge>
                            <Badge
                                variant="outline"
                                class="bg-emerald-50 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
                                :title="__('Team Leaders')"
                            >
                                {{ userStats.by_role.team_leader }} TL
                            </Badge>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- User Accounts Header & Action Bar -->
            <div class="space-y-4">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="text-lg font-bold tracking-tight">
                            {{ __('User Accounts & Access Control') }}
                        </h2>
                        <p class="text-muted-foreground text-xs">
                            {{
                                __(
                                    'Provision logins, assign operational roles, scope permissions, and manage credential resets.',
                                )
                            }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            size="sm"
                            data-test="btn-add-user"
                            @click="openCreateUser"
                        >
                            <UserPlus class="mr-1.5 size-4" />
                            <span>{{ __('+ Add User Account') }}</span>
                        </Button>
                    </div>
                </div>

                <!-- Search and Filter Bar -->
                <div
                    class="flex flex-col items-stretch justify-between gap-3 lg:flex-row lg:items-center"
                >
                    <div
                        class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center"
                    >
                        <div class="relative w-full sm:max-w-xs">
                            <Search
                                class="text-muted-foreground absolute top-2.5 left-2.5 size-4"
                            />
                            <Input
                                v-model="userSearchInput"
                                type="search"
                                :placeholder="
                                    __('Search by name, email, NPK...')
                                "
                                class="h-9 pl-8 text-xs"
                                data-test="input-search-users"
                                @keyup.enter="applyUserFilters"
                            />
                        </div>

                        <!-- Department Filter Dropdown -->
                        <select
                            v-model="userDeptFilter"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus:ring-ring h-9 rounded-md border px-3 text-xs shadow-xs focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
                            data-test="select-filter-dept"
                            @change="applyUserFilters"
                        >
                            <option value="">
                                {{ __('All Departments') }}
                            </option>
                            <option
                                v-for="dept in departments"
                                :key="dept.department_id"
                                :value="dept.department_id"
                            >
                                {{ dept.department_code }} -
                                {{ dept.department_name }}
                            </option>
                        </select>

                        <Button
                            variant="secondary"
                            size="sm"
                            class="h-9 px-3 text-xs"
                            data-test="btn-apply-user-search"
                            @click="applyUserFilters"
                        >
                            {{ __('Filter') }}
                        </Button>
                    </div>

                    <!-- Role & Status Pills -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Role Pills -->
                        <div
                            class="bg-muted/40 flex items-center gap-1 rounded-lg border p-1"
                        >
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs"
                                :class="{
                                    'bg-background font-semibold shadow-xs':
                                        userRoleFilter === 'all',
                                }"
                                data-test="filter-role-all"
                                @click="selectUserRoleFilter('all')"
                            >
                                {{ __('All Roles') }}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs"
                                :class="{
                                    'bg-background font-semibold shadow-xs':
                                        userRoleFilter === 'admin',
                                }"
                                data-test="filter-role-admin"
                                @click="selectUserRoleFilter('admin')"
                            >
                                {{ __('Admin') }}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs"
                                :class="{
                                    'bg-background font-semibold shadow-xs':
                                        userRoleFilter === 'manager',
                                }"
                                data-test="filter-role-manager"
                                @click="selectUserRoleFilter('manager')"
                            >
                                {{ __('Manager') }}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs"
                                :class="{
                                    'bg-background font-semibold shadow-xs':
                                        userRoleFilter === 'team_leader',
                                }"
                                data-test="filter-role-team-leader"
                                @click="selectUserRoleFilter('team_leader')"
                            >
                                {{ __('Team Leader') }}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs"
                                :class="{
                                    'bg-background font-semibold shadow-xs':
                                        userRoleFilter === 'user',
                                }"
                                data-test="filter-role-user"
                                @click="selectUserRoleFilter('user')"
                            >
                                {{ __('Operator') }}
                            </Button>
                        </div>

                        <!-- Status Filter -->
                        <div
                            class="bg-muted/40 flex items-center gap-1 rounded-lg border p-1"
                        >
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs"
                                :class="{
                                    'bg-background font-semibold shadow-xs':
                                        userStatusFilter === 'all',
                                }"
                                data-test="filter-status-all"
                                @click="selectUserStatusFilter('all')"
                            >
                                {{ __('All Status') }}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs"
                                :class="{
                                    'bg-background font-semibold text-emerald-700 shadow-xs dark:text-emerald-300':
                                        userStatusFilter === 'active',
                                }"
                                data-test="filter-status-active"
                                @click="selectUserStatusFilter('active')"
                            >
                                {{ __('Active') }}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs"
                                :class="{
                                    'bg-background font-semibold text-rose-700 shadow-xs dark:text-rose-300':
                                        userStatusFilter === 'inactive',
                                }"
                                data-test="filter-status-inactive"
                                @click="selectUserStatusFilter('inactive')"
                            >
                                {{ __('Inactive') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- User Accounts Table -->
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
                                        {{ __('User / Credentials') }}
                                    </th>
                                    <th class="px-4 py-3 font-semibold">
                                        {{ __('Role') }}
                                    </th>
                                    <th class="px-4 py-3 font-semibold">
                                        {{ __('Organizational Assignment') }}
                                    </th>
                                    <th class="px-4 py-3 font-semibold">
                                        {{ __('Last Login (WIB)') }}
                                    </th>
                                    <th
                                        class="px-4 py-3 text-center font-semibold"
                                    >
                                        {{ __('Status') }}
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
                                    v-if="!users || users.data.length === 0"
                                    class="text-center"
                                >
                                    <td
                                        colspan="6"
                                        class="text-muted-foreground py-8 text-sm"
                                    >
                                        {{ __('No user accounts found.') }}
                                    </td>
                                </tr>

                                <tr
                                    v-for="userItem in users?.data"
                                    :key="userItem.id"
                                    class="hover:bg-muted/40 transition-colors"
                                    :data-test="`row-user-${userItem.email}`"
                                >
                                    <!-- User Identity -->
                                    <td class="px-4 py-3">
                                        <div class="space-y-0.5">
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="text-foreground font-semibold"
                                                >
                                                    {{ userItem.name }}
                                                </span>
                                                <Badge
                                                    v-if="userItem.is_self"
                                                    variant="secondary"
                                                    class="border-primary/30 bg-primary/10 text-primary text-2xs"
                                                >
                                                    {{ __('You') }}
                                                </Badge>
                                            </div>
                                            <div
                                                class="text-muted-foreground flex items-center gap-2 text-xs"
                                            >
                                                <span>{{
                                                    userItem.email
                                                }}</span>
                                                <span
                                                    v-if="userItem.npk"
                                                    class="border-border bg-muted/60 text-2xs rounded border px-1 font-mono"
                                                >
                                                    {{ userItem.npk }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Role Badge -->
                                    <td class="px-4 py-3">
                                        <Badge
                                            class="text-xs font-semibold capitalize"
                                            :class="
                                                getRoleBadgeStyle(userItem.role)
                                            "
                                        >
                                            {{ userItem.role_label }}
                                        </Badge>
                                    </td>

                                    <!-- Scope -->
                                    <td class="px-4 py-3 text-xs">
                                        <div
                                            v-if="userItem.department"
                                            class="space-y-0.5"
                                        >
                                            <div
                                                class="flex items-center gap-1.5 font-medium"
                                            >
                                                <Building2
                                                    class="text-muted-foreground size-3.5"
                                                />
                                                <span>{{
                                                    userItem.department.name
                                                }}</span>
                                            </div>
                                            <div
                                                v-if="userItem.section"
                                                class="text-muted-foreground text-2xs ml-5 flex items-center gap-1"
                                            >
                                                <Layers class="size-3" />
                                                <span>{{
                                                    userItem.section.name
                                                }}</span>
                                            </div>
                                        </div>
                                        <span
                                            v-else
                                            class="text-muted-foreground text-2xs italic"
                                        >
                                            {{
                                                __('Plant-wide / Unrestricted')
                                            }}
                                        </span>
                                    </td>

                                    <!-- Last Login -->
                                    <td class="px-4 py-3 text-xs">
                                        <span
                                            v-if="userItem.last_login_at"
                                            class="font-mono"
                                        >
                                            {{ userItem.last_login_at }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-muted-foreground text-2xs italic"
                                        >
                                            {{ __('Never Logged In') }}
                                        </span>
                                    </td>

                                    <!-- Status Badge & Toggle -->
                                    <td class="px-4 py-3 text-center">
                                        <div
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <Badge
                                                v-if="userItem.is_active"
                                                class="border-emerald-200 bg-emerald-100 text-xs text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/60 dark:text-emerald-300"
                                            >
                                                <CheckCircle2
                                                    class="mr-1 size-3"
                                                />
                                                {{ __('Active') }}
                                            </Badge>
                                            <Badge
                                                v-else
                                                class="border-rose-200 bg-rose-100 text-xs text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/60 dark:text-rose-300"
                                            >
                                                <XCircle class="mr-1 size-3" />
                                                {{ __('Inactive') }}
                                            </Badge>
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-4 py-3 text-right">
                                        <div
                                            class="flex items-center justify-end gap-1"
                                        >
                                            <!-- Status Toggle Button -->
                                            <Button
                                                v-if="!userItem.is_self"
                                                variant="ghost"
                                                size="icon-sm"
                                                :data-test="`btn-toggle-status-${userItem.email}`"
                                                :title="
                                                    userItem.is_active
                                                        ? __(
                                                              'Deactivate Account',
                                                          )
                                                        : __('Activate Account')
                                                "
                                                @click="
                                                    triggerToggleStatus(
                                                        userItem,
                                                    )
                                                "
                                            >
                                                <UserX
                                                    v-if="userItem.is_active"
                                                    class="size-4 text-amber-600 hover:text-amber-700"
                                                />
                                                <UserCheck
                                                    v-else
                                                    class="size-4 text-emerald-600 hover:text-emerald-700"
                                                />
                                            </Button>

                                            <!-- Edit User Button -->
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                :data-test="`btn-edit-user-${userItem.id}`"
                                                :title="__('Edit User Account')"
                                                @click="openEditUser(userItem)"
                                            >
                                                <Edit2
                                                    class="text-muted-foreground hover:text-foreground size-4"
                                                />
                                            </Button>

                                            <!-- Send Password Reset Link -->
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                :data-test="`btn-reset-password-${userItem.email}`"
                                                :title="
                                                    __(
                                                        'Send Password Reset Link',
                                                    )
                                                "
                                                @click="
                                                    triggerResetPassword(
                                                        userItem,
                                                    )
                                                "
                                            >
                                                <KeyRound
                                                    class="size-4 text-blue-500 hover:text-blue-600"
                                                />
                                            </Button>

                                            <!-- Delete User Button (Guarded) -->
                                            <Button
                                                v-if="userItem.can_delete"
                                                variant="ghost"
                                                size="icon-sm"
                                                :data-test="`btn-delete-user-${userItem.email}`"
                                                :title="
                                                    __('Delete User Account')
                                                "
                                                @click="
                                                    triggerDeleteUser(userItem)
                                                "
                                            >
                                                <Trash2
                                                    class="size-4 text-red-500 hover:text-red-600"
                                                />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- User Pagination Bar -->
                    <div
                        v-if="users && users.total > 0"
                        class="border-border/70 bg-muted/20 text-muted-foreground flex flex-col gap-3 border-t px-4 py-3 text-xs sm:flex-row sm:items-center sm:justify-between"
                        data-test="user-pagination-bar"
                    >
                        <div>
                            {{
                                __(
                                    'Showing :from to :to of :total user accounts',
                                    {
                                        from: users.from ?? 0,
                                        to: users.to ?? 0,
                                        total: users.total,
                                    },
                                )
                            }}
                        </div>

                        <div class="flex items-center gap-1 self-center">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="size-8 p-0"
                                :disabled="!users.prev_page_url"
                                data-test="btn-prev-page"
                                @click="
                                    handleUserPagination(users.prev_page_url)
                                "
                            >
                                <ChevronLeft class="size-4" />
                            </Button>

                            <template
                                v-for="(link, idx) in users.links.slice(1, -1)"
                                :key="idx"
                            >
                                <Button
                                    type="button"
                                    size="sm"
                                    :variant="
                                        link.active ? 'default' : 'outline'
                                    "
                                    class="h-8 min-w-8 px-2 text-xs"
                                    :disabled="!link.url"
                                    @click="handleUserPagination(link.url)"
                                >
                                    <span v-html="link.label" />
                                </Button>
                            </template>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="size-8 p-0"
                                :disabled="!users.next_page_url"
                                data-test="btn-next-page"
                                @click="
                                    handleUserPagination(users.next_page_url)
                                "
                            >
                                <ChevronRight class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sheet Form: Policy Threshold (Tab 1) -->
        <PolicyThresholdSheet
            v-model:open="sheetOpen"
            :is-plant-default="sheetIsPlantDefault"
            :threshold="selectedThreshold"
            :available-departments="availableDepartments"
            :existing-override-dept-ids="existingOverrideDeptIds"
        />

        <!-- Sheet Form: User Account (Tab 2) -->
        <UserFormSheet
            v-model:open="userSheetOpen"
            :user="selectedUser"
            :departments="departmentsWithSections || []"
            :roles="availableRoles || []"
        />

        <!-- Dialog: Delete Policy Override -->
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

        <!-- Dialog: Toggle User Active Status -->
        <ConfirmationDialog
            v-model:open="statusDialogOpen"
            :title="
                userToToggle?.is_active
                    ? __('Deactivate user account :name?', {
                          name: userToToggle?.name ?? '',
                      })
                    : __('Activate user account :name?', {
                          name: userToToggle?.name ?? '',
                      })
            "
            :description="
                userToToggle?.is_active
                    ? __(
                          'This user will be blocked from logging into the system until re-activated. All historical overtime records and review trails remain securely preserved.',
                      )
                    : __(
                          'This user will regain access to log in and perform actions matching their assigned role.',
                      )
            "
            :confirm-text="
                userToToggle?.is_active
                    ? __('Yes, Deactivate Account')
                    : __('Yes, Activate Account')
            "
            :cancel-text="__('Cancel')"
            :variant="userToToggle?.is_active ? 'destructive' : 'default'"
            :loading="isTogglingStatus"
            @confirm="executeToggleStatus"
        />

        <!-- Dialog: Send Password Reset Link -->
        <ConfirmationDialog
            v-model:open="resetDialogOpen"
            :title="
                __('Send password reset link to :email?', {
                    email: userToReset?.email ?? '',
                })
            "
            :description="
                __(
                    'An email containing a secure password reset link will be sent to the user. The link will remain active for 60 minutes.',
                )
            "
            :confirm-text="__('Send Reset Link')"
            :cancel-text="__('Cancel')"
            variant="default"
            :loading="isSendingReset"
            @confirm="executeResetPassword"
        />

        <!-- Dialog: Delete User Account -->
        <ConfirmationDialog
            v-model:open="deleteUserDialogOpen"
            :title="
                __('Delete user account :name?', {
                    name: userToDelete?.name ?? '',
                })
            "
            :description="
                __(
                    'This user account will be permanently deleted from the system. This action cannot be reversed.',
                )
            "
            :confirm-text="__('Yes, Delete Account')"
            :cancel-text="__('Cancel')"
            variant="destructive"
            :loading="isDeletingUser"
            @confirm="executeDeleteUser"
        />
    </div>
</template>
