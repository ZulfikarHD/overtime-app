<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Save, Send, Users } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useSpreadsheetNav } from '@/composables/useSpreadsheetNav';
import { useTrans } from '@/composables/useTrans';
import { dashboard } from '@/routes';
import {
    index as planningIndex,
    create as planningCreate,
    store as planningStore,
    update as planningUpdate,
    publish as planningPublish,
} from '@/routes/overtime/planning';

const { __ } = useTrans();

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

type MonitorTab = 'weekly' | 'plan_vs_actual';
const monitorTab = ref<MonitorTab>('weekly');

// -------------------------------------------------------
// Props
// -------------------------------------------------------
interface DepartmentOption {
    id: number;
    code: string;
    name: string;
}
interface SectionOption {
    id: number;
    department_id: number;
    code: string;
    name: string;
}
interface RosterEmployee {
    id: number;
    npk: string;
    full_name: string;
    job_position: string;
}
interface CalendarDay {
    day: number;
    date: string;
    day_type: 'HKN' | 'HLR';
    day_name: string;
}

interface PlanItemData {
    employee_id: number;
    plan_date: string;
    hours_production: number;
    hours_tpm: number;
    hours_project: number;
    hours_others: number;
}

interface ExistingPlan {
    id: number;
    plan_code: string;
    status: 'DRAFT' | 'PUBLISHED';
    notes: string | null;
    section_id: number;
    department_id: number;
    fiscal_year: number;
    fiscal_month: number;
    items: Array<{
        employee_id: number;
        plan_date: string;
        hours_production: number | string;
        hours_tpm: number | string;
        hours_project: number | string;
        hours_others: number | string;
        employee?: { id: number; npk: string; full_name: string } | null;
    }>;
}

interface WeekActualBucket {
    hours_production: number;
    hours_tpm: number;
    hours_project: number;
    hours_others: number;
    index_total: number;
}

interface EmployeeActuals {
    weeks: Record<number, WeekActualBucket>;
}

const props = defineProps<{
    departments: DepartmentOption[];
    sections: SectionOption[];
    selected_department_id: number | null;
    selected_section_id: number | null;
    fiscal_year: number;
    fiscal_month: number;
    calendar_days: CalendarDay[];
    initial_roster: RosterEmployee[];
    existing_plan: ExistingPlan | null;
    actuals: Record<number, EmployeeActuals>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Planning OT', href: planningIndex() },
        ],
    },
});

// -------------------------------------------------------
// Constants
// -------------------------------------------------------
type CategoryKey =
    | 'hours_production'
    | 'hours_tpm'
    | 'hours_project'
    | 'hours_others';

const CATEGORIES: {
    key: CategoryKey;
    label: string;
    letter: string;
    color: string;
}[] = [
    {
        key: 'hours_production',
        label: 'Prod',
        letter: 'A',
        color: 'text-blue-600',
    },
    { key: 'hours_tpm', label: 'TPM', letter: 'B', color: 'text-amber-600' },
    {
        key: 'hours_project',
        label: 'Proj',
        letter: 'C',
        color: 'text-emerald-600',
    },
    {
        key: 'hours_others',
        label: 'Lain',
        letter: 'D',
        color: 'text-slate-500',
    },
];

const HKN_MULTIPLIER = 1.5;
const HLR_MULTIPLIER = 2.0;
const WEEK_NUMBERS = [1, 2, 3, 4, 5] as const;

type CellData = {
    hours_production: number;
    hours_tpm: number;
    hours_project: number;
    hours_others: number;
};

// -------------------------------------------------------
// State
// -------------------------------------------------------
const isPublished = computed(() => props.existing_plan?.status === 'PUBLISHED');
const selectedSectionId = ref<number | null>(props.selected_section_id);
const selectedDepartmentId = ref<number | null>(props.selected_department_id);
const roster = ref<RosterEmployee[]>(props.initial_roster);
const gridData = ref<Record<number, Record<string, CellData>>>({});
const gridRoot = ref<HTMLElement | null>(null);
const draftInputs = ref<Record<string, string>>({});

function emptyCell(): CellData {
    return {
        hours_production: 0,
        hours_tpm: 0,
        hours_project: 0,
        hours_others: 0,
    };
}

function initGrid() {
    const data: Record<number, Record<string, CellData>> = {};

    roster.value.forEach((emp) => {
        data[emp.id] = {};
        props.calendar_days.forEach((d) => {
            data[emp.id][d.date] = emptyCell();
        });
    });

    if (props.existing_plan?.items) {
        props.existing_plan.items.forEach((item) => {
            if (data[item.employee_id]?.[item.plan_date]) {
                data[item.employee_id][item.plan_date] = {
                    hours_production: Number(item.hours_production) || 0,
                    hours_tpm: Number(item.hours_tpm) || 0,
                    hours_project: Number(item.hours_project) || 0,
                    hours_others: Number(item.hours_others) || 0,
                };
            }
        });
    }

    gridData.value = data;
    draftInputs.value = {};
}

initGrid();

// -------------------------------------------------------
// Spreadsheet navigation
// -------------------------------------------------------
const rowCount = computed(() => roster.value.length);
const colCount = computed(() => props.calendar_days.length * CATEGORIES.length);

function flatToDayCat(flatCol: number): {
    dayIndex: number;
    catIndex: number;
} {
    return {
        dayIndex: Math.floor(flatCol / CATEGORIES.length),
        catIndex: flatCol % CATEGORIES.length,
    };
}

function dayCatToFlat(dayIndex: number, catIndex: number): number {
    return dayIndex * CATEGORIES.length + catIndex;
}

function cellInputId(row: number, col: number): string {
    return `plan-cell-${row}-${col}`;
}

const { handleKeydown } = useSpreadsheetNav({
    rowCount,
    colCount,
    getCellInput: (row, col) =>
        gridRoot.value?.querySelector<HTMLInputElement>(
            `#${cellInputId(row, col)}`,
        ) ?? null,
    onClear: (row, col) => {
        const emp = roster.value[row];
        const { dayIndex, catIndex } = flatToDayCat(col);
        const day = props.calendar_days[dayIndex];
        const cat = CATEGORIES[catIndex];
        if (!emp || !day || !cat) return;
        setCellValue(emp.id, day.date, cat.key, 0);
    },
});

// -------------------------------------------------------
// Computed helpers
// -------------------------------------------------------
const filteredSections = computed(() =>
    props.sections.filter(
        (s) =>
            !selectedDepartmentId.value ||
            s.department_id === selectedDepartmentId.value,
    ),
);

function weekOfDay(day: number): number {
    if (day <= 7) return 1;
    if (day <= 14) return 2;
    if (day <= 21) return 3;
    if (day <= 28) return 4;
    return 5;
}

function dayMultiplier(dayType: string): number {
    return dayType === 'HLR' ? HLR_MULTIPLIER : HKN_MULTIPLIER;
}

/** Strong right edge after each day's D column; light dividers between A/B/C. */
function dayCategoryBorderClass(catIndex: number): string {
    return catIndex === CATEGORIES.length - 1
        ? 'border-r-2 border-slate-400 dark:border-slate-500'
        : 'border-r border-slate-200/80 dark:border-slate-700';
}

/**
 * Alternating day-band backgrounds so date groups are easy to scan.
 * HLR keeps a red tint; HKN alternates white / soft slate.
 */
function dayBandClass(
    dayIndex: number,
    dayType: string,
    tone: 'header' | 'sub' | 'cell' | 'footer' = 'cell',
): string {
    if (dayType === 'HLR') {
        if (tone === 'header') {
            return 'bg-red-100 text-[#cc0000] dark:bg-red-950/50';
        }
        if (tone === 'footer') {
            return 'bg-red-50/70 text-slate-900 dark:bg-red-950/30 dark:text-white';
        }
        return 'bg-red-50/50 dark:bg-red-950/25';
    }

    const odd = dayIndex % 2 === 1;
    if (tone === 'header') {
        return odd
            ? 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    }
    if (tone === 'footer') {
        return odd
            ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white'
            : 'bg-white text-slate-900 dark:bg-slate-900 dark:text-white';
    }
    return odd
        ? 'bg-slate-100/80 dark:bg-slate-800/60'
        : 'bg-white dark:bg-slate-900';
}

/** Sanitize a potentially NaN/undefined hour value to a safe number ≥ 0. */
function safeHours(val: unknown): number {
    const n = Number(val);
    return isNaN(n) || n < 0 ? 0 : n;
}

function formatCell(val: number): string {
    if (!val || val === 0) {
        return '';
    }
    if (Number.isInteger(val)) {
        return String(val);
    }
    return String(Math.round(val * 100) / 100);
}

function formatMonitor(val: number): string {
    if (!val || Math.abs(val) < 0.001) return '–';
    return val.toFixed(1);
}

function draftKey(empId: number, date: string, key: CategoryKey): string {
    return `${empId}|${date}|${key}`;
}

function getDisplayValue(
    empId: number,
    date: string,
    key: CategoryKey,
): string {
    const dk = draftKey(empId, date, key);
    if (dk in draftInputs.value) {
        return draftInputs.value[dk];
    }
    return formatCell(safeHours(gridData.value[empId]?.[date]?.[key]));
}

function setCellValue(
    empId: number,
    date: string,
    key: CategoryKey,
    value: number,
): void {
    if (!gridData.value[empId]) {
        gridData.value[empId] = {};
    }
    if (!gridData.value[empId][date]) {
        gridData.value[empId][date] = emptyCell();
    }
    gridData.value[empId][date][key] = safeHours(value);
    const dk = draftKey(empId, date, key);
    delete draftInputs.value[dk];
}

function onCellInput(
    empId: number,
    date: string,
    key: CategoryKey,
    raw: string,
): void {
    draftInputs.value[draftKey(empId, date, key)] = raw;
}

function onCellBlur(empId: number, date: string, key: CategoryKey): void {
    const dk = draftKey(empId, date, key);
    const raw = draftInputs.value[dk];
    if (raw === undefined) return;
    const cleaned = raw.trim().replace(',', '.');
    if (cleaned === '' || cleaned === '.') {
        setCellValue(empId, date, key, 0);
        return;
    }
    setCellValue(empId, date, key, Number(cleaned));
}

function cellHasValue(empId: number, date: string, key: CategoryKey): boolean {
    return safeHours(gridData.value[empId]?.[date]?.[key]) > 0;
}

function totalForEmployee(empId: number): number {
    const empData = gridData.value[empId];
    if (!empData) return 0;
    return Object.values(empData).reduce((sum, cell) => {
        return (
            sum +
            safeHours(cell.hours_production) +
            safeHours(cell.hours_tpm) +
            safeHours(cell.hours_project) +
            safeHours(cell.hours_others)
        );
    }, 0);
}

function conversiIdxForEmployee(empId: number): number {
    const empData = gridData.value[empId];
    if (!empData) return 0;
    let total = 0;
    props.calendar_days.forEach((d) => {
        const cell = empData[d.date];
        if (!cell) return;
        const hours =
            safeHours(cell.hours_production) +
            safeHours(cell.hours_tpm) +
            safeHours(cell.hours_project) +
            safeHours(cell.hours_others);
        total += hours * dayMultiplier(d.day_type);
    });
    return total;
}

function weekCategoryHours(
    empId: number,
    week: number,
    key: CategoryKey,
): number {
    const empData = gridData.value[empId];
    if (!empData) return 0;
    return props.calendar_days.reduce((sum, d) => {
        if (weekOfDay(d.day) !== week) return sum;
        return sum + safeHours(empData[d.date]?.[key]);
    }, 0);
}

function weekTotalHours(empId: number, week: number): number {
    return CATEGORIES.reduce(
        (sum, cat) => sum + weekCategoryHours(empId, week, cat.key),
        0,
    );
}

function gtHour(empId: number): number {
    return WEEK_NUMBERS.reduce((sum, w) => sum + weekTotalHours(empId, w), 0);
}

function planWeekIndex(empId: number, week: number): number {
    const empData = gridData.value[empId];
    if (!empData) return 0;
    return props.calendar_days.reduce((sum, d) => {
        if (weekOfDay(d.day) !== week) return sum;
        const cell = empData[d.date];
        if (!cell) return sum;
        const hours =
            safeHours(cell.hours_production) +
            safeHours(cell.hours_tpm) +
            safeHours(cell.hours_project) +
            safeHours(cell.hours_others);
        return sum + hours * dayMultiplier(d.day_type);
    }, 0);
}

function actualWeekIndex(empId: number, week: number): number {
    return safeHours(props.actuals?.[empId]?.weeks?.[week]?.index_total);
}

function planGtIndex(empId: number): number {
    return WEEK_NUMBERS.reduce((sum, w) => sum + planWeekIndex(empId, w), 0);
}

function actualGtIndex(empId: number): number {
    return WEEK_NUMBERS.reduce((sum, w) => sum + actualWeekIndex(empId, w), 0);
}

function dayTotal(date: string): number {
    return Object.values(gridData.value).reduce((sum, empData) => {
        const cell = empData[date];
        if (!cell) return sum;
        return (
            sum +
            safeHours(cell.hours_production) +
            safeHours(cell.hours_tpm) +
            safeHours(cell.hours_project) +
            safeHours(cell.hours_others)
        );
    }, 0);
}

function grandTotal(): number {
    return roster.value.reduce((sum, emp) => sum + totalForEmployee(emp.id), 0);
}

function buildItems(): PlanItemData[] {
    const items: PlanItemData[] = [];
    roster.value.forEach((emp) => {
        props.calendar_days.forEach((d) => {
            const cell = gridData.value[emp.id]?.[d.date];
            if (!cell) return;
            const total =
                cell.hours_production +
                cell.hours_tpm +
                cell.hours_project +
                cell.hours_others;
            if (total > 0) {
                items.push({
                    employee_id: emp.id,
                    plan_date: d.date,
                    hours_production: cell.hours_production,
                    hours_tpm: cell.hours_tpm,
                    hours_project: cell.hours_project,
                    hours_others: cell.hours_others,
                });
            }
        });
    });
    return items;
}

// -------------------------------------------------------
// Section change — full Inertia reload (roster + actuals + plan)
// -------------------------------------------------------
watch(selectedSectionId, (newId, oldId) => {
    const id = Number(newId);
    const previousId = Number(oldId);

    if (!id || id <= 0 || id === previousId) {
        return;
    }

    if (id === props.selected_section_id) {
        return;
    }

    router.get(
        planningCreate(),
        {
            fiscal_year: props.fiscal_year,
            fiscal_month: props.fiscal_month,
            section_id: id,
        },
        { preserveState: false },
    );
});

// -------------------------------------------------------
// Form submission
// -------------------------------------------------------
const form = useForm<{
    section_id: number | null;
    department_id: number | null;
    fiscal_year: number;
    fiscal_month: number;
    notes: string;
    items: PlanItemData[];
}>({
    section_id: props.selected_section_id,
    department_id: props.selected_department_id,
    fiscal_year: props.fiscal_year,
    fiscal_month: props.fiscal_month,
    notes: props.existing_plan?.notes ?? '',
    items: [],
});

function submitPlan() {
    form.items = buildItems();
    form.section_id = selectedSectionId.value;
    form.department_id = selectedDepartmentId.value;

    if (props.existing_plan) {
        form.put(planningUpdate.url(props.existing_plan.id), {
            preserveScroll: true,
        });
    } else {
        form.post(planningStore.url(), {
            preserveScroll: true,
        });
    }
}

function publishPlan() {
    if (!props.existing_plan) return;
    router.patch(
        planningPublish.url(props.existing_plan.id),
        {},
        {
            preserveScroll: true,
        },
    );
}

function goToPeriod(year: number, month: number) {
    if (year === props.fiscal_year && month === props.fiscal_month) {
        return;
    }
    router.get(
        planningCreate(),
        {
            fiscal_year: year,
            fiscal_month: month,
            section_id: selectedSectionId.value,
        },
        { preserveState: false },
    );
}

function onYearChange(value: unknown) {
    goToPeriod(Number(value), props.fiscal_month);
}

function onMonthChange(value: unknown) {
    goToPeriod(props.fiscal_year, Number(value));
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col p-4 sm:p-6">
        <Head :title="__('Planning Overtime')" />

        <!-- Header -->
        <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="__('Planning Overtime')"
                :description="
                    __(
                        'Susun rencana lembur bulanan per seksi berdasarkan kategori A/B/C/D.',
                    )
                "
            />
        </div>

        <!-- Filters row -->
        <div class="mb-3 flex flex-wrap items-end gap-3">
            <div class="w-32">
                <label
                    class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300"
                    >{{ __('Tahun') }}</label
                >
                <Select
                    :model-value="fiscal_year"
                    @update:model-value="onYearChange"
                >
                    <SelectTrigger class="h-9 text-xs" data-test="period-year">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="y in years" :key="y" :value="y">{{
                            y
                        }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="w-40">
                <label
                    class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300"
                    >{{ __('Bulan') }}</label
                >
                <Select
                    :model-value="fiscal_month"
                    @update:model-value="onMonthChange"
                >
                    <SelectTrigger class="h-9 text-xs" data-test="period-month">
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
            <div class="w-44">
                <label
                    class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300"
                    >{{ __('Departemen') }}</label
                >
                <Select
                    v-model="selectedDepartmentId"
                    @update:model-value="
                        (v) => {
                            selectedDepartmentId = Number(v);
                            const sec = props.sections.find(
                                (s) => s.id === selectedSectionId,
                            );
                            if (sec && sec.department_id !== Number(v)) {
                                selectedSectionId = null;
                            }
                        }
                    "
                >
                    <SelectTrigger class="h-9 text-xs">
                        <SelectValue :placeholder="__('Departemen')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="dept in departments"
                            :key="dept.id"
                            :value="dept.id"
                        >
                            {{ dept.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="w-44">
                <label
                    class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300"
                    >{{ __('Pilih Seksi') }}</label
                >
                <Select v-model="selectedSectionId">
                    <SelectTrigger class="h-9 text-xs">
                        <SelectValue :placeholder="__('Pilih Seksi')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="sec in filteredSections"
                            :key="sec.id"
                            :value="sec.id"
                        >
                            {{ sec.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="ml-auto flex items-center gap-2 self-center pt-4">
                <Badge
                    v-if="existing_plan"
                    :class="
                        existing_plan.status === 'PUBLISHED'
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            : 'border-amber-200 bg-amber-50 text-amber-700'
                    "
                    variant="outline"
                >
                    {{
                        existing_plan.status === 'PUBLISHED'
                            ? __('Dipublikasikan')
                            : __('Draft')
                    }}
                </Badge>
                <span
                    v-if="existing_plan"
                    class="font-mono text-xs text-slate-500"
                >
                    {{ existing_plan.plan_code }}
                </span>
            </div>
        </div>

        <!-- Legend + keyboard hint -->
        <div
            class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px]"
        >
            <div
                v-for="cat in CATEGORIES"
                :key="cat.key"
                class="flex items-center gap-1"
            >
                <span
                    class="inline-flex size-4 items-center justify-center rounded bg-slate-100 font-mono text-[9px] font-bold dark:bg-slate-800"
                    :class="cat.color"
                    >{{ cat.letter }}</span
                >
                <span :class="cat.color" class="font-semibold">{{
                    cat.label
                }}</span>
            </div>
            <span class="text-slate-400">
                {{
                    __(
                        'Navigasi: Tab / panah untuk pindah sel, ketik langsung untuk mengisi.',
                    )
                }}
            </span>
        </div>

        <!-- Empty / loading states -->
        <div
            v-if="!selectedSectionId"
            class="mb-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900"
        >
            <Users class="mx-auto mb-2 size-8 opacity-40" />
            {{ __('Pilih seksi untuk memuat daftar karyawan.') }}
        </div>

        <div
            v-else-if="roster.length === 0"
            class="mb-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900"
        >
            {{ __('Tidak ada karyawan aktif di seksi ini.') }}
        </div>

        <!-- Excel-parity Planning Grid (entry only: days 1–N) -->
        <template v-else>
            <div
                ref="gridRoot"
                data-test="planning-spreadsheet"
                class="mb-4 overflow-hidden rounded-lg border border-slate-300 bg-white shadow-xs dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="max-h-[55vh] overflow-auto pb-4">
                    <table
                        class="w-max min-w-full border-collapse text-left text-[10px]"
                    >
                        <thead class="sticky top-0 z-20">
                            <tr
                                class="border-b border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800"
                            >
                                <th
                                    class="sticky left-0 z-30 min-w-[36px] border-r border-slate-200 bg-slate-100 px-1 py-1 text-center font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800"
                                    rowspan="2"
                                >
                                    {{ __('NO') }}
                                </th>
                                <th
                                    class="sticky left-[36px] z-30 min-w-[140px] border-r border-slate-200 bg-slate-100 px-2 py-1 text-left font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800"
                                    rowspan="2"
                                >
                                    {{ __('NAMA') }}
                                </th>
                                <th
                                    class="sticky left-[176px] z-30 min-w-[56px] border-r-2 border-slate-400 bg-slate-100 px-1 py-1 text-center font-semibold text-slate-500 dark:border-slate-500 dark:bg-slate-800"
                                    rowspan="2"
                                >
                                    {{ __('NPK') }}
                                </th>
                                <th
                                    v-for="(d, dayIndex) in calendar_days"
                                    :key="'day-' + d.date"
                                    :colspan="4"
                                    class="border-r-2 border-slate-400 px-0.5 py-1 text-center font-bold dark:border-slate-500"
                                    :class="
                                        dayBandClass(
                                            dayIndex,
                                            d.day_type,
                                            'header',
                                        )
                                    "
                                >
                                    <div>{{ d.day }}</div>
                                    <div
                                        class="text-[8px] font-normal opacity-70"
                                    >
                                        {{ d.day_name }} · {{ d.day_type }}
                                    </div>
                                </th>
                                <th
                                    class="min-w-[52px] border-l-2 border-slate-400 bg-emerald-50 px-1 py-1 text-center font-semibold text-emerald-800 dark:border-slate-500 dark:bg-emerald-950/40 dark:text-emerald-300"
                                    rowspan="2"
                                    data-test="col-total-jam"
                                >
                                    {{ __('Total Jam') }}
                                </th>
                            </tr>
                            <tr
                                class="border-b border-slate-300 bg-slate-50 dark:border-slate-600 dark:bg-slate-900"
                            >
                                <template
                                    v-for="(d, dayIndex) in calendar_days"
                                    :key="'sub-' + d.date"
                                >
                                    <th
                                        v-for="(cat, catIndex) in CATEGORIES"
                                        :key="d.date + cat.key"
                                        class="w-[28px] min-w-[28px] px-0 py-0.5 text-center font-bold"
                                        :class="[
                                            cat.color,
                                            dayCategoryBorderClass(catIndex),
                                            dayBandClass(
                                                dayIndex,
                                                d.day_type,
                                                'sub',
                                            ),
                                        ]"
                                    >
                                        {{ cat.letter }}
                                    </th>
                                </template>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="(emp, empIndex) in roster"
                                :key="emp.id"
                                class="border-b border-slate-100 hover:bg-slate-50/50 dark:border-slate-800 dark:hover:bg-slate-800/40"
                            >
                                <td
                                    class="sticky left-0 z-10 border-r border-slate-200 bg-white px-1 py-0.5 text-center font-mono text-slate-400 dark:border-slate-700 dark:bg-slate-900"
                                >
                                    {{ empIndex + 1 }}
                                </td>
                                <td
                                    class="sticky left-[36px] z-10 max-w-[140px] truncate border-r border-slate-200 bg-white px-2 py-0.5 font-semibold text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    :title="emp.full_name"
                                >
                                    {{ emp.full_name }}
                                </td>
                                <td
                                    class="sticky left-[176px] z-10 border-r-2 border-slate-400 bg-white px-1 py-0.5 text-center font-mono text-slate-500 dark:border-slate-500 dark:bg-slate-900"
                                >
                                    {{ emp.npk }}
                                </td>

                                <template
                                    v-for="(d, dayIndex) in calendar_days"
                                    :key="emp.id + '-' + d.date"
                                >
                                    <td
                                        v-for="(cat, catIndex) in CATEGORIES"
                                        :key="d.date + cat.key"
                                        class="p-0"
                                        :class="[
                                            dayCategoryBorderClass(catIndex),
                                            dayBandClass(
                                                dayIndex,
                                                d.day_type,
                                                'cell',
                                            ),
                                        ]"
                                    >
                                        <input
                                            :id="
                                                cellInputId(
                                                    empIndex,
                                                    dayCatToFlat(
                                                        dayIndex,
                                                        catIndex,
                                                    ),
                                                )
                                            "
                                            type="text"
                                            inputmode="decimal"
                                            autocomplete="off"
                                            data-test="plan-cell-input"
                                            :readonly="isPublished"
                                            class="h-7 w-[28px] border-0 bg-transparent text-center font-mono text-[10px] tabular-nums outline-none focus:ring-1 focus:ring-[#cc0000] focus:ring-inset"
                                            :class="[
                                                cellHasValue(
                                                    emp.id,
                                                    d.date,
                                                    cat.key,
                                                )
                                                    ? 'bg-emerald-100 font-bold text-slate-900 dark:bg-emerald-900/40 dark:text-white'
                                                    : 'text-slate-400',
                                                isPublished
                                                    ? 'cursor-not-allowed opacity-80'
                                                    : '',
                                            ]"
                                            :value="
                                                getDisplayValue(
                                                    emp.id,
                                                    d.date,
                                                    cat.key,
                                                )
                                            "
                                            @focus="
                                                (
                                                    $event.target as HTMLInputElement
                                                ).select()
                                            "
                                            @input="
                                                onCellInput(
                                                    emp.id,
                                                    d.date,
                                                    cat.key,
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                            @blur="
                                                onCellBlur(
                                                    emp.id,
                                                    d.date,
                                                    cat.key,
                                                )
                                            "
                                            @keydown="
                                                handleKeydown(
                                                    $event,
                                                    empIndex,
                                                    dayCatToFlat(
                                                        dayIndex,
                                                        catIndex,
                                                    ),
                                                )
                                            "
                                        />
                                    </td>
                                </template>

                                <td
                                    class="bg-emerald-50/60 px-1 text-right font-mono font-bold text-slate-900 tabular-nums dark:bg-emerald-950/30 dark:text-white"
                                >
                                    {{
                                        formatMonitor(totalForEmployee(emp.id))
                                    }}
                                </td>
                            </tr>
                        </tbody>

                        <tfoot>
                            <tr
                                class="sticky bottom-0 border-t-2 border-slate-300 bg-slate-100 font-semibold dark:border-slate-600 dark:bg-slate-800"
                            >
                                <td
                                    colspan="3"
                                    class="sticky left-0 z-10 border-r-2 border-slate-400 bg-slate-100 px-2 py-1 text-[11px] text-slate-600 dark:border-slate-500 dark:bg-slate-800"
                                >
                                    {{ __('Total Hari') }}
                                </td>
                                <template
                                    v-for="(d, dayIndex) in calendar_days"
                                    :key="'ft-' + d.date"
                                >
                                    <td
                                        :colspan="4"
                                        class="border-r-2 border-slate-400 px-0.5 py-1 text-center font-mono text-[10px] tabular-nums dark:border-slate-500"
                                        :class="[
                                            dayBandClass(
                                                dayIndex,
                                                d.day_type,
                                                'footer',
                                            ),
                                            dayTotal(d.date) > 0
                                                ? ''
                                                : 'text-slate-300 dark:text-slate-600',
                                        ]"
                                    >
                                        {{
                                            dayTotal(d.date) > 0
                                                ? dayTotal(d.date).toFixed(1)
                                                : '–'
                                        }}
                                    </td>
                                </template>
                                <td
                                    class="border-l-2 border-slate-400 bg-emerald-100 px-1 text-right font-mono font-bold text-[#cc0000] tabular-nums dark:border-slate-500 dark:bg-emerald-950/50"
                                >
                                    {{ grandTotal().toFixed(1) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Monitoring summary (separate from day entry grid) -->
            <div
                class="mb-4 overflow-hidden rounded-lg border border-slate-300 bg-white shadow-xs dark:border-slate-700 dark:bg-slate-900"
                data-test="planning-monitoring"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-3 py-2 dark:border-slate-700"
                >
                    <h3
                        class="text-xs font-semibold tracking-wide text-slate-700 uppercase dark:text-slate-200"
                    >
                        {{ __('Monitoring Ringkasan') }}
                    </h3>
                    <div
                        class="inline-flex rounded-md border border-slate-200 bg-slate-50 p-0.5 dark:border-slate-700 dark:bg-slate-800"
                        role="tablist"
                    >
                        <button
                            type="button"
                            role="tab"
                            data-test="tab-monitor-weekly"
                            class="rounded px-2.5 py-1 text-[11px] font-semibold transition-colors"
                            :class="
                                monitorTab === 'weekly'
                                    ? 'bg-white text-[#cc0000] shadow-xs dark:bg-slate-900'
                                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'
                            "
                            @click="monitorTab = 'weekly'"
                        >
                            {{ __('Jam Mingguan') }}
                        </button>
                        <button
                            type="button"
                            role="tab"
                            data-test="tab-monitor-pva"
                            class="rounded px-2.5 py-1 text-[11px] font-semibold transition-colors"
                            :class="
                                monitorTab === 'plan_vs_actual'
                                    ? 'bg-white text-[#cc0000] shadow-xs dark:bg-slate-900'
                                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'
                            "
                            @click="monitorTab = 'plan_vs_actual'"
                        >
                            {{ __('Plan vs Actual') }}
                        </button>
                    </div>
                </div>

                <!-- Tab: Weekly hours + Conversi Idx -->
                <div
                    v-show="monitorTab === 'weekly'"
                    class="max-h-[40vh] overflow-auto"
                    data-test="monitor-weekly-panel"
                >
                    <table
                        class="w-max min-w-full border-collapse text-left text-[10px]"
                    >
                        <thead class="sticky top-0 z-10">
                            <tr
                                class="border-b border-slate-200 bg-sky-50 dark:border-slate-700 dark:bg-sky-950/40"
                            >
                                <th
                                    class="sticky left-0 z-20 min-w-[36px] border-r border-slate-200 bg-sky-50 px-1 py-1 text-center font-semibold text-slate-500 dark:border-slate-700 dark:bg-sky-950/40"
                                    rowspan="2"
                                >
                                    {{ __('NO') }}
                                </th>
                                <th
                                    class="sticky left-[36px] z-20 min-w-[140px] border-r border-slate-200 bg-sky-50 px-2 py-1 font-semibold text-slate-500 dark:border-slate-700 dark:bg-sky-950/40"
                                    rowspan="2"
                                >
                                    {{ __('NAMA') }}
                                </th>
                                <th
                                    class="sticky left-[176px] z-20 min-w-[56px] border-r border-slate-300 bg-sky-50 px-1 py-1 text-center font-semibold text-slate-500 dark:border-slate-600 dark:bg-sky-950/40"
                                    rowspan="2"
                                >
                                    {{ __('NPK') }}
                                </th>
                                <th
                                    class="min-w-[52px] border-r border-emerald-200 bg-emerald-50 px-1 py-1 text-center font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
                                    rowspan="2"
                                    data-test="col-conversi-idx"
                                >
                                    {{ __('Conversi Idx') }}
                                </th>
                                <template
                                    v-for="w in WEEK_NUMBERS"
                                    :key="'mwh-' + w"
                                >
                                    <th
                                        :colspan="5"
                                        class="border-r border-sky-200 px-0.5 py-1 text-center font-semibold text-sky-800 dark:border-sky-900 dark:text-sky-300"
                                        :data-test="
                                            w === 1
                                                ? 'col-week-hours'
                                                : undefined
                                        "
                                    >
                                        {{ __('W:week', { week: w }) }}
                                    </th>
                                </template>
                                <th
                                    class="min-w-[48px] bg-sky-100 px-1 py-1 text-center font-bold text-sky-900 dark:bg-sky-950/60 dark:text-sky-200"
                                    rowspan="2"
                                    data-test="col-gt-hour"
                                >
                                    {{ __('GT HOUR') }}
                                </th>
                            </tr>
                            <tr
                                class="border-b border-slate-300 bg-sky-50/80 dark:border-slate-600 dark:bg-sky-950/30"
                            >
                                <template
                                    v-for="w in WEEK_NUMBERS"
                                    :key="'mwsub-' + w"
                                >
                                    <th
                                        v-for="cat in CATEGORIES"
                                        :key="'mw' + w + cat.key"
                                        class="w-[28px] min-w-[28px] border-r border-sky-100 px-0 py-0.5 text-center font-bold dark:border-sky-900"
                                        :class="cat.color"
                                    >
                                        {{ cat.letter }}
                                    </th>
                                    <th
                                        class="w-[32px] min-w-[32px] border-r border-sky-200 bg-sky-100 px-0 py-0.5 text-center font-semibold text-sky-700 dark:border-sky-800 dark:bg-sky-950/50 dark:text-sky-300"
                                    >
                                        Σ
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(emp, empIndex) in roster"
                                :key="'mw-' + emp.id"
                                class="border-b border-slate-100 dark:border-slate-800"
                            >
                                <td
                                    class="sticky left-0 z-10 border-r border-slate-200 bg-white px-1 py-0.5 text-center font-mono text-slate-400 dark:border-slate-700 dark:bg-slate-900"
                                >
                                    {{ empIndex + 1 }}
                                </td>
                                <td
                                    class="sticky left-[36px] z-10 max-w-[140px] truncate border-r border-slate-200 bg-white px-2 py-0.5 font-semibold text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                >
                                    {{ emp.full_name }}
                                </td>
                                <td
                                    class="sticky left-[176px] z-10 border-r border-slate-300 bg-white px-1 py-0.5 text-center font-mono text-slate-500 dark:border-slate-600 dark:bg-slate-900"
                                >
                                    {{ emp.npk }}
                                </td>
                                <td
                                    class="border-r border-emerald-200 bg-emerald-50/60 px-1 text-right font-mono font-bold text-emerald-800 tabular-nums dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300"
                                >
                                    {{
                                        formatMonitor(
                                            conversiIdxForEmployee(emp.id),
                                        )
                                    }}
                                </td>
                                <template
                                    v-for="w in WEEK_NUMBERS"
                                    :key="'mwhr-' + emp.id + '-' + w"
                                >
                                    <td
                                        v-for="cat in CATEGORIES"
                                        :key="'mwhc-' + w + cat.key"
                                        class="border-r border-sky-100 px-0.5 text-center font-mono text-slate-600 tabular-nums dark:border-sky-900 dark:text-slate-300"
                                    >
                                        {{
                                            formatMonitor(
                                                weekCategoryHours(
                                                    emp.id,
                                                    w,
                                                    cat.key,
                                                ),
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="border-r border-sky-200 bg-sky-100/70 px-0.5 text-center font-mono font-bold text-sky-900 tabular-nums dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200"
                                    >
                                        {{
                                            formatMonitor(
                                                weekTotalHours(emp.id, w),
                                            )
                                        }}
                                    </td>
                                </template>
                                <td
                                    class="bg-sky-100 px-1 text-right font-mono font-bold text-sky-950 tabular-nums dark:bg-sky-950/50 dark:text-sky-100"
                                >
                                    {{ formatMonitor(gtHour(emp.id)) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Tab: Plan vs Actual index -->
                <div
                    v-show="monitorTab === 'plan_vs_actual'"
                    class="max-h-[40vh] overflow-auto"
                    data-test="monitor-pva-panel"
                >
                    <table
                        class="w-max min-w-full border-collapse text-left text-[10px]"
                    >
                        <thead class="sticky top-0 z-10">
                            <tr
                                class="border-b border-slate-200 bg-violet-50 dark:border-slate-700 dark:bg-violet-950/40"
                            >
                                <th
                                    class="sticky left-0 z-20 min-w-[36px] border-r border-slate-200 bg-violet-50 px-1 py-1 text-center font-semibold text-slate-500 dark:border-slate-700 dark:bg-violet-950/40"
                                    rowspan="2"
                                >
                                    {{ __('NO') }}
                                </th>
                                <th
                                    class="sticky left-[36px] z-20 min-w-[140px] border-r border-slate-200 bg-violet-50 px-2 py-1 font-semibold text-slate-500 dark:border-slate-700 dark:bg-violet-950/40"
                                    rowspan="2"
                                >
                                    {{ __('NAMA') }}
                                </th>
                                <th
                                    class="sticky left-[176px] z-20 min-w-[56px] border-r border-slate-300 bg-violet-50 px-1 py-1 text-center font-semibold text-slate-500 dark:border-slate-600 dark:bg-violet-950/40"
                                    rowspan="2"
                                >
                                    {{ __('NPK') }}
                                </th>
                                <template
                                    v-for="w in WEEK_NUMBERS"
                                    :key="'mpva-' + w"
                                >
                                    <th
                                        :colspan="2"
                                        class="border-r border-violet-200 px-0.5 py-1 text-center font-semibold text-violet-800 dark:border-violet-900 dark:text-violet-300"
                                        :data-test="
                                            w === 1
                                                ? 'col-plan-vs-actual'
                                                : undefined
                                        "
                                    >
                                        {{ __('Idx W:week', { week: w }) }}
                                    </th>
                                </template>
                                <th
                                    :colspan="2"
                                    class="bg-violet-100 px-0.5 py-1 text-center font-bold text-violet-900 dark:bg-violet-950/60 dark:text-violet-200"
                                >
                                    {{ __('GT Idx') }}
                                </th>
                            </tr>
                            <tr
                                class="border-b border-slate-300 bg-violet-50/80 dark:border-slate-600 dark:bg-violet-950/30"
                            >
                                <template
                                    v-for="w in WEEK_NUMBERS"
                                    :key="'mpvasub-' + w"
                                >
                                    <th
                                        class="w-[40px] min-w-[40px] border-r border-violet-100 px-0 py-0.5 text-center font-bold text-violet-700 dark:border-violet-900"
                                    >
                                        P
                                    </th>
                                    <th
                                        class="w-[40px] min-w-[40px] border-r border-violet-200 px-0 py-0.5 text-center font-bold text-violet-700 dark:border-violet-800"
                                    >
                                        A
                                    </th>
                                </template>
                                <th
                                    class="w-[40px] min-w-[40px] border-r border-violet-200 bg-violet-100 px-0 py-0.5 text-center font-bold text-violet-800 dark:border-violet-800 dark:bg-violet-950/50"
                                >
                                    P
                                </th>
                                <th
                                    class="w-[40px] min-w-[40px] bg-violet-100 px-0 py-0.5 text-center font-bold text-violet-800 dark:bg-violet-950/50"
                                >
                                    A
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(emp, empIndex) in roster"
                                :key="'mpva-' + emp.id"
                                class="border-b border-slate-100 dark:border-slate-800"
                            >
                                <td
                                    class="sticky left-0 z-10 border-r border-slate-200 bg-white px-1 py-0.5 text-center font-mono text-slate-400 dark:border-slate-700 dark:bg-slate-900"
                                >
                                    {{ empIndex + 1 }}
                                </td>
                                <td
                                    class="sticky left-[36px] z-10 max-w-[140px] truncate border-r border-slate-200 bg-white px-2 py-0.5 font-semibold text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                >
                                    {{ emp.full_name }}
                                </td>
                                <td
                                    class="sticky left-[176px] z-10 border-r border-slate-300 bg-white px-1 py-0.5 text-center font-mono text-slate-500 dark:border-slate-600 dark:bg-slate-900"
                                >
                                    {{ emp.npk }}
                                </td>
                                <template
                                    v-for="w in WEEK_NUMBERS"
                                    :key="'mpvar-' + emp.id + '-' + w"
                                >
                                    <td
                                        class="border-r border-violet-100 px-0.5 text-center font-mono text-violet-800 tabular-nums dark:border-violet-900 dark:text-violet-300"
                                    >
                                        {{
                                            formatMonitor(
                                                planWeekIndex(emp.id, w),
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="border-r border-violet-200 px-0.5 text-center font-mono text-violet-700 tabular-nums dark:border-violet-800 dark:text-violet-400"
                                    >
                                        {{
                                            formatMonitor(
                                                actualWeekIndex(emp.id, w),
                                            )
                                        }}
                                    </td>
                                </template>
                                <td
                                    class="border-r border-violet-200 bg-violet-100/80 px-0.5 text-center font-mono font-bold text-violet-900 tabular-nums dark:border-violet-800 dark:bg-violet-950/40"
                                >
                                    {{ formatMonitor(planGtIndex(emp.id)) }}
                                </td>
                                <td
                                    class="bg-violet-100/80 px-0.5 text-center font-mono font-bold text-violet-900 tabular-nums dark:bg-violet-950/40"
                                >
                                    {{ formatMonitor(actualGtIndex(emp.id)) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <!-- Notes + Actions -->
        <div
            v-if="selectedSectionId && roster.length > 0"
            class="flex flex-wrap items-start gap-4"
        >
            <div class="min-w-[240px] flex-1">
                <label
                    class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300"
                >
                    {{ __('Catatan Planning') }}
                </label>
                <textarea
                    v-model="form.notes"
                    rows="2"
                    :readonly="isPublished"
                    :placeholder="
                        __('Catatan opsional untuk planning bulan ini...')
                    "
                    class="w-full resize-none rounded-md border border-slate-300 bg-white px-3 py-2 text-xs focus:border-[#cc0000] focus:ring-1 focus:ring-[#cc0000] focus:outline-none dark:border-slate-700 dark:bg-slate-800"
                    :class="isPublished ? 'cursor-not-allowed opacity-80' : ''"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-5">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="submitPlan()"
                    :disabled="form.processing || isPublished"
                >
                    <Save class="mr-1 size-4" />
                    {{ __('Simpan Draft') }}
                </Button>

                <Button
                    v-if="existing_plan && existing_plan.status === 'DRAFT'"
                    type="button"
                    size="sm"
                    class="bg-emerald-600 text-white hover:bg-emerald-700"
                    @click="publishPlan"
                    :disabled="form.processing"
                >
                    <Send class="mr-1 size-4" />
                    {{ __('Publikasikan') }}
                </Button>
            </div>
        </div>
    </div>
</template>
