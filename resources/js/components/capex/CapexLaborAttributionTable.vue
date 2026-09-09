<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Building2,
    Calendar,
    Download,
    ExternalLink,
    FileSpreadsheet,
    Filter,
    FolderKanban,
    Loader2,
    RotateCcw,
    Search,
    ShieldCheck,
    Tag,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { DepartmentOption } from '@/components/admin/CapexProjectDrawer.vue';
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
import { show as showEmployeeDossier } from '@/routes/reports/employees';

export interface AttributionItem {
    id: number;
    date: string;
    employee_id: number;
    npk: string;
    employee_name: string;
    hours_project: number;
    hourly_rate_snapshot: number;
    total_cost_snapshot: number;
    submission_code: string;
    reviewed_at: string | null;
    reviewed_by_name: string;
}

export interface AttributionProjectGroup {
    project_id: number;
    project_code: string;
    project_name: string;
    asset_code: string | null;
    department_id: number | null;
    department_name: string;
    items: AttributionItem[];
    subtotal_hours: number;
    subtotal_cost: number;
    item_count: number;
}

export interface AttributionReportData {
    groups: AttributionProjectGroup[];
    grand_total_hours: number;
    grand_total_cost: number;
    total_items: number;
    total_projects: number;
}

export interface CapexProjectOption {
    id: number;
    project_code: string;
    name: string;
    asset_code: string | null;
    department_id: number;
}

const props = withDefaults(
    defineProps<{
        attribution: AttributionReportData | null;
        departments?: DepartmentOption[];
        capexProjectsList?: CapexProjectOption[];
        filters?: {
            project_id?: string | number | null;
            department_id?: string | number | null;
            search?: string;
            date_from?: string;
            date_to?: string;
        };
        isAdmin?: boolean;
    }>(),
    {
        departments: () => [],
        capexProjectsList: () => [],
        filters: () => ({}),
        isAdmin: true,
    },
);

const emit = defineEmits<{
    (e: 'filter', filters: Record<string, any>): void;
    (e: 'reset'): void;
}>();

const { __ } = useTrans();

const selectedProjectId = ref<string>(
    props.filters.project_id ? String(props.filters.project_id) : '',
);
const selectedDepartmentId = ref<string>(
    props.filters.department_id ? String(props.filters.department_id) : '',
);
const searchInput = ref<string>(props.filters.search ?? '');
const dateFrom = ref<string>(props.filters.date_from ?? '');
const dateTo = ref<string>(props.filters.date_to ?? '');
const isExporting = ref(false);

const isFilterActive = computed(() => {
    return (
        Boolean(selectedProjectId.value) ||
        Boolean(selectedDepartmentId.value) ||
        Boolean(searchInput.value.trim()) ||
        Boolean(dateFrom.value) ||
        Boolean(dateTo.value)
    );
});

// Debounced search
let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(searchInput, (val) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        emitFilters({ search: val });
    }, 350);
});

// Debounced date inputs
let dateTimer: ReturnType<typeof setTimeout> | null = null;
watch([dateFrom, dateTo], ([from, to]) => {
    if (dateTimer) clearTimeout(dateTimer);
    dateTimer = setTimeout(() => {
        emitFilters({ date_from: from, date_to: to });
    }, 300);
});

function handleProjectChange() {
    emitFilters({ project_id: selectedProjectId.value });
}

function handleDepartmentChange() {
    emitFilters({ department_id: selectedDepartmentId.value });
}

function emitFilters(overrides: Record<string, any> = {}) {
    emit('filter', {
        project_id:
            overrides.project_id !== undefined
                ? overrides.project_id
                : selectedProjectId.value || undefined,
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
    });
}

function setDatePreset(preset: 'this_month' | 'ytd' | 'all') {
    const now = new Date();
    const year = now.getFullYear();

    if (preset === 'this_month') {
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const lastDay = new Date(year, now.getMonth() + 1, 0).getDate();
        dateFrom.value = `${year}-${month}-01`;
        dateTo.value = `${year}-${month}-${String(lastDay).padStart(2, '0')}`;
    } else if (preset === 'ytd') {
        dateFrom.value = `${year}-01-01`;
        dateTo.value = now.toISOString().split('T')[0];
    } else {
        dateFrom.value = '';
        dateTo.value = '';
    }

    emitFilters({
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    });
}

function handleReset() {
    selectedProjectId.value = '';
    selectedDepartmentId.value = '';
    searchInput.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    emit('reset');
}

function triggerExcelExport() {
    isExporting.value = true;

    const query: Record<string, any> = {
        format: 'xlsx',
        project_id: selectedProjectId.value || undefined,
        department_id: selectedDepartmentId.value || undefined,
        search: searchInput.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    };

    // Filter out undefined keys
    Object.keys(query).forEach((key) => {
        if (query[key] === undefined || query[key] === '') {
            delete query[key];
        }
    });

    const exportUrl = capexProjectsRoute.exportAttribution.url({ query });
    window.location.href = exportUrl;

    setTimeout(() => {
        isExporting.value = false;
    }, 2500);
}
</script>

<template>
    <div class="space-y-6" data-test="capex-attribution-container">
        <!-- Toolbar & Filter Controls -->
        <Card
            class="border-border bg-card shadow-xs"
            data-test="attribution-toolbar"
        >
            <CardContent class="space-y-3 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <FileSpreadsheet
                            class="size-4 text-sky-600 dark:text-sky-400"
                        />
                        <span
                            class="text-foreground text-xs font-bold tracking-wider uppercase"
                        >
                            {{ __('Filter Laporan Atribusi Finansial') }}
                        </span>
                    </div>

                    <!-- Export Action Button -->
                    <Button
                        type="button"
                        class="gap-1.5 bg-emerald-600 text-xs font-semibold text-white shadow-xs transition-all hover:bg-emerald-700 active:scale-95"
                        :disabled="isExporting"
                        @click="triggerExcelExport"
                        data-test="btn-export-attribution-excel"
                    >
                        <Loader2
                            v-if="isExporting"
                            class="size-3.5 animate-spin"
                        />
                        <Download v-else class="size-3.5" />
                        <span>{{
                            isExporting
                                ? __('Menyiapkan Excel...')
                                : __('Unduh Excel (.xlsx)')
                        }}</span>
                    </Button>
                </div>

                <!-- Filter Controls Grid -->
                <div
                    class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-5"
                >
                    <!-- Project Selector -->
                    <div>
                        <label
                            class="text-muted-foreground mb-1 block text-[11px] font-medium"
                        >
                            {{ __('Proyek CapEx') }}
                        </label>
                        <select
                            v-model="selectedProjectId"
                            @change="handleProjectChange"
                            class="border-input bg-background focus-visible:ring-ring h-8 w-full rounded-md border px-2 py-1 text-xs shadow-xs focus-visible:ring-1 focus-visible:outline-hidden"
                            data-test="filter-attribution-project"
                        >
                            <option value="">
                                {{ __('Semua Proyek CapEx') }}
                            </option>
                            <option
                                v-for="proj in capexProjectsList"
                                :key="proj.id"
                                :value="proj.id"
                            >
                                [{{ proj.project_code }}] {{ proj.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Department Selector (Admin only) -->
                    <div v-if="isAdmin && departments.length > 1">
                        <label
                            class="text-muted-foreground mb-1 block text-[11px] font-medium"
                        >
                            {{ __('Departemen') }}
                        </label>
                        <select
                            v-model="selectedDepartmentId"
                            @change="handleDepartmentChange"
                            class="border-input bg-background focus-visible:ring-ring h-8 w-full rounded-md border px-2 py-1 text-xs shadow-xs focus-visible:ring-1 focus-visible:outline-hidden"
                            data-test="filter-attribution-department"
                        >
                            <option value="">
                                {{ __('Semua Departemen') }}
                            </option>
                            <option
                                v-for="dept in departments"
                                :key="dept.id"
                                :value="dept.id"
                            >
                                {{ dept.code }} — {{ dept.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Date From -->
                    <div>
                        <label
                            class="text-muted-foreground mb-1 block text-[11px] font-medium"
                        >
                            {{ __('Tanggal Mulai (WIB)') }}
                        </label>
                        <Input
                            v-model="dateFrom"
                            type="date"
                            class="h-8 font-mono text-xs"
                            data-test="input-attribution-date-from"
                        />
                    </div>

                    <!-- Date To -->
                    <div>
                        <label
                            class="text-muted-foreground mb-1 block text-[11px] font-medium"
                        >
                            {{ __('Tanggal Akhir (WIB)') }}
                        </label>
                        <Input
                            v-model="dateTo"
                            type="date"
                            class="h-8 font-mono text-xs"
                            data-test="input-attribution-date-to"
                        />
                    </div>

                    <!-- Search Input -->
                    <div>
                        <label
                            class="text-muted-foreground mb-1 block text-[11px] font-medium"
                        >
                            {{ __('Cari NPK / Nama / SPKL') }}
                        </label>
                        <div class="relative w-full">
                            <Search
                                class="text-muted-foreground absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2"
                            />
                            <Input
                                v-model="searchInput"
                                type="text"
                                :placeholder="__('NPK, nama, No. SPKL...')"
                                class="h-8 pl-8 font-mono text-xs"
                                data-test="input-attribution-search"
                            />
                        </div>
                    </div>
                </div>

                <!-- Date Presets & Reset -->
                <div
                    class="border-border/50 flex flex-wrap items-center justify-between gap-2 border-t pt-1"
                >
                    <div class="flex items-center gap-1.5 text-xs">
                        <span
                            class="text-muted-foreground mr-1 text-[11px] font-medium"
                            >{{ __('Preset Tanggal:') }}</span
                        >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-6 px-2 text-[11px]"
                            @click="setDatePreset('this_month')"
                            data-test="btn-preset-this-month"
                        >
                            {{ __('Bulan Ini') }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-6 px-2 text-[11px]"
                            @click="setDatePreset('ytd')"
                            data-test="btn-preset-ytd"
                        >
                            {{ __('YTD (Tahun Ini)') }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-6 px-2 text-[11px]"
                            @click="setDatePreset('all')"
                            data-test="btn-preset-all"
                        >
                            {{ __('Semua Waktu') }}
                        </Button>
                    </div>

                    <Button
                        v-if="isFilterActive"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-muted-foreground hover:text-foreground h-6 px-2 text-[11px]"
                        @click="handleReset"
                        data-test="btn-attribution-reset"
                    >
                        <RotateCcw class="mr-1 size-3" />
                        {{ __('Reset Filter') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Executive Grand Total Summary Card -->
        <Card
            v-if="attribution && attribution.total_items > 0"
            class="border-sky-300 bg-sky-50/70 shadow-xs dark:border-sky-800 dark:bg-sky-950/30"
            data-test="attribution-grand-total-card"
        >
            <CardContent
                class="flex flex-col justify-between gap-4 p-4 md:flex-row md:items-center"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1 rounded-full border border-sky-300 bg-sky-100 px-2 py-0.5 text-xs font-bold text-sky-800 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-300"
                        >
                            <FolderKanban
                                class="size-3 text-sky-600 dark:text-sky-400"
                            />
                            {{ __('Rekapitulasi Kapitalisasi PSAK 16') }}
                        </span>
                        <span
                            class="text-muted-foreground font-mono text-xs tabular-nums"
                        >
                            {{ attribution.total_projects }}
                            {{ __('Proyek') }} • {{ attribution.total_items }}
                            {{ __('Catatan Lembur') }}
                        </span>
                    </div>
                    <p class="text-muted-foreground text-xs">
                        {{
                            __(
                                'Seluruh data biaya lembur di bawah menggunakan snapshot tarif permanen pada saat disetujui, sesuai ketentuan audit pajak dan aset tetap.',
                            )
                        }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-4 sm:gap-6">
                    <div class="space-y-0.5">
                        <span
                            class="text-muted-foreground block text-[11px] font-semibold tracking-wider uppercase"
                        >
                            {{ __('Grand Total Jam') }}
                        </span>
                        <span
                            class="text-foreground font-mono text-xl font-bold tabular-nums"
                            data-test="attribution-grand-total-hours"
                        >
                            {{
                                attribution.grand_total_hours.toLocaleString(
                                    'id-ID',
                                    {
                                        minimumFractionDigits: 1,
                                        maximumFractionDigits: 1,
                                    },
                                )
                            }}
                            <span
                                class="text-muted-foreground text-xs font-normal"
                                >jam</span
                            >
                        </span>
                    </div>

                    <div
                        class="space-y-0.5 border-l border-sky-200 pl-4 sm:pl-6 dark:border-sky-800/80"
                    >
                        <span
                            class="text-muted-foreground block text-[11px] font-semibold tracking-wider uppercase"
                        >
                            {{ __('Grand Total Biaya Terkapitalisasi') }}
                        </span>
                        <span
                            class="font-mono text-xl font-bold text-emerald-700 tabular-nums dark:text-emerald-400"
                            data-test="attribution-grand-total-cost"
                        >
                            {{
                                formatRupiah(attribution.grand_total_cost, {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                })
                            }}
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Project Groups Listing -->
        <div
            v-if="attribution && attribution.groups.length > 0"
            class="space-y-6"
        >
            <Card
                v-for="group in attribution.groups"
                :key="group.project_id"
                class="border-border bg-card overflow-hidden shadow-xs"
                data-test="project-group-card"
            >
                <!-- Group Header -->
                <CardHeader class="border-border bg-muted/40 border-b p-4">
                    <div
                        class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
                    >
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-md border border-sky-300 bg-sky-100 px-2 py-0.5 font-mono text-xs font-bold text-sky-800 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-300"
                                >
                                    {{ group.project_code }}
                                </span>
                                <span
                                    v-if="group.asset_code"
                                    class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-slate-100 px-2 py-0.5 font-mono text-[11px] text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    <Tag class="size-3 text-slate-500" />
                                    {{ group.asset_code }}
                                </span>
                                <Badge
                                    variant="outline"
                                    class="text-[11px] font-medium"
                                >
                                    <Building2
                                        class="mr-1 size-3 text-slate-500"
                                    />
                                    {{ group.department_name }}
                                </Badge>
                            </div>
                            <h4 class="text-foreground text-sm font-bold">
                                {{ group.project_name }}
                            </h4>
                        </div>

                        <div class="flex items-center gap-3">
                            <Link
                                :href="
                                    capexProjectsRoute.show({
                                        capex_project: group.project_id,
                                    }).url
                                "
                                class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 hover:text-sky-700 hover:underline dark:text-sky-400"
                            >
                                <span>{{ __('Buka Cockpit Proyek') }}</span>
                                <ExternalLink class="size-3" />
                            </Link>
                        </div>
                    </div>
                </CardHeader>

                <!-- Group Table -->
                <div class="overflow-x-auto">
                    <table
                        class="w-full text-left text-xs"
                        data-test="project-group-table"
                    >
                        <thead
                            class="bg-muted/70 text-muted-foreground border-border border-b font-semibold"
                        >
                            <tr>
                                <th class="px-3 py-2.5">
                                    {{ __('Tanggal (WIB)') }}
                                </th>
                                <th class="px-3 py-2.5">{{ __('NPK') }}</th>
                                <th class="px-3 py-2.5">
                                    {{ __('Nama Karyawan') }}
                                </th>
                                <th class="px-3 py-2.5 text-right">
                                    {{ __('Jam Proyek') }}
                                </th>
                                <th class="px-3 py-2.5 text-right">
                                    {{ __('Tarif Snapshot/Jam') }}
                                </th>
                                <th class="px-3 py-2.5 text-right">
                                    {{ __('Total Biaya (Rp)') }}
                                </th>
                                <th class="px-3 py-2.5">
                                    {{ __('No. SPKL') }}
                                </th>
                                <th class="px-3 py-2.5">
                                    {{ __('Tgl Persetujuan') }}
                                </th>
                                <th class="px-3 py-2.5">
                                    {{ __('Disetujui Oleh') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-border/60 divide-y">
                            <tr
                                v-for="item in group.items"
                                :key="item.id"
                                class="hover:bg-muted/30 transition-colors"
                            >
                                <td
                                    class="px-3 py-2 font-mono whitespace-nowrap"
                                >
                                    {{ item.date }}
                                </td>
                                <td
                                    class="px-3 py-2 font-mono font-medium whitespace-nowrap text-slate-700 dark:text-slate-300"
                                >
                                    <Link
                                        :href="
                                            showEmployeeDossier({
                                                npk: item.npk,
                                            }).url
                                        "
                                        class="font-semibold text-sky-600 hover:underline dark:text-sky-400"
                                        :title="__('Buka Dossier Karyawan')"
                                    >
                                        {{ item.npk }}
                                    </Link>
                                </td>
                                <td
                                    class="text-foreground px-3 py-2 font-medium whitespace-nowrap"
                                >
                                    {{ item.employee_name }}
                                </td>
                                <td
                                    class="text-foreground px-3 py-2 text-right font-mono font-semibold whitespace-nowrap tabular-nums"
                                >
                                    {{
                                        item.hours_project.toLocaleString(
                                            'id-ID',
                                            {
                                                minimumFractionDigits: 1,
                                                maximumFractionDigits: 1,
                                            },
                                        )
                                    }}
                                    jam
                                </td>
                                <td
                                    class="text-muted-foreground px-3 py-2 text-right font-mono whitespace-nowrap tabular-nums"
                                >
                                    {{
                                        formatRupiah(
                                            item.hourly_rate_snapshot,
                                            {
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            },
                                        )
                                    }}
                                </td>
                                <td
                                    class="text-foreground px-3 py-2 text-right font-mono font-semibold whitespace-nowrap tabular-nums"
                                >
                                    {{
                                        formatRupiah(item.total_cost_snapshot, {
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}
                                </td>
                                <td
                                    class="text-muted-foreground px-3 py-2 font-mono text-[11px] whitespace-nowrap"
                                >
                                    {{ item.submission_code }}
                                </td>
                                <td
                                    class="text-muted-foreground px-3 py-2 font-mono text-[11px] whitespace-nowrap"
                                >
                                    {{ item.reviewed_at || '-' }}
                                </td>
                                <td
                                    class="text-muted-foreground px-3 py-2 text-[11px] whitespace-nowrap"
                                >
                                    {{ item.reviewed_by_name }}
                                </td>
                            </tr>
                        </tbody>
                        <!-- Project Subtotal Row -->
                        <tfoot
                            class="border-border bg-muted/50 border-t-2 font-semibold"
                        >
                            <tr>
                                <td
                                    colspan="3"
                                    class="text-foreground px-3 py-2.5 text-xs font-bold"
                                >
                                    {{
                                        __('Subtotal Proyek :code:', {
                                            code: group.project_code,
                                        })
                                    }}
                                    <span
                                        class="text-muted-foreground text-[11px] font-normal"
                                    >
                                        ({{ group.item_count }}
                                        {{ __('catatan') }})
                                    </span>
                                </td>
                                <td
                                    class="text-foreground px-3 py-2.5 text-right font-mono text-xs font-bold whitespace-nowrap tabular-nums"
                                    data-test="project-subtotal-hours"
                                >
                                    {{
                                        group.subtotal_hours.toLocaleString(
                                            'id-ID',
                                            {
                                                minimumFractionDigits: 1,
                                                maximumFractionDigits: 1,
                                            },
                                        )
                                    }}
                                    jam
                                </td>
                                <td
                                    class="text-muted-foreground px-3 py-2.5 text-right font-mono text-xs"
                                >
                                    -
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right font-mono text-xs font-bold whitespace-nowrap text-sky-700 tabular-nums dark:text-sky-300"
                                    data-test="project-subtotal-cost"
                                >
                                    {{
                                        formatRupiah(group.subtotal_cost, {
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}
                                </td>
                                <td colspan="3" class="px-3 py-2.5"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </Card>
        </div>

        <!-- Empty State -->
        <Card
            v-else
            class="border-border bg-card p-10 text-center shadow-xs"
            data-test="attribution-empty-state"
        >
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
                                'Tidak ada catatan lembur CapEx yang disetujui pada filter ini',
                            )
                        }}
                    </h3>
                    <p class="text-muted-foreground text-xs leading-relaxed">
                        {{
                            __(
                                'Pastikan jam lembur dengan alokasi Proyek CapEx telah berstatus disetujui (APPROVED) atau sesuaikan rentang tanggal dan parameter pencarian.',
                            )
                        }}
                    </p>
                </div>
                <div v-if="isFilterActive" class="pt-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="gap-1.5 text-xs font-semibold"
                        @click="handleReset"
                    >
                        <RotateCcw class="size-3.5" />
                        {{ __('Reset Filter') }}
                    </Button>
                </div>
            </div>
        </Card>
    </div>
</template>
