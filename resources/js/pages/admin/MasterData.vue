<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    Calendar,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Edit2,
    FileSpreadsheet,
    FolderTree,
    Layers,
    Plus,
    Search,
    Trash2,
    UploadCloud,
    UserCheck,
    Users,
    UserX,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmationDialog from '@/components/admin/ConfirmationDialog.vue';
import DepartmentFormDialog, {
    type DepartmentRecord,
} from '@/components/admin/DepartmentFormDialog.vue';
import EmployeeFormSheet, {
    type EmployeeRecord,
} from '@/components/admin/EmployeeFormSheet.vue';
import EmployeeImportSheet from '@/components/admin/EmployeeImportSheet.vue';
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
import employeesRoute from '@/routes/admin/employees';
import sectionsRoute from '@/routes/admin/sections';

export type DepartmentWithSections = DepartmentRecord & {
    sections: SectionRecord[];
};

export type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
};

const props = defineProps<{
    activeTab?: string;
    departments: DepartmentWithSections[];
    employees?: PaginatedData<EmployeeRecord>;
    employeeStats?: {
        total: number;
        active: number;
        inactive: number;
    };
    filters?: {
        search?: string;
        department_id?: number | null;
        status?: string;
    };
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

// Department Tab State
const deptSearchQuery = ref('');
const deptStatusFilter = ref<'all' | 'active' | 'inactive'>('all');
const expandedDeptIds = ref<Set<number>>(
    new Set(props.departments.map((d) => d.id)),
);

// Employee Tab State
const empSearchQuery = ref(props.filters?.search || '');
const empDeptFilter = ref<number | ''>(props.filters?.department_id ?? '');
const empStatusFilter = ref<string>(props.filters?.status || 'all');

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
        masterData.url({
            query: {
                tab,
                ...(tab === 'employees'
                    ? {
                          search: empSearchQuery.value || undefined,
                          department_id: empDeptFilter.value || undefined,
                          status:
                              empStatusFilter.value !== 'all'
                                  ? empStatusFilter.value
                                  : undefined,
                      }
                    : {}),
            },
        }),
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
}

// Dialog & Sheet States
const isDeptDialogOpen = ref(false);
const selectedDept = ref<DepartmentRecord | null>(null);

const isSectionDialogOpen = ref(false);
const selectedSection = ref<SectionRecord | null>(null);
const sectionParentDept = ref<DepartmentRecord | null>(null);

const isEmpFormOpen = ref(false);
const selectedEmp = ref<EmployeeRecord | null>(null);

const isEmpImportOpen = ref(false);

const confirmDialog = ref({
    open: false,
    title: '',
    description: '',
    confirmText: '',
    variant: 'destructive' as 'destructive' | 'default',
    loading: false,
    action: () => {},
});

// Computed Metrics - Department
const totalDepartments = computed(() => props.departments.length);
const totalSections = computed(() =>
    props.departments.reduce((sum, d) => sum + (d.sections?.length || 0), 0),
);
const activeDepartmentsCount = computed(
    () => props.departments.filter((d) => d.is_active).length,
);

// Computed Metrics - Employees
const totalEmployees = computed(
    () => props.employeeStats?.total ?? props.employees?.total ?? 0,
);
const activeEmployeesCount = computed(() => props.employeeStats?.active ?? 0);
const inactiveEmployeesCount = computed(
    () => props.employeeStats?.inactive ?? 0,
);

// Filtering - Department Tab
const filteredDepartments = computed(() => {
    const query = deptSearchQuery.value.trim().toLowerCase();

    return props.departments
        .map((dept) => {
            const matchesDept =
                !query ||
                dept.name.toLowerCase().includes(query) ||
                dept.code.toLowerCase().includes(query) ||
                dept.cost_center_code.toLowerCase().includes(query);

            const matchingSections = (dept.sections || []).filter((sec) => {
                if (deptStatusFilter.value === 'active' && !sec.is_active) {
                    return false;
                }
                if (deptStatusFilter.value === 'inactive' && sec.is_active) {
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

            if (deptStatusFilter.value === 'active' && !dept.is_active) {
                return null;
            }
            if (deptStatusFilter.value === 'inactive' && dept.is_active) {
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

// Handlers & Filtering for Employees
let empSearchTimeout: ReturnType<typeof setTimeout> | null = null;
function handleEmployeeSearch(val: string | number) {
    empSearchQuery.value = String(val);
    if (empSearchTimeout) {
        clearTimeout(empSearchTimeout);
    }
    empSearchTimeout = setTimeout(() => {
        applyEmployeeFilters();
    }, 350);
}

function applyEmployeeFilters() {
    router.get(
        masterData.url({
            query: {
                tab: 'employees',
                search: empSearchQuery.value.trim() || undefined,
                department_id: empDeptFilter.value || undefined,
                status:
                    empStatusFilter.value !== 'all'
                        ? empStatusFilter.value
                        : undefined,
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

function handleAddEmployee() {
    selectedEmp.value = null;
    isEmpFormOpen.value = true;
}

function handleEditEmployee(emp: EmployeeRecord) {
    selectedEmp.value = emp;
    isEmpFormOpen.value = true;
}

function handleToggleEmployeeStatus(emp: EmployeeRecord) {
    const nextState = !emp.is_active;

    confirmDialog.value = {
        open: true,
        title: nextState
            ? __('Aktifkan Karyawan :name?', { name: emp.full_name })
            : __('Nonaktifkan Karyawan :name?', { name: emp.full_name }),
        description: nextState
            ? __(
                  'Karyawan ini akan aktif kembali dan dapat dipilih pada pengajuan lembur baru oleh Team Leader.',
              )
            : __(
                  'Karyawan ini tidak akan dapat dipilih pada pengajuan lembur baru oleh Team Leader. Seluruh data historis lembur dan laporan terdahulu tetap tersimpan aman.',
              ),
        confirmText: nextState ? __('Ya, Aktifkan') : __('Ya, Nonaktifkan'),
        variant: nextState ? 'default' : 'destructive',
        loading: false,
        action: () => {
            confirmDialog.value.loading = true;
            router.put(
                employeesRoute.update.url(emp.id),
                {
                    full_name: emp.full_name,
                    department_id: emp.department_id,
                    section_id: emp.section_id,
                    job_position: emp.job_position,
                    hourly_rate: emp.hourly_rate,
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

function handleDeleteEmployee(emp: EmployeeRecord) {
    const hasOvertime = (emp.overtime_items_count ?? 0) > 0;

    if (hasOvertime) {
        confirmDialog.value = {
            open: true,
            title: __('Penghapusan Diblokir'),
            description: __(
                'Tidak dapat menghapus karyawan karena memiliki :count data riwayat lembur terkait. Silakan nonaktifkan karyawan untuk menjaga integritas riwayat.',
                { count: emp.overtime_items_count ?? 0 },
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
        title: __('Hapus Karyawan :name?', { name: emp.full_name }),
        description: __(
            'Tindakan ini tidak dapat dibatalkan. Karyawan yang dihapus akan hilang dari basis data sistem.',
        ),
        confirmText: __('Hapus Karyawan'),
        variant: 'destructive',
        loading: false,
        action: () => {
            confirmDialog.value.loading = true;
            router.delete(employeesRoute.destroy.url(emp.id), {
                preserveScroll: true,
                onFinish: () => {
                    confirmDialog.value.loading = false;
                    confirmDialog.value.open = false;
                },
            });
        },
    };
}

function handlePagination(url: string | null) {
    if (!url) return;
    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
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
                        currentTab === 'employees'
                            ? __(
                                  'Manage active plant employee rosters, hourly labor rates, and bulk CSV onboardings',
                              )
                            : __(
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
                    <Badge
                        v-if="totalEmployees > 0"
                        variant="secondary"
                        class="ml-1 text-xs"
                    >
                        {{ totalEmployees }}
                    </Badge>
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
                            v-model="deptSearchQuery"
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
                                deptStatusFilter === 'all'
                                    ? 'secondary'
                                    : 'ghost'
                            "
                            class="text-xs"
                            @click="deptStatusFilter = 'all'"
                        >
                            {{ __('All Status') }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                deptStatusFilter === 'active'
                                    ? 'secondary'
                                    : 'ghost'
                            "
                            class="text-xs"
                            @click="deptStatusFilter = 'active'"
                        >
                            {{ __('Only Active') }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                deptStatusFilter === 'inactive'
                                    ? 'secondary'
                                    : 'ghost'
                            "
                            class="text-xs"
                            @click="deptStatusFilter = 'inactive'"
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

        <!-- TAB 2: EMPLOYEES (E02-02 Active) -->
        <div v-else-if="currentTab === 'employees'" class="space-y-6">
            <!-- Metrics Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-3">
                <Card class="border-border/60 shadow-xs">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle
                            class="text-muted-foreground text-sm font-medium"
                        >
                            {{ __('Total Registered Employees') }}
                        </CardTitle>
                        <Users class="text-muted-foreground size-4" />
                    </CardHeader>
                    <CardContent>
                        <div
                            class="text-2xl font-bold"
                            data-test="metric-total-employees"
                        >
                            {{ totalEmployees }}
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
                            {{ __('Active Roster Workers') }}
                        </CardTitle>
                        <UserCheck class="size-4 text-emerald-500" />
                    </CardHeader>
                    <CardContent>
                        <div
                            class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"
                            data-test="metric-active-employees"
                        >
                            {{ activeEmployeesCount }}
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
                            {{ __('Inactive Employees') }}
                        </CardTitle>
                        <UserX class="text-muted-foreground size-4" />
                    </CardHeader>
                    <CardContent>
                        <div
                            class="text-muted-foreground text-2xl font-bold"
                            data-test="metric-inactive-employees"
                        >
                            {{ inactiveEmployeesCount }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Toolbar & Filter Bar -->
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
                            :model-value="empSearchQuery"
                            type="search"
                            :placeholder="__('Search NPK, name, section...')"
                            class="pl-8 text-sm"
                            data-test="input-search-employees"
                            @update:model-value="handleEmployeeSearch"
                        />
                    </div>

                    <!-- Department Filter -->
                    <select
                        v-model="empDeptFilter"
                        class="border-input bg-background focus:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-xs shadow-xs focus:ring-2 focus:outline-hidden sm:w-56"
                        data-test="select-filter-department"
                        @change="applyEmployeeFilters"
                    >
                        <option value="">{{ __('Semua Departemen') }}</option>
                        <option
                            v-for="dept in departments"
                            :key="dept.id"
                            :value="dept.id"
                        >
                            {{ dept.code }} — {{ dept.name }}
                        </option>
                    </select>

                    <!-- Status Filter Pills -->
                    <div class="flex items-center gap-1">
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                empStatusFilter === 'all'
                                    ? 'secondary'
                                    : 'ghost'
                            "
                            class="text-xs"
                            @click="
                                empStatusFilter = 'all';
                                applyEmployeeFilters();
                            "
                        >
                            {{ __('Semua Status') }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                empStatusFilter === 'active'
                                    ? 'secondary'
                                    : 'ghost'
                            "
                            class="text-xs"
                            @click="
                                empStatusFilter = 'active';
                                applyEmployeeFilters();
                            "
                        >
                            {{ __('Aktif') }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                empStatusFilter === 'inactive'
                                    ? 'secondary'
                                    : 'ghost'
                            "
                            class="text-xs"
                            @click="
                                empStatusFilter = 'inactive';
                                applyEmployeeFilters();
                            "
                        >
                            {{ __('Nonaktif') }}
                        </Button>
                    </div>
                </div>

                <!-- Action Buttons: Import CSV (Secondary) & + Tambah Karyawan (Primary) -->
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="gap-1.5"
                        data-test="btn-import-csv"
                        @click="isEmpImportOpen = true"
                    >
                        <UploadCloud class="size-4" />
                        <span>{{ __('Import CSV') }}</span>
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="gap-1.5"
                        data-test="btn-add-employee"
                        @click="handleAddEmployee"
                    >
                        <Plus class="size-4" />
                        <span>{{ __('+ Tambah Karyawan') }}</span>
                    </Button>
                </div>
            </div>

            <!-- Employee Roster Table -->
            <div
                class="border-border/80 bg-card overflow-hidden rounded-lg border shadow-xs"
            >
                <div class="overflow-x-auto">
                    <table
                        class="w-full text-left text-xs"
                        data-test="table-employee-roster"
                    >
                        <thead
                            class="bg-muted/50 text-muted-foreground border-border border-b font-semibold"
                        >
                            <tr>
                                <th class="px-4 py-3">{{ __('NPK') }}</th>
                                <th class="px-4 py-3">
                                    {{ __('Nama Lengkap') }}
                                </th>
                                <th class="px-4 py-3">
                                    {{ __('Departemen') }}
                                </th>
                                <th class="px-4 py-3">{{ __('Seksi') }}</th>
                                <th class="px-4 py-3">
                                    {{ __('Jabatan / Posisi') }}
                                </th>
                                <th class="px-4 py-3">
                                    {{ __('Tarif per Jam') }}
                                </th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-right">
                                    {{ __('Aksi') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-border/60 divide-y">
                            <tr
                                v-if="!employees || employees.data.length === 0"
                                class="text-center"
                            >
                                <td
                                    colspan="8"
                                    class="text-muted-foreground p-8 text-center"
                                >
                                    <Users
                                        class="text-muted-foreground/50 mx-auto mb-2 size-8"
                                    />
                                    <p
                                        class="text-foreground text-sm font-medium"
                                    >
                                        {{
                                            __(
                                                'Tidak ada data karyawan ditemukan.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-muted-foreground mt-0.5 text-xs"
                                    >
                                        {{
                                            __(
                                                'Sesuaikan kata kunci pencarian atau daftarkan karyawan baru.',
                                            )
                                        }}
                                    </p>
                                </td>
                            </tr>

                            <tr
                                v-for="emp in employees?.data"
                                :key="emp.id"
                                class="hover:bg-muted/30 transition-colors"
                                :data-test="`employee-row-${emp.npk}`"
                            >
                                <!-- NPK -->
                                <td class="px-4 py-3 font-mono font-medium">
                                    <Badge
                                        variant="outline"
                                        class="font-mono text-xs font-semibold"
                                    >
                                        {{ emp.npk }}
                                    </Badge>
                                </td>

                                <!-- Full Name -->
                                <td
                                    class="text-foreground px-4 py-3 text-sm font-medium"
                                >
                                    {{ emp.full_name }}
                                </td>

                                <!-- Department -->
                                <td class="px-4 py-3">
                                    <span class="text-foreground font-medium">
                                        {{ emp.department?.name ?? '-' }}
                                    </span>
                                    <Badge
                                        v-if="emp.department?.code"
                                        variant="secondary"
                                        class="ml-1.5 font-mono text-[10px]"
                                    >
                                        {{ emp.department.code }}
                                    </Badge>
                                </td>

                                <!-- Section -->
                                <td class="px-4 py-3">
                                    <span class="text-foreground font-medium">
                                        {{ emp.section?.name ?? '-' }}
                                    </span>
                                    <Badge
                                        v-if="emp.section?.code"
                                        variant="outline"
                                        class="ml-1.5 font-mono text-[10px]"
                                    >
                                        {{ emp.section.code }}
                                    </Badge>
                                </td>

                                <!-- Position -->
                                <td class="text-muted-foreground px-4 py-3">
                                    {{ emp.job_position }}
                                </td>

                                <!-- Hourly Rate -->
                                <td class="px-4 py-3 font-mono">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="text-foreground font-medium"
                                        >
                                            {{
                                                formatRupiah(
                                                    emp.effective_hourly_rate ??
                                                        emp.hourly_rate ??
                                                        emp.department
                                                            ?.default_hourly_rate,
                                                )
                                            }}
                                        </span>
                                        <span
                                            v-if="emp.hourly_rate === null"
                                            class="text-muted-foreground text-[10px] italic"
                                        >
                                            ({{ __('Dept Default') }})
                                        </span>
                                    </div>
                                </td>

                                <!-- Status Badge -->
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            emp.is_active
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        :class="
                                            emp.is_active
                                                ? 'border-emerald-500/20 bg-emerald-600/15 text-emerald-800 dark:text-emerald-300'
                                                : 'text-muted-foreground'
                                        "
                                        class="text-[11px]"
                                    >
                                        {{
                                            emp.is_active
                                                ? __('Aktif')
                                                : __('Nonaktif')
                                        }}
                                    </Badge>
                                </td>

                                <!-- Action Buttons -->
                                <td class="px-4 py-3 text-right">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <!-- Edit Action (NPK Locked) -->
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            class="size-8 p-0"
                                            :title="__('Edit Karyawan')"
                                            :data-test="`btn-edit-emp-${emp.npk}`"
                                            @click="handleEditEmployee(emp)"
                                        >
                                            <Edit2 class="size-3.5" />
                                        </Button>

                                        <!-- Toggle Status Action -->
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            :class="[
                                                'size-8 p-0',
                                                emp.is_active
                                                    ? 'text-amber-600 hover:text-amber-700'
                                                    : 'text-emerald-600 hover:text-emerald-700',
                                            ]"
                                            :title="
                                                emp.is_active
                                                    ? __('Nonaktifkan Karyawan')
                                                    : __('Aktifkan Karyawan')
                                            "
                                            :data-test="`btn-toggle-emp-${emp.npk}`"
                                            @click="
                                                handleToggleEmployeeStatus(emp)
                                            "
                                        >
                                            <XCircle
                                                v-if="emp.is_active"
                                                class="size-4"
                                            />
                                            <CheckCircle2
                                                v-else
                                                class="size-4"
                                            />
                                        </Button>

                                        <!-- Delete Action -->
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            class="size-8 p-0 text-red-600 hover:text-red-700 disabled:opacity-40"
                                            :disabled="
                                                (emp.overtime_items_count ??
                                                    0) > 0
                                            "
                                            :title="
                                                (emp.overtime_items_count ??
                                                    0) > 0
                                                    ? __(
                                                          'Tidak dapat menghapus karyawan karena memiliki riwayat lembur.',
                                                      )
                                                    : __('Hapus Karyawan')
                                            "
                                            :data-test="`btn-delete-emp-${emp.npk}`"
                                            @click="handleDeleteEmployee(emp)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div
                    v-if="employees && employees.total > 0"
                    class="border-border/70 bg-muted/20 text-muted-foreground flex flex-col gap-3 border-t px-4 py-3 text-xs sm:flex-row sm:items-center sm:justify-between"
                    data-test="pagination-bar"
                >
                    <div>
                        {{
                            __(
                                'Menampilkan :from sampai :to dari total :total karyawan',
                                {
                                    from: employees.from ?? 0,
                                    to: employees.to ?? 0,
                                    total: employees.total,
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
                            :disabled="!employees.prev_page_url"
                            @click="handlePagination(employees.prev_page_url)"
                        >
                            <ChevronLeft class="size-4" />
                        </Button>

                        <template
                            v-for="(link, idx) in employees.links.slice(1, -1)"
                            :key="idx"
                        >
                            <Button
                                type="button"
                                size="sm"
                                :variant="link.active ? 'default' : 'outline'"
                                class="h-8 min-w-8 px-2 text-xs"
                                :disabled="!link.url"
                                @click="handlePagination(link.url)"
                            >
                                <span v-html="link.label" />
                            </Button>
                        </template>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="size-8 p-0"
                            :disabled="!employees.next_page_url"
                            @click="handlePagination(employees.next_page_url)"
                        >
                            <ChevronRight class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>
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

    <!-- Employee Form Sheet (E02-02) -->
    <EmployeeFormSheet
        v-model:open="isEmpFormOpen"
        :employee="selectedEmp"
        :departments="departments"
    />

    <!-- Employee CSV Import Sheet (E02-02) -->
    <EmployeeImportSheet v-model:open="isEmpImportOpen" />

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
