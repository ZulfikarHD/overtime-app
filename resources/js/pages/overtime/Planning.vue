<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Calendar,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Download,
    FileCheck,
    ListPlus,
    RefreshCw,
    Save,
    Send,
    Users,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    index as planningIndex,
    create as planningCreate,
    store as planningStore,
    update as planningUpdate,
    publish as planningPublish,
} from '@/routes/overtime/planning';

const { __ } = useTrans();

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
// State
// -------------------------------------------------------
const selectedSectionId = ref<number | null>(props.selected_section_id);
const selectedDepartmentId = ref<number | null>(props.selected_department_id);
const roster = ref<RosterEmployee[]>(props.initial_roster);

// Category keys
type CategoryKey =
    | 'hours_production'
    | 'hours_tpm'
    | 'hours_project'
    | 'hours_others';
const CATEGORIES: { key: CategoryKey; label: string; color: string }[] = [
    { key: 'hours_production', label: 'Prod', color: 'text-blue-600' },
    { key: 'hours_tpm', label: 'TPM', color: 'text-amber-600' },
    { key: 'hours_project', label: 'Proj', color: 'text-emerald-600' },
    { key: 'hours_others', label: 'Lain', color: 'text-slate-500' },
];

// Build the grid data: { [employeeId]: { [date]: { prod, tpm, proj, others } } }
type CellData = {
    hours_production: number;
    hours_tpm: number;
    hours_project: number;
    hours_others: number;
};
const gridData = ref<Record<number, Record<string, CellData>>>({});

// Initialize grid from existing plan items
function initGrid() {
    const data: Record<number, Record<string, CellData>> = {};

    roster.value.forEach((emp) => {
        data[emp.id] = {};
        props.calendar_days.forEach((d) => {
            data[emp.id][d.date] = {
                hours_production: 0,
                hours_tpm: 0,
                hours_project: 0,
                hours_others: 0,
            };
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
}

initGrid();

// Active category for quick-entry (click on day header selects category)
const activeCategory = ref<CategoryKey>('hours_production');
const showCategoryBreakdown = ref(false);

// -------------------------------------------------------
// Computed
// -------------------------------------------------------
const filteredSections = computed(() =>
    props.sections.filter(
        (s) =>
            !selectedDepartmentId.value ||
            s.department_id === selectedDepartmentId.value,
    ),
);

const monthName = computed(() => {
    return new Date(
        props.fiscal_year,
        props.fiscal_month - 1,
        1,
    ).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
});

function totalForEmployee(empId: number): number {
    const empData = gridData.value[empId];
    if (!empData) return 0;
    return Object.values(empData).reduce((sum, cell) => {
        return (
            sum +
            cell.hours_production +
            cell.hours_tpm +
            cell.hours_project +
            cell.hours_others
        );
    }, 0);
}

function categoryTotalForEmployee(empId: number, key: CategoryKey): number {
    const empData = gridData.value[empId];
    if (!empData) return 0;
    return Object.values(empData).reduce(
        (sum, cell) => sum + (cell[key] || 0),
        0,
    );
}

function dayTotal(date: string): number {
    return Object.values(gridData.value).reduce((sum, empData) => {
        const cell = empData[date];
        if (!cell) return sum;
        return (
            sum +
            cell.hours_production +
            cell.hours_tpm +
            cell.hours_project +
            cell.hours_others
        );
    }, 0);
}

function grandTotal(): number {
    return roster.value.reduce((sum, emp) => sum + totalForEmployee(emp.id), 0);
}

// Convert grid to flat items for submission
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
// Roster loading
// -------------------------------------------------------
const rosterLoading = ref(false);

async function loadRoster(sectionId: number) {
    rosterLoading.value = true;
    try {
        const res = await fetch(`/overtime/planning/roster/${sectionId}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const json = await res.json();
        roster.value = json.employees ?? [];
        // Re-init grid for new roster
        const data: Record<number, Record<string, CellData>> = {};
        roster.value.forEach((emp) => {
            data[emp.id] = {};
            props.calendar_days.forEach((d) => {
                data[emp.id][d.date] = {
                    hours_production: 0,
                    hours_tpm: 0,
                    hours_project: 0,
                    hours_others: 0,
                };
            });
        });
        gridData.value = data;
    } finally {
        rosterLoading.value = false;
    }
}

watch(selectedSectionId, (newId) => {
    if (newId) {
        loadRoster(newId);
    }
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

function submitPlan(status?: 'PUBLISHED') {
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

// -------------------------------------------------------
// Month navigation
// -------------------------------------------------------
function navigateMonth(delta: number) {
    let year = props.fiscal_year;
    let month = props.fiscal_month + delta;
    if (month > 12) {
        month = 1;
        year++;
    }
    if (month < 1) {
        month = 12;
        year--;
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

// -------------------------------------------------------
// Cell editing state
// -------------------------------------------------------
const editingCell = ref<{ empId: number; date: string } | null>(null);
const editForm = ref<CellData>({
    hours_production: 0,
    hours_tpm: 0,
    hours_project: 0,
    hours_others: 0,
});
// Ref for the modal card so we can auto-focus the first input on open
const modalRef = ref<HTMLDivElement | null>(null);

function openCell(empId: number, date: string) {
    editingCell.value = { empId, date };
    editForm.value = {
        ...(gridData.value[empId]?.[date] ?? {
            hours_production: 0,
            hours_tpm: 0,
            hours_project: 0,
            hours_others: 0,
        }),
    };
    // Auto-focus + select the first number input so the user can type immediately
    nextTick(() => {
        const first = modalRef.value?.querySelector<HTMLInputElement>(
            'input[type="number"]',
        );
        first?.focus();
        first?.select();
    });
}

function closeCell() {
    if (editingCell.value) {
        const { empId, date } = editingCell.value;
        if (!gridData.value[empId]) gridData.value[empId] = {};
        gridData.value[empId][date] = { ...editForm.value };
    }
    editingCell.value = null;
}

function quickCellValue(empId: number, date: string): number {
    const cell = gridData.value[empId]?.[date];
    if (!cell) return 0;
    return (
        cell.hours_production +
        cell.hours_tpm +
        cell.hours_project +
        cell.hours_others
    );
}

function hasNonZeroCell(empId: number): boolean {
    return Object.values(gridData.value[empId] ?? {}).some(
        (c) =>
            c.hours_production +
                c.hours_tpm +
                c.hours_project +
                c.hours_others >
            0,
    );
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col p-4 sm:p-6">
        <Head :title="__('Planning Overtime')" />

        <!-- Header -->
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="__('Planning Overtime')"
                :description="__('Susun rencana lembur bulanan per seksi berdasarkan kategori A/B/C/D.')"
            />
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" @click="navigateMonth(-1)">
                    <ChevronLeft class="size-4" />
                </Button>
                <span
                    class="min-w-[120px] text-center text-sm font-semibold text-slate-700 capitalize dark:text-slate-200"
                >
                    {{ monthName }}
                </span>
                <Button variant="outline" size="sm" @click="navigateMonth(1)">
                    <ChevronRight class="size-4" />
                </Button>
            </div>
        </div>

        <!-- Filters row -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <!-- Department -->
            <div class="w-44">
                <Select
                    v-model="selectedDepartmentId"
                    @update:model-value="
                        (v) => {
                            selectedDepartmentId = Number(v);
                            selectedSectionId = null;
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
            <!-- Section -->
            <div class="w-44">
                <Select
                    v-model="selectedSectionId"
                    @update:model-value="(v) => (selectedSectionId = Number(v))"
                >
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

            <div class="ml-auto flex items-center gap-2">
                <!-- Status badge -->
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
                <!-- Plan code -->
                <span
                    v-if="existing_plan"
                    class="font-mono text-xs text-slate-500"
                >
                    {{ existing_plan.plan_code }}
                </span>
            </div>
        </div>

        <!-- Legend -->
        <div class="mb-4 flex flex-wrap gap-3 text-[11px]">
            <div
                v-for="cat in CATEGORIES"
                :key="cat.key"
                class="flex items-center gap-1"
            >
                <span
                    class="size-2 rounded-full"
                    :class="cat.color.replace('text-', 'bg-')"
                ></span>
                <span :class="cat.color" class="font-semibold">{{
                    cat.label
                }}</span>
                <span class="text-slate-400">
                    =
                    {{
                        cat.key === 'hours_production'
                            ? 'Produksi (61,62)'
                            : cat.key === 'hours_tpm'
                              ? 'TPM (65,66)'
                              : cat.key === 'hours_project'
                                ? 'Project (67,68)'
                                : 'Lainnya (63,64,69+)'
                    }}
                </span>
            </div>
        </div>

        <!-- No roster warning -->
        <div
            v-if="!selectedSectionId"
            class="mb-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900"
        >
            <Users class="mx-auto mb-2 size-8 opacity-40" />
            {{ __('Pilih seksi untuk memuat daftar karyawan.') }}
        </div>

        <div
            v-else-if="rosterLoading"
            class="mb-4 animate-pulse rounded-lg border border-slate-200 p-8 text-center text-sm text-slate-400"
        >
            {{ __('Memuat karyawan...') }}
        </div>

        <div
            v-else-if="roster.length === 0"
            class="mb-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900"
        >
            {{ __('Tidak ada karyawan aktif di seksi ini.') }}
        </div>

        <!-- Planning Grid -->
        <div
            v-else
            class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="overflow-x-auto">
                <table
                    class="w-full min-w-max border-collapse text-left text-xs"
                >
                    <!-- Header row: day numbers -->
                    <thead>
                        <tr
                            class="dark:bg-slate-850 border-b border-slate-200 bg-slate-50 dark:border-slate-800"
                        >
                            <!-- Employee col -->
                            <th
                                class="dark:bg-slate-850 sticky left-0 z-10 min-w-[180px] border-r border-slate-200 bg-slate-50 p-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase dark:border-slate-700"
                            >
                                <div class="flex items-center gap-1">
                                    <Users class="size-3" />
                                    {{ __('Karyawan') }}
                                </div>
                            </th>
                            <!-- Day columns -->
                            <th
                                v-for="d in calendar_days"
                                :key="d.date"
                                class="min-w-[42px] border-r border-slate-100 p-1.5 text-center text-[11px] font-semibold uppercase dark:border-slate-800"
                                :class="
                                    d.day_type === 'HLR'
                                        ? 'bg-red-50 text-[#cc0000] dark:bg-red-950/30'
                                        : 'text-slate-500'
                                "
                            >
                                <div class="leading-tight">
                                    <div>{{ d.day }}</div>
                                    <div class="font-normal opacity-70">
                                        {{ d.day_name }}
                                    </div>
                                    <div
                                        class="text-[9px]"
                                        :class="
                                            d.day_type === 'HLR'
                                                ? 'text-[#cc0000]'
                                                : 'text-slate-400'
                                        "
                                    >
                                        {{ d.day_type }}
                                    </div>
                                </div>
                            </th>
                            <!-- Total -->
                            <th
                                class="min-w-[64px] p-3 text-right text-[11px] font-semibold tracking-wider text-slate-500 uppercase"
                            >
                                {{ __('Total') }}
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <tr
                            v-for="emp in roster"
                            :key="emp.id"
                            class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/50"
                        >
                            <!-- Employee info -->
                            <td
                                class="sticky left-0 z-10 border-r border-slate-200 bg-white p-2.5 dark:border-slate-700 dark:bg-slate-900"
                            >
                                <div
                                    class="text-[11px] leading-tight font-bold text-slate-900 dark:text-white"
                                >
                                    {{ emp.full_name }}
                                </div>
                                <div
                                    class="font-mono text-[10px] text-slate-400"
                                >
                                    {{ emp.npk }}
                                </div>
                            </td>

                            <!-- Day cells -->
                            <td
                                v-for="d in calendar_days"
                                :key="d.date"
                                class="border-r border-slate-100 p-0.5 text-center dark:border-slate-800"
                                :class="
                                    d.day_type === 'HLR'
                                        ? 'bg-red-50/30 dark:bg-red-950/10'
                                        : ''
                                "
                            >
                                <button
                                    type="button"
                                    class="h-10 w-full min-w-[38px] rounded text-center font-mono text-xs tabular-nums transition-colors focus:ring-1 focus:ring-[#cc0000] focus:outline-none"
                                    :class="
                                        quickCellValue(emp.id, d.date) > 0
                                            ? 'bg-slate-100 font-bold text-slate-900 dark:bg-slate-700 dark:text-white'
                                            : 'text-slate-300 hover:bg-slate-50 dark:text-slate-700 dark:hover:bg-slate-800'
                                    "
                                    @click="openCell(emp.id, d.date)"
                                >
                                    <span
                                        v-if="
                                            quickCellValue(emp.id, d.date) > 0
                                        "
                                        >{{
                                            quickCellValue(
                                                emp.id,
                                                d.date,
                                            ).toFixed(1)
                                        }}</span
                                    >
                                    <span v-else class="opacity-30">·</span>
                                </button>
                            </td>

                            <!-- Total per employee -->
                            <td
                                class="p-2.5 text-right font-mono text-xs font-bold text-slate-900 tabular-nums dark:text-white"
                            >
                                <span v-if="totalForEmployee(emp.id) > 0">{{
                                    totalForEmployee(emp.id).toFixed(1)
                                }}</span>
                                <span v-else class="text-slate-300">–</span>
                            </td>
                        </tr>
                    </tbody>

                    <!-- Day totals footer -->
                    <tfoot>
                        <tr
                            class="dark:bg-slate-850 border-t border-slate-200 bg-slate-50 font-semibold dark:border-slate-800"
                        >
                            <td
                                class="dark:bg-slate-850 sticky left-0 border-r border-slate-200 bg-slate-50 p-2.5 text-[11px] text-slate-500 dark:border-slate-700"
                            >
                                {{ __('Total Hari') }}
                            </td>
                            <td
                                v-for="d in calendar_days"
                                :key="d.date"
                                class="border-r border-slate-100 p-1.5 text-center font-mono text-[11px] tabular-nums dark:border-slate-800"
                                :class="
                                    dayTotal(d.date) > 0
                                        ? 'text-slate-900 dark:text-white'
                                        : 'text-slate-300'
                                "
                            >
                                {{
                                    dayTotal(d.date) > 0
                                        ? dayTotal(d.date).toFixed(1)
                                        : '–'
                                }}
                            </td>
                            <td
                                class="p-2.5 text-right font-mono text-xs font-bold text-[#cc0000] tabular-nums"
                            >
                                {{ grandTotal().toFixed(1) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Notes + Actions -->
        <div
            v-if="selectedSectionId && roster.length > 0"
            class="flex flex-wrap items-start gap-4"
        >
            <!-- Notes -->
            <div class="min-w-[240px] flex-1">
                <label
                    class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300"
                >
                    {{ __('Catatan Planning') }}
                </label>
                <textarea
                    v-model="form.notes"
                    rows="2"
                    :placeholder="
                        __('Catatan opsional untuk planning bulan ini...')
                    "
                    class="w-full resize-none rounded-md border border-slate-300 bg-white px-3 py-2 text-xs focus:border-[#cc0000] focus:ring-1 focus:ring-[#cc0000] focus:outline-none dark:border-slate-700 dark:bg-slate-800"
                />
            </div>

            <!-- Action buttons -->
            <div class="flex flex-wrap items-center gap-2 pt-5">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="submitPlan()"
                    :disabled="form.processing"
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

        <!-- Cell Edit Modal -->
        <div
            v-if="editingCell"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            @click.self="closeCell"
        >
            <div
                class="w-72 rounded-xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900"
                ref="modalRef"
            >
                <div class="mb-3">
                    <div
                        class="text-sm font-bold text-slate-900 dark:text-white"
                    >
                        {{
                            roster.find((e) => e.id === editingCell!.empId)
                                ?.full_name
                        }}
                    </div>
                    <div class="text-xs text-slate-500">
                        {{
                            new Date(editingCell.date).toLocaleDateString(
                                'id-ID',
                                {
                                    weekday: 'long',
                                    day: 'numeric',
                                    month: 'long',
                                },
                            )
                        }}
                        —
                        <span
                            :class="
                                calendar_days.find(
                                    (d) => d.date === editingCell!.date,
                                )?.day_type === 'HLR'
                                    ? 'font-semibold text-[#cc0000]'
                                    : 'text-slate-500'
                            "
                        >
                            {{
                                calendar_days.find(
                                    (d) => d.date === editingCell!.date,
                                )?.day_type
                            }}
                        </span>
                    </div>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="cat in CATEGORIES"
                        :key="cat.key"
                        class="flex items-center justify-between gap-2"
                    >
                        <label
                            class="w-24 text-xs font-medium"
                            :class="cat.color"
                        >
                            {{ cat.label }}
                        </label>
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                class="flex size-7 items-center justify-center rounded border border-slate-300 text-sm font-bold text-slate-600 hover:bg-slate-100 dark:border-slate-700"
                                @click="
                                    editForm[cat.key] = Math.max(
                                        0,
                                        editForm[cat.key] - 0.5,
                                    )
                                "
                            >
                                −
                            </button>
                            <input
                                v-model.number="editForm[cat.key]"
                                type="number"
                                min="0"
                                max="24"
                                step="0.25"
                                class="h-7 w-16 rounded border border-slate-300 bg-white text-center font-mono text-xs font-bold focus:border-[#cc0000] focus:outline-none dark:border-slate-700 dark:bg-slate-800"
                                @focus="
                                    ($event.target as HTMLInputElement).select()
                                "
                            />
                            <button
                                type="button"
                                class="flex size-7 items-center justify-center rounded border border-slate-300 text-sm font-bold text-slate-600 hover:bg-slate-100 dark:border-slate-700"
                                @click="editForm[cat.key] += 0.5"
                            >
                                +
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3 dark:border-slate-700"
                >
                    <span
                        class="text-xs font-semibold text-slate-700 dark:text-slate-200"
                    >
                        {{ __('Total') }}:
                        <span class="font-mono text-[#cc0000] tabular-nums">
                            {{
                                (
                                    editForm.hours_production +
                                    editForm.hours_tpm +
                                    editForm.hours_project +
                                    editForm.hours_others
                                ).toFixed(2)
                            }}
                            jam
                        </span>
                    </span>
                    <Button
                        size="sm"
                        class="h-7 bg-[#cc0000] px-3 text-xs text-white hover:bg-[#b30000]"
                        @click="closeCell"
                    >
                        {{ __('OK') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
