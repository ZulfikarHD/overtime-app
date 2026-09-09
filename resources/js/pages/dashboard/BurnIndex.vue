<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Flame,
    Layers,
    PieChart,
    RefreshCw,
    Search,
    Table,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import BurnIndexCard, {
    type SectionSnapshotData,
} from '@/components/dashboard/BurnIndexCard.vue';
import CapexOpexTab, {
    type CapexOpexData,
} from '@/components/dashboard/CapexOpexTab.vue';
import SectionBurndownSheet from '@/components/dashboard/SectionBurndownSheet.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTrans } from '@/composables/useTrans';
import { burnIndex } from '@/routes/dashboard';
import type { User } from '@/types';

interface DepartmentSummary {
    total_planned_hours: number;
    total_actual_hours: number;
    total_remaining_hours: number;
    department_burn_index_pct: number;
    department_burn_zone: string;
    warning_sections_count: number;
    danger_sections_count: number;
    configured_sections_count: number;
    total_sections_count: number;
}

interface DepartmentItem {
    id: number;
    code: string;
    name: string;
}

const props = defineProps<{
    departments: DepartmentItem[];
    selected_department: DepartmentItem | null;
    fiscal_year: number;
    fiscal_month: number;
    current_tab: string;
    snapshots: SectionSnapshotData[];
    summary: DepartmentSummary;
    capex_opex?: CapexOpexData;
}>();

const { __ } = useTrans();
const page = usePage();
const currentUser = computed(() => page.props.auth?.user as User | undefined);

// Local State
const activeTab = ref(props.current_tab || 'sections');
const selectedYear = ref(props.fiscal_year);
const selectedMonth = ref(props.fiscal_month);
const selectedDepartmentId = ref(
    props.selected_department ? String(props.selected_department.id) : '',
);
const searchQuery = ref('');
const statusFilter = ref<
    'all' | 'safe' | 'on_track' | 'warning' | 'danger' | 'unconfigured'
>('all');
const isRefreshing = ref(false);
const activeRangeType = ref(props.capex_opex?.summary?.range_type || 'month');
const activeStartDate = ref(props.capex_opex?.summary?.start_date || '');
const activeEndDate = ref(props.capex_opex?.summary?.end_date || '');

// Month names list
const months = [
    { value: 1, label: __('Januari') },
    { value: 2, label: __('Februari') },
    { value: 3, label: __('Maret') },
    { value: 4, label: __('April') },
    { value: 5, label: __('Mei') },
    { value: 6, label: __('Juni') },
    { value: 7, label: __('Juli') },
    { value: 8, label: __('Agustus') },
    { value: 9, label: __('September') },
    { value: 10, label: __('Oktober') },
    { value: 11, label: __('November') },
    { value: 12, label: __('Desember') },
];

const availableYears = computed(() => {
    const current = new Date().getFullYear();
    return [current - 1, current, current + 1];
});

// Sync props changes with local state
watch(
    () => props.current_tab,
    (val) => {
        if (val) activeTab.value = val;
    },
);

watch(
    () => props.fiscal_year,
    (val) => {
        selectedYear.value = val;
    },
);

watch(
    () => props.fiscal_month,
    (val) => {
        selectedMonth.value = val;
    },
);

watch(
    () => props.selected_department,
    (val) => {
        selectedDepartmentId.value = val ? String(val.id) : '';
    },
);

// Navigation / Filter Apply
function applyFilters(
    overrideDepartmentId?: string,
    overrideTab?: string,
    overrideRange?: { rangeType: string; startDate?: string; endDate?: string },
) {
    const targetDept =
        overrideDepartmentId !== undefined
            ? overrideDepartmentId
            : selectedDepartmentId.value;
    const targetTab = overrideTab !== undefined ? overrideTab : activeTab.value;
    const rangeType = overrideRange?.rangeType ?? activeRangeType.value;
    const startDate =
        overrideRange?.startDate ??
        (rangeType === 'custom' ? activeStartDate.value : undefined);
    const endDate =
        overrideRange?.endDate ??
        (rangeType === 'custom' ? activeEndDate.value : undefined);

    router.get(
        burnIndex.url(),
        {
            year: selectedYear.value,
            month: selectedMonth.value,
            department_id: targetDept || undefined,
            tab: targetTab,
            range_type: targetTab === 'capex-opex' ? rangeType : undefined,
            start_date:
                targetTab === 'capex-opex' && rangeType === 'custom'
                    ? startDate
                    : undefined,
            end_date:
                targetTab === 'capex-opex' && rangeType === 'custom'
                    ? endDate
                    : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function handleDepartmentChange(value: unknown) {
    if (typeof value === 'string') {
        selectedDepartmentId.value = value;
        applyFilters(value);
    }
}

function handleMonthChange(value: unknown) {
    if (typeof value === 'string') {
        selectedMonth.value = parseInt(value, 10);
        applyFilters();
    }
}

function handleYearChange(value: unknown) {
    if (typeof value === 'string') {
        selectedYear.value = parseInt(value, 10);
        applyFilters();
    }
}

function handleTabChange(tabKey: string) {
    activeTab.value = tabKey;
    applyFilters(undefined, tabKey);
}

function handleCapexOpexRangeFilter(payload: {
    rangeType: string;
    startDate?: string;
    endDate?: string;
}) {
    activeRangeType.value = payload.rangeType;
    if (payload.startDate) activeStartDate.value = payload.startDate;
    if (payload.endDate) activeEndDate.value = payload.endDate;
    applyFilters(undefined, 'capex-opex', payload);
}

// Manual Refresh
function manualRefresh() {
    isRefreshing.value = true;
    router.reload({
        only: ['snapshots', 'summary', 'capex_opex'],
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
}

// Section Burndown Sheet State (E05-02)
const isSheetOpen = ref(false);
const selectedSectionId = ref<number | null>(null);

function handleSelectSection(sectionId: number) {
    selectedSectionId.value = sectionId;
    isSheetOpen.value = true;
}

function handleSheetOpenChange(val: boolean) {
    isSheetOpen.value = val;
    if (!val) {
        selectedSectionId.value = null;
        if (typeof window !== 'undefined') {
            const url = new URL(window.location.href);
            if (url.searchParams.has('section')) {
                url.searchParams.delete('section');
                window.history.replaceState({}, '', url.toString());
            }
        }
    }
}

// 60-Second Auto Refresh Polling
let pollingInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    // Check if deep linked via query param ?section={id}
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        const secParam = urlParams.get('section');
        if (secParam) {
            const secId = parseInt(secParam, 10);
            if (!isNaN(secId)) {
                handleSelectSection(secId);
            }
        }
    }

    pollingInterval = setInterval(() => {
        router.reload({
            only: ['snapshots', 'summary', 'capex_opex'],
        });
    }, 60000);
});

onUnmounted(() => {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
});

// Client-side search and status filtering for section cards
const filteredSnapshots = computed(() => {
    let result = props.snapshots;

    if (searchQuery.value.trim() !== '') {
        const q = searchQuery.value.toLowerCase().trim();
        result = result.filter(
            (s) =>
                s.section_name.toLowerCase().includes(q) ||
                s.section_code.toLowerCase().includes(q) ||
                s.department_name.toLowerCase().includes(q),
        );
    }

    if (statusFilter.value === 'all') {
        return result;
    }

    return result.filter((s) => {
        if (statusFilter.value === 'unconfigured') {
            return !s.is_budget_configured;
        }
        if (!s.is_budget_configured) {
            return false;
        }
        if (statusFilter.value === 'danger') {
            return s.burn_index_pct > 115;
        }
        if (statusFilter.value === 'warning') {
            return s.burn_index_pct > 100 && s.burn_index_pct <= 115;
        }
        if (statusFilter.value === 'on_track') {
            return s.burn_index_pct >= 85 && s.burn_index_pct <= 100;
        }
        if (statusFilter.value === 'safe') {
            return s.burn_index_pct < 85;
        }
        return true;
    });
});

const deptBurnStatusClass = computed(() => {
    const pct = props.summary.department_burn_index_pct;
    if (pct > 115) {
        return 'text-[#cc0000] bg-red-50 dark:bg-red-950/40 border-red-200 dark:border-red-900';
    }
    if (pct > 100) {
        return 'text-amber-700 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-900';
    }
    if (pct >= 85) {
        return 'text-sky-700 bg-sky-50 dark:bg-sky-950/40 border-sky-200 dark:border-sky-900';
    }
    return 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-900';
});

const deptZoneLabel = computed(() => {
    switch (props.summary.department_burn_zone) {
        case 'ZONE_1_EXCELLENT':
            return __('Zona 1: Sangat Baik (Aman)');
        case 'ZONE_2_GOOD':
            return __('Zona 2: Baik (Terkendali)');
        case 'ZONE_3_WARNING':
            return __('Zona 3: Peringatan (Burn Cepat)');
        case 'ZONE_4_POOR':
        default:
            return __('Zona 4: Defisit (Melebihi Anggaran)');
    }
});
</script>

<template>
    <div
        class="mx-auto max-w-7xl space-y-6 p-4 md:p-6"
        data-test="burn-index-dashboard"
    >
        <Head :title="__('Dashboard Burn Index & Anggaran')" />

        <!-- Top Header & Primary Controls -->
        <div
            class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
        >
            <div>
                <div class="flex items-center gap-2">
                    <div
                        class="rounded-lg bg-red-50 p-1.5 text-[#cc0000] dark:bg-red-950/50"
                    >
                        <Flame class="size-5" />
                    </div>
                    <h1
                        class="text-xl font-bold tracking-tight text-slate-900 md:text-2xl dark:text-white"
                    >
                        {{ __('Dashboard Burn Index & Anggaran') }}
                    </h1>
                </div>
                <div
                    class="text-muted-foreground mt-1 flex flex-wrap items-center gap-2 text-xs"
                >
                    <div class="flex items-center gap-1 font-mono">
                        <Clock class="size-3.5 text-slate-400" />
                        <span>{{ __('WIB (Asia/Jakarta)') }}</span>
                    </div>
                    <span>•</span>
                    <span class="inline-flex items-center gap-1 text-slate-500">
                        <span
                            class="size-1.5 animate-pulse rounded-full bg-emerald-500"
                        />
                        {{ __('Pembaruan otomatis tiap 60 detik') }}
                    </span>
                </div>
            </div>

            <!-- Toolbar Filters: Department + Month/Year + Manual Refresh -->
            <div
                class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
            >
                <!-- Department Selector (Admin sees select; Manager sees locked badge) -->
                <div
                    v-if="
                        currentUser?.role === 'admin' && departments.length > 0
                    "
                    class="w-full min-w-0 sm:w-64 lg:w-72"
                >
                    <Select
                        :model-value="selectedDepartmentId"
                        @update:model-value="handleDepartmentChange"
                    >
                        <SelectTrigger class="h-9 w-full min-w-0 text-xs">
                            <div
                                class="flex min-w-0 items-center gap-1.5 truncate"
                            >
                                <Building2
                                    class="text-muted-foreground size-3.5 shrink-0"
                                />
                                <SelectValue
                                    :placeholder="__('Pilih Departemen')"
                                    class="truncate"
                                />
                            </div>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="dept in departments"
                                :key="dept.id"
                                :value="String(dept.id)"
                                class="text-xs"
                            >
                                {{ dept.code }} - {{ dept.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div
                    v-else-if="selected_department"
                    class="flex h-9 w-full max-w-full min-w-0 items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-3 text-xs font-semibold sm:w-auto sm:max-w-xs dark:border-slate-800 dark:bg-slate-900"
                    :title="`${selected_department.code} - ${selected_department.name}`"
                >
                    <Building2
                        class="text-muted-foreground size-3.5 shrink-0"
                    />
                    <span class="truncate"
                        >{{ selected_department.code }} -
                        {{ selected_department.name }}</span
                    >
                </div>

                <!-- Date & Action Group: Month Picker + Year Picker + Refresh -->
                <div class="flex items-center gap-2">
                    <!-- Month Picker -->
                    <div class="w-36 min-w-0">
                        <Select
                            :model-value="String(selectedMonth)"
                            @update:model-value="handleMonthChange"
                        >
                            <SelectTrigger class="h-9 w-full min-w-0 text-xs">
                                <div
                                    class="flex min-w-0 items-center gap-1.5 truncate"
                                >
                                    <Calendar
                                        class="text-muted-foreground size-3.5 shrink-0"
                                    />
                                    <SelectValue class="truncate" />
                                </div>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="m in months"
                                    :key="m.value"
                                    :value="String(m.value)"
                                    class="text-xs"
                                >
                                    {{ m.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Year Picker -->
                    <div class="w-24 min-w-0 shrink-0">
                        <Select
                            :model-value="String(selectedYear)"
                            @update:model-value="handleYearChange"
                        >
                            <SelectTrigger
                                class="h-9 w-full min-w-0 font-mono text-xs"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="y in availableYears"
                                    :key="y"
                                    :value="String(y)"
                                    class="font-mono text-xs"
                                >
                                    {{ y }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Manual Refresh Button -->
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 shrink-0 cursor-pointer px-3"
                        :disabled="isRefreshing"
                        @click="manualRefresh"
                        data-test="btn-refresh-dashboard"
                    >
                        <RefreshCw
                            class="mr-1 size-3.5"
                            :class="{ 'animate-spin': isRefreshing }"
                        />
                        <span class="text-xs">{{ __('Segarkan') }}</span>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Department KPI Macro Summary Bar -->
        <div
            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
            data-test="department-kpi-bar"
        >
            <!-- Total Hours Quota vs Actual -->
            <Card class="shadow-2xs">
                <CardContent class="space-y-1 p-4">
                    <div
                        class="text-muted-foreground flex items-center justify-between text-xs font-medium"
                    >
                        <span>{{ __('Total Jam Departemen') }}</span>
                        <Layers class="size-3.5 text-slate-400" />
                    </div>
                    <div
                        class="font-mono text-lg font-bold text-slate-900 tabular-nums dark:text-white"
                    >
                        {{ summary.total_actual_hours.toFixed(1) }}
                        <span class="text-muted-foreground text-xs font-normal"
                            >/ {{ summary.total_planned_hours.toFixed(1) }}
                            {{ __('jam') }}</span
                        >
                    </div>
                    <div class="text-muted-foreground text-[11px]">
                        <span>{{ __('Sisa kuota') }}: </span>
                        <span
                            class="font-mono font-semibold tabular-nums"
                            :class="
                                summary.total_remaining_hours < 0
                                    ? 'text-[#cc0000]'
                                    : 'text-slate-700 dark:text-slate-300'
                            "
                        >
                            {{ summary.total_remaining_hours.toFixed(1) }}
                            {{ __('jam') }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Department Burn Index % -->
            <Card class="shadow-2xs">
                <CardContent class="space-y-1 p-4">
                    <div
                        class="text-muted-foreground flex items-center justify-between text-xs font-medium"
                    >
                        <span>{{ __('Indeks Burn Departemen') }}</span>
                        <Flame class="size-3.5 text-slate-400" />
                    </div>
                    <div class="flex items-center gap-2">
                        <div
                            class="font-mono text-2xl font-extrabold tracking-tight tabular-nums"
                        >
                            {{ summary.department_burn_index_pct.toFixed(1) }}%
                        </div>
                        <Badge
                            variant="outline"
                            class="border px-2 py-0.5 text-[10px] font-semibold"
                            :class="deptBurnStatusClass"
                        >
                            {{
                                summary.department_burn_index_pct > 115
                                    ? __('Defisit')
                                    : summary.department_burn_index_pct > 100
                                      ? __('Peringatan')
                                      : summary.department_burn_index_pct >= 85
                                        ? __('Terkendali')
                                        : __('Aman')
                            }}
                        </Badge>
                    </div>
                    <div class="text-muted-foreground text-[11px]">
                        <span
                            >{{ summary.configured_sections_count }}
                            {{ __('dari') }} {{ summary.total_sections_count }}
                            {{ __('seksi terkonfigurasi') }}</span
                        >
                    </div>
                </CardContent>
            </Card>

            <!-- Department Budget Control Matrix Zone -->
            <Card class="shadow-2xs">
                <CardContent class="space-y-1 p-4">
                    <div
                        class="text-muted-foreground flex items-center justify-between text-xs font-medium"
                    >
                        <span>{{ __('Matriks Kontrol Anggaran') }}</span>
                        <Activity class="size-3.5 text-slate-400" />
                    </div>
                    <div
                        class="truncate text-sm font-bold text-slate-900 dark:text-white"
                        :title="deptZoneLabel"
                    >
                        {{ deptZoneLabel }}
                    </div>
                    <div class="text-muted-foreground text-[11px]">
                        {{ __('Status agregat seluruh seksi') }}
                    </div>
                </CardContent>
            </Card>

            <!-- High-Risk Section Counter -->
            <Card class="shadow-2xs">
                <CardContent class="space-y-1 p-4">
                    <div
                        class="text-muted-foreground flex items-center justify-between text-xs font-medium"
                    >
                        <span>{{ __('Status Risiko Seksi') }}</span>
                        <AlertTriangle class="size-3.5 text-slate-400" />
                    </div>
                    <div class="flex items-center gap-3 pt-0.5">
                        <div
                            class="flex items-center gap-1.5 font-mono text-xs font-bold text-[#cc0000] tabular-nums"
                        >
                            <span class="size-2 rounded-full bg-[#cc0000]" />
                            <span>{{ summary.danger_sections_count }}</span>
                            <span
                                class="text-muted-foreground font-sans text-[10px] font-normal"
                                >{{ __('Defisit') }}</span
                            >
                        </div>
                        <div
                            class="flex items-center gap-1.5 font-mono text-xs font-bold text-amber-600 tabular-nums"
                        >
                            <span class="size-2 rounded-full bg-amber-500" />
                            <span>{{ summary.warning_sections_count }}</span>
                            <span
                                class="text-muted-foreground font-sans text-[10px] font-normal"
                                >{{ __('Peringatan') }}</span
                            >
                        </div>
                        <div
                            class="flex items-center gap-1.5 font-mono text-xs font-bold text-emerald-600 tabular-nums"
                        >
                            <CheckCircle2 class="size-3 text-emerald-500" />
                            <span>{{
                                Math.max(
                                    0,
                                    summary.total_sections_count -
                                        summary.warning_sections_count -
                                        summary.danger_sections_count,
                                )
                            }}</span>
                            <span
                                class="text-muted-foreground font-sans text-[10px] font-normal"
                                >{{ __('Aman') }}</span
                            >
                        </div>
                    </div>
                    <div class="text-muted-foreground text-[11px]">
                        {{ __('Distribusi kepatuhan kuota seksi') }}
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Navigation Tabs per UX Plan Section 1.2 -->
        <div
            class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-0 dark:border-slate-800"
        >
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-semibold transition-all"
                    :class="
                        activeTab === 'sections'
                            ? 'border-[#cc0000] text-[#cc0000] dark:text-red-400'
                            : 'text-muted-foreground border-transparent hover:text-slate-900 dark:hover:text-white'
                    "
                    @click="handleTabChange('sections')"
                    data-test="tab-sections"
                >
                    <Layers class="size-3.5" />
                    <span>{{ __('Ringkasan Seksi (Cards)') }}</span>
                    <Badge
                        variant="secondary"
                        class="ml-1 px-1.5 py-0 font-mono text-[10px]"
                    >
                        {{ snapshots.length }}
                    </Badge>
                </button>

                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-semibold transition-all"
                    :class="
                        activeTab === 'department'
                            ? 'border-[#cc0000] text-[#cc0000] dark:text-red-400'
                            : 'text-muted-foreground border-transparent hover:text-slate-900 dark:hover:text-white'
                    "
                    @click="handleTabChange('department')"
                    data-test="tab-department"
                >
                    <Table class="size-3.5" />
                    <span>{{ __('Konsolidasi Departemen (Tabel)') }}</span>
                </button>

                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-semibold transition-all"
                    :class="
                        activeTab === 'capex-opex'
                            ? 'border-[#cc0000] text-[#cc0000] dark:text-red-400'
                            : 'text-muted-foreground border-transparent hover:text-slate-900 dark:hover:text-white'
                    "
                    @click="handleTabChange('capex-opex')"
                    data-test="tab-capex-opex"
                >
                    <PieChart class="size-3.5" />
                    <span>{{ __('Distribusi CapEx vs OpEx') }}</span>
                </button>
            </div>
        </div>

        <!-- TAB 1: SECTION CARDS VIEW (E05-01 CORE) -->
        <div v-if="activeTab === 'sections'" class="space-y-4">
            <!-- Filter Bar for Section Cards -->
            <div
                class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
            >
                <!-- Search input -->
                <div class="relative w-full sm:w-72">
                    <Search
                        class="text-muted-foreground absolute top-2.5 left-2.5 size-3.5"
                    />
                    <Input
                        v-model="searchQuery"
                        :placeholder="__('Cari seksi atau kode...')"
                        class="bg-card h-9 pl-8 text-xs"
                        data-test="input-search-sections"
                    />
                </div>

                <!-- Status Filter Pills -->
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <button
                        type="button"
                        class="cursor-pointer rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors"
                        :class="
                            statusFilter === 'all'
                                ? 'border-transparent bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                                : 'bg-card text-muted-foreground border-slate-200 hover:bg-slate-50 dark:border-slate-800'
                        "
                        @click="statusFilter = 'all'"
                    >
                        {{ __('Semua') }} ({{ snapshots.length }})
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors"
                        :class="
                            statusFilter === 'safe'
                                ? 'border-transparent bg-emerald-600 text-white'
                                : 'bg-card border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-400'
                        "
                        @click="statusFilter = 'safe'"
                    >
                        {{ __('Aman (<85%)') }}
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors"
                        :class="
                            statusFilter === 'on_track'
                                ? 'border-transparent bg-sky-600 text-white'
                                : 'bg-card border-sky-200 text-sky-700 hover:bg-sky-50 dark:border-sky-900 dark:text-sky-400'
                        "
                        @click="statusFilter = 'on_track'"
                    >
                        {{ __('Terkendali (85–100%)') }}
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors"
                        :class="
                            statusFilter === 'warning'
                                ? 'border-transparent bg-amber-600 text-white'
                                : 'bg-card border-amber-200 text-amber-700 hover:bg-amber-50 dark:border-amber-900 dark:text-amber-400'
                        "
                        @click="statusFilter = 'warning'"
                    >
                        {{ __('Peringatan (101–115%)') }}
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors"
                        :class="
                            statusFilter === 'danger'
                                ? 'border-transparent bg-[#cc0000] text-white'
                                : 'bg-card border-red-200 text-[#cc0000] hover:bg-red-50 dark:border-red-900 dark:text-red-400'
                        "
                        @click="statusFilter = 'danger'"
                    >
                        {{ __('Defisit (>115%)') }}
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors"
                        :class="
                            statusFilter === 'unconfigured'
                                ? 'border-transparent bg-slate-600 text-white'
                                : 'bg-card border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-400'
                        "
                        @click="statusFilter = 'unconfigured'"
                    >
                        {{ __('Belum Diatur') }}
                    </button>
                </div>
            </div>

            <!-- Empty Grid Notice -->
            <div
                v-if="filteredSnapshots.length === 0"
                class="bg-card space-y-3 rounded-xl border border-dashed border-slate-200 p-12 text-center dark:border-slate-800"
            >
                <div class="flex justify-center">
                    <Flame class="text-muted-foreground/50 size-10" />
                </div>
                <h3
                    class="text-sm font-bold text-slate-800 dark:text-slate-200"
                >
                    {{ __('Tidak Ada Data Seksi') }}
                </h3>
                <p class="text-muted-foreground mx-auto max-w-sm text-xs">
                    {{
                        __(
                            'Tidak ada kartu seksi yang cocok dengan filter atau departemen yang dipilih.',
                        )
                    }}
                </p>
            </div>

            <!-- Responsive Cards Grid -->
            <div
                v-else
                class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                data-test="burn-cards-grid"
            >
                <BurnIndexCard
                    v-for="item in filteredSnapshots"
                    :key="item.id"
                    :snapshot="item"
                    @select-section="handleSelectSection"
                />
            </div>
        </div>

        <!-- TAB 2 PLACEHOLDER (E05-05 Future Story) -->
        <div
            v-else-if="activeTab === 'department'"
            class="bg-card space-y-2 rounded-xl border border-dashed p-8 text-center"
        >
            <Table class="text-muted-foreground mx-auto size-8" />
            <h3 class="text-sm font-bold">
                {{ __('Konsolidasi Departemen (Tabel)') }}
            </h3>
            <p class="text-muted-foreground mx-auto max-w-md text-xs">
                {{
                    __(
                        'Tampilan tabel berperingkat dan ringkasan eksekutif departemen akan diaktifkan pada Story E05-05.',
                    )
                }}
            </p>
        </div>

        <!-- TAB 3: CAPEX VS OPEX DISTRIBUTION (E05-03 CORE) -->
        <CapexOpexTab
            v-else-if="activeTab === 'capex-opex' && capex_opex"
            :capex-opex="capex_opex"
            @filter-range="handleCapexOpexRangeFilter"
        />

        <!-- Section Burndown & Control Matrix Slide-in Sheet (E05-02) -->
        <SectionBurndownSheet
            :open="isSheetOpen"
            :section-id="selectedSectionId"
            :fiscal-year="selectedYear"
            :fiscal-month="selectedMonth"
            @update:open="handleSheetOpenChange"
        />
    </div>
</template>
