<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    Calendar,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Edit2,
    FolderTree,
    Layers,
    Plus,
    Search,
    Trash2,
    Users,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmationDialog from '@/components/admin/ConfirmationDialog.vue';
import DepartmentFormDialog, {
    type DepartmentRecord,
} from '@/components/admin/DepartmentFormDialog.vue';
import SectionFormDialog, {
    type SectionRecord,
} from '@/components/admin/SectionFormDialog.vue';
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
import { formatRupiah } from '@/lib/formatters';
import { dashboard } from '@/routes';
import { masterData } from '@/routes/admin';
import departmentsRoute from '@/routes/admin/departments';
import sectionsRoute from '@/routes/admin/sections';

export type DepartmentWithSections = DepartmentRecord & {
    sections: SectionRecord[];
};

const props = defineProps<{
    activeTab?: string;
    departments: DepartmentWithSections[];
}>();

const { __ } = useTrans();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Master Data',
                href: masterData(),
            },
        ],
    },
});

const currentTab = ref(props.activeTab || 'departments');
const searchQuery = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');

// Expanded accordion IDs
const expandedDeptIds = ref<Set<number>>(
    new Set(props.departments.map((d) => d.id)),
);

watch(
    () => props.departments,
    (depts) => {
        depts.forEach((d) => expandedDeptIds.value.add(d.id));
    },
    { deep: true },
);

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
        masterData.url({ query: { tab } }),
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
}

// Dialog States
const isDeptDialogOpen = ref(false);
const selectedDept = ref<DepartmentRecord | null>(null);

const isSectionDialogOpen = ref(false);
const selectedSection = ref<SectionRecord | null>(null);
const sectionParentDept = ref<DepartmentRecord | null>(null);

const confirmDialog = ref({
    open: false,
    title: '',
    description: '',
    confirmText: '',
    variant: 'destructive' as 'destructive' | 'default',
    loading: false,
    action: () => {},
});

// Computed Department Metrics
const totalDepartments = computed(() => props.departments.length);
const totalSections = computed(() =>
    props.departments.reduce((sum, d) => sum + (d.sections?.length || 0), 0),
);
const activeDepartmentsCount = computed(
    () => props.departments.filter((d) => d.is_active).length,
);

// Filtering
const filteredDepartments = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return props.departments
        .map((dept) => {
            const matchesDept =
                !query ||
                dept.name.toLowerCase().includes(query) ||
                dept.code.toLowerCase().includes(query) ||
                dept.cost_center_code.toLowerCase().includes(query);

            const matchingSections = (dept.sections || []).filter((sec) => {
                if (statusFilter.value === 'active' && !sec.is_active) {
                    return false;
                }
                if (statusFilter.value === 'inactive' && sec.is_active) {
                    return false;
                }

                if (!query) {
                    return true;
                }
                return (
                    sec.name.toLowerCase().includes(query) ||
                    sec.code.toLowerCase().includes(query)
                );
            });

            if (statusFilter.value === 'active' && !dept.is_active) {
                return null;
            }
            if (statusFilter.value === 'inactive' && dept.is_active) {
                return null;
            }

            if (matchesDept || matchingSections.length > 0) {
                return {
                    ...dept,
                    sections: matchingSections,
                };
            }

            return null;
        })
        .filter((dept): dept is DepartmentWithSections => dept !== null);
});

function toggleAccordion(id: number) {
    if (expandedDeptIds.value.has(id)) {
        expandedDeptIds.value.delete(id);
    } else {
        expandedDeptIds.value.add(id);
    }
}

function expandAll() {
    expandedDeptIds.value = new Set(props.departments.map((d) => d.id));
}

function collapseAll() {
    expandedDeptIds.value.clear();
}

// Handlers for Department
function handleAddDepartment() {
    selectedDept.value = null;
    isDeptDialogOpen.value = true;
}

function handleEditDepartment(dept: DepartmentRecord) {
    selectedDept.value = dept;
    isDeptDialogOpen.value = true;
}

function handleToggleDepartmentStatus(dept: DepartmentWithSections) {
    const willDeactivate = dept.is_active;

    if (willDeactivate) {
        const activeChildSections = (dept.sections || []).filter(
            (s) => s.is_active,
        );
        if (activeChildSections.length > 0) {
            confirmDialog.value = {
                open: true,
                title: __('Cannot Deactivate Department'),
                description: __(
                    'Cannot deactivate department because it still has :count active section(s). Deactivate child sections first.',
                    { count: activeChildSections.length },
                ),
                confirmText: __('Understood'),
                variant: 'default',
                loading: false,
                action: () => {
                    confirmDialog.value.open = false;
                },
            };
            return;
        }

        confirmDialog.value = {
            open: true,
            title: __('Deactivate Department') + ` "${dept.name}"?`,
            description: __(
                'This department will be soft-disabled and hidden from new timesheet entries. Existing records and history will remain safe.',
            ),
            confirmText: __('Yes, Deactivate'),
            variant: 'destructive',
            loading: false,
            action: () => {
                confirmDialog.value.loading = true;
                router.put(
                    departmentsRoute.update.url(dept.id),
                    {
                        name: dept.name,
                        cost_center_code: dept.cost_center_code,
                        default_hourly_rate: dept.default_hourly_rate,
                        is_active: false,
                    },
                    {
                        preserveScroll: true,
                        onFinish: () => {
                            confirmDialog.value.loading = false;
                            confirmDialog.value.open = false;
                        },
                    },
                );
            },
        };
    } else {
        confirmDialog.value = {
            open: true,
            title: __('Activate Department') + ` "${dept.name}"?`,
            description: __(
                'This department will become active and available for timesheet entries and employee assignments.',
            ),
            confirmText: __('Yes, Activate'),
            variant: 'default',
            loading: false,
            action: () => {
                confirmDialog.value.loading = true;
                router.put(
                    departmentsRoute.update.url(dept.id),
                    {
                        name: dept.name,
                        cost_center_code: dept.cost_center_code,
                        default_hourly_rate: dept.default_hourly_rate,
                        is_active: true,
                    },
                    {
                        preserveScroll: true,
                        onFinish: () => {
                            confirmDialog.value.loading = false;
                            confirmDialog.value.open = false;
                        },
                    },
                );
            },
        };
    }
}

function handleDeleteDepartment(dept: DepartmentWithSections) {
    const hasSections =
        (dept.sections_count ?? 0) > 0 || (dept.sections?.length ?? 0) > 0;
    const hasEmployees = (dept.employees_count ?? 0) > 0;
    const hasSubmissions = (dept.overtime_submissions_count ?? 0) > 0;

    if (hasSections || hasEmployees || hasSubmissions) {
        let msg = '';
        if (hasSections) {
            msg = __(
                'Cannot delete department because it contains :count section(s). Remove or reassign sections first.',
                { count: dept.sections_count || dept.sections?.length || 0 },
            );
        } else if (hasEmployees) {
            msg = __(
                'Cannot delete department because it still has :count registered employee(s). Reassign employees first.',
                { count: dept.employees_count ?? 0 },
            );
        } else {
            msg = __(
                'Cannot delete department because it has :count linked overtime submission record(s). Deactivate instead to preserve historical integrity.',
                { count: dept.overtime_submissions_count ?? 0 },
            );
        }

        confirmDialog.value = {
            open: true,
            title: __('Deletion Blocked'),
            description: msg,
            confirmText: __('Understood'),
            variant: 'default',
            loading: false,
            action: () => {
                confirmDialog.value.open = false;
            },
        };
        return;
    }

    confirmDialog.value = {
        open: true,
        title: __('Delete Department') + ` "${dept.name}"?`,
        description: __(
            'This action cannot be undone. Historical records should remain safe by choosing deactivation instead.',
        ),
        confirmText: __('Delete Department'),
        variant: 'destructive',
        loading: false,
        action: () => {
            confirmDialog.value.loading = true;
            router.delete(departmentsRoute.destroy.url(dept.id), {
                preserveScroll: true,
                onFinish: () => {
                    confirmDialog.value.loading = false;
                    confirmDialog.value.open = false;
                },
            });
        },
    };
}

// Handlers for Section
function handleAddSection(dept: DepartmentRecord) {
    expandedDeptIds.value.add(dept.id);
    selectedSection.value = null;
    sectionParentDept.value = dept;
    isSectionDialogOpen.value = true;
}

function handleEditSection(dept: DepartmentRecord, sec: SectionRecord) {
    selectedSection.value = sec;
    sectionParentDept.value = dept;
    isSectionDialogOpen.value = true;
}

function handleToggleSectionStatus(sec: SectionRecord) {
    const nextState = !sec.is_active;

    confirmDialog.value = {
        open: true,
        title: nextState
            ? __('Activate Section') + ` "${sec.name}"?`
            : __('Deactivate Section') + ` "${sec.name}"?`,
        description: nextState
            ? __(
                  'This section will become active and available for timesheet entries.',
              )
            : __(
                  'This section will be hidden from new timesheet entries. All historical data remains safe.',
              ),
        confirmText: nextState ? __('Yes, Activate') : __('Yes, Deactivate'),
        variant: nextState ? 'default' : 'destructive',
        loading: false,
        action: () => {
            confirmDialog.value.loading = true;
            router.put(
                sectionsRoute.update.url(sec.id),
                {
                    name: sec.name,
                    is_active: nextState,
                },
                {
                    preserveScroll: true,
                    onFinish: () => {
                        confirmDialog.value.loading = false;
                        confirmDialog.value.open = false;
                    },
                },
            );
        },
    };
}

function handleDeleteSection(sec: SectionRecord) {
    const hasEmployees = (sec.employees_count ?? 0) > 0;
    const hasSubmissions = (sec.overtime_submissions_count ?? 0) > 0;

    if (hasEmployees || hasSubmissions) {
        const msg = hasEmployees
            ? __(
                  'Cannot delete section because it still has :count registered employee(s). Reassign employees first.',
                  { count: sec.employees_count ?? 0 },
              )
            : __(
                  'Cannot delete section because it has :count linked overtime submission record(s). Deactivate instead to preserve historical integrity.',
                  { count: sec.overtime_submissions_count ?? 0 },
              );

        confirmDialog.value = {
            open: true,
            title: __('Deletion Blocked'),
            description: msg,
            confirmText: __('Understood'),
            variant: 'default',
            loading: false,
            action: () => {
                confirmDialog.value.open = false;
            },
        };
        return;
    }

    confirmDialog.value = {
        open: true,
        title: __('Delete Section') + ` "${sec.name}"?`,
        description: __(
            'This action cannot be undone. Historical records should remain safe by choosing deactivation instead.',
        ),
        confirmText: __('Delete Section'),
        variant: 'destructive',
        loading: false,
        action: () => {
            confirmDialog.value.loading = true;
            router.delete(sectionsRoute.destroy.url(sec.id), {
                preserveScroll: true,
                onFinish: () => {
                    confirmDialog.value.loading = false;
                    confirmDialog.value.open = false;
                },
            });
        },
    };
}
</script>

<template>
    <Head :title="__('Master Data Hub')" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- Page Header -->
        <div
            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-foreground text-2xl font-bold tracking-tight">
                    {{ __('Master Data Hub') }}
                </h1>
                <p class="text-muted-foreground text-sm">
                    {{
                        __(
                            'Manage factory departments and their child production/maintenance sections',
                        )
                    }}
                </p>
            </div>
        </div>

        <!-- Master Data Hub Navigation Tabs (Hard UX Constraint) -->
        <div class="border-border/80 border-b">
            <nav class="-mb-px flex space-x-6">
                <button
                    type="button"
                    :class="[
                        currentTab === 'departments'
                            ? 'border-primary text-primary font-semibold'
                            : 'text-muted-foreground hover:border-border hover:text-foreground border-transparent',
                        'flex cursor-pointer items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors',
                    ]"
                    data-test="tab-departments"
                    @click="switchTab('departments')"
                >
                    <FolderTree class="size-4" />
                    <span>{{ __('Departments & Sections') }}</span>
                    <Badge variant="secondary" class="ml-1 text-xs">
                        {{ departments.length }}
                    </Badge>
                </button>

                <button
                    type="button"
                    :class="[
                        currentTab === 'employees'
                            ? 'border-primary text-primary font-semibold'
                            : 'text-muted-foreground hover:border-border hover:text-foreground border-transparent',
                        'flex cursor-pointer items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors',
                    ]"
                    data-test="tab-employees"
                    @click="switchTab('employees')"
                >
                    <Users class="size-4" />
                    <span>{{ __('Employees') }}</span>
                </button>

                <button
                    type="button"
                    :class="[
                        currentTab === 'calendar'
                            ? 'border-primary text-primary font-semibold'
                            : 'text-muted-foreground hover:border-border hover:text-foreground border-transparent',
                        'flex cursor-pointer items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors',
                    ]"
                    data-test="tab-calendar"
                    @click="switchTab('calendar')"
                >
                    <Calendar class="size-4" />
                    <span>{{ __('Operational Calendar') }}</span>
                </button>
            </nav>
        </div>

        <!-- TAB 1: DEPARTMENTS & SECTIONS -->
        <div v-if="currentTab === 'departments'" class="space-y-6">
            <!-- Metrics Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-3">
                <Card class="border-border/60 shadow-xs">
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
                        <div
                            class="text-2xl font-bold"
                            data-test="metric-total-departments"
                        >
                            {{ totalDepartments }}
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-xs">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle
                            class="text-muted-foreground text-sm font-medium"
                        >
                            {{ __('Total Sections') }}
                        </CardTitle>
                        <Layers class="text-muted-foreground size-4" />
                    </CardHeader>
                    <CardContent>
                        <div
                            class="text-2xl font-bold"
                            data-test="metric-total-sections"
                        >
                            {{ totalSections }}
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 shadow-xs">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle
                            class="text-muted-foreground text-sm font-medium"
                        >
                            {{ __('Active Departments') }}
                        </CardTitle>
                        <CheckCircle2 class="size-4 text-emerald-500" />
                    </CardHeader>
                    <CardContent>
                        <div
                            class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"
                        >
                            {{ activeDepartmentsCount }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Toolbar & Actions -->
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div
                    class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center"
                >
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-72">
                        <Search
                            class="text-muted-foreground absolute top-2.5 left-2.5 size-4"
                        />
                        <Input
                            v-model="searchQuery"
                            type="search"
                            :placeholder="__('Search department or section...')"
                            class="pl-8 text-sm"
                            data-test="search-input"
                        />
                    </div>

                    <!-- Status Filter -->
                    <div class="flex items-center gap-1">
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                statusFilter === 'all' ? 'secondary' : 'ghost'
                            "
                            class="text-xs"
                            @click="statusFilter = 'all'"
                        >
                            {{ __('All Status') }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                statusFilter === 'active'
                                    ? 'secondary'
                                    : 'ghost'
                            "
                            class="text-xs"
                            @click="statusFilter = 'active'"
                        >
                            {{ __('Only Active') }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                statusFilter === 'inactive'
                                    ? 'secondary'
                                    : 'ghost'
                            "
                            class="text-xs"
                            @click="statusFilter = 'inactive'"
                        >
                            {{ __('Only Inactive') }}
                        </Button>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-xs"
                        @click="
                            expandedDeptIds.size > 0
                                ? collapseAll()
                                : expandAll()
                        "
                    >
                        {{
                            expandedDeptIds.size > 0
                                ? __('Collapse All')
                                : __('Expand All')
                        }}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="gap-1.5"
                        data-test="btn-add-department"
                        @click="handleAddDepartment"
                    >
                        <Plus class="size-4" />
                        <span>{{ __('+ Add Department') }}</span>
                    </Button>
                </div>
            </div>

            <!-- Hierarchical Department & Section Accordion/List -->
            <div class="space-y-3">
                <div
                    v-if="filteredDepartments.length === 0"
                    class="border-border rounded-lg border border-dashed p-8 text-center"
                >
                    <FolderTree
                        class="text-muted-foreground/60 mx-auto size-8"
                    />
                    <h3 class="text-foreground mt-2 text-sm font-semibold">
                        {{ __('No departments found.') }}
                    </h3>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {{
                            __(
                                'Try adjusting your search query or add a new department.',
                            )
                        }}
                    </p>
                </div>

                <div
                    v-for="dept in filteredDepartments"
                    :key="dept.id"
                    class="border-border/70 bg-card overflow-hidden rounded-lg border shadow-xs transition-shadow"
                    :data-test="`dept-card-${dept.code}`"
                >
                    <!-- Department Row Header -->
                    <div
                        class="bg-muted/20 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer rounded-md p-1"
                                :aria-label="dept.name"
                                @click="toggleAccordion(dept.id)"
                            >
                                <ChevronDown
                                    v-if="expandedDeptIds.has(dept.id)"
                                    class="size-4 transition-transform"
                                />
                                <ChevronRight
                                    v-else
                                    class="size-4 transition-transform"
                                />
                            </button>

                            <div>
                                <div class="flex items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        class="font-mono text-xs font-bold tracking-wider"
                                    >
                                        {{ dept.code }}
                                    </Badge>
                                    <span
                                        class="text-foreground text-base font-semibold"
                                    >
                                        {{ dept.name }}
                                    </span>
                                    <Badge
                                        :variant="
                                            dept.is_active
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        :class="
                                            dept.is_active
                                                ? 'border-emerald-500/20 bg-emerald-600/15 text-emerald-800 dark:text-emerald-300'
                                                : 'text-muted-foreground'
                                        "
                                        class="text-[11px]"
                                    >
                                        {{
                                            dept.is_active
                                                ? __('Active')
                                                : __('Inactive')
                                        }}
                                    </Badge>
                                </div>

                                <div
                                    class="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs"
                                >
                                    <span
                                        class="flex items-center gap-1 font-mono"
                                    >
                                        <span>Cost Center:</span>
                                        <span
                                            class="text-foreground font-medium"
                                            >{{ dept.cost_center_code }}</span
                                        >
                                    </span>
                                    <span>•</span>
                                    <span class="flex items-center gap-1">
                                        <span
                                            >{{
                                                __('Default Hourly Rate')
                                            }}:</span
                                        >
                                        <span
                                            class="text-foreground font-mono font-medium"
                                        >
                                            {{
                                                formatRupiah(
                                                    dept.default_hourly_rate,
                                                )
                                            }}
                                        </span>
                                    </span>
                                    <span>•</span>
                                    <span class="flex items-center gap-1">
                                        <span>{{ __('Sections') }}:</span>
                                        <span
                                            class="text-foreground font-medium"
                                            >{{
                                                dept.sections?.length || 0
                                            }}</span
                                        >
                                    </span>
                                    <span v-if="(dept.employees_count ?? 0) > 0"
                                        >•</span
                                    >
                                    <span
                                        v-if="(dept.employees_count ?? 0) > 0"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            >{{ __('Total Employees') }}:</span
                                        >
                                        <span
                                            class="text-foreground font-medium"
                                            >{{ dept.employees_count }}</span
                                        >
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Department Actions -->
                        <div
                            class="flex items-center gap-1.5 self-end sm:self-center"
                        >
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="h-8 gap-1 text-xs"
                                :data-test="`btn-add-section-${dept.code}`"
                                @click="handleAddSection(dept)"
                            >
                                <Plus class="size-3.5" />
                                <span>{{ __('Add Section') }}</span>
                            </Button>

                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="size-8 p-0"
                                :title="__('Edit Department')"
                                :data-test="`btn-edit-dept-${dept.code}`"
                                @click="handleEditDepartment(dept)"
                            >
                                <Edit2 class="size-3.5" />
                            </Button>

                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                :class="[
                                    'size-8 p-0',
                                    dept.is_active
                                        ? 'text-amber-600 hover:text-amber-700'
                                        : 'text-emerald-600 hover:text-emerald-700',
                                ]"
                                :title="
                                    dept.is_active
                                        ? __('Deactivate Department')
                                        : __('Activate Department')
                                "
                                :data-test="`btn-toggle-dept-${dept.code}`"
                                @click="handleToggleDepartmentStatus(dept)"
                            >
                                <XCircle v-if="dept.is_active" class="size-4" />
                                <CheckCircle2 v-else class="size-4" />
                            </Button>

                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                class="size-8 p-0 text-red-600 hover:text-red-700 disabled:opacity-40"
                                :disabled="
                                    (dept.sections_count ?? 0) > 0 ||
                                    (dept.sections?.length ?? 0) > 0 ||
                                    (dept.employees_count ?? 0) > 0 ||
                                    (dept.overtime_submissions_count ?? 0) > 0
                                "
                                :title="
                                    (dept.sections_count ?? 0) > 0 ||
                                    (dept.sections?.length ?? 0) > 0
                                        ? __(
                                              'Cannot delete department because it contains sections.',
                                          )
                                        : (dept.employees_count ?? 0) > 0
                                          ? __(
                                                'Cannot delete department because it has registered employees.',
                                            )
                                          : __('Delete Department')
                                "
                                :data-test="`btn-delete-dept-${dept.code}`"
                                @click="handleDeleteDepartment(dept)"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>

                    <!-- Nested Sections Accordion Content -->
                    <div
                        v-if="expandedDeptIds.has(dept.id)"
                        class="border-border/60 bg-background/50 border-t px-4 py-3"
                    >
                        <div
                            v-if="!dept.sections || dept.sections.length === 0"
                            class="text-muted-foreground py-3 text-center text-xs"
                        >
                            {{
                                __(
                                    'No sections registered under this department yet.',
                                )
                            }}
                            <button
                                type="button"
                                class="text-primary ml-1 inline-flex cursor-pointer items-center gap-1 font-medium hover:underline"
                                @click="handleAddSection(dept)"
                            >
                                <Plus class="size-3" />
                                {{ __('Add Section') }}
                            </button>
                        </div>

                        <div v-else class="space-y-1.5">
                            <div
                                v-for="sec in dept.sections"
                                :key="sec.id"
                                class="border-border/50 bg-card/60 hover:bg-muted/30 flex items-center justify-between rounded-md border px-3 py-2 text-xs transition-colors"
                                :data-test="`section-row-${sec.code}`"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="bg-primary/70 size-1.5 rounded-full"
                                    />
                                    <Badge
                                        variant="secondary"
                                        class="font-mono text-[11px] font-semibold"
                                    >
                                        {{ sec.code }}
                                    </Badge>
                                    <span class="text-foreground font-medium">
                                        {{ sec.name }}
                                    </span>
                                    <Badge
                                        :variant="
                                            sec.is_active
                                                ? 'outline'
                                                : 'secondary'
                                        "
                                        :class="
                                            sec.is_active
                                                ? 'border-emerald-500/30 text-emerald-700 dark:text-emerald-300'
                                                : 'text-muted-foreground'
                                        "
                                        class="text-[10px]"
                                    >
                                        {{
                                            sec.is_active
                                                ? __('Active')
                                                : __('Inactive')
                                        }}
                                    </Badge>
                                </div>

                                <!-- Section Actions -->
                                <div class="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        class="size-7 p-0"
                                        :title="__('Edit Section')"
                                        :data-test="`btn-edit-sec-${sec.code}`"
                                        @click="handleEditSection(dept, sec)"
                                    >
                                        <Edit2 class="size-3" />
                                    </Button>

                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        :class="[
                                            'size-7 p-0',
                                            sec.is_active
                                                ? 'text-amber-600 hover:text-amber-700'
                                                : 'text-emerald-600 hover:text-emerald-700',
                                        ]"
                                        :title="
                                            sec.is_active
                                                ? __('Deactivate Section')
                                                : __('Activate Section')
                                        "
                                        :data-test="`btn-toggle-sec-${sec.code}`"
                                        @click="handleToggleSectionStatus(sec)"
                                    >
                                        <XCircle
                                            v-if="sec.is_active"
                                            class="size-3.5"
                                        />
                                        <CheckCircle2 v-else class="size-3.5" />
                                    </Button>

                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        class="size-7 p-0 text-red-600 hover:text-red-700 disabled:opacity-40"
                                        :disabled="
                                            (sec.employees_count ?? 0) > 0 ||
                                            (sec.overtime_submissions_count ??
                                                0) > 0
                                        "
                                        :title="
                                            (sec.employees_count ?? 0) > 0
                                                ? __(
                                                      'Cannot delete section because it has registered employees.',
                                                  )
                                                : __('Delete Section')
                                        "
                                        :data-test="`btn-delete-sec-${sec.code}`"
                                        @click="handleDeleteSection(sec)"
                                    >
                                        <Trash2 class="size-3" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: EMPLOYEES (Coming in E02-02) -->
        <div
            v-else-if="currentTab === 'employees'"
            class="border-border rounded-lg border border-dashed p-12 text-center"
        >
            <Users class="text-muted-foreground/60 mx-auto size-10" />
            <h3 class="text-foreground mt-3 text-base font-semibold">
                {{ __('Employees') }} — {{ __('Coming Soon in Next Sprint') }}
            </h3>
            <p class="text-muted-foreground mt-1 text-sm">
                {{
                    __(
                        'Employee Roster and Operational Calendar modules will be available in the upcoming Sprint 2 releases.',
                    )
                }}
            </p>
        </div>

        <!-- TAB 3: OPERATIONAL CALENDAR (Coming in E02-03) -->
        <div
            v-else-if="currentTab === 'calendar'"
            class="border-border rounded-lg border border-dashed p-12 text-center"
        >
            <Calendar class="text-muted-foreground/60 mx-auto size-10" />
            <h3 class="text-foreground mt-3 text-base font-semibold">
                {{ __('Operational Calendar') }} —
                {{ __('Coming Soon in Next Sprint') }}
            </h3>
            <p class="text-muted-foreground mt-1 text-sm">
                {{
                    __(
                        'Employee Roster and Operational Calendar modules will be available in the upcoming Sprint 2 releases.',
                    )
                }}
            </p>
        </div>
    </div>

    <!-- Modals & Sheets -->
    <DepartmentFormDialog
        v-model:open="isDeptDialogOpen"
        :department="selectedDept"
    />

    <SectionFormDialog
        v-model:open="isSectionDialogOpen"
        :section="selectedSection"
        :department-id="sectionParentDept?.id"
        :department-name="sectionParentDept?.name"
        :department-code="sectionParentDept?.code"
    />

    <ConfirmationDialog
        v-model:open="confirmDialog.open"
        :title="confirmDialog.title"
        :description="confirmDialog.description"
        :confirm-text="confirmDialog.confirmText"
        :variant="confirmDialog.variant"
        :loading="confirmDialog.loading"
        @confirm="confirmDialog.action"
    />
</template>
