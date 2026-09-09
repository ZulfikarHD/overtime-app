<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Calendar,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    Clock,
    Download,
    FileSpreadsheet,
    Filter,
    FolderKanban,
    RotateCcw,
    Search,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { exportTimesheet } from '@/actions/App/Http/Controllers/Reports/EmployeeReportController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';
import { show as showEmployeeDossier } from '@/routes/reports/employees';

export interface TimesheetItem {
    id: number;
    submission_id: number;
    submission_code: string;
    operational_date: string | null;
    formatted_date: string;
    day_name: string;
    day_type: 'HKN' | 'HLR';
    hours_production: number;
    hours_tpm: number;
    hours_project: number;
    hours_others: number;
    total_hours: number;
    hourly_rate: number;
    total_cost: number;
    status: 'PENDING' | 'APPROVED' | 'REJECTED';
    task_description: string | null;
    rca_category: string | null;
    rca_notes: string | null;
    rejection_reason: string | null;
    capex_project: {
        id: number;
        project_code: string;
        name: string;
    } | null;
    is_capex: boolean;
}

export interface TimesheetData {
    data: TimesheetItem[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    summary: {
        total_items: number;
        total_hours: number;
        approved_hours: number;
        pending_hours: number;
        rejected_hours: number;
        total_cost: number;
    };
}

export interface TimesheetFilters {
    status?: string;
    category?: string;
    date_from?: string | null;
    date_to?: string | null;
    all_time?: boolean;
    search?: string | null;
    sort_by?: string;
    sort_dir?: 'asc' | 'desc';
    fiscal_year?: number;
    fiscal_month?: number;
}

const props = defineProps<{
    npk: string;
    employeeName: string;
    timesheet: TimesheetData;
    filters?: TimesheetFilters;
    fiscalYear: number;
    fiscalMonth: number;
}>();

const { __ } = useTrans();

// Filter States
const statusFilter = ref<string>(props.filters?.status || 'all');
const categoryFilter = ref<string>(props.filters?.category || 'all');
const dateFrom = ref<string>(props.filters?.date_from || '');
const dateTo = ref<string>(props.filters?.date_to || '');
const searchQuery = ref<string>(props.filters?.search || '');
const sortBy = ref<string>(props.filters?.sort_by || 'operational_date');
const sortDir = ref<'asc' | 'desc'>(props.filters?.sort_dir || 'desc');
const isAllTime = ref<boolean>(Boolean(props.filters?.all_time));

// Accordion Expand/Collapse State
const expandedItemIds = ref<number[]>([]);

function toggleRow(id: number) {
    if (expandedItemIds.value.includes(id)) {
        expandedItemIds.value = expandedItemIds.value.filter(
            (itemId) => itemId !== id,
        );
    } else {
        expandedItemIds.value.push(id);
    }
}

function isRowExpanded(id: number): boolean {
    return expandedItemIds.value.includes(id);
}

// Watch incoming props updates
watch(
    () => props.filters,
    (newFilters) => {
        if (!newFilters) return;
        statusFilter.value = newFilters.status || 'all';
        categoryFilter.value = newFilters.category || 'all';
        dateFrom.value = newFilters.date_from || '';
        dateTo.value = newFilters.date_to || '';
        searchQuery.value = newFilters.search || '';
        sortBy.value = newFilters.sort_by || 'operational_date';
        sortDir.value = newFilters.sort_dir || 'desc';
        isAllTime.value = Boolean(newFilters.all_time);
    },
    { deep: true },
);

function applyFilters(page = 1) {
    const query: Record<string, string | number | boolean> = {
        tab: 'timesheet',
        year: props.fiscalYear,
        month: props.fiscalMonth,
        page,
    };

    if (statusFilter.value && statusFilter.value !== 'all') {
        query.status = statusFilter.value;
    }
    if (categoryFilter.value && categoryFilter.value !== 'all') {
        query.category = categoryFilter.value;
    }
    if (dateFrom.value) {
        query.date_from = dateFrom.value;
    }
    if (dateTo.value) {
        query.date_to = dateTo.value;
    }
    if (isAllTime.value) {
        query.all_time = true;
    }
    if (searchQuery.value.trim()) {
        query.search = searchQuery.value.trim();
    }
    if (sortBy.value) {
        query.sort_by = sortBy.value;
        query.sort_dir = sortDir.value;
    }

    router.visit(showEmployeeDossier.url({ npk: props.npk }, { query }), {
        preserveScroll: true,
    });
}

function toggleSort(field: string) {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'desc';
    }
    applyFilters(1);
}

function resetFilters() {
    statusFilter.value = 'all';
    categoryFilter.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    searchQuery.value = '';
    isAllTime.value = false;
    sortBy.value = 'operational_date';
    sortDir.value = 'desc';

    applyFilters(1);
}

function handlePageChange(newPage: number) {
    if (
        newPage < 1 ||
        newPage > props.timesheet.pagination.last_page ||
        newPage === props.timesheet.pagination.current_page
    ) {
        return;
    }
    applyFilters(newPage);
}

const csvExportUrl = computed(() => {
    const query: Record<string, string | number | boolean> = {
        year: props.fiscalYear,
        month: props.fiscalMonth,
    };
    if (statusFilter.value && statusFilter.value !== 'all') {
        query.status = statusFilter.value;
    }
    if (categoryFilter.value && categoryFilter.value !== 'all') {
        query.category = categoryFilter.value;
    }
    if (dateFrom.value) {
        query.date_from = dateFrom.value;
    }
    if (dateTo.value) {
        query.date_to = dateTo.value;
    }
    if (isAllTime.value) {
        query.all_time = true;
    }
    if (searchQuery.value.trim()) {
        query.search = searchQuery.value.trim();
    }
    if (sortBy.value) {
        query.sort_by = sortBy.value;
        query.sort_dir = sortDir.value;
    }

    return exportTimesheet.url({ npk: props.npk }, { query });
});

const isFilterActive = computed(() => {
    return (
        statusFilter.value !== 'all' ||
        categoryFilter.value !== 'all' ||
        Boolean(dateFrom.value) ||
        Boolean(dateTo.value) ||
        Boolean(searchQuery.value.trim()) ||
        isAllTime.value
    );
});
</script>

<template>
    <div class="flex flex-col gap-5">
        <!-- Timesheet Header & Metrics Strip -->
        <div
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5"
            data-test="timesheet-metrics-summary"
        >
            <div
                class="border-border bg-card flex flex-col justify-between rounded-xl border p-3.5 shadow-2xs"
            >
                <span
                    class="text-muted-foreground text-[11px] font-medium tracking-wider uppercase"
                >
                    {{ __('Total Item') }}
                </span>
                <span
                    class="text-foreground mt-1 font-mono text-xl font-bold tabular-nums"
                >
                    {{ timesheet.summary.total_items }}
                </span>
            </div>

            <div
                class="border-border bg-card flex flex-col justify-between rounded-xl border p-3.5 shadow-2xs"
            >
                <span
                    class="text-muted-foreground text-[11px] font-medium tracking-wider uppercase"
                >
                    {{ __('Total Jam') }}
                </span>
                <span
                    class="text-foreground mt-1 font-mono text-xl font-bold tabular-nums"
                >
                    {{ timesheet.summary.total_hours.toFixed(1) }}
                    <span class="text-muted-foreground text-xs font-normal">{{
                        __('jam')
                    }}</span>
                </span>
            </div>

            <div
                class="flex flex-col justify-between rounded-xl border border-emerald-200 bg-emerald-50/50 p-3.5 shadow-2xs dark:border-emerald-900/60 dark:bg-emerald-950/20"
            >
                <span
                    class="text-[11px] font-medium tracking-wider text-emerald-700 uppercase dark:text-emerald-300"
                >
                    {{ __('Disetujui') }}
                </span>
                <span
                    class="mt-1 font-mono text-xl font-bold text-emerald-700 tabular-nums dark:text-emerald-300"
                >
                    {{ timesheet.summary.approved_hours.toFixed(1) }}
                    <span class="text-xs font-normal">{{ __('jam') }}</span>
                </span>
            </div>

            <div
                class="flex flex-col justify-between rounded-xl border border-amber-200 bg-amber-50/50 p-3.5 shadow-2xs dark:border-amber-900/60 dark:bg-amber-950/20"
            >
                <span
                    class="text-[11px] font-medium tracking-wider text-amber-700 uppercase dark:text-amber-300"
                >
                    {{ __('Menunggu') }}
                </span>
                <span
                    class="mt-1 font-mono text-xl font-bold text-amber-700 tabular-nums dark:text-amber-300"
                >
                    {{ timesheet.summary.pending_hours.toFixed(1) }}
                    <span class="text-xs font-normal">{{ __('jam') }}</span>
                </span>
            </div>

            <div
                class="col-span-2 flex flex-col justify-between rounded-xl border border-red-200 bg-red-50/50 p-3.5 shadow-2xs sm:col-span-1 dark:border-red-900/60 dark:bg-red-950/20"
            >
                <span
                    class="text-[11px] font-medium tracking-wider text-[#cc0000] uppercase dark:text-red-400"
                >
                    {{ __('Ditolak') }}
                </span>
                <span
                    class="mt-1 font-mono text-xl font-bold text-[#cc0000] tabular-nums dark:text-red-400"
                >
                    {{ timesheet.summary.rejected_hours.toFixed(1) }}
                    <span class="text-xs font-normal">{{ __('jam') }}</span>
                </span>
            </div>
        </div>

        <!-- Filter Toolbar & Export Action -->
        <Card class="border-border shadow-xs">
            <CardHeader class="pb-3">
                <div
                    class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
                >
                    <div class="flex items-center gap-2">
                        <FileSpreadsheet class="text-primary size-5" />
                        <div>
                            <CardTitle class="text-base font-bold">
                                {{ __('Buku Jam Lembur Kronologis') }}
                            </CardTitle>
                            <CardDescription class="text-xs">
                                {{
                                    __(
                                        'Audit seluruh riwayat lembur per tanggal kerja untuk :name',
                                        { name: employeeName },
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <!-- Direct CSV Export Trigger (Zero Memory Streaming) -->
                    <a
                        :href="csvExportUrl"
                        data-test="btn-export-csv"
                        class="bg-primary hover:bg-primary/90 text-primary-foreground focus-visible:ring-ring inline-flex h-9 items-center justify-center gap-1.5 rounded-lg px-4 text-xs font-semibold shadow-xs transition-colors focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <Download class="size-4" />
                        <span>{{ __('Unduh CSV') }}</span>
                    </a>
                </div>
            </CardHeader>
            <CardContent class="flex flex-col gap-3">
                <!-- Filter Grid Controls -->
                <div
                    class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-12"
                >
                    <!-- Search Input -->
                    <div class="relative lg:col-span-4">
                        <Search
                            class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
                        />
                        <Input
                            v-model="searchQuery"
                            data-test="timesheet-search-input"
                            :placeholder="
                                __('Cari tugas, RCA, kode pengajuan...')
                            "
                            class="h-9 pl-9 text-xs"
                            @keydown.enter="applyFilters(1)"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
                            @click="
                                searchQuery = '';
                                applyFilters(1);
                            "
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>

                    <!-- Status Filter -->
                    <div class="lg:col-span-2">
                        <Select
                            v-model="statusFilter"
                            @update:model-value="applyFilters(1)"
                        >
                            <SelectTrigger
                                class="h-9 text-xs"
                                data-test="filter-status-trigger"
                            >
                                <SelectValue
                                    :placeholder="__('Status Persetujuan')"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {{ __('Semua Status') }}
                                </SelectItem>
                                <SelectItem value="APPROVED">
                                    {{ __('Disetujui (Approved)') }}
                                </SelectItem>
                                <SelectItem value="PENDING">
                                    {{ __('Menunggu (Pending)') }}
                                </SelectItem>
                                <SelectItem value="REJECTED">
                                    {{ __('Ditolak (Rejected)') }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Category Filter -->
                    <div class="lg:col-span-2">
                        <Select
                            v-model="categoryFilter"
                            @update:model-value="applyFilters(1)"
                        >
                            <SelectTrigger
                                class="h-9 text-xs"
                                data-test="filter-category-trigger"
                            >
                                <SelectValue :placeholder="__('Kategori')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    {{ __('Semua Kategori') }}
                                </SelectItem>
                                <SelectItem value="production">
                                    {{ __('Produksi') }}
                                </SelectItem>
                                <SelectItem value="tpm">
                                    {{ __('TPM') }}
                                </SelectItem>
                                <SelectItem value="capex">
                                    {{ __('CapEx Proyek') }}
                                </SelectItem>
                                <SelectItem value="others">
                                    {{ __('Lainnya') }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Date Range Inputs (or All Time Switch) -->
                    <div
                        class="flex items-center gap-1.5 sm:col-span-2 lg:col-span-3"
                    >
                        <Input
                            v-model="dateFrom"
                            type="date"
                            class="h-9 text-xs"
                            title="Tanggal Mulai"
                            data-test="filter-date-from"
                            @change="applyFilters(1)"
                        />
                        <span class="text-muted-foreground text-xs">-</span>
                        <Input
                            v-model="dateTo"
                            type="date"
                            class="h-9 text-xs"
                            title="Tanggal Selesai"
                            data-test="filter-date-to"
                            @change="applyFilters(1)"
                        />
                    </div>

                    <!-- Filter Actions: Apply & Reset -->
                    <div
                        class="flex items-center justify-end gap-1.5 lg:col-span-1"
                    >
                        <Button
                            variant="secondary"
                            size="sm"
                            class="h-9 w-full gap-1 text-xs sm:w-auto"
                            data-test="btn-apply-filters"
                            @click="applyFilters(1)"
                        >
                            <Filter class="size-3.5" />
                            <span
                                class="sr-only sm:not-sr-only sm:hidden lg:inline"
                                >{{ __('Filter') }}</span
                            >
                        </Button>
                        <Button
                            v-if="isFilterActive"
                            variant="ghost"
                            size="sm"
                            class="text-muted-foreground hover:text-foreground h-9 px-2 text-xs"
                            data-test="btn-reset-filters"
                            :title="__('Reset Filter')"
                            @click="resetFilters"
                        >
                            <RotateCcw class="size-3.5" />
                        </Button>
                    </div>
                </div>

                <!-- Active Filter Tags / Filter Information -->
                <div
                    v-if="isFilterActive"
                    class="flex flex-wrap items-center gap-2 pt-1 text-xs"
                >
                    <span class="text-muted-foreground text-[11px] font-medium">
                        {{ __('Filter Aktif:') }}
                    </span>
                    <Badge
                        v-if="statusFilter !== 'all'"
                        variant="secondary"
                        class="gap-1 text-[11px]"
                    >
                        <span>Status: {{ statusFilter }}</span>
                        <button
                            type="button"
                            @click="
                                statusFilter = 'all';
                                applyFilters(1);
                            "
                        >
                            <X class="size-3" />
                        </button>
                    </Badge>

                    <Badge
                        v-if="categoryFilter !== 'all'"
                        variant="secondary"
                        class="gap-1 text-[11px]"
                    >
                        <span>Kategori: {{ categoryFilter }}</span>
                        <button
                            type="button"
                            @click="
                                categoryFilter = 'all';
                                applyFilters(1);
                            "
                        >
                            <X class="size-3" />
                        </button>
                    </Badge>

                    <Badge
                        v-if="dateFrom || dateTo"
                        variant="secondary"
                        class="gap-1 text-[11px]"
                    >
                        <span
                            >Rentang: {{ dateFrom || '*' }} s/d
                            {{ dateTo || '*' }}</span
                        >
                        <button
                            type="button"
                            @click="
                                dateFrom = '';
                                dateTo = '';
                                applyFilters(1);
                            "
                        >
                            <X class="size-3" />
                        </button>
                    </Badge>

                    <Badge
                        v-if="searchQuery"
                        variant="secondary"
                        class="gap-1 text-[11px]"
                    >
                        <span>Teks: "{{ searchQuery }}"</span>
                        <button
                            type="button"
                            @click="
                                searchQuery = '';
                                applyFilters(1);
                            "
                        >
                            <X class="size-3" />
                        </button>
                    </Badge>

                    <Button
                        variant="link"
                        size="sm"
                        class="text-primary h-auto p-0 text-[11px]"
                        @click="resetFilters"
                    >
                        {{ __('Hapus Semua Filter') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Chronological Timesheet Table Card -->
        <Card class="border-border overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table
                    class="w-full border-collapse text-left text-xs"
                    data-test="personal-timesheet-table"
                >
                    <thead>
                        <tr
                            class="bg-muted/40 border-border text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase"
                        >
                            <!-- Date & Day (Sortable) -->
                            <th
                                class="hover:text-foreground cursor-pointer p-3 transition-colors select-none"
                                @click="toggleSort('operational_date')"
                            >
                                <div class="flex items-center gap-1.5">
                                    <span>{{ __('Tanggal (WIB)') }}</span>
                                    <ArrowUp
                                        v-if="
                                            sortBy === 'operational_date' &&
                                            sortDir === 'asc'
                                        "
                                        class="size-3.5"
                                    />
                                    <ArrowDown
                                        v-else-if="
                                            sortBy === 'operational_date' &&
                                            sortDir === 'desc'
                                        "
                                        class="size-3.5"
                                    />
                                    <ArrowUpDown
                                        v-else
                                        class="text-muted-foreground/40 size-3"
                                    />
                                </div>
                            </th>

                            <!-- Day Type -->
                            <th class="p-3 text-center">
                                {{ __('Jenis Hari') }}
                            </th>

                            <!-- Category Columns -->
                            <th class="p-3 text-right">
                                {{ __('Produksi') }}
                            </th>
                            <th class="p-3 text-right">
                                {{ __('TPM') }}
                            </th>
                            <th class="p-3 text-right">
                                {{ __('CapEx') }}
                            </th>
                            <th class="p-3 text-right">
                                {{ __('Lainnya') }}
                            </th>

                            <!-- Total Hours (Sortable) -->
                            <th
                                class="hover:text-foreground cursor-pointer p-3 text-right transition-colors select-none"
                                @click="toggleSort('total_hours')"
                            >
                                <div
                                    class="flex items-center justify-end gap-1.5"
                                >
                                    <span>{{ __('Total Jam') }}</span>
                                    <ArrowUp
                                        v-if="
                                            sortBy === 'total_hours' &&
                                            sortDir === 'asc'
                                        "
                                        class="size-3.5"
                                    />
                                    <ArrowDown
                                        v-else-if="
                                            sortBy === 'total_hours' &&
                                            sortDir === 'desc'
                                        "
                                        class="size-3.5"
                                    />
                                    <ArrowUpDown
                                        v-else
                                        class="text-muted-foreground/40 size-3"
                                    />
                                </div>
                            </th>

                            <!-- Status Badge (Sortable) -->
                            <th
                                class="hover:text-foreground cursor-pointer p-3 text-center transition-colors select-none"
                                @click="toggleSort('status')"
                            >
                                <div
                                    class="flex items-center justify-center gap-1.5"
                                >
                                    <span>{{ __('Status') }}</span>
                                    <ArrowUp
                                        v-if="
                                            sortBy === 'status' &&
                                            sortDir === 'asc'
                                        "
                                        class="size-3.5"
                                    />
                                    <ArrowDown
                                        v-else-if="
                                            sortBy === 'status' &&
                                            sortDir === 'desc'
                                        "
                                        class="size-3.5"
                                    />
                                    <ArrowUpDown
                                        v-else
                                        class="text-muted-foreground/40 size-3"
                                    />
                                </div>
                            </th>

                            <!-- Action / Details Toggle -->
                            <th class="p-3 text-right">
                                {{ __('Rincian') }}
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-border divide-y">
                        <!-- Loaded Data Rows -->
                        <template v-for="item in timesheet.data" :key="item.id">
                            <tr
                                :data-test="`timesheet-row-${item.id}`"
                                :class="[
                                    'hover:bg-muted/30 transition-colors',
                                    item.status === 'REJECTED'
                                        ? 'bg-red-50/25 dark:bg-red-950/10'
                                        : '',
                                    isRowExpanded(item.id) ? 'bg-muted/20' : '',
                                ]"
                            >
                                <!-- Date & Day -->
                                <td class="p-3">
                                    <div class="flex flex-col">
                                        <span
                                            class="text-foreground font-semibold"
                                        >
                                            {{ item.formatted_date }}
                                        </span>
                                        <div
                                            class="text-muted-foreground flex items-center gap-1.5 text-[11px]"
                                        >
                                            <span>{{ item.day_name }}</span>
                                            <span>&bull;</span>
                                            <span
                                                class="font-mono text-[10px]"
                                                >{{
                                                    item.submission_code
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </td>

                                <!-- Day Type Badge -->
                                <td class="p-3 text-center">
                                    <Badge
                                        v-if="item.day_type === 'HLR'"
                                        variant="outline"
                                        class="border-purple-300 bg-purple-50 text-[10px] font-bold text-purple-700 dark:border-purple-800 dark:bg-purple-950/60 dark:text-purple-300"
                                    >
                                        {{ __('HLR') }}
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="outline"
                                        class="border-slate-200 bg-slate-50 text-[10px] font-medium text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400"
                                    >
                                        {{ __('HKN') }}
                                    </Badge>
                                </td>

                                <!-- Production -->
                                <td
                                    class="text-foreground p-3 text-right font-mono tabular-nums"
                                >
                                    <span
                                        :class="
                                            item.hours_production > 0
                                                ? 'font-medium'
                                                : 'text-muted-foreground/50'
                                        "
                                    >
                                        {{ item.hours_production.toFixed(1) }}
                                    </span>
                                </td>

                                <!-- TPM -->
                                <td
                                    class="text-foreground p-3 text-right font-mono tabular-nums"
                                >
                                    <span
                                        :class="
                                            item.hours_tpm > 0
                                                ? 'font-medium text-amber-600 dark:text-amber-400'
                                                : 'text-muted-foreground/50'
                                        "
                                    >
                                        {{ item.hours_tpm.toFixed(1) }}
                                    </span>
                                </td>

                                <!-- CapEx -->
                                <td
                                    class="text-foreground p-3 text-right font-mono tabular-nums"
                                >
                                    <div
                                        class="flex flex-col items-end gap-0.5"
                                    >
                                        <span
                                            :class="
                                                item.hours_project > 0
                                                    ? 'font-bold text-sky-700 dark:text-sky-400'
                                                    : 'text-muted-foreground/50'
                                            "
                                        >
                                            {{ item.hours_project.toFixed(1) }}
                                        </span>
                                        <span
                                            v-if="item.capex_project"
                                            class="py-0.2 inline-flex items-center gap-0.5 rounded bg-sky-100 px-1 text-[9px] font-bold text-sky-800 dark:bg-sky-950/80 dark:text-sky-300"
                                            :title="item.capex_project.name"
                                        >
                                            <FolderKanban class="size-2.5" />
                                            {{
                                                item.capex_project.project_code
                                            }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Others -->
                                <td
                                    class="text-foreground p-3 text-right font-mono tabular-nums"
                                >
                                    <span
                                        :class="
                                            item.hours_others > 0
                                                ? 'font-medium'
                                                : 'text-muted-foreground/50'
                                        "
                                    >
                                        {{ item.hours_others.toFixed(1) }}
                                    </span>
                                </td>

                                <!-- Total Hours -->
                                <td
                                    class="text-foreground p-3 text-right font-mono text-sm font-bold tabular-nums"
                                >
                                    {{ item.total_hours.toFixed(1) }}
                                </td>

                                <!-- Approval Status Badge -->
                                <td class="p-3 text-center">
                                    <Badge
                                        v-if="item.status === 'APPROVED'"
                                        data-test="badge-approved"
                                        variant="outline"
                                        class="border-emerald-300 bg-emerald-50 text-[10px] font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                                    >
                                        {{ __('Disetujui') }}
                                    </Badge>
                                    <Badge
                                        v-else-if="item.status === 'PENDING'"
                                        data-test="badge-pending"
                                        variant="outline"
                                        class="border-amber-300 bg-amber-50 text-[10px] font-semibold text-amber-700 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-300"
                                    >
                                        {{ __('Menunggu') }}
                                    </Badge>
                                    <Badge
                                        v-else-if="item.status === 'REJECTED'"
                                        data-test="badge-rejected"
                                        variant="outline"
                                        class="border-red-300 bg-red-50 text-[10px] font-semibold text-[#cc0000] dark:border-red-800 dark:bg-red-950/60 dark:text-red-300"
                                    >
                                        {{ __('Ditolak') }}
                                    </Badge>
                                </td>

                                <!-- Action / Expand Row -->
                                <td class="p-3 text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        :data-test="`btn-toggle-row-${item.id}`"
                                        class="text-muted-foreground hover:text-foreground h-7 w-7 p-0"
                                        :aria-expanded="isRowExpanded(item.id)"
                                        @click="toggleRow(item.id)"
                                    >
                                        <ChevronUp
                                            v-if="isRowExpanded(item.id)"
                                            class="size-4"
                                        />
                                        <ChevronDown v-else class="size-4" />
                                        <span class="sr-only">{{
                                            __('Rincian')
                                        }}</span>
                                    </Button>
                                </td>
                            </tr>

                            <!-- Expandable Accordion Detail Row (Anti-Splitting Rule 5.3) -->
                            <tr
                                v-if="isRowExpanded(item.id)"
                                :key="`expanded-${item.id}`"
                                :data-test="`expanded-row-${item.id}`"
                                class="bg-muted/15 border-border border-b"
                            >
                                <td colspan="9" class="p-4">
                                    <div
                                        class="border-border bg-card flex flex-col gap-3 rounded-lg border p-3.5 shadow-2xs"
                                    >
                                        <!-- Rejection Callout if status is REJECTED -->
                                        <div
                                            v-if="
                                                item.status === 'REJECTED' &&
                                                item.rejection_reason
                                            "
                                            data-test="rejection-reason-callout"
                                            class="flex items-start gap-2.5 rounded-md border border-red-200 bg-red-50/70 p-3 text-xs dark:border-red-900/60 dark:bg-red-950/40"
                                        >
                                            <AlertCircle
                                                class="mt-0.5 size-4 shrink-0 text-[#cc0000] dark:text-red-400"
                                            />
                                            <div class="flex flex-col gap-0.5">
                                                <span
                                                    class="font-bold text-[#cc0000] dark:text-red-300"
                                                >
                                                    {{
                                                        __('Alasan Penolakan:')
                                                    }}
                                                </span>
                                                <span
                                                    class="text-red-900 dark:text-red-200"
                                                >
                                                    {{ item.rejection_reason }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Detail Grid: Task Notes, RCA, CapEx Asset Details -->
                                        <div
                                            class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2 lg:grid-cols-3"
                                        >
                                            <!-- Task Description -->
                                            <div class="flex flex-col gap-1">
                                                <span
                                                    class="text-muted-foreground text-[11px] font-medium"
                                                >
                                                    {{
                                                        __(
                                                            'Deskripsi Pekerjaan',
                                                        )
                                                    }}
                                                </span>
                                                <p
                                                    class="text-foreground bg-muted/30 rounded p-2 text-xs"
                                                >
                                                    {{
                                                        item.task_description ||
                                                        __('-')
                                                    }}
                                                </p>
                                            </div>

                                            <!-- RCA Category & Notes -->
                                            <div class="flex flex-col gap-1">
                                                <span
                                                    class="text-muted-foreground text-[11px] font-medium"
                                                >
                                                    {{
                                                        __(
                                                            'Analisis Akar Masalah (RCA)',
                                                        )
                                                    }}
                                                </span>
                                                <div
                                                    class="bg-muted/30 flex flex-col gap-1 rounded p-2 text-xs"
                                                >
                                                    <div
                                                        v-if="item.rca_category"
                                                        class="flex items-center gap-1.5"
                                                    >
                                                        <Badge
                                                            variant="outline"
                                                            class="text-[10px]"
                                                        >
                                                            {{
                                                                item.rca_category
                                                            }}
                                                        </Badge>
                                                    </div>
                                                    <span
                                                        v-if="item.rca_notes"
                                                        class="text-muted-foreground text-[11px]"
                                                    >
                                                        {{ item.rca_notes }}
                                                    </span>
                                                    <span
                                                        v-else-if="
                                                            !item.rca_category
                                                        "
                                                        class="text-muted-foreground"
                                                    >
                                                        -
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Financial Breakdown Snapshot -->
                                            <div class="flex flex-col gap-1">
                                                <span
                                                    class="text-muted-foreground text-[11px] font-medium"
                                                >
                                                    {{
                                                        __(
                                                            'Estimasi Nilai Lembur',
                                                        )
                                                    }}
                                                </span>
                                                <div
                                                    class="bg-muted/30 flex flex-col gap-1 rounded p-2 text-xs"
                                                >
                                                    <div
                                                        class="flex items-center justify-between font-mono"
                                                    >
                                                        <span
                                                            class="text-muted-foreground text-[11px]"
                                                            >{{
                                                                __('Tarif/Jam:')
                                                            }}</span
                                                        >
                                                        <span
                                                            class="font-semibold"
                                                            >{{
                                                                formatRupiah(
                                                                    item.hourly_rate,
                                                                )
                                                            }}</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="flex items-center justify-between font-mono"
                                                    >
                                                        <span
                                                            class="text-muted-foreground text-[11px]"
                                                            >{{
                                                                __(
                                                                    'Total Biaya:',
                                                                )
                                                            }}</span
                                                        >
                                                        <span
                                                            class="text-foreground font-bold"
                                                            >{{
                                                                formatRupiah(
                                                                    item.total_cost,
                                                                )
                                                            }}</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- CapEx Detailed Banner if attributed -->
                                        <div
                                            v-if="item.capex_project"
                                            class="flex items-center justify-between rounded border-sky-200 bg-sky-50/60 p-2.5 text-xs dark:border-sky-900/60 dark:bg-sky-950/30"
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <FolderKanban
                                                    class="size-4 text-sky-700 dark:text-sky-300"
                                                />
                                                <div>
                                                    <span
                                                        class="font-bold text-sky-900 dark:text-sky-200"
                                                    >
                                                        {{
                                                            item.capex_project
                                                                .project_code
                                                        }}
                                                    </span>
                                                    <span
                                                        class="ml-1.5 text-sky-800 dark:text-sky-300"
                                                    >
                                                        {{
                                                            item.capex_project
                                                                .name
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                            <span
                                                class="font-mono text-xs font-bold text-sky-800 dark:text-sky-300"
                                            >
                                                {{
                                                    item.hours_project.toFixed(
                                                        1,
                                                    )
                                                }}
                                                {{ __('Jam CapEx') }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <!-- Empty State -->
                        <tr v-if="timesheet.data.length === 0">
                            <td
                                colspan="9"
                                class="border-border p-8 text-center"
                                data-test="timesheet-empty-state"
                            >
                                <div
                                    class="flex flex-col items-center justify-center gap-2"
                                >
                                    <Clock
                                        class="text-muted-foreground size-8"
                                    />
                                    <span
                                        class="text-foreground text-sm font-medium"
                                    >
                                        {{
                                            __(
                                                'Belum ada catatan lembur pada filter yang dipilih',
                                            )
                                        }}
                                    </span>
                                    <p
                                        class="text-muted-foreground max-w-sm text-xs"
                                    >
                                        {{
                                            __(
                                                'Sesuaikan rentang tanggal, status persetujuan, atau kata kunci pencarian.',
                                            )
                                        }}
                                    </p>
                                    <Button
                                        v-if="isFilterActive"
                                        variant="outline"
                                        size="sm"
                                        class="mt-2 text-xs"
                                        @click="resetFilters"
                                    >
                                        {{ __('Reset Semua Filter') }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Server-Side Pagination Footer -->
            <div
                v-if="timesheet.pagination.total > 0"
                class="border-border bg-muted/20 flex flex-col items-center justify-between gap-3 border-t px-4 py-3 text-xs sm:flex-row"
                data-test="timesheet-pagination"
            >
                <!-- Results Counter -->
                <div class="text-muted-foreground font-mono">
                    {{
                        __('Menampilkan :from–:to dari :total catatan', {
                            from: timesheet.pagination.from || 0,
                            to: timesheet.pagination.to || 0,
                            total: timesheet.pagination.total,
                        })
                    }}
                </div>

                <!-- Pagination Buttons -->
                <div class="flex items-center gap-1.5">
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 gap-1 text-xs"
                        :disabled="timesheet.pagination.current_page <= 1"
                        data-test="btn-pagination-prev"
                        @click="
                            handlePageChange(
                                timesheet.pagination.current_page - 1,
                            )
                        "
                    >
                        <ChevronLeft class="size-3.5" />
                        <span>{{ __('Sebelumnya') }}</span>
                    </Button>

                    <div
                        class="text-muted-foreground px-2 font-mono text-xs font-semibold tabular-nums"
                    >
                        {{ timesheet.pagination.current_page }} /
                        {{ timesheet.pagination.last_page }}
                    </div>

                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 gap-1 text-xs"
                        :disabled="
                            timesheet.pagination.current_page >=
                            timesheet.pagination.last_page
                        "
                        data-test="btn-pagination-next"
                        @click="
                            handlePageChange(
                                timesheet.pagination.current_page + 1,
                            )
                        "
                    >
                        <span>{{ __('Berikutnya') }}</span>
                        <ChevronRight class="size-3.5" />
                    </Button>
                </div>
            </div>
        </Card>
    </div>
</template>
