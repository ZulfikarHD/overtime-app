<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    Calendar as CalendarIcon,
    Clock,
    DollarSign,
    FileText,
    Layers,
    ListPlus,
    Plus,
    RefreshCw,
    Search,
    Send,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import OvertimeItemRow, {
    type CapexProjectOption,
    type OvertimeItemModel,
} from '@/components/overtime/OvertimeItemRow.vue';
import PostSubmissionSuccessCard, {
    type LastSubmissionSummary,
} from '@/components/overtime/PostSubmissionSuccessCard.vue';
import SectionBurnIndicator from '@/components/overtime/SectionBurnIndicator.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCalendarDayType } from '@/composables/useCalendarDayType';
import {
    useSectionRoster,
    type BurnIndicator,
    type RosterEmployee,
} from '@/composables/useSectionRoster';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';
import { dashboard } from '@/routes';
import {
    create as createSubmissionRoute,
    index as indexSubmissionRoute,
    store as storeSubmissionRoute,
} from '@/routes/overtime/submissions';
import type { User } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Input Lembur',
                href: createSubmissionRoute(),
            },
        ],
    },
});

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

const props = defineProps<{
    departments: DepartmentOption[];
    sections: SectionOption[];
    selected_department_id: number | null;
    selected_section_id: number | null;
    active_capex_projects: CapexProjectOption[];
    today: string;
    default_day_type: 'HKN' | 'HLR';
    burn_indicator: BurnIndicator;
    initial_roster: RosterEmployee[];
    last_submission?: LastSubmissionSummary | null;
}>();

const { __ } = useTrans();
const page = usePage();
const authUser = computed(() => page.props.auth?.user as User | undefined);

// Operational date ref & reactive calendar classification
const operationalDate = ref(props.today);
const {
    dayType,
    isHoliday,
    holidayName,
    isOverridden,
    toggleOverride,
    isLoading: isLoadingCalendar,
} = useCalendarDayType(operationalDate, props.default_day_type);

// Section roster & budget burn indicator
const selectedSectionId = ref<number | null>(props.selected_section_id);
const {
    roster,
    burnIndicator,
    isLoading: isLoadingRoster,
} = useSectionRoster(
    selectedSectionId,
    props.initial_roster,
    props.burn_indicator,
);

watch(
    () => props.initial_roster,
    (newRoster) => {
        if (newRoster && newRoster.length > 0) {
            roster.value = [...newRoster];
        }
    },
);

watch(
    () => props.selected_section_id,
    (newSecId) => {
        if (newSecId) {
            selectedSectionId.value = newSecId;
        }
    },
);

// Form state using Inertia useForm (ensures zero data loss upon validation failure)
const form = useForm({
    operational_date: props.today,
    day_type: props.default_day_type,
    department_id: props.selected_department_id ?? 0,
    section_id: props.selected_section_id ?? 0,
    submission_notes: '',
    items: [] as OvertimeItemModel[],
});

// Sync date & day_type into form
watch(operationalDate, (newDate) => {
    form.operational_date = newDate;
});

watch(dayType, (newDayType) => {
    form.day_type = newDayType;
});

watch(selectedSectionId, (newSecId) => {
    form.section_id = newSecId ?? 0;
});

// Selected department & sections filtering
const selectedDepartmentId = ref<number | null>(props.selected_department_id);
const availableSections = computed(() => {
    if (!selectedDepartmentId.value) {
        return props.sections;
    }
    return props.sections.filter(
        (s) => s.department_id === selectedDepartmentId.value,
    );
});

function handleDepartmentChange(deptId: number) {
    selectedDepartmentId.value = deptId;
    form.department_id = deptId;
    const firstSec = availableSections.value[0];
    if (firstSec) {
        selectedSectionId.value = firstSec.id;
        form.section_id = firstSec.id;
    } else {
        selectedSectionId.value = null;
        form.section_id = 0;
    }
}

// Roster employee search and quick add
const workerSearch = ref('');
const availableRosterWorkers = computed(() => {
    const existingIds = new Set(form.items.map((item) => item.employee_id));
    return roster.value.filter((worker) => {
        if (existingIds.has(worker.id)) {
            return false;
        }
        if (!workerSearch.value) {
            return true;
        }
        const q = workerSearch.value.toLowerCase();
        return (
            worker.full_name.toLowerCase().includes(q) ||
            worker.npk.toLowerCase().includes(q)
        );
    });
});

function addEmployeeToTimesheet(worker: RosterEmployee) {
    form.items.push({
        employee_id: worker.id,
        npk: worker.npk,
        full_name: worker.full_name,
        job_position: worker.job_position,
        hourly_rate: worker.hourly_rate,
        hours_production: 0,
        hours_tpm: 0,
        hours_project: 0,
        hours_others: 0,
        capex_project_id: null,
        rca_category: null,
        rca_notes: null,
        task_description: null,
    });
}

function addAllRosterWorkers() {
    const workersToAdd = [...availableRosterWorkers.value];
    for (const worker of workersToAdd) {
        addEmployeeToTimesheet(worker);
    }
}

function onSelectWorker(event: Event) {
    const target = event.target as HTMLSelectElement;
    const id = Number(target.value);
    const w = roster.value.find((x) => x.id === id);
    if (w) {
        addEmployeeToTimesheet(w);
    }
    target.value = '';
}

function removeEmployeeFromTimesheet(index: number) {
    form.items.splice(index, 1);
}

function clearAllEmployees() {
    form.items = [];
}

// Row error lookup
function getRowError(index: number): string | undefined {
    const prefix = `items.${index}.`;
    for (const [key, msg] of Object.entries(form.errors)) {
        if (key.startsWith(prefix)) {
            return msg;
        }
    }
    return undefined;
}

// Batch statistics
const batchCrewCount = computed(() => form.items.length);

const batchTotalHours = computed(() => {
    return form.items.reduce((acc, item) => {
        const prod = Number(item.hours_production) || 0;
        const tpm = Number(item.hours_tpm) || 0;
        const proj = Number(item.hours_project) || 0;
        const oth = Number(item.hours_others) || 0;
        return acc + prod + tpm + proj + oth;
    }, 0);
});

const defaultHourlyRate = computed(() => {
    return 35000;
});

const batchEstimatedCost = computed(() => {
    return form.items.reduce((acc, item) => {
        const prod = Number(item.hours_production) || 0;
        const tpm = Number(item.hours_tpm) || 0;
        const proj = Number(item.hours_project) || 0;
        const oth = Number(item.hours_others) || 0;
        const lineTotal = prod + tpm + proj + oth;
        const empRate = Number(item.hourly_rate);
        const rate =
            !isNaN(empRate) && empRate > 0 ? empRate : defaultHourlyRate.value;
        return acc + lineTotal * rate;
    }, 0);
});

// Success card state
const successSubmission = ref<LastSubmissionSummary | null>(
    props.last_submission ?? null,
);
const showSuccessCard = ref(Boolean(props.last_submission));

const pageProps = computed(() => page.props as Record<string, any>);
watch(
    () =>
        pageProps.value.flash?.last_submission ??
        pageProps.value.last_submission,
    (newVal) => {
        if (newVal) {
            successSubmission.value = newVal;
            showSuccessCard.value = true;
        }
    },
    { immediate: true },
);

function dismissSuccessCard() {
    showSuccessCard.value = false;
}

// Form submission handler
function submitOvertime() {
    form.post(storeSubmissionRoute.url(), {
        preserveScroll: true,
        onSuccess: (pageRes) => {
            const returnedSubmission =
                (pageRes.props.flash as any)?.last_submission ??
                (pageRes.props as any).last_submission;
            if (returnedSubmission) {
                successSubmission.value = returnedSubmission;
            }
            form.items = [];
            form.submission_notes = '';
            showSuccessCard.value = true;
        },
    });
}
</script>

<template>
    <div class="space-y-4 p-4 md:p-6" data-test="overtime-create-page">
        <Head :title="__('Form Input Lembur Shift')" />

        <!-- Top Navigation Switcher -->
        <div
            class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800"
        >
            <div class="flex items-center gap-2">
                <Link
                    :href="createSubmissionRoute()"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#cc0000] px-3.5 py-1.5 text-xs font-bold text-white shadow-xs"
                    data-test="tab-form-input"
                >
                    <ListPlus class="size-4" />
                    <span>{{ __('Form Input Lembur') }}</span>
                </Link>
                <Link
                    :href="indexSubmissionRoute()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                    data-test="tab-history-link"
                >
                    <FileText class="size-4 text-slate-400" />
                    <span>{{ __('Riwayat Pengajuan') }}</span>
                </Link>
            </div>

            <!-- Role Badge / Section Indicator -->
            <div class="hidden text-right sm:block">
                <span class="text-xs text-slate-500">{{
                    __('Area Kerja:')
                }}</span>
                <span
                    class="ml-1 font-mono text-xs font-bold text-slate-900 dark:text-white"
                >
                    {{
                        authUser?.section?.name ??
                        authUser?.department?.name ??
                        'Plant-wide'
                    }}
                </span>
            </div>
        </div>

        <!-- Post-Submission Celebratory Success Card (Zero Data Loss Confirmation) -->
        <PostSubmissionSuccessCard
            v-if="showSuccessCard && successSubmission"
            :submission="successSubmission"
            @dismiss="dismissSuccessCard"
        />

        <!-- General Form Error Banner -->
        <div
            v-if="form.errors.items || form.errors.section_id"
            class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
            data-test="general-error-banner"
        >
            <p v-if="form.errors.items" class="font-semibold">
                {{ form.errors.items }}
            </p>
            <p v-if="form.errors.section_id" class="font-semibold">
                {{ form.errors.section_id }}
            </p>
        </div>

        <!-- Header Metadata Bar (Date, Day Type, Section, Budget Burn Indicator) -->
        <Card
            class="border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardContent class="p-4">
                <div
                    class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-center"
                >
                    <!-- Date Picker & Day Type Badge (5 cols) -->
                    <div class="space-y-1.5 lg:col-span-5">
                        <label
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            {{ __('Tanggal Operasional Lembur') }}
                        </label>
                        <div class="flex flex-wrap items-center gap-2">
                            <input
                                type="date"
                                v-model="operationalDate"
                                :data-test="'input-operational-date'"
                                class="h-9 rounded-md border border-slate-300 bg-white px-3 font-mono text-xs font-semibold text-slate-900 focus:border-[#cc0000] focus:ring-1 focus:ring-[#cc0000] dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            />

                            <!-- Day Type Badge -->
                            <span
                                v-if="dayType === 'HKN'"
                                class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                data-test="day-type-badge-hkn"
                            >
                                📅 {{ __('Hari Kerja Normal (HKN)') }}
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-bold text-[#cc0000] dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                                data-test="day-type-badge-hlr"
                            >
                                🔴 {{ __('Hari Libur (HLR)') }}
                            </span>

                            <!-- Day Type Override Toggle Switch -->
                            <button
                                type="button"
                                @click="toggleOverride"
                                class="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-2.5 text-[11px] font-semibold text-slate-600 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:text-white"
                                data-test="btn-toggle-day-type"
                            >
                                <RefreshCw class="mr-1 size-3" />
                                <span>{{
                                    isOverridden
                                        ? __('Reset Kalender')
                                        : __('Ubah HKN/HLR')
                                }}</span>
                            </button>
                        </div>
                        <p
                            v-if="isHoliday && holidayName"
                            class="text-[11px] text-amber-600 dark:text-amber-400"
                        >
                            ⭐ {{ holidayName }}
                        </p>
                    </div>

                    <!-- Department & Section Selector (4 cols) -->
                    <div class="grid grid-cols-2 gap-2 lg:col-span-4">
                        <div>
                            <label
                                class="text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                                >{{ __('Departemen') }}</label
                            >
                            <select
                                :value="selectedDepartmentId || ''"
                                @change="
                                    handleDepartmentChange(
                                        Number(
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        ),
                                    )
                                "
                                :disabled="authUser?.role !== 'admin'"
                                class="dark:disabled:bg-slate-850 mt-1 h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-xs font-semibold text-slate-900 disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                data-test="select-department"
                            >
                                <option
                                    v-for="dept in departments"
                                    :key="dept.id"
                                    :value="dept.id"
                                >
                                    {{ dept.code }} - {{ dept.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                                >{{ __('Seksi') }}</label
                            >
                            <select
                                :value="selectedSectionId || ''"
                                @change="
                                    selectedSectionId = Number(
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                                :disabled="
                                    authUser?.role !== 'admin' &&
                                    authUser?.role !== 'manager'
                                "
                                class="dark:disabled:bg-slate-850 mt-1 h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-xs font-semibold text-slate-900 disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                data-test="select-section"
                            >
                                <option
                                    v-for="sec in availableSections"
                                    :key="sec.id"
                                    :value="sec.id"
                                >
                                    {{ sec.code }} - {{ sec.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Section Monthly Burn Indicator (3 cols) -->
                    <div class="lg:col-span-3">
                        <SectionBurnIndicator :burn="burnIndicator" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Timesheet Work Area (Roster Toolbar + High-Density Table) -->
        <Card
            class="border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader
                class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
            >
                <div class="flex items-center gap-2">
                    <CardTitle
                        class="text-sm font-bold text-slate-900 dark:text-white"
                    >
                        {{ __('Daftar Tenaga Kerja Lembur') }}
                    </CardTitle>
                    <Badge variant="outline" class="font-mono text-xs">
                        {{ form.items.length }} {{ __('Karyawan') }}
                    </Badge>
                </div>

                <!-- Roster Actions Toolbar -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Quick Search Worker -->
                    <div class="relative w-44 sm:w-56">
                        <Search
                            class="absolute top-2 left-2.5 size-3.5 text-slate-400"
                        />
                        <input
                            type="text"
                            v-model="workerSearch"
                            :placeholder="__('Cari NPK / Nama...')"
                            class="h-8 w-full rounded-md border border-slate-300 bg-white pr-2 pl-8 text-xs text-slate-900 placeholder:text-slate-400 focus:border-[#cc0000] focus:ring-1 focus:ring-[#cc0000] dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            data-test="input-search-worker"
                        />
                    </div>

                    <!-- Dropdown add single worker -->
                    <select
                        @change="onSelectWorker"
                        class="h-8 rounded-md border border-slate-300 bg-white px-2 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        data-test="select-add-employee"
                    >
                        <option value="">
                            {{ __('+ Tambah Karyawan...') }}
                        </option>
                        <option
                            v-for="worker in availableRosterWorkers"
                            :key="worker.id"
                            :value="worker.id"
                        >
                            {{ worker.npk }} - {{ worker.full_name }}
                        </option>
                    </select>

                    <!-- Add All Button (Shift-End 1-click ergonomics) -->
                    <button
                        type="button"
                        @click="addAllRosterWorkers"
                        :disabled="availableRosterWorkers.length === 0"
                        class="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 hover:bg-slate-100 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        data-test="btn-add-all-roster"
                    >
                        <Users class="mr-1.5 size-3.5 text-[#cc0000]" />
                        <span>{{ __('Pilih Semua (Add All)') }}</span>
                    </button>

                    <!-- Clear Table Button -->
                    <Button
                        v-if="form.items.length > 0"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="clearAllEmployees"
                        class="h-8 text-xs text-slate-500 hover:text-red-600 dark:text-slate-400"
                        data-test="btn-clear-roster"
                    >
                        {{ __('Kosongkan') }}
                    </Button>
                </div>
            </CardHeader>

            <!-- Table Header Bar -->
            <div
                class="dark:bg-slate-850 hidden border-b border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold tracking-wider text-slate-600 uppercase sm:grid sm:grid-cols-12 sm:gap-2 dark:border-slate-800 dark:text-slate-400"
            >
                <div class="col-span-3">{{ __('Karyawan (NPK & Nama)') }}</div>
                <div class="col-span-2 text-right">
                    {{ __('Produksi (Jam)') }}
                </div>
                <div class="col-span-2 text-right">{{ __('TPM (Jam)') }}</div>
                <div class="col-span-2 text-right">{{ __('CapEx (Jam)') }}</div>
                <div class="col-span-1 text-right">{{ __('Lainnya') }}</div>
                <div class="col-span-1 text-right">
                    {{ __('Total & Biaya') }}
                </div>
                <div class="col-span-1 text-right">{{ __('Aksi') }}</div>
            </div>

            <!-- Table Body / Empty State -->
            <CardContent class="p-0">
                <div
                    v-if="form.items.length === 0"
                    class="flex flex-col items-center justify-center p-8 text-center"
                    data-test="empty-timesheet-state"
                >
                    <div
                        class="flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500"
                    >
                        <Users class="size-6" />
                    </div>
                    <h4
                        class="mt-3 text-sm font-bold text-slate-900 dark:text-white"
                    >
                        {{ __('Belum Ada Karyawan Ditambahkan') }}
                    </h4>
                    <p
                        class="mt-1 max-w-sm text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{
                            __(
                                'Pilih karyawan dari daftar seksi atau klik "Pilih Semua" untuk mengisi seluruh kru lembur shift ini secara instan.',
                            )
                        }}
                    </p>
                    <div class="mt-4 flex items-center gap-2">
                        <button
                            type="button"
                            @click="addAllRosterWorkers"
                            :disabled="availableRosterWorkers.length === 0"
                            class="inline-flex items-center rounded-md bg-[#cc0000] px-3 py-2 text-xs font-semibold text-white shadow-xs hover:bg-[#b30000] disabled:opacity-50"
                            data-test="btn-empty-add-all"
                        >
                            <Users class="mr-1.5 size-3.5" />
                            <span>{{
                                __('+ Tambah Semua Anggota Seksi (:count)', {
                                    count: availableRosterWorkers.length,
                                })
                            }}</span>
                        </button>
                    </div>
                </div>

                <div
                    v-else
                    class="divide-y divide-slate-100 dark:divide-slate-800"
                >
                    <OvertimeItemRow
                        v-for="(item, index) in form.items"
                        :key="item.employee_id"
                        v-model="form.items[index]"
                        :index="index"
                        :default-hourly-rate="defaultHourlyRate"
                        :capex-projects="active_capex_projects"
                        :error="getRowError(index)"
                        @remove="removeEmployeeFromTimesheet(index)"
                    />
                </div>
            </CardContent>

            <!-- Sticky Summary Footer Bar -->
            <div
                class="dark:bg-slate-850 border-t border-slate-200 bg-slate-50 p-4 dark:border-slate-800"
            >
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <!-- Batch Notes Input -->
                    <div class="flex-1 lg:max-w-md">
                        <label
                            class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                        >
                            {{ __('Catatan Pengajuan Batch (Opsional)') }}
                        </label>
                        <input
                            type="text"
                            v-model="form.submission_notes"
                            :placeholder="
                                __(
                                    'Contoh: Lembur shift-2 penyelesaian target perakitan',
                                )
                            "
                            class="mt-1 h-8 w-full rounded-md border border-slate-300 bg-white px-3 text-xs text-slate-900 focus:border-[#cc0000] focus:ring-1 focus:ring-[#cc0000] dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            data-test="input-submission-notes"
                        />
                    </div>

                    <!-- Live Aggregates & Submit Action -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-4 sm:justify-end"
                    >
                        <!-- Summary Counter Pills -->
                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <div
                                    class="text-[10px] text-slate-500 uppercase"
                                >
                                    {{ __('Total Kru') }}
                                </div>
                                <div
                                    class="font-mono text-sm font-bold text-slate-900 tabular-nums dark:text-white"
                                    data-test="footer-crew-count"
                                >
                                    {{ batchCrewCount }}
                                    <span
                                        class="text-xs font-normal text-slate-500"
                                        >org</span
                                    >
                                </div>
                            </div>
                            <div
                                class="h-8 w-px bg-slate-200 dark:bg-slate-700"
                            />
                            <div class="text-right">
                                <div
                                    class="text-[10px] text-slate-500 uppercase"
                                >
                                    {{ __('Total Jam Lembur') }}
                                </div>
                                <div
                                    class="font-mono text-sm font-bold text-slate-900 tabular-nums dark:text-white"
                                    data-test="footer-total-hours"
                                >
                                    {{ batchTotalHours.toFixed(1) }}
                                    <span
                                        class="text-xs font-normal text-slate-500"
                                        >jam</span
                                    >
                                </div>
                            </div>
                            <div
                                class="h-8 w-px bg-slate-200 dark:bg-slate-700"
                            />
                            <div class="text-right">
                                <div
                                    class="text-[10px] text-slate-500 uppercase"
                                >
                                    {{ __('Estimasi Biaya') }}
                                </div>
                                <div
                                    class="cursor-help font-mono text-sm font-bold text-[#cc0000] tabular-nums dark:text-red-400"
                                    :title="
                                        __(
                                            'Estimasi biaya dihitung otomatis menggunakan tarif standar karyawan saat pengajuan (Snapshot Biaya Terkunci).',
                                        )
                                    "
                                    data-test="footer-estimated-cost"
                                >
                                    {{ formatRupiah(batchEstimatedCost) }}
                                </div>
                            </div>
                        </div>

                        <!-- Primary Submit Button -->
                        <button
                            type="button"
                            @click="submitOvertime"
                            :disabled="
                                form.processing || form.items.length === 0
                            "
                            class="inline-flex h-10 items-center justify-center rounded-md bg-[#cc0000] px-5 font-bold text-white shadow-sm hover:bg-[#b30000] active:scale-95 disabled:bg-slate-300 disabled:text-slate-500 dark:disabled:bg-slate-800"
                            data-test="btn-submit-overtime"
                        >
                            <Send v-if="!form.processing" class="mr-2 size-4" />
                            <RefreshCw
                                v-else
                                class="mr-2 size-4 animate-spin"
                            />
                            <span>{{
                                form.processing
                                    ? __('Menyimpan Pengajuan...')
                                    : __('Kirim Pengajuan Lembur')
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Card>
    </div>
</template>
