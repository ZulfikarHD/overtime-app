<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowDownUp,
    ArrowUpDown,
    CheckCircle2,
    FileSpreadsheet,
    RefreshCw,
    Search,
    Trash2,
    Upload,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTrans } from '@/composables/useTrans';
import { dashboard } from '@/routes';
import {
    index as splIndex,
    importMethod as splImport,
    destroy as splDestroy,
} from '@/routes/overtime/spl';

const { __ } = useTrans();

// -------------------------------------------------------
// Props
// -------------------------------------------------------
interface SplEntryRecord {
    id: number;
    npk_snapshot: string;
    employee_name_snapshot: string;
    section_name_snapshot: string | null;
    department_name_snapshot: string | null;
    realization_date: string;
    day_type: 'HKN' | 'HLR';
    start_time: string;
    end_time: string;
    total_hours: string | number;
    jenis_pekerjaan: string | null;
    type_ot_code: number | null;
    keterangan_lembur: string | null;
    section: { id: number; name: string; code: string } | null;
    employee: { id: number; npk: string; full_name: string } | null;
    imported_by: { id: number; name: string } | null;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface ImportResult {
    imported: number;
    updated: number;
    skipped: number;
    errors: string[];
}

const props = defineProps<{
    entries: Paginated<SplEntryRecord>;
    sections: Array<{ id: number; code: string; name: string }>;
    filters: {
        fiscal_year?: string;
        fiscal_month?: string;
        section_id?: string;
        sort?: string;
        direction?: string;
        search?: string;
    };
    current_year: number;
    current_month: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Input Lembur (SPL)', href: splIndex() },
        ],
    },
});

// -------------------------------------------------------
// State
// -------------------------------------------------------
const page = usePage();
const importResult = computed<ImportResult | null>(
    () => (page.props.import_result as ImportResult | null) ?? null,
);

const MONTHS = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - 1 + i);

const fileInput = ref<HTMLInputElement | null>(null);
const dragActive = ref(false);

const form = useForm<{
    file: File | null;
    fiscal_year: number;
    fiscal_month: number;
}>({
    file: null,
    fiscal_year: props.current_year,
    fiscal_month: props.current_month,
});

// -------------------------------------------------------
// Sorting
// -------------------------------------------------------
const sortBy = ref<string>(props.filters.sort ?? 'date');
const sortDir = ref<'asc' | 'desc'>(
    (props.filters.direction as 'asc' | 'desc') ?? 'desc',
);

interface SortCol {
    key: string;
    label: string;
}

function applySort(col: string) {
    if (sortBy.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = col;
        sortDir.value = col === 'date' || col === 'hours' ? 'desc' : 'asc';
    }

    router.get(
        splIndex(),
        {
            ...props.filters,
            sort: sortBy.value,
            direction: sortDir.value,
        },
        { preserveState: true, replace: true },
    );
}

// -------------------------------------------------------
// Filters
// -------------------------------------------------------
const filterYear = ref<number>(
    props.filters.fiscal_year
        ? Number(props.filters.fiscal_year)
        : props.current_year,
);
const filterMonth = ref<number>(
    props.filters.fiscal_month
        ? Number(props.filters.fiscal_month)
        : props.current_month,
);
const filterSection = ref<string>(props.filters.section_id ?? '');
const filterSearch = ref<string>(props.filters.search ?? '');
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

function applyFilters() {
    router.get(
        splIndex(),
        {
            fiscal_year: filterYear.value,
            fiscal_month: filterMonth.value,
            section_id: filterSection.value || undefined,
            search: filterSearch.value || undefined,
            sort: sortBy.value,
            direction: sortDir.value,
        },
        { preserveState: true, replace: true },
    );
}

function onSearchInput() {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(applyFilters, 400);
}

// -------------------------------------------------------
// File upload
// -------------------------------------------------------
function onFileDrop(e: DragEvent) {
    dragActive.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls'))) {
        form.file = file;
    }
}

function onFileInput(e: Event) {
    const target = e.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
}

function removeFile() {
    form.file = null;
    if (fileInput.value) fileInput.value.value = '';
}

function submit() {
    form.post(splImport.url(), {
        forceFormData: true,
    });
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function formatTime(timeStr: string): string {
    return timeStr?.substring(0, 5) ?? '–';
}

function typeOtLabel(code: number | null): string {
    if (!code) return '–';
    if ([61, 62].includes(code)) return `A · ${code}`;
    if ([65, 66].includes(code)) return `B · ${code}`;
    if ([67, 68].includes(code)) return `C · ${code}`;
    return `D · ${code}`;
}

function typeOtColor(code: number | null): string {
    if (!code) return 'text-slate-400';
    if ([61, 62].includes(code)) return 'text-blue-600';
    if ([65, 66].includes(code)) return 'text-amber-600';
    if ([67, 68].includes(code)) return 'text-emerald-600';
    return 'text-slate-500';
}

const deleteForm = useForm({});

function deleteEntry(entry: SplEntryRecord) {
    if (
        !confirm(
            __('Hapus data SPL untuk :name tanggal :date?', {
                name: entry.employee_name_snapshot,
                date: entry.realization_date,
            }),
        )
    )
        return;
    deleteForm.delete(splDestroy.url(entry.id));
}

// -------------------------------------------------------
// Grouping helpers
// -------------------------------------------------------
type GroupMode = 'none' | 'date' | 'section';
const groupMode = ref<GroupMode>('none');

interface GroupedEntries {
    key: string;
    label: string;
    entries: SplEntryRecord[];
}

const groupedEntries = computed<GroupedEntries[]>(() => {
    if (groupMode.value === 'none') {
        return [{ key: 'all', label: '', entries: props.entries.data }];
    }

    const map = new Map<string, SplEntryRecord[]>();

    for (const e of props.entries.data) {
        const key =
            groupMode.value === 'date'
                ? e.realization_date
                : (e.section_name_snapshot ?? e.section?.name ?? '–');

        if (!map.has(key)) map.set(key, []);
        map.get(key)!.push(e);
    }

    return [...map.entries()].map(([key, entries]) => ({
        key,
        label: key,
        entries,
    }));
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col p-4 sm:p-6">
        <Head :title="__('Input Lembur (SPL)')" />

        <div class="mb-6">
            <Heading
                :title="__('Input Lembur — Upload SPL')"
                :description="
                    __(
                        'Unggah file Excel SPL (spl_manual_ot.xlsx). Nama sheet = nomor tanggal. Data duplikat (NPK + Tanggal + Mulai) akan diperbarui.',
                    )
                "
            />
        </div>

        <!-- Import Result Alert -->
        <div v-if="importResult" class="mb-4">
            <div
                class="rounded-lg border p-4 text-sm"
                :class="
                    importResult.errors.length
                        ? 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30'
                        : 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30'
                "
            >
                <div class="flex items-start gap-2">
                    <CheckCircle2
                        v-if="!importResult.errors.length"
                        class="mt-0.5 size-4 shrink-0 text-emerald-600"
                    />
                    <AlertCircle
                        v-else
                        class="mt-0.5 size-4 shrink-0 text-amber-600"
                    />
                    <div>
                        <p
                            class="font-semibold"
                            :class="
                                importResult.errors.length
                                    ? 'text-amber-700'
                                    : 'text-emerald-700'
                            "
                        >
                            {{ __('Impor selesai') }}:
                            <span class="font-mono">{{
                                importResult.imported
                            }}</span>
                            {{ __('ditambahkan') }},
                            <span class="font-mono">{{
                                importResult.updated
                            }}</span>
                            {{ __('diperbarui') }},
                            <span class="font-mono">{{
                                importResult.skipped
                            }}</span>
                            {{ __('dilewati') }}.
                        </p>
                        <ul
                            v-if="importResult.errors.length"
                            class="mt-2 space-y-0.5 text-xs text-amber-600"
                        >
                            <li
                                v-for="(err, i) in importResult.errors.slice(
                                    0,
                                    10,
                                )"
                                :key="i"
                            >
                                • {{ err }}
                            </li>
                            <li
                                v-if="importResult.errors.length > 10"
                                class="text-amber-500"
                            >
                                + {{ importResult.errors.length - 10 }}
                                {{ __('error lainnya') }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Card -->
        <Card class="mb-6 border-slate-200 dark:border-slate-800">
            <CardHeader class="pb-3">
                <CardTitle
                    class="flex items-center gap-2 text-sm font-semibold"
                >
                    <Upload class="size-4 text-[#cc0000]" />
                    {{ __('Upload File SPL Excel') }}
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Year + Month selectors -->
                <div class="flex flex-wrap gap-3">
                    <div class="w-32">
                        <label
                            class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300"
                            >{{ __('Tahun') }}</label
                        >
                        <Select
                            v-model="form.fiscal_year"
                            @update:model-value="
                                (v) => (form.fiscal_year = Number(v))
                            "
                        >
                            <SelectTrigger class="h-9 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="y in years"
                                    :key="y"
                                    :value="y"
                                    >{{ y }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="w-40">
                        <label
                            class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300"
                            >{{ __('Bulan') }}</label
                        >
                        <Select
                            v-model="form.fiscal_month"
                            @update:model-value="
                                (v) => (form.fiscal_month = Number(v))
                            "
                        >
                            <SelectTrigger class="h-9 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="m in MONTHS"
                                    :key="m.value"
                                    :value="m.value"
                                    >{{ m.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Dropzone -->
                <div
                    class="relative cursor-pointer rounded-lg border-2 border-dashed transition-colors"
                    :class="
                        dragActive
                            ? 'border-[#cc0000] bg-red-50 dark:bg-red-950/20'
                            : 'border-slate-300 hover:border-slate-400 dark:border-slate-700'
                    "
                    @dragover.prevent="dragActive = true"
                    @dragleave="dragActive = false"
                    @drop.prevent="onFileDrop"
                    @click="fileInput?.click()"
                >
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".xlsx,.xls"
                        class="hidden"
                        @change="onFileInput"
                    />

                    <div v-if="!form.file" class="p-8 text-center">
                        <FileSpreadsheet
                            class="mx-auto mb-2 size-10 text-slate-300 dark:text-slate-600"
                        />
                        <p
                            class="text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            {{ __('Klik atau seret file Excel ke sini') }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ __('Format: .xlsx atau .xls — Maksimal 10 MB') }}
                        </p>
                        <p class="text-xs text-slate-400">
                            {{
                                __(
                                    'Nama sheet = nomor tanggal (1–31), sesuai format spl_manual_ot.xlsx',
                                )
                            }}
                        </p>
                    </div>

                    <div
                        v-else
                        class="flex items-center justify-between gap-3 p-4"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <FileSpreadsheet
                                class="size-8 shrink-0 text-emerald-600"
                            />
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ form.file.name }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ (form.file.size / 1024).toFixed(1) }} KB
                                </p>
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7 shrink-0 text-slate-400 hover:text-red-500"
                            @click.stop="removeFile"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>
                </div>

                <!-- Error messages -->
                <p v-if="form.errors.file" class="text-xs text-red-500">
                    {{ form.errors.file }}
                </p>

                <!-- Submit -->
                <div class="flex items-center justify-between gap-4">
                    <p class="text-xs text-slate-500">
                        {{
                            __(
                                'Gate: Hanya Admin & Manager yang dapat mengimpor data SPL.',
                            )
                        }}
                    </p>
                    <Button
                        type="button"
                        class="shrink-0 bg-[#cc0000] text-white hover:bg-[#b30000]"
                        :disabled="!form.file || form.processing"
                        @click="submit"
                    >
                        <RefreshCw
                            v-if="form.processing"
                            class="mr-2 size-4 animate-spin"
                        />
                        <Upload v-else class="mr-2 size-4" />
                        {{
                            form.processing
                                ? __('Memproses...')
                                : __('Import SPL')
                        }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Filter + Sort toolbar -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <!-- Year -->
            <Select
                v-model="filterYear"
                @update:model-value="
                    (v) => {
                        filterYear = Number(v);
                        applyFilters();
                    }
                "
            >
                <SelectTrigger class="h-8 w-24 text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="y in years" :key="y" :value="y">{{
                        y
                    }}</SelectItem>
                </SelectContent>
            </Select>

            <!-- Month -->
            <Select
                v-model="filterMonth"
                @update:model-value="
                    (v) => {
                        filterMonth = Number(v);
                        applyFilters();
                    }
                "
            >
                <SelectTrigger class="h-8 w-32 text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="m in MONTHS"
                        :key="m.value"
                        :value="m.value"
                        >{{ m.label }}</SelectItem
                    >
                </SelectContent>
            </Select>

            <!-- Section -->
            <Select
                v-if="sections.length > 1"
                v-model="filterSection"
                @update:model-value="
                    (v) => {
                        filterSection = String(v ?? '');
                        applyFilters();
                    }
                "
            >
                <SelectTrigger class="h-8 w-48 text-xs">
                    <SelectValue :placeholder="__('Semua Seksi')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">{{ __('Semua Seksi') }}</SelectItem>
                    <SelectItem
                        v-for="s in sections"
                        :key="s.id"
                        :value="String(s.id)"
                        >{{ s.name }}</SelectItem
                    >
                </SelectContent>
            </Select>

            <!-- Search -->
            <div class="relative min-w-40 flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-slate-400"
                />
                <Input
                    v-model="filterSearch"
                    class="h-8 pl-8 text-xs"
                    :placeholder="__('Cari nama, NPK, seksi…')"
                    @input="onSearchInput"
                />
            </div>

            <!-- Group by -->
            <Select v-model="groupMode">
                <SelectTrigger class="h-8 w-36 text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">{{ __('Tanpa Grup') }}</SelectItem>
                    <SelectItem value="date">{{
                        __('Grup per Tanggal')
                    }}</SelectItem>
                    <SelectItem value="section">{{
                        __('Grup per Seksi')
                    }}</SelectItem>
                </SelectContent>
            </Select>

            <span class="ml-auto text-xs text-slate-400 tabular-nums">
                {{ entries.total }} {{ __('entri') }}
            </span>
        </div>

        <!-- Empty state -->
        <div
            v-if="entries.data.length === 0"
            class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center dark:border-slate-700 dark:bg-slate-900"
        >
            <FileSpreadsheet class="mx-auto mb-2 size-8 text-slate-300" />
            <p class="text-sm text-slate-500">
                {{ __('Belum ada data SPL. Import file Excel SPL di atas.') }}
            </p>
        </div>

        <!-- Table -->
        <div
            v-else
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-xs">
                    <thead>
                        <tr
                            class="dark:bg-slate-850 border-b border-slate-200 bg-slate-50 text-[11px] font-semibold tracking-wider text-slate-500 uppercase dark:border-slate-800"
                        >
                            <th
                                class="cursor-pointer p-3 select-none hover:bg-slate-100 dark:hover:bg-slate-800"
                                @click="applySort('employee')"
                            >
                                <span class="inline-flex items-center gap-1">
                                    {{ __('Karyawan') }}
                                    <ArrowDownUp
                                        v-if="sortBy !== 'employee'"
                                        class="size-3 text-slate-300"
                                    />
                                    <ArrowUpDown
                                        v-else
                                        class="size-3 text-[#cc0000]"
                                    />
                                </span>
                            </th>
                            <th
                                class="cursor-pointer p-3 select-none hover:bg-slate-100 dark:hover:bg-slate-800"
                                @click="applySort('date')"
                            >
                                <span class="inline-flex items-center gap-1">
                                    {{ __('Tanggal') }}
                                    <ArrowDownUp
                                        v-if="sortBy !== 'date'"
                                        class="size-3 text-slate-300"
                                    />
                                    <ArrowUpDown
                                        v-else
                                        class="size-3 text-[#cc0000]"
                                    />
                                </span>
                            </th>
                            <th
                                class="cursor-pointer p-3 text-center select-none hover:bg-slate-100 dark:hover:bg-slate-800"
                                @click="applySort('day_type')"
                            >
                                <span class="inline-flex items-center gap-1">
                                    {{ __('Hari') }}
                                    <ArrowDownUp
                                        v-if="sortBy !== 'day_type'"
                                        class="size-3 text-slate-300"
                                    />
                                    <ArrowUpDown
                                        v-else
                                        class="size-3 text-[#cc0000]"
                                    />
                                </span>
                            </th>
                            <th
                                class="cursor-pointer p-3 text-right select-none hover:bg-slate-100 dark:hover:bg-slate-800"
                                @click="applySort('hours')"
                            >
                                <span
                                    class="inline-flex items-center justify-end gap-1"
                                >
                                    {{ __('Jam') }}
                                    <ArrowDownUp
                                        v-if="sortBy !== 'hours'"
                                        class="size-3 text-slate-300"
                                    />
                                    <ArrowUpDown
                                        v-else
                                        class="size-3 text-[#cc0000]"
                                    />
                                </span>
                            </th>
                            <th
                                class="cursor-pointer p-3 text-center select-none hover:bg-slate-100 dark:hover:bg-slate-800"
                                @click="applySort('ot_type')"
                            >
                                <span class="inline-flex items-center gap-1">
                                    {{ __('Type OT') }}
                                    <ArrowDownUp
                                        v-if="sortBy !== 'ot_type'"
                                        class="size-3 text-slate-300"
                                    />
                                    <ArrowUpDown
                                        v-else
                                        class="size-3 text-[#cc0000]"
                                    />
                                </span>
                            </th>
                            <th
                                class="cursor-pointer p-3 select-none hover:bg-slate-100 dark:hover:bg-slate-800"
                                @click="applySort('section')"
                            >
                                <span class="inline-flex items-center gap-1">
                                    {{ __('Dept · Seksi') }}
                                    <ArrowDownUp
                                        v-if="sortBy !== 'section'"
                                        class="size-3 text-slate-300"
                                    />
                                    <ArrowUpDown
                                        v-else
                                        class="size-3 text-[#cc0000]"
                                    />
                                </span>
                            </th>
                            <th class="p-3">{{ __('Ket. Lembur') }}</th>
                            <th class="p-3 text-right">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <template
                            v-for="group in groupedEntries"
                            :key="group.key"
                        >
                            <!-- Group header row -->
                            <tr
                                v-if="groupMode !== 'none'"
                                class="bg-slate-100/80 dark:bg-slate-800/60"
                            >
                                <td
                                    colspan="8"
                                    class="px-3 py-1.5 text-[11px] font-bold tracking-wide text-slate-600 uppercase dark:text-slate-300"
                                >
                                    {{ group.label }}
                                    <span
                                        class="ml-2 font-normal text-slate-400"
                                        >({{ group.entries.length }})</span
                                    >
                                </td>
                            </tr>

                            <!-- Data rows -->
                            <tr
                                v-for="entry in group.entries"
                                :key="entry.id"
                                class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/50"
                            >
                                <!-- Karyawan -->
                                <td class="p-2.5">
                                    <div
                                        class="leading-tight font-bold text-slate-900 dark:text-white"
                                    >
                                        {{ entry.employee_name_snapshot }}
                                    </div>
                                    <div
                                        class="font-mono text-[10px] text-slate-400"
                                    >
                                        {{ entry.npk_snapshot }}
                                    </div>
                                    <div
                                        v-if="!entry.employee"
                                        class="text-[10px] text-amber-500"
                                    >
                                        {{ __('NPK tdk ditemukan') }}
                                    </div>
                                </td>

                                <!-- Tanggal -->
                                <td
                                    class="p-2.5 font-mono text-slate-700 tabular-nums dark:text-slate-300"
                                >
                                    {{ entry.realization_date }}
                                </td>

                                <!-- Hari -->
                                <td class="p-2.5 text-center">
                                    <Badge
                                        variant="outline"
                                        :class="
                                            entry.day_type === 'HLR'
                                                ? 'border-red-200 bg-red-50 text-[10px] text-[#cc0000]'
                                                : 'border-slate-200 bg-slate-50 text-[10px] text-slate-600'
                                        "
                                    >
                                        {{ entry.day_type }}
                                    </Badge>
                                </td>

                                <!-- Jam (total + start–end) -->
                                <td
                                    class="p-2.5 text-right font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                                >
                                    {{ Number(entry.total_hours).toFixed(2) }}
                                    <div
                                        class="text-[10px] font-normal text-slate-400"
                                    >
                                        {{ formatTime(entry.start_time) }}–{{
                                            formatTime(entry.end_time)
                                        }}
                                    </div>
                                </td>

                                <!-- Type OT -->
                                <td
                                    class="p-2.5 text-center font-mono"
                                    :class="typeOtColor(entry.type_ot_code)"
                                >
                                    {{ typeOtLabel(entry.type_ot_code) }}
                                </td>

                                <!-- Dept · Seksi -->
                                <td class="p-2.5">
                                    <div
                                        class="text-slate-700 dark:text-slate-200"
                                    >
                                        {{
                                            entry.section_name_snapshot ??
                                            entry.section?.name ??
                                            '–'
                                        }}
                                    </div>
                                    <div
                                        v-if="entry.department_name_snapshot"
                                        class="text-[10px] text-slate-400"
                                    >
                                        {{ entry.department_name_snapshot }}
                                    </div>
                                </td>

                                <!-- Ket Lembur + Jenis Pekerjaan -->
                                <td
                                    class="max-w-[180px] p-2.5 text-slate-600 dark:text-slate-300"
                                >
                                    <div
                                        class="truncate"
                                        :title="entry.keterangan_lembur ?? ''"
                                    >
                                        {{ entry.keterangan_lembur ?? '–' }}
                                    </div>
                                    <div
                                        v-if="entry.jenis_pekerjaan"
                                        class="truncate text-[10px] text-slate-400"
                                        :title="entry.jenis_pekerjaan"
                                    >
                                        {{ entry.jenis_pekerjaan }}
                                    </div>
                                </td>

                                <!-- Aksi -->
                                <td class="p-2.5 text-right">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 text-red-400 hover:text-red-600"
                                        @click="deleteEntry(entry)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Pagination links -->
            <div
                v-if="entries.last_page > 1"
                class="flex items-center justify-between gap-2 border-t border-slate-100 p-3 text-xs text-slate-500 dark:border-slate-800"
            >
                <span
                    >{{ __('Halaman') }} {{ entries.current_page }} /
                    {{ entries.last_page }} · {{ entries.total }}
                    {{ __('entri') }}</span
                >
                <div class="flex gap-1">
                    <template v-for="link in entries.links" :key="link.label">
                        <a
                            v-if="link.url"
                            :href="link.url"
                            class="rounded border px-2 py-1"
                            :class="
                                link.active
                                    ? 'border-[#cc0000] bg-[#cc0000] text-white'
                                    : 'border-slate-200 hover:bg-slate-50'
                            "
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="cursor-not-allowed rounded border border-slate-100 px-2 py-1 text-slate-300"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
