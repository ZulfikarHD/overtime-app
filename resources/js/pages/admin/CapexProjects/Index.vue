<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    Building2,
    Calendar,
    Clock,
    DollarSign,
    Edit2,
    FileSpreadsheet,
    Flame,
    FolderKanban,
    Layers,
    Plus,
    RefreshCw,
    RotateCcw,
    Search,
    ShieldCheck,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CapexPortfolioTable from '@/components/capex/CapexPortfolioTable.vue';
import ConfirmationDialog from '@/components/admin/ConfirmationDialog.vue';
import CapexProjectDrawer, {
    type CapexProjectRecord,
    type DepartmentOption,
} from '@/components/admin/CapexProjectDrawer.vue';
import ProjectStatusTransitionModal, {
    type CapexProjectTransitionTarget,
} from '@/components/admin/ProjectStatusTransitionModal.vue';
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
import { formatDateIndo, formatRupiah } from '@/lib/formatters';
import capexProjectsRoute from '@/routes/admin/capex-projects';

export interface PaginatedData<T> {
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
}

export interface CapexProjectListItem extends CapexProjectRecord {
    consumed_hours?: number | string | null;
    consumed_cost?: number | string | null;
    computed_burn_index_pct?: number;
    computed_milestone_burn_ratio?: number;
    days_remaining?: number;
    is_overdue?: boolean;
    is_at_risk?: boolean;
}

export interface PortfolioStats {
    total_active: number;
    total_allocated_hours: number;
    total_consumed_hours: number;
    total_capitalized_cost: number;
    at_risk_count: number;
    burn_rate_pct?: number;
    department_name?: string | null;
}

const props = defineProps<{
    projects: PaginatedData<CapexProjectListItem>;
    stats: PortfolioStats;
    departments: DepartmentOption[];
    filters: {
        status?: string;
        department_id?: string | number | null;
        search?: string;
        date_from?: string;
        date_to?: string;
        sort_by?: string;
        sort_dir?: 'asc' | 'desc';
    };
    activeTab?: string;
}>();

const { __ } = useTrans();
const page = usePage();

const isAdmin = computed(() => {
    const role = (page.props.auth as any)?.user?.role;
    return role === 'admin' || props.departments.length > 1;
});

const currentTab = ref(props.activeTab ?? 'portfolio');
const searchInput = ref(props.filters.search ?? '');
const selectedDepartmentId = ref(
    props.filters.department_id ? String(props.filters.department_id) : '',
);
const activeStatusFilter = ref(props.filters.status ?? 'ALL');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const sortBy = ref(props.filters.sort_by ?? 'created_at');
const sortDir = ref<'asc' | 'desc'>(
    (props.filters.sort_dir as 'asc' | 'desc') ?? 'desc',
);

// Drawer & Modal state
const isDrawerOpen = ref(false);
const editingProject = ref<CapexProjectRecord | null>(null);

const isStatusModalOpen = ref(false);
const statusTargetProject = ref<CapexProjectTransitionTarget | null>(null);

const isDeleteDialogOpen = ref(false);
const deletingProject = ref<CapexProjectRecord | null>(null);
const isDeleting = ref(false);

const isFilterActive = computed(() => {
    return (
        activeStatusFilter.value !== 'ALL' ||
        Boolean(selectedDepartmentId.value) ||
        Boolean(searchInput.value.trim()) ||
        Boolean(dateFrom.value) ||
        Boolean(dateTo.value) ||
        sortBy.value !== 'created_at' ||
        sortDir.value !== 'desc'
    );
});

const statusFilterChips = [
    { value: 'ALL', label: 'Semua Status' },
    { value: 'PLANNING', label: 'Planning' },
    { value: 'ACTIVE', label: 'Active' },
    { value: 'ON_HOLD', label: 'On Hold' },
    { value: 'COMPLETED', label: 'Completed' },
    { value: 'CLOSED', label: 'Closed' },
];

function switchTab(tab: string) {
    currentTab.value = tab;
    router.get(
        capexProjectsRoute.index.url({
            query: {
                tab,
                status:
                    activeStatusFilter.value !== 'ALL'
                        ? activeStatusFilter.value
                        : undefined,
                department_id: selectedDepartmentId.value || undefined,
                search: searchInput.value || undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
                sort_by:
                    sortBy.value !== 'created_at' ? sortBy.value : undefined,
                sort_dir: sortDir.value !== 'desc' ? sortDir.value : undefined,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

// Debounced search
let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(searchInput, (val) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        applyFilters({ search: val });
    }, 350);
});

// Debounced date range filter
let dateTimer: ReturnType<typeof setTimeout> | null = null;
watch([dateFrom, dateTo], ([from, to]) => {
    if (dateTimer) clearTimeout(dateTimer);
    dateTimer = setTimeout(() => {
        applyFilters({ date_from: from, date_to: to });
    }, 300);
});

function applyFilters(overrides: Record<string, any> = {}) {
    const query: Record<string, any> = {
        tab: currentTab.value,
        status:
            overrides.status !== undefined
                ? overrides.status
                : activeStatusFilter.value !== 'ALL'
                  ? activeStatusFilter.value
                  : undefined,
        department_id:
            overrides.department_id !== undefined
                ? overrides.department_id
                : selectedDepartmentId.value || undefined,
        search:
            overrides.search !== undefined
                ? overrides.search
                : searchInput.value || undefined,
        date_from:
            overrides.date_from !== undefined
                ? overrides.date_from
                : dateFrom.value || undefined,
        date_to:
            overrides.date_to !== undefined
                ? overrides.date_to
                : dateTo.value || undefined,
        sort_by:
            overrides.sort_by !== undefined
                ? overrides.sort_by
                : sortBy.value !== 'created_at'
                  ? sortBy.value
                  : undefined,
        sort_dir:
            overrides.sort_dir !== undefined
                ? overrides.sort_dir
                : sortDir.value !== 'desc'
                  ? sortDir.value
                  : undefined,
    };

    // Clean up empty keys
    Object.keys(query).forEach((key) => {
        if (
            query[key] === undefined ||
            query[key] === '' ||
            query[key] === 'ALL'
        ) {
            delete query[key];
        }
    });

    router.get(
        capexProjectsRoute.index.url({ query }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function handleSort(field: string) {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'desc';
    }
    applyFilters({ sort_by: sortBy.value, sort_dir: sortDir.value });
}

function handleDateChange() {
    applyFilters({ date_from: dateFrom.value, date_to: dateTo.value });
}

function resetFilters() {
    activeStatusFilter.value = 'ALL';
    selectedDepartmentId.value = '';
    searchInput.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    sortBy.value = 'created_at';
    sortDir.value = 'desc';
    applyFilters({
        status: 'ALL',
        department_id: '',
        search: '',
        date_from: '',
        date_to: '',
        sort_by: 'created_at',
        sort_dir: 'desc',
    });
}

function selectStatusFilter(status: string) {
    activeStatusFilter.value = status;
    applyFilters({ status });
}

function handleDepartmentChange() {
    applyFilters({ department_id: selectedDepartmentId.value });
}

function openCreateDrawer() {
    editingProject.value = null;
    isDrawerOpen.value = true;
}

function openEditDrawer(project: CapexProjectListItem) {
    editingProject.value = project;
    isDrawerOpen.value = true;
}

function openStatusModal(project: CapexProjectListItem) {
    statusTargetProject.value = {
        id: project.id,
        project_code: project.project_code,
        name: project.name,
        status: project.status,
    };
    isStatusModalOpen.value = true;
}

function confirmDelete(project: CapexProjectListItem) {
    deletingProject.value = project;
    isDeleteDialogOpen.value = true;
}

function handleDelete() {
    if (!deletingProject.value) return;
    isDeleting.value = true;
    router.delete(
        capexProjectsRoute.destroy.url({
            capex_project: deletingProject.value.id,
        }),
        {
            preserveScroll: true,
            onFinish: () => {
                isDeleting.value = false;
                isDeleteDialogOpen.value = false;
                deletingProject.value = null;
            },
        },
    );
}

function handleRefresh() {
    router.reload();
}

function getStatusBadgeClass(status: string) {
    switch (status) {
        case 'PLANNING':
            return 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300';
        case 'ACTIVE':
            return 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300';
        case 'ON_HOLD':
            return 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300';
        case 'COMPLETED':
            return 'bg-sky-50 text-sky-700 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300';
        case 'CLOSED':
            return 'bg-zinc-100 text-zinc-600 border-zinc-300 dark:bg-zinc-800 dark:text-zinc-400';
        default:
            return 'bg-slate-100 text-slate-700';
    }
}

function getBurnIndexBadgeClass(index: number) {
    if (index > 115) {
        return 'bg-red-50 text-[#cc0000] border-red-200 dark:bg-red-950/40 dark:text-red-400 dark:border-red-900 font-bold';
    }
    if (index > 100) {
        return 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/40 dark:text-amber-300 font-semibold';
    }
    if (index >= 85) {
        return 'bg-sky-50 text-sky-700 border-sky-300 dark:bg-sky-950/40 dark:text-sky-300';
    }
    return 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300';
}
</script>

<template>
    <Head :title="__('Manajemen Proyek CapEx & Portofolio')" />

    <div class="space-y-6">
        <!-- Top Industrial Header -->
        <div
            class="border-border flex flex-col justify-between gap-4 border-b pb-5 sm:flex-row sm:items-center"
        >
            <div class="space-y-1">
                <div
                    class="flex items-center gap-2 text-xs font-bold tracking-wider text-[#cc0000] uppercase"
                >
                    <FolderKanban class="size-4" />
                    <span>{{
                        __('Manajemen Fixed Asset & Tenaga Kerja CapEx')
                    }}</span>
                </div>
                <h1 class="text-foreground text-2xl font-bold tracking-tight">
                    {{ __('Manajemen Proyek CapEx & Portofolio') }}
                </h1>
                <p class="text-muted-foreground text-xs">
                    {{
                        __(
                            'Pusat master data belanja modal, pelacakan pembakaran jam lembur fisik, dan audit kepatuhan PSAK 16.',
                        )
                    }}
                </p>
            </div>

            <!-- Tab Navigation & Action -->
            <div class="flex items-center gap-2">
                <div
                    class="bg-muted border-border inline-flex rounded-lg border p-1"
                    data-test="capex-hub-tabs"
                >
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold transition-all"
                        :class="[
                            currentTab === 'portfolio'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="switchTab('portfolio')"
                        data-test="tab-portfolio"
                    >
                        {{ __('Portofolio & Master Data') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold transition-all"
                        :class="[
                            currentTab === 'attribution'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="switchTab('attribution')"
                        data-test="tab-attribution"
                    >
                        {{ __('Laporan Atribusi Finansial') }}
                    </button>
                </div>

                <Button
                    v-if="currentTab === 'portfolio'"
                    class="gap-1.5 bg-[#cc0000] text-xs font-semibold text-white shadow-xs hover:bg-[#b30000]"
                    @click="openCreateDrawer"
                    data-test="btn-create-capex"
                >
                    <Plus class="size-4" />
                    <span>{{ __('+ Tambah Proyek CapEx') }}</span>
                </Button>
            </div>
        </div>

        <!-- TAB 1: Portofolio & Master Data -->
        <div
            v-if="currentTab === 'portfolio'"
            class="space-y-6"
            data-test="portfolio-tab-content"
        >
            <!-- Department KPI Summary Bar -->
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                data-test="capex-kpi-bar"
            >
                <!-- KPI 1: Active Projects -->
                <Card class="border-border bg-card">
                    <CardContent class="flex items-center justify-between p-4">
                        <div class="space-y-1">
                            <span
                                class="text-muted-foreground text-xs font-medium"
                                >{{ __('Proyek Aktif') }}</span
                            >
                            <div
                                class="text-foreground font-mono text-2xl font-bold tabular-nums"
                            >
                                {{ stats.total_active }}
                            </div>
                            <span class="text-muted-foreground text-[11px]">{{
                                __('Dalam pengerjaan fisik')
                            }}</span>
                        </div>
                        <div
                            class="flex size-10 items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-600 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400"
                        >
                            <FolderKanban class="size-5" />
                        </div>
                    </CardContent>
                </Card>

                <!-- KPI 2: Consumed vs Allocated Hours -->
                <Card class="border-border bg-card">
                    <CardContent class="flex items-center justify-between p-4">
                        <div class="space-y-1">
                            <span
                                class="text-muted-foreground text-xs font-medium"
                                >{{ __('Realisasi / Alokasi Jam') }}</span
                            >
                            <div
                                class="text-foreground font-mono text-xl font-bold tabular-nums"
                            >
                                {{
                                    stats.total_consumed_hours.toLocaleString(
                                        'id-ID',
                                        {
                                            minimumFractionDigits: 1,
                                            maximumFractionDigits: 1,
                                        },
                                    )
                                }}
                                <span
                                    class="text-muted-foreground text-xs font-normal"
                                    >/
                                    {{
                                        stats.total_allocated_hours.toLocaleString(
                                            'id-ID',
                                            {
                                                minimumFractionDigits: 1,
                                                maximumFractionDigits: 1,
                                            },
                                        )
                                    }}
                                    jam</span
                                >
                            </div>
                            <span
                                class="font-mono text-[11px] font-semibold text-sky-600 dark:text-sky-400"
                            >
                                {{
                                    stats.total_allocated_hours > 0
                                        ? (
                                              (stats.total_consumed_hours /
                                                  stats.total_allocated_hours) *
                                              100
                                          ).toFixed(1)
                                        : '0.0'
                                }}% {{ __('burn rate') }}
                            </span>
                        </div>
                        <div
                            class="flex size-10 items-center justify-center rounded-lg border border-sky-300 bg-sky-50 text-sky-600 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-400"
                        >
                            <Clock class="size-5" />
                        </div>
                    </CardContent>
                </Card>

                <!-- KPI 3: Capitalized Cost -->
                <Card class="border-border bg-card">
                    <CardContent class="flex items-center justify-between p-4">
                        <div class="space-y-1">
                            <span
                                class="text-muted-foreground text-xs font-medium"
                                >{{ __('Total Biaya Terkapitalisasi') }}</span
                            >
                            <div
                                class="text-foreground font-mono text-lg font-bold tabular-nums"
                            >
                                {{
                                    formatRupiah(stats.total_capitalized_cost, {
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    })
                                }}
                            </div>
                            <span class="text-muted-foreground text-[11px]">{{
                                __('Snapshot audit PSAK 16')
                            }}</span>
                        </div>
                        <div
                            class="flex size-10 items-center justify-center rounded-lg border border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            <DollarSign class="size-5" />
                        </div>
                    </CardContent>
                </Card>

                <!-- KPI 4: Overrun Risk Warning Counter -->
                <Card
                    class="border-border bg-card"
                    :class="{
                        'border-amber-400 dark:border-amber-800':
                            stats.at_risk_count > 0,
                    }"
                >
                    <CardContent class="flex items-center justify-between p-4">
                        <div class="space-y-1">
                            <span
                                class="text-muted-foreground text-xs font-medium"
                                >{{ __('Proyek Berisiko Tinggi') }}</span
                            >
                            <div
                                class="font-mono text-2xl font-bold tabular-nums"
                                :class="
                                    stats.at_risk_count > 0
                                        ? 'text-[#cc0000]'
                                        : 'text-foreground'
                                "
                            >
                                {{ stats.at_risk_count }}
                            </div>
                            <span
                                class="text-[11px]"
                                :class="
                                    stats.at_risk_count > 0
                                        ? 'font-semibold text-amber-600'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{
                                    stats.at_risk_count > 0
                                        ? __('Milestone > 1.2 / Burn > 90%')
                                        : __('Semua proyek normal')
                                }}
                            </span>
                        </div>
                        <div
                            class="flex size-10 items-center justify-center rounded-lg"
                            :class="
                                stats.at_risk_count > 0
                                    ? 'border border-red-300 bg-red-50 text-[#cc0000]'
                                    : 'bg-muted text-muted-foreground border-border border'
                            "
                        >
                            <AlertTriangle class="size-5" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Department Summary Header (AC5) -->
            <div
                class="flex flex-col items-start justify-between gap-2 rounded-lg border border-sky-200 bg-sky-50/60 px-4 py-3 text-xs shadow-xs sm:flex-row sm:items-center dark:border-sky-900/60 dark:bg-sky-950/30"
                data-test="department-summary-header"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-bold text-sky-800 dark:text-sky-300">
                        {{
                            stats.department_name
                                ? __('Ringkasan :dept', {
                                      dept: stats.department_name,
                                  })
                                : __('Ringkasan Departemen')
                        }}:
                    </span>
                    <span
                        class="font-mono font-semibold text-slate-900 tabular-nums dark:text-slate-100"
                    >
                        {{
                            __(
                                'Dept Total: :active Proyek Aktif | :consumed / :allocated jam (:burn%)',
                                {
                                    active: stats.total_active,
                                    consumed:
                                        stats.total_consumed_hours.toLocaleString(
                                            'id-ID',
                                            {
                                                minimumFractionDigits: 1,
                                                maximumFractionDigits: 1,
                                            },
                                        ),
                                    allocated:
                                        stats.total_allocated_hours.toLocaleString(
                                            'id-ID',
                                            {
                                                minimumFractionDigits: 1,
                                                maximumFractionDigits: 1,
                                            },
                                        ),
                                    burn: (
                                        stats.burn_rate_pct ??
                                        (stats.total_allocated_hours > 0
                                            ? (stats.total_consumed_hours /
                                                  stats.total_allocated_hours) *
                                              100
                                            : 0)
                                    ).toFixed(1),
                                },
                            )
                        }}
                    </span>
                </div>
                <div
                    v-if="stats.at_risk_count > 0"
                    class="flex items-center gap-1.5 font-semibold text-[#cc0000]"
                >
                    <span>⚠️</span>
                    <span>{{
                        __(':count Proyek Berisiko Tinggi', {
                            count: stats.at_risk_count,
                        })
                    }}</span>
                </div>
            </div>

            <!-- Toolbar & Filter Action Bar -->
            <div
                class="bg-card border-border flex flex-col items-stretch justify-between gap-3 rounded-lg border p-3 lg:flex-row lg:items-center"
                data-test="portfolio-toolbar"
            >
                <!-- Status Filter Chips -->
                <div
                    class="flex items-center gap-1.5 overflow-x-auto pb-1 lg:pb-0"
                    data-test="status-filter-chips"
                >
                    <button
                        v-for="chip in statusFilterChips"
                        :key="chip.value"
                        type="button"
                        class="rounded-full border px-2.5 py-1 text-xs font-semibold whitespace-nowrap transition-all"
                        :class="[
                            activeStatusFilter === chip.value
                                ? 'border-[#cc0000] bg-[#cc0000] text-white'
                                : 'bg-background text-muted-foreground border-border hover:bg-muted',
                        ]"
                        @click="selectStatusFilter(chip.value)"
                        :data-test="`filter-chip-${chip.value.toLowerCase()}`"
                    >
                        {{ __(chip.label) }}
                    </button>
                </div>

                <!-- Right Toolbar Filters -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Date Range Filter (Target End Date) -->
                    <div class="flex items-center gap-1.5">
                        <div
                            class="text-muted-foreground flex items-center gap-1 text-xs"
                        >
                            <Calendar class="text-muted-foreground size-3.5" />
                            <span class="hidden sm:inline">{{
                                __('Target:')
                            }}</span>
                        </div>
                        <Input
                            v-model="dateFrom"
                            type="date"
                            class="h-8 w-32 font-mono text-xs"
                            :title="__('Target Selesai Dari')"
                            @change="handleDateChange"
                            data-test="input-date-from"
                        />
                        <span class="text-muted-foreground text-xs">-</span>
                        <Input
                            v-model="dateTo"
                            type="date"
                            class="h-8 w-32 font-mono text-xs"
                            :title="__('Target Selesai Hingga')"
                            @change="handleDateChange"
                            data-test="input-date-to"
                        />
                    </div>

                    <!-- Department Filter -->
                    <select
                        v-if="departments.length > 1"
                        v-model="selectedDepartmentId"
                        @change="handleDepartmentChange"
                        class="border-input bg-background focus-visible:ring-ring h-8 rounded-md border px-2 py-1 text-xs shadow-xs focus-visible:ring-1 focus-visible:outline-hidden"
                        data-test="filter-department"
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

                    <!-- Search Input -->
                    <div class="relative w-full sm:w-56">
                        <Search
                            class="text-muted-foreground absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2"
                        />
                        <Input
                            v-model="searchInput"
                            type="text"
                            :placeholder="__('Cari kode, nama, aset...')"
                            class="h-8 pl-8 font-mono text-xs"
                            data-test="input-search"
                        />
                    </div>

                    <!-- Reset Filters Button -->
                    <Button
                        v-if="isFilterActive"
                        variant="ghost"
                        size="sm"
                        class="text-muted-foreground hover:text-foreground h-8 px-2 text-xs"
                        @click="resetFilters"
                        data-test="btn-reset-filters"
                        :title="__('Reset Filter')"
                    >
                        <RotateCcw class="size-3.5" />
                        <span class="ml-1 hidden sm:inline">{{
                            __('Reset')
                        }}</span>
                    </Button>
                </div>
            </div>

            <!-- Unified Portfolio & Master Data Table (CapexPortfolioTable Component) -->
            <CapexPortfolioTable
                :projects="projects"
                :is-admin="isAdmin"
                :sort-by="sortBy"
                :sort-dir="sortDir"
                @sort="handleSort"
                @edit="openEditDrawer"
                @status="openStatusModal"
                @delete="confirmDelete"
            />
        </div>

        <!-- TAB 2: Laporan Atribusi Finansial (Placeholder / Hook for E07-04) -->
        <div
            v-else-if="currentTab === 'attribution'"
            class="space-y-6"
            data-test="attribution-tab-content"
        >
            <Card class="border-border bg-card p-8 text-center">
                <div class="mx-auto max-w-md space-y-4">
                    <div
                        class="mx-auto flex size-14 items-center justify-center rounded-full border border-sky-300 bg-sky-50 text-sky-600 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-400"
                    >
                        <FileSpreadsheet class="size-7" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-foreground text-base font-bold">
                            {{
                                __(
                                    'Laporan Atribusi Finansial CapEx & Ekspor Excel (.xlsx)',
                                )
                            }}
                        </h3>
                        <p
                            class="text-muted-foreground text-xs leading-relaxed"
                        >
                            {{
                                __(
                                    'Fitur laporan audit item lembur terkapitalisasi dan ekspor spreadsheet PSAK 16 dijadwalkan pada Sprint 7 (Story E07-04). Anda dapat memantau data master dan portofolio proyek pada tab Portofolio & Master Data.',
                                )
                            }}
                        </p>
                    </div>
                    <div>
                        <Button
                            variant="outline"
                            class="gap-1.5 text-xs font-semibold"
                            @click="switchTab('portfolio')"
                        >
                            <ArrowRight class="size-3.5 rotate-180" />
                            {{ __('Kembali ke Portofolio & Master Data') }}
                        </Button>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Slide-in Drawer: CapexProjectDrawer -->
        <CapexProjectDrawer
            v-model:open="isDrawerOpen"
            :project="editingProject"
            :departments="departments"
            @success="handleRefresh"
        />

        <!-- Status Transition Modal: ProjectStatusTransitionModal -->
        <ProjectStatusTransitionModal
            v-model:open="isStatusModalOpen"
            :project="statusTargetProject"
            @success="handleRefresh"
        />

        <!-- Confirmation Dialog for Deleting Project -->
        <ConfirmationDialog
            :open="isDeleteDialogOpen"
            :title="__('Hapus Proyek CapEx')"
            :description="
                __(
                    'Apakah Anda yakin ingin menghapus proyek :code? Tindakan ini hanya dapat dilakukan jika belum ada jam lembur yang tercatat.',
                    { code: deletingProject?.project_code ?? '' },
                )
            "
            :confirm-text="__('Hapus Proyek')"
            :cancel-text="__('Batal')"
            variant="destructive"
            :loading="isDeleting"
            @confirm="handleDelete"
            @cancel="isDeleteDialogOpen = false"
        />
    </div>
</template>
