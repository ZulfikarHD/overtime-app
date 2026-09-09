<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
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
    Search,
    ShieldCheck,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
}

const props = defineProps<{
    projects: PaginatedData<CapexProjectListItem>;
    stats: PortfolioStats;
    departments: DepartmentOption[];
    filters: {
        status?: string;
        department_id?: string | number | null;
        search?: string;
    };
    activeTab?: string;
}>();

const { __ } = useTrans();

const currentTab = ref(props.activeTab ?? 'portfolio');
const searchInput = ref(props.filters.search ?? '');
const selectedDepartmentId = ref(
    props.filters.department_id ? String(props.filters.department_id) : '',
);
const activeStatusFilter = ref(props.filters.status ?? 'ALL');

// Drawer & Modal state
const isDrawerOpen = ref(false);
const editingProject = ref<CapexProjectRecord | null>(null);

const isStatusModalOpen = ref(false);
const statusTargetProject = ref<CapexProjectTransitionTarget | null>(null);

const isDeleteDialogOpen = ref(false);
const deletingProject = ref<CapexProjectRecord | null>(null);
const isDeleting = ref(false);

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

            <!-- Toolbar & Filter Action Bar -->
            <div
                class="bg-card border-border flex flex-col items-stretch justify-between gap-3 rounded-lg border p-3 md:flex-row md:items-center"
                data-test="portfolio-toolbar"
            >
                <!-- Status Filter Chips -->
                <div
                    class="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0"
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
                <div class="flex items-center gap-2">
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
                    <div class="relative w-full md:w-64">
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
                </div>
            </div>

            <!-- Unified Portfolio & Master Data Table -->
            <Card class="border-border overflow-hidden">
                <div class="overflow-x-auto">
                    <table
                        class="w-full border-collapse text-left text-xs"
                        data-test="capex-projects-table"
                    >
                        <thead>
                            <tr
                                class="border-border bg-muted/50 text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase"
                            >
                                <th class="p-3">{{ __('Kode Proyek') }}</th>
                                <th class="p-3">{{ __('Nama Proyek') }}</th>
                                <th class="p-3">{{ __('Aset Tetap') }}</th>
                                <th class="p-3">{{ __('Departemen') }}</th>
                                <th class="p-3">{{ __('Status') }}</th>
                                <th class="p-3 text-right">
                                    {{ __('Alokasi (Jam)') }}
                                </th>
                                <th class="p-3 text-right">
                                    {{ __('Realisasi (Jam)') }}
                                </th>
                                <th class="p-3 text-right">
                                    {{ __('Indeks Burn') }}
                                </th>
                                <th class="p-3 text-right">
                                    {{ __('Kemajuan Fisik') }}
                                </th>
                                <th class="p-3 text-right">
                                    {{ __('Target Selesai') }}
                                </th>
                                <th class="p-3 text-right">
                                    {{ __('Sisa Hari') }}
                                </th>
                                <th class="p-3 text-center">
                                    {{ __('Aksi') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-border divide-y">
                            <tr
                                v-for="item in projects.data"
                                :key="item.id"
                                class="hover:bg-muted/40 transition-colors"
                                :class="{
                                    'bg-amber-50/20 dark:bg-amber-950/10':
                                        item.is_at_risk,
                                }"
                                :data-test="`project-row-${item.id}`"
                            >
                                <!-- Kode Proyek -->
                                <td
                                    class="text-foreground p-3 font-mono font-bold whitespace-nowrap tabular-nums"
                                >
                                    <Link
                                        :href="
                                            capexProjectsRoute.show.url({
                                                capex_project: item.id,
                                            })
                                        "
                                        class="flex items-center gap-1 text-[#cc0000] hover:underline"
                                        data-test="link-project-code"
                                    >
                                        {{ item.project_code }}
                                        <span
                                            v-if="item.is_at_risk"
                                            class="font-normal text-amber-500"
                                            title="Proyek Berisiko Tinggi"
                                            >⚠️</span
                                        >
                                    </Link>
                                </td>

                                <!-- Nama Proyek -->
                                <td
                                    class="text-foreground max-w-xs truncate p-3 font-medium"
                                    :title="item.name"
                                >
                                    {{ item.name }}
                                </td>

                                <!-- Aset Tetap -->
                                <td
                                    class="text-muted-foreground p-3 font-mono whitespace-nowrap tabular-nums"
                                >
                                    {{ item.asset_code || '—' }}
                                </td>

                                <!-- Departemen -->
                                <td class="p-3 whitespace-nowrap">
                                    <span
                                        class="text-foreground inline-flex items-center gap-1 font-medium"
                                    >
                                        <Building2
                                            class="text-muted-foreground size-3"
                                        />
                                        {{ item.department?.code || '—' }}
                                    </span>
                                </td>

                                <!-- Status -->
                                <td class="p-3 whitespace-nowrap">
                                    <Badge
                                        variant="outline"
                                        :class="
                                            getStatusBadgeClass(item.status)
                                        "
                                        class="text-[11px] font-semibold uppercase"
                                        :data-test="`badge-status-${item.id}`"
                                    >
                                        {{ item.status }}
                                    </Badge>
                                </td>

                                <!-- Alokasi Jam -->
                                <td
                                    class="p-3 text-right font-mono whitespace-nowrap tabular-nums"
                                >
                                    {{
                                        Number(
                                            item.allocated_labor_hours,
                                        ).toLocaleString('id-ID', {
                                            minimumFractionDigits: 1,
                                            maximumFractionDigits: 1,
                                        })
                                    }}
                                </td>

                                <!-- Realisasi Jam -->
                                <td
                                    class="p-3 text-right font-mono font-semibold whitespace-nowrap tabular-nums"
                                >
                                    {{
                                        Number(
                                            item.consumed_hours || 0,
                                        ).toLocaleString('id-ID', {
                                            minimumFractionDigits: 1,
                                            maximumFractionDigits: 1,
                                        })
                                    }}
                                </td>

                                <!-- Indeks Burn -->
                                <td class="p-3 text-right whitespace-nowrap">
                                    <Badge
                                        variant="outline"
                                        :class="
                                            getBurnIndexBadgeClass(
                                                item.computed_burn_index_pct ??
                                                    0,
                                            )
                                        "
                                        class="font-mono text-[11px] tabular-nums"
                                    >
                                        {{
                                            (
                                                item.computed_burn_index_pct ??
                                                0
                                            ).toFixed(1)
                                        }}%
                                    </Badge>
                                </td>

                                <!-- Kemajuan Fisik -->
                                <td
                                    class="p-3 text-right font-mono whitespace-nowrap tabular-nums"
                                >
                                    <div
                                        class="flex items-center justify-end gap-1.5"
                                    >
                                        <span class="font-semibold"
                                            >{{
                                                Number(
                                                    item.physical_progress_pct ||
                                                        0,
                                                ).toFixed(1)
                                            }}%</span
                                        >
                                        <span
                                            v-if="
                                                (item.computed_milestone_burn_ratio ??
                                                    0) > 1.2
                                            "
                                            class="text-[10px] font-semibold text-amber-600"
                                            title="Rasio Milestone > 1.2 (Lembur membakar lebih cepat dibanding kemajuan fisik)"
                                        >
                                            (M:
                                            {{
                                                (
                                                    item.computed_milestone_burn_ratio ??
                                                    0
                                                ).toFixed(2)
                                            }})
                                        </span>
                                    </div>
                                </td>

                                <!-- Target Selesai -->
                                <td
                                    class="text-muted-foreground p-3 text-right font-mono whitespace-nowrap tabular-nums"
                                >
                                    {{ formatDateIndo(item.target_end_date) }}
                                </td>

                                <!-- Sisa Hari -->
                                <td
                                    class="p-3 text-right font-mono whitespace-nowrap tabular-nums"
                                >
                                    <span
                                        v-if="item.is_overdue"
                                        class="font-bold text-[#cc0000]"
                                    >
                                        {{
                                            __('Lewat :days h', {
                                                days: Math.abs(
                                                    item.days_remaining ?? 0,
                                                ),
                                            })
                                        }}
                                    </span>
                                    <span
                                        v-else-if="
                                            ['COMPLETED', 'CLOSED'].includes(
                                                item.status,
                                            )
                                        "
                                        class="text-muted-foreground"
                                    >
                                        —
                                    </span>
                                    <span v-else class="text-muted-foreground">
                                        {{
                                            __(':days hari', {
                                                days: item.days_remaining ?? 0,
                                            })
                                        }}
                                    </span>
                                </td>

                                <!-- Aksi -->
                                <td class="p-3 text-center whitespace-nowrap">
                                    <div
                                        class="flex items-center justify-center gap-1.5"
                                    >
                                        <Link
                                            :href="
                                                capexProjectsRoute.show.url({
                                                    capex_project: item.id,
                                                })
                                            "
                                            class="hover:bg-muted text-muted-foreground hover:text-foreground rounded-md p-1.5 transition-colors"
                                            :title="__('Lihat Detail Cockpit')"
                                            :data-test="`btn-detail-${item.id}`"
                                        >
                                            <ArrowRight class="size-3.5" />
                                        </Link>
                                        <button
                                            type="button"
                                            class="hover:bg-muted text-muted-foreground hover:text-foreground rounded-md p-1.5 transition-colors"
                                            :title="__('Edit Master Data')"
                                            @click="openEditDrawer(item)"
                                            :data-test="`btn-edit-${item.id}`"
                                        >
                                            <Edit2 class="size-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            class="hover:bg-muted rounded-md p-1.5 text-sky-600 transition-colors hover:text-sky-700"
                                            :title="__('Ubah Status Proyek')"
                                            @click="openStatusModal(item)"
                                            :data-test="`btn-status-${item.id}`"
                                        >
                                            <RefreshCw class="size-3.5" />
                                        </button>
                                        <button
                                            v-if="
                                                !item.consumed_hours ||
                                                Number(item.consumed_hours) ===
                                                    0
                                            "
                                            type="button"
                                            class="text-muted-foreground rounded-md p-1.5 transition-colors hover:bg-red-50 hover:text-[#cc0000]"
                                            :title="__('Hapus Proyek')"
                                            @click="confirmDelete(item)"
                                            :data-test="`btn-delete-${item.id}`"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="projects.data.length === 0">
                                <td
                                    colspan="12"
                                    class="text-muted-foreground p-10 text-center"
                                >
                                    <div
                                        class="flex flex-col items-center justify-center space-y-2"
                                    >
                                        <FolderKanban
                                            class="text-muted-foreground/60 size-8"
                                        />
                                        <p
                                            class="text-foreground text-sm font-semibold"
                                        >
                                            {{
                                                __(
                                                    'Tidak ada proyek CapEx yang ditemukan',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-muted-foreground text-xs"
                                        >
                                            {{
                                                __(
                                                    'Sesuaikan filter pencarian atau daftarkan proyek belanja modal baru menggunakan tombol di atas.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div
                    v-if="projects.total > 0"
                    class="border-border bg-card text-muted-foreground flex flex-col items-center justify-between gap-2 border-t p-3 text-xs sm:flex-row"
                    data-test="pagination-footer"
                >
                    <div>
                        {{
                            __(
                                'Menampilkan :from - :to dari total :total proyek',
                                {
                                    from: projects.from ?? 0,
                                    to: projects.to ?? 0,
                                    total: projects.total,
                                },
                            )
                        }}
                    </div>
                    <div class="flex items-center gap-1">
                        <Link
                            v-if="projects.prev_page_url"
                            :href="projects.prev_page_url"
                            class="border-border bg-background hover:bg-muted text-foreground rounded-md border px-2.5 py-1 font-semibold"
                            data-test="btn-prev-page"
                        >
                            {{ __('Sebelumnya') }}
                        </Link>
                        <span class="px-2 font-mono">
                            {{ projects.current_page }} /
                            {{ projects.last_page }}
                        </span>
                        <Link
                            v-if="projects.next_page_url"
                            :href="projects.next_page_url"
                            class="border-border bg-background hover:bg-muted text-foreground rounded-md border px-2.5 py-1 font-semibold"
                            data-test="btn-next-page"
                        >
                            {{ __('Selanjutnya') }}
                        </Link>
                    </div>
                </div>
            </Card>
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
