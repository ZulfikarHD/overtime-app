<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ArrowLeft,
    BarChart3,
    Briefcase,
    Building2,
    Calendar,
    Clock,
    FileSpreadsheet,
    FileText,
    HeartPulse,
    Layers,
    Search,
    Shield,
    Sparkles,
    User as UserIcon,
    Users,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import RoleBadge from '@/components/RoleBadge.vue';
import CategoryDonutChart from '@/components/reports/CategoryDonutChart.vue';
import DayTypeBreakdownBar from '@/components/reports/DayTypeBreakdownBar.vue';
import EmployeeSearch from '@/components/reports/EmployeeSearch.vue';
import FatigueRollingChart from '@/components/reports/FatigueRollingChart.vue';
import KpiSummaryCards, {
    type EmployeeSummaryMetrics,
} from '@/components/reports/KpiSummaryCards.vue';
import PeerComparisonPanel, {
    type PeerComparisonData,
} from '@/components/reports/PeerComparisonPanel.vue';
import RecentLookups from '@/components/reports/RecentLookups.vue';
import SafetyScoreGauge from '@/components/reports/SafetyScoreGauge.vue';
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
import { useRecentLookups } from '@/composables/useRecentLookups';
import { useShiftInfo } from '@/composables/useShiftInfo';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';
import { dashboard } from '@/routes';
import {
    index as reportsEmployees,
    show as showEmployeeDossier,
} from '@/routes/reports/employees';
import type { BreadcrumbItem, User } from '@/types';
import type { WelfareStatusData } from '@/types/ui';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Laporan Karyawan',
                href: reportsEmployees(),
            },
        ],
    },
});

export interface DossierEmployee {
    id: number;
    npk: string;
    full_name: string;
    job_position: string;
    hourly_rate: number;
    is_active: boolean;
    department: {
        id: number;
        code: string;
        name: string;
    } | null;
    section: {
        id: number;
        code: string;
        name: string;
    } | null;
}

export interface RosterItem {
    id: number;
    npk: string;
    full_name: string;
    job_position: string;
    is_active: boolean;
    department: {
        id: number;
        code: string;
        name: string;
    } | null;
    section: {
        id: number;
        code: string;
        name: string;
    } | null;
}

const props = defineProps<{
    employee: DossierEmployee | null;
    summary?: EmployeeSummaryMetrics | null;
    peer_comparison?: PeerComparisonData | null;
    welfare_status?: WelfareStatusData | null;
    roster: RosterItem[];
    filters?: {
        department_id?: string | number | null;
        section_id?: string | number | null;
        search?: string | null;
    };
    departments?: Array<{ id: number; code: string; name: string }>;
    sections?: Array<{
        id: number;
        department_id: number;
        code: string;
        name: string;
    }>;
    fiscal_year: number;
    fiscal_month: number;
    current_tab?: string;
    is_own_dossier?: boolean;
}>();

const { __ } = useTrans();
const { timeString, currentShift } = useShiftInfo();
const { addLookup } = useRecentLookups();
const page = usePage();
const currentUser = computed(() => page.props.auth?.user as User | undefined);

const activeTab = ref(props.current_tab || 'overview');
const selectedYear = ref(props.fiscal_year || 2026);
const selectedMonth = ref(props.fiscal_month || 9);

watch(
    () => props.fiscal_year,
    (val) => {
        if (val) selectedYear.value = val;
    },
);

watch(
    () => props.fiscal_month,
    (val) => {
        if (val) selectedMonth.value = val;
    },
);
const rosterSearch = ref(props.filters?.search || '');
const selectedDepartmentId = ref<string>(
    props.filters?.department_id ? String(props.filters.department_id) : 'all',
);
const selectedSectionId = ref<string>(
    props.filters?.section_id ? String(props.filters.section_id) : 'all',
);

const months = [
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

const years = [2025, 2026, 2027];

onMounted(() => {
    if (props.employee) {
        addLookup({
            id: props.employee.id,
            npk: props.employee.npk,
            name: props.employee.full_name,
            job_position: props.employee.job_position,
            department_name: props.employee.department?.name,
            section_name: props.employee.section?.name,
        });
    }
});

const handleTabChange = (tab: string) => {
    activeTab.value = tab;
    if (props.employee) {
        router.visit(
            showEmployeeDossier.url(
                { npk: props.employee.npk },
                {
                    query: {
                        tab,
                        year: selectedYear.value,
                        month: selectedMonth.value,
                    },
                },
            ),
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    }
};

const handlePeriodChange = () => {
    if (props.employee) {
        router.visit(
            showEmployeeDossier.url(
                { npk: props.employee.npk },
                {
                    query: {
                        tab: activeTab.value,
                        year: selectedYear.value,
                        month: selectedMonth.value,
                    },
                },
            ),
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    }
};

const filterRoster = () => {
    const query: Record<string, string | number> = {};
    if (selectedDepartmentId.value && selectedDepartmentId.value !== 'all') {
        query.department_id = selectedDepartmentId.value;
    }
    if (selectedSectionId.value && selectedSectionId.value !== 'all') {
        query.section_id = selectedSectionId.value;
    }
    if (rosterSearch.value.trim()) {
        query.search = rosterSearch.value.trim();
    }

    router.visit(reportsEmployees.url({ query }), {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearRosterFilters = () => {
    selectedDepartmentId.value = 'all';
    selectedSectionId.value = 'all';
    rosterSearch.value = '';
    router.visit(reportsEmployees.url(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const openEmployeeDossier = (npk: string) => {
    router.visit(showEmployeeDossier.url({ npk }));
};

const filteredSections = computed(() => {
    if (!props.sections) return [];
    if (selectedDepartmentId.value === 'all') return props.sections;
    return props.sections.filter(
        (s) => String(s.department_id) === selectedDepartmentId.value,
    );
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <Head
            :title="
                employee
                    ? `${employee.full_name} (${employee.npk}) - ${__('Laporan Karyawan')}`
                    : __('Laporan Karyawan & Kesejahteraan')
            "
        />

        <!-- ========================================== -->
        <!-- VIEW 1: DOSSIER SELECTED (props.employee)   -->
        <!-- ========================================== -->
        <template v-if="employee">
            <!-- Persistent Dossier Header Card -->
            <div
                data-test="dossier-header"
                class="border-border bg-card relative overflow-hidden rounded-xl border p-5 shadow-xs transition-all"
            >
                <div
                    class="bg-primary absolute top-0 right-0 left-0 h-1.5"
                ></div>

                <div class="flex flex-col gap-4 pt-1">
                    <!-- Top Navigation & Compact Search -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <Button
                            variant="ghost"
                            size="sm"
                            as-child
                            class="text-muted-foreground hover:text-foreground -ml-2 gap-1.5 text-xs"
                        >
                            <Link :href="reportsEmployees.url()">
                                <ArrowLeft class="size-4" />
                                <span>{{
                                    __('Kembali ke Daftar Karyawan')
                                }}</span>
                            </Link>
                        </Button>

                        <!-- Compact Employee Switcher Search -->
                        <div class="w-full sm:w-72">
                            <EmployeeSearch
                                compact
                                placeholder="Ganti karyawan..."
                            />
                        </div>
                    </div>

                    <!-- Main Employee Identity Profile Banner -->
                    <div
                        class="border-border/60 flex flex-col items-start justify-between gap-4 border-t pt-2 md:flex-row md:items-center"
                    >
                        <div class="flex items-start gap-4">
                            <!-- Avatar Circle with ISUZU Brand Accent -->
                            <div
                                class="bg-primary/10 text-primary border-primary/25 flex size-14 shrink-0 items-center justify-center rounded-xl border text-lg font-bold shadow-2xs"
                            >
                                <UserIcon class="size-7" />
                            </div>

                            <div class="flex flex-col gap-1">
                                <div
                                    class="flex flex-wrap items-center gap-2.5"
                                >
                                    <h1
                                        data-test="dossier-name"
                                        class="text-foreground text-xl font-bold tracking-tight md:text-2xl"
                                    >
                                        {{ employee.full_name }}
                                    </h1>

                                    <!-- Monospace Tabular NPK Pill -->
                                    <span
                                        data-test="dossier-npk"
                                        class="bg-muted text-foreground border-border rounded-md border px-2.5 py-0.5 font-mono text-xs font-semibold tabular-nums"
                                    >
                                        {{ employee.npk }}
                                    </span>

                                    <!-- Active Status Badge -->
                                    <Badge
                                        v-if="employee.is_active"
                                        data-test="dossier-status"
                                        variant="outline"
                                        class="border-emerald-300 bg-emerald-50 text-xs font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                                    >
                                        {{ __('Aktif') }}
                                    </Badge>
                                    <Badge
                                        v-else
                                        data-test="dossier-status"
                                        variant="outline"
                                        class="border-slate-300 bg-slate-50 text-xs font-medium text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400"
                                    >
                                        {{ __('Nonaktif') }}
                                    </Badge>
                                </div>

                                <!-- Metadata Row: Position, Section, Department -->
                                <div
                                    class="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs"
                                >
                                    <div
                                        class="flex items-center gap-1.5"
                                        data-test="dossier-job-position"
                                    >
                                        <Briefcase
                                            class="text-muted-foreground/80 size-3.5"
                                        />
                                        <span
                                            class="text-foreground font-medium"
                                            >{{ employee.job_position }}</span
                                        >
                                    </div>

                                    <span>&bull;</span>

                                    <div
                                        class="flex items-center gap-1.5"
                                        data-test="dossier-section"
                                    >
                                        <Layers
                                            class="text-muted-foreground/80 size-3.5"
                                        />
                                        <span>{{
                                            employee.section?.name ?? '-'
                                        }}</span>
                                    </div>

                                    <span>&bull;</span>

                                    <div
                                        class="flex items-center gap-1.5"
                                        data-test="dossier-department"
                                    >
                                        <Building2
                                            class="text-muted-foreground/80 size-3.5"
                                        />
                                        <span>{{
                                            employee.department?.name ?? '-'
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Period Selector (WIB Month & Year) -->
                        <div
                            class="border-border/60 flex items-center justify-end gap-2 self-stretch border-t pt-3 md:self-auto md:border-t-0 md:pt-0"
                        >
                            <div
                                class="text-muted-foreground mr-1 flex items-center gap-1.5 text-xs font-medium"
                            >
                                <Calendar class="size-3.5" />
                                <span>{{ __('Periode:') }}</span>
                            </div>

                            <Select
                                v-model="selectedMonth"
                                @update:model-value="handlePeriodChange"
                            >
                                <SelectTrigger class="bg-card h-9 w-32 text-xs">
                                    <SelectValue
                                        :placeholder="__('Pilih Bulan')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="m in months"
                                        :key="m.value"
                                        :value="m.value"
                                    >
                                        {{ m.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <Select
                                v-model="selectedYear"
                                @update:model-value="handlePeriodChange"
                            >
                                <SelectTrigger class="bg-card h-9 w-24 text-xs">
                                    <SelectValue
                                        :placeholder="__('Pilih Tahun')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="y in years"
                                        :key="y"
                                        :value="y"
                                    >
                                        {{ y }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Unified Tab Navigation Bar (Anti-Splitting Rule 5.1 & 5.2) -->
                    <div
                        class="border-border mt-2 flex items-center gap-6 border-t pt-3"
                    >
                        <button
                            type="button"
                            data-test="tab-overview"
                            :class="[
                                'relative flex items-center gap-2 pb-2 text-sm font-medium transition-all',
                                activeTab === 'overview'
                                    ? 'text-primary font-semibold'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="handleTabChange('overview')"
                        >
                            <HeartPulse class="size-4" />
                            <span>{{ __('Ringkasan & Kesejahteraan') }}</span>
                            <span
                                v-if="activeTab === 'overview'"
                                class="bg-primary absolute right-0 bottom-0 left-0 h-0.5 rounded-full"
                            ></span>
                        </button>

                        <button
                            type="button"
                            data-test="tab-timesheet"
                            :class="[
                                'relative flex items-center gap-2 pb-2 text-sm font-medium transition-all',
                                activeTab === 'timesheet'
                                    ? 'text-primary font-semibold'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="handleTabChange('timesheet')"
                        >
                            <FileSpreadsheet class="size-4" />
                            <span>{{ __('Buku Jam Lembur') }}</span>
                            <span
                                v-if="activeTab === 'timesheet'"
                                class="bg-primary absolute right-0 bottom-0 left-0 h-0.5 rounded-full"
                            ></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab Content Panel -->
            <div class="flex flex-col gap-6">
                <!-- TAB 1: Overview & Welfare (E06-02, E06-03, E06-04) -->
                <div
                    v-if="activeTab === 'overview'"
                    class="flex flex-col gap-6"
                >
                    <!-- 4 KPI Summary Cards & Financial Cost Snapshot (E06-02) -->
                    <KpiSummaryCards
                        v-if="summary"
                        :summary="summary"
                        :fiscal-year="selectedYear"
                        :fiscal-month="selectedMonth"
                    />

                    <!-- Overtime Hours Category Donut & Day-Type Breakdown Bar (E06-02) -->
                    <div
                        v-if="summary"
                        class="grid grid-cols-1 gap-6 lg:grid-cols-2"
                    >
                        <CategoryDonutChart
                            :breakdown="summary.category_breakdown"
                        />
                        <DayTypeBreakdownBar
                            :breakdown="summary.day_type_breakdown"
                        />
                    </div>

                    <!-- Quick Info Notice Card -->
                    <Card class="border-border shadow-xs">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-semibold"
                                >
                                    <Activity class="text-primary size-4" />
                                    <span>{{
                                        __('Informasi Master & Status Dossier')
                                    }}</span>
                                </CardTitle>
                                <span
                                    class="text-muted-foreground font-mono text-xs"
                                    >WIB (Asia/Jakarta)</span
                                >
                            </div>
                            <CardDescription>
                                {{
                                    __(
                                        'Profil karyawan terverifikasi. Modul analitik lembur (E06-02), peer benchmarking (E06-03), dan indikator kelelahan (E06-04) terhubung ke dossier ini.',
                                    )
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                            >
                                <div
                                    class="border-border bg-muted/30 rounded-lg border p-3.5"
                                >
                                    <div class="text-muted-foreground text-xs">
                                        {{ __('Nomor Pokok Karyawan (NPK)') }}
                                    </div>
                                    <div
                                        class="text-foreground mt-1 font-mono text-base font-bold tabular-nums"
                                    >
                                        {{ employee.npk }}
                                    </div>
                                </div>

                                <div
                                    class="border-border bg-muted/30 rounded-lg border p-3.5"
                                >
                                    <div class="text-muted-foreground text-xs">
                                        {{ __('Seksi Kerja') }}
                                    </div>
                                    <div
                                        class="text-foreground mt-1 text-base font-bold"
                                    >
                                        {{ employee.section?.name ?? '-' }}
                                    </div>
                                    <div
                                        class="text-muted-foreground mt-0.5 font-mono text-[11px]"
                                    >
                                        {{ employee.section?.code ?? '' }}
                                    </div>
                                </div>

                                <div
                                    class="border-border bg-muted/30 rounded-lg border p-3.5"
                                >
                                    <div class="text-muted-foreground text-xs">
                                        {{ __('Departemen') }}
                                    </div>
                                    <div
                                        class="text-foreground mt-1 text-base font-bold"
                                    >
                                        {{ employee.department?.name ?? '-' }}
                                    </div>
                                    <div
                                        class="text-muted-foreground mt-0.5 font-mono text-[11px]"
                                    >
                                        {{ employee.department?.code ?? '' }}
                                    </div>
                                </div>

                                <div
                                    class="border-border bg-muted/30 rounded-lg border p-3.5"
                                >
                                    <div class="text-muted-foreground text-xs">
                                        {{ __('Tarif Jam Lembur Efektif') }}
                                    </div>
                                    <div
                                        class="text-foreground mt-1 font-mono text-base font-bold tabular-nums"
                                    >
                                        {{ formatRupiah(employee.hourly_rate) }}
                                    </div>
                                    <div
                                        class="text-muted-foreground mt-0.5 text-[11px]"
                                    >
                                        {{ __('per jam operasional') }}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Peer Benchmarking & Section Workload Distribution (E06-03) -->
                    <PeerComparisonPanel
                        v-if="peer_comparison"
                        :peer-comparison="peer_comparison"
                    />

                    <!-- Safety & Fatigue Soft Indicators (E06-04) -->
                    <div
                        v-if="welfare_status"
                        class="grid grid-cols-1 gap-6 lg:grid-cols-12"
                        data-test="welfare-indicators-section"
                    >
                        <!-- Left: Rolling 4-Week Workload Bar Chart (7 cols) -->
                        <div class="lg:col-span-7">
                            <FatigueRollingChart
                                :rolling-weeks="welfare_status.rolling_weeks"
                                :weekly-limit="welfare_status.weekly_limit"
                                :consecutive-weeks="
                                    welfare_status.consecutive_weeks
                                "
                                :consecutive-weeks-alert="
                                    welfare_status.consecutive_weeks_alert
                                "
                            />
                        </div>

                        <!-- Right: Safety Score Gauge & Badges (5 cols) -->
                        <div class="lg:col-span-5">
                            <SafetyScoreGauge
                                :safety-score-pct="
                                    welfare_status.safety_score_pct
                                "
                                :alert-level="welfare_status.alert_level"
                                :current-week-hours="
                                    welfare_status.current_week_hours
                                "
                                :weekly-limit="welfare_status.weekly_limit"
                                :consecutive-weeks="
                                    welfare_status.consecutive_weeks
                                "
                                :consecutive-weeks-alert="
                                    welfare_status.consecutive_weeks_alert
                                "
                                :exceeded-weeks-count="
                                    welfare_status.exceeded_weeks_count
                                "
                                :badges="welfare_status.badges"
                                :advisory-message="
                                    welfare_status.advisory_message
                                "
                            />
                        </div>
                    </div>
                </div>

                <!-- TAB 2: Chronological Timesheet Placeholder (Scaffold for E06-05) -->
                <div
                    v-else-if="activeTab === 'timesheet'"
                    class="flex flex-col gap-6"
                >
                    <Card class="border-border shadow-xs">
                        <CardHeader>
                            <CardTitle
                                class="flex items-center gap-2 text-base font-semibold"
                            >
                                <FileSpreadsheet class="text-primary size-4" />
                                <span>{{
                                    __('Buku Jam Lembur Kronologis (E06-05)')
                                }}</span>
                            </CardTitle>
                            <CardDescription>
                                {{
                                    __(
                                        'Tabel riwayat item lembur per tanggal kerja, status persetujuan, rincian CapEx/OpEx, dan fitur ekspor CSV.',
                                    )
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                class="border-border bg-muted/20 rounded-lg border border-dashed p-8 text-center"
                            >
                                <FileText
                                    class="text-muted-foreground mx-auto mb-2 size-8"
                                />
                                <p class="text-foreground text-sm font-medium">
                                    {{
                                        __(
                                            'Riwayat lembur kronologis karyawan: :name',
                                            { name: employee.full_name },
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-muted-foreground mx-auto mt-1 max-w-md text-xs"
                                >
                                    {{
                                        __(
                                            'Fitur tabel timesheet detail dan ekspor CSV terjadwal diimplementasikan pada sub-epic E06-05.',
                                        )
                                    }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </template>

        <!-- ========================================== -->
        <!-- VIEW 2: ROSTER & LOOKUP HUB (No employee)   -->
        <!-- ========================================== -->
        <template v-else>
            <!-- Page Header -->
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1
                            class="text-foreground text-2xl font-bold tracking-tight md:text-3xl"
                        >
                            {{ __('Laporan Karyawan & Kesejahteraan') }}
                        </h1>
                        <Badge
                            variant="outline"
                            class="border-primary/30 bg-primary/10 text-primary text-xs font-semibold"
                        >
                            {{ __('Dossier Hub') }}
                        </Badge>
                    </div>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{
                            __(
                                'Cari karyawan berdasarkan NPK atau nama untuk memantau jam lembur, riwayat persetujuan, dan status keselamatan kerja.',
                            )
                        }}
                    </p>
                </div>

                <!-- WIB Live Clock & Role Status -->
                <div class="flex items-center gap-3">
                    <div class="hidden flex-col items-end text-xs sm:flex">
                        <div
                            class="text-muted-foreground flex items-center gap-1.5 font-mono"
                        >
                            <Clock class="size-3.5" />
                            <span>{{ timeString }} WIB</span>
                        </div>
                        <span
                            class="text-muted-foreground/80 mt-0.5 font-medium"
                        >
                            {{ currentShift.badgeText }}
                        </span>
                    </div>

                    <RoleBadge
                        v-if="currentUser?.role"
                        :role="currentUser.role"
                    />
                </div>
            </div>

            <!-- Prominent Employee Search & Recent Lookups Section -->
            <Card class="border-border shadow-xs">
                <CardHeader class="pb-3">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-semibold"
                    >
                        <Search class="text-primary size-4" />
                        <span>{{ __('Pencarian Cepat Karyawan') }}</span>
                    </CardTitle>
                    <CardDescription>
                        {{
                            __(
                                'Ketik minimal 3 karakter NPK atau nama karyawan. Hasil pencarian otomatis disesuaikan dengan lingkup wewenang Anda.',
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <!-- Search Input Component -->
                    <EmployeeSearch />

                    <!-- Recent Lookups Strip -->
                    <RecentLookups />
                </CardContent>
            </Card>

            <!-- Quick-Pick Roster List Grid -->
            <Card class="border-border shadow-xs" data-test="roster-grid">
                <CardHeader class="pb-3">
                    <div
                        class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-2 text-base font-semibold"
                            >
                                <Users class="text-primary size-4" />
                                <span>{{
                                    __('Daftar Karyawan Terdaftar')
                                }}</span>
                            </CardTitle>
                            <CardDescription>
                                {{
                                    __(
                                        'Pilih salah satu karyawan di bawah untuk membuka dossier individual lengkap.',
                                    )
                                }}
                            </CardDescription>
                        </div>

                        <!-- Department & Section Filters (if applicable) -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Department Filter for Admin -->
                            <Select
                                v-if="departments && departments.length > 0"
                                v-model="selectedDepartmentId"
                                @update:model-value="filterRoster"
                            >
                                <SelectTrigger class="bg-card h-8 w-40 text-xs">
                                    <SelectValue
                                        :placeholder="__('Departemen')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{{
                                        __('Semua Departemen')
                                    }}</SelectItem>
                                    <SelectItem
                                        v-for="dept in departments"
                                        :key="dept.id"
                                        :value="String(dept.id)"
                                    >
                                        {{ dept.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <!-- Section Filter -->
                            <Select
                                v-if="
                                    filteredSections &&
                                    filteredSections.length > 0
                                "
                                v-model="selectedSectionId"
                                @update:model-value="filterRoster"
                            >
                                <SelectTrigger class="bg-card h-8 w-40 text-xs">
                                    <SelectValue :placeholder="__('Seksi')" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{{
                                        __('Semua Seksi')
                                    }}</SelectItem>
                                    <SelectItem
                                        v-for="sec in filteredSections"
                                        :key="sec.id"
                                        :value="String(sec.id)"
                                    >
                                        {{ sec.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <Button
                                v-if="
                                    selectedDepartmentId !== 'all' ||
                                    selectedSectionId !== 'all' ||
                                    rosterSearch
                                "
                                variant="ghost"
                                size="sm"
                                class="text-muted-foreground hover:text-foreground h-8 text-xs"
                                @click="clearRosterFilters"
                            >
                                {{ __('Reset Filter') }}
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Employee Cards Grid -->
                    <div
                        v-if="roster.length > 0"
                        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="emp in roster"
                            :key="emp.id"
                            data-test="roster-card"
                            class="group border-border bg-card hover:border-primary/60 relative flex cursor-pointer flex-col justify-between rounded-xl border p-4 shadow-2xs transition-all hover:shadow-sm"
                            @click="openEmployeeDossier(emp.npk)"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div
                                        class="bg-primary/10 text-primary border-primary/20 flex size-10 shrink-0 items-center justify-center rounded-lg border transition-transform group-hover:scale-105"
                                    >
                                        <UserIcon class="size-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <div
                                            class="text-foreground group-hover:text-primary truncate text-sm font-semibold transition-colors"
                                        >
                                            {{ emp.full_name }}
                                        </div>
                                        <div
                                            class="text-muted-foreground font-mono text-xs tabular-nums"
                                        >
                                            {{ emp.npk }}
                                        </div>
                                    </div>
                                </div>

                                <Badge
                                    v-if="emp.is_active"
                                    variant="outline"
                                    class="shrink-0 border-emerald-300 bg-emerald-50 text-[10px] text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                                >
                                    {{ __('Aktif') }}
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="shrink-0 border-slate-300 bg-slate-50 text-[10px] text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400"
                                >
                                    {{ __('Nonaktif') }}
                                </Badge>
                            </div>

                            <div
                                class="border-border/60 text-muted-foreground mt-3 flex items-center justify-between border-t pt-2.5 text-xs"
                            >
                                <span class="truncate font-medium">{{
                                    emp.job_position
                                }}</span>
                                <span
                                    class="text-muted-foreground/80 shrink-0 text-[11px]"
                                >
                                    {{
                                        emp.section?.name ??
                                        emp.department?.name ??
                                        '-'
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else
                        class="border-border bg-muted/10 flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-10 text-center"
                    >
                        <Users class="text-muted-foreground size-8" />
                        <p class="text-foreground text-sm font-semibold">
                            {{ __('Tidak ada karyawan ditemukan') }}
                        </p>
                        <p class="text-muted-foreground max-w-sm text-xs">
                            {{
                                __(
                                    'Tidak ada data karyawan yang sesuai dengan kriteria filter atau lingkup wewenang seksi/departemen Anda.',
                                )
                            }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
