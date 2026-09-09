<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    BarChart3,
    Briefcase,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    FileText,
    HeartPulse,
    Layers,
    Shield,
    ShieldAlert,
    ShieldCheck,
    User as UserIcon,
} from '@lucide/vue';
import { computed } from 'vue';
import RoleBadge from '@/components/RoleBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useShiftInfo } from '@/composables/useShiftInfo';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';
import { dashboard as myDashboard } from '@/routes/my';
import { show as showEmployeeDossier } from '@/routes/reports/employees';
import type { User } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard Pribadi',
                href: myDashboard(),
            },
        ],
    },
});

export interface SelfServiceEmployee {
    id: number;
    npk: string;
    full_name: string;
    job_position: string;
    department: {
        id: number;
        name: string;
        code: string;
    } | null;
    section: {
        id: number;
        name: string;
        code: string;
    } | null;
}

export interface SelfServiceSummary {
    current_month_hours: number;
    total_cost_idr: number;
    ytd_hours: number;
    burn_rate_pct: number;
    category_breakdown: {
        production: number;
        tpm: number;
        project: number;
        others: number;
        total: number;
        production_pct: number;
        tpm_pct: number;
        project_pct: number;
        others_pct: number;
    };
    day_type_breakdown: {
        hkn_hours: number;
        hlr_hours: number;
        hkn_pct: number;
        hlr_pct: number;
    };
}

export interface SelfServiceWelfareStatus {
    employee_id: number;
    current_week_hours: number;
    weekly_limit: number;
    consecutive_weeks: number;
    consecutive_weeks_alert: number;
    exceeded_weeks_count: number;
    safety_score_pct: number;
    alert_level: 'safe' | 'warning' | 'danger';
    badges: Array<{
        type: 'safe' | 'warning' | 'danger';
        label: string;
        message: string;
    }>;
    is_advisory: boolean;
    advisory_message: string;
}

export interface SelfServiceRecentItem {
    id: number;
    submission_id: number;
    submission_code: string;
    operational_date: string;
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
    status: 'APPROVED' | 'PENDING' | 'REJECTED';
    task_description?: string;
    rca_category?: string;
    rca_notes?: string;
    rejection_reason?: string | null;
    is_capex?: boolean;
}

interface Props {
    employee: SelfServiceEmployee | null;
    summary: SelfServiceSummary | null;
    welfare_status: SelfServiceWelfareStatus | null;
    recent_timesheet: SelfServiceRecentItem[];
    fiscal_year: number;
    fiscal_month: number;
}

const props = defineProps<Props>();

const { __ } = useTrans();
const { timeString, dateString, currentShift } = useShiftInfo();
const page = usePage();
const authUser = computed(() => page.props.auth?.user as User | undefined);

const displayName = computed(() => {
    return props.employee?.full_name ?? authUser.value?.name ?? 'Karyawan';
});

const greetingText = computed(() => {
    return __('Halo, :name!', { name: displayName.value });
});

const monthNames = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

const currentMonthLabel = computed(() => {
    return monthNames[(props.fiscal_month || 1) - 1] ?? '';
});

// Welfare UI Helpers
const welfareAlertConfig = computed(() => {
    const level = props.welfare_status?.alert_level ?? 'safe';
    switch (level) {
        case 'danger':
            return {
                label: __('Risiko Kelelahan'),
                badgeClass:
                    'bg-destructive text-destructive-foreground border-transparent font-semibold',
                icon: ShieldAlert,
                iconColor: 'text-destructive',
                bgColor: 'bg-destructive/10 border-destructive/30',
            };
        case 'warning':
            return {
                label: __('Perlu Rotasi'),
                badgeClass:
                    'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-300 dark:border-amber-700 font-semibold',
                icon: AlertTriangle,
                iconColor: 'text-amber-600 dark:text-amber-400',
                bgColor:
                    'bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800',
            };
        case 'safe':
        default:
            return {
                label: __('Aman'),
                badgeClass:
                    'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-700 font-semibold',
                icon: ShieldCheck,
                iconColor: 'text-emerald-600 dark:text-emerald-400',
                bgColor:
                    'bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800',
            };
    }
});

const safetyScore = computed(() => {
    return Math.round(props.welfare_status?.safety_score_pct ?? 100);
});

// Status Badge Helper for Recent Items
const getStatusBadge = (status: 'APPROVED' | 'PENDING' | 'REJECTED') => {
    switch (status) {
        case 'APPROVED':
            return {
                label: __('Disetujui'),
                class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                dotClass: 'bg-emerald-500',
            };
        case 'PENDING':
            return {
                label: __('Menunggu'),
                class: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                dotClass: 'bg-amber-500 animate-pulse',
            };
        case 'REJECTED':
            return {
                label: __('Ditolak'),
                class: 'bg-destructive/15 text-destructive dark:text-red-400 border-destructive/30',
                dotClass: 'bg-destructive',
            };
    }
};
</script>

<template>
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Head :title="__('Dashboard Pribadi')" />

        <!-- 1. Header & Greeting Card -->
        <Card
            class="border-border/70 from-card via-card to-muted/20 overflow-hidden bg-linear-to-r shadow-sm"
        >
            <CardContent class="p-4 sm:p-6">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <!-- Left: Identity & Shift -->
                    <div class="space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1
                                class="text-foreground text-xl font-bold tracking-tight sm:text-2xl"
                            >
                                {{ greetingText }}
                            </h1>
                            <RoleBadge role="user" size="sm" />
                            <Badge
                                v-if="employee?.npk"
                                variant="outline"
                                class="px-2 py-0.5 font-mono text-xs font-semibold tabular-nums"
                                data-testid="employee-npk-badge"
                            >
                                NPK: {{ employee.npk }}
                            </Badge>
                        </div>

                        <p
                            class="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
                        >
                            <span
                                v-if="employee?.job_position"
                                class="text-foreground/80 inline-flex items-center gap-1 font-medium"
                            >
                                <Briefcase
                                    class="text-muted-foreground h-3.5 w-3.5"
                                />
                                {{ employee.job_position }}
                            </span>
                            <span
                                v-if="employee?.department"
                                class="inline-flex items-center gap-1"
                            >
                                <Building2
                                    class="text-muted-foreground h-3.5 w-3.5"
                                />
                                {{ employee.department.name }}
                            </span>
                            <span
                                v-if="employee?.section"
                                class="inline-flex items-center gap-1"
                            >
                                <Layers
                                    class="text-muted-foreground h-3.5 w-3.5"
                                />
                                {{ employee.section.name }}
                            </span>
                        </p>
                    </div>

                    <!-- Right: Live Clock & Shift Badge -->
                    <div
                        class="border-border/40 flex items-center justify-between gap-1.5 border-t pt-2 sm:flex-col sm:items-end sm:border-t-0 sm:pt-0"
                    >
                        <div
                            class="bg-primary/10 text-primary border-primary/20 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
                        >
                            <span class="relative flex h-2 w-2">
                                <span
                                    class="bg-primary absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                                ></span>
                                <span
                                    class="bg-primary relative inline-flex h-2 w-2 rounded-full"
                                ></span>
                            </span>
                            <span class="font-semibold">{{
                                currentShift.name
                            }}</span>
                            <span
                                class="text-muted-foreground hidden text-[11px] md:inline"
                                >({{ currentShift.hours }})</span
                            >
                        </div>
                        <div
                            class="text-muted-foreground text-right font-mono text-xs tabular-nums"
                        >
                            <span class="text-foreground font-medium">{{
                                timeString
                            }}</span>
                            &bull; {{ dateString }}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Unlinked Profile Warning (Edge Case) -->
        <Card
            v-if="!employee"
            class="border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/20 dark:text-amber-200"
            data-testid="unlinked-account-alert"
        >
            <CardContent class="flex items-start gap-4 p-4 sm:p-6">
                <AlertCircle
                    class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400"
                />
                <div class="space-y-1 text-sm">
                    <p class="font-semibold">
                        {{ __('Akun Belum Terhubung Karyawan') }}
                    </p>
                    <p
                        class="leading-relaxed text-amber-800/90 dark:text-amber-300/90"
                    >
                        {{
                            __(
                                'Akun login Anda belum terhubung dengan nomor pokok karyawan (NPK). Silakan hubungi bagian HR atau Administrator sistem untuk menghubungkan profil karyawan Anda.',
                            )
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <template v-if="employee">
            <!-- 2. Three Compact KPI Cards (Mobile-First 1-col -> 3-col Grid) -->
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                data-testid="self-service-kpi-grid"
            >
                <!-- KPI 1: Jam Lembur Bulan Ini -->
                <Card
                    class="border-border/70 relative overflow-hidden transition-shadow hover:shadow-sm"
                    data-testid="kpi-current-month"
                >
                    <div class="bg-primary absolute top-0 right-0 left-0 h-1" />
                    <CardHeader class="px-4 pt-4 pb-2 sm:px-6">
                        <div
                            class="text-muted-foreground flex items-center justify-between"
                        >
                            <span
                                class="text-xs font-semibold tracking-wider uppercase"
                                >{{ __('Total Lembur Bulan Ini') }}</span
                            >
                            <div
                                class="bg-primary/10 text-primary rounded-lg p-2"
                            >
                                <Clock class="h-4 w-4" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="px-4 pb-4 sm:px-6">
                        <div class="flex items-baseline gap-2">
                            <span
                                class="text-foreground font-mono text-3xl font-extrabold tracking-tight tabular-nums sm:text-4xl"
                                data-testid="kpi-month-hours"
                            >
                                {{
                                    (summary?.current_month_hours ?? 0).toFixed(
                                        1,
                                    )
                                }}
                            </span>
                            <span
                                class="text-muted-foreground text-sm font-semibold"
                                >jam</span
                            >
                        </div>
                        <div
                            class="border-border/50 text-muted-foreground mt-3 flex items-center justify-between border-t pt-2 text-xs"
                        >
                            <span
                                >{{ currentMonthLabel }} {{ fiscal_year }}</span
                            >
                            <span
                                class="text-foreground font-mono font-semibold tabular-nums"
                                data-testid="kpi-month-cost"
                            >
                                {{
                                    formatRupiah(summary?.total_cost_idr ?? 0, {
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    })
                                }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- KPI 2: Total Tahun Ini (YTD) -->
                <Card
                    class="border-border/70 relative overflow-hidden transition-shadow hover:shadow-sm"
                    data-testid="kpi-ytd"
                >
                    <div
                        class="bg-muted-foreground/30 absolute top-0 right-0 left-0 h-1"
                    />
                    <CardHeader class="px-4 pt-4 pb-2 sm:px-6">
                        <div
                            class="text-muted-foreground flex items-center justify-between"
                        >
                            <span
                                class="text-xs font-semibold tracking-wider uppercase"
                                >{{ __('Total Tahun Ini (YTD)') }}</span
                            >
                            <div
                                class="bg-muted text-muted-foreground rounded-lg p-2"
                            >
                                <Calendar class="h-4 w-4" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="px-4 pb-4 sm:px-6">
                        <div class="flex items-baseline gap-2">
                            <span
                                class="text-foreground font-mono text-3xl font-extrabold tracking-tight tabular-nums sm:text-4xl"
                                data-testid="kpi-ytd-hours"
                            >
                                {{ (summary?.ytd_hours ?? 0).toFixed(1) }}
                            </span>
                            <span
                                class="text-muted-foreground text-sm font-semibold"
                                >jam</span
                            >
                        </div>
                        <div
                            class="border-border/50 text-muted-foreground mt-3 flex items-center justify-between border-t pt-2 text-xs"
                        >
                            <span>Tahun {{ fiscal_year }}</span>
                            <span class="text-muted-foreground font-medium">
                                Item Disetujui
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- KPI 3: Status Kesejahteraan -->
                <Card
                    class="border-border/70 relative overflow-hidden transition-shadow hover:shadow-sm sm:col-span-2 lg:col-span-1"
                    data-testid="kpi-welfare"
                >
                    <div
                        class="absolute top-0 right-0 left-0 h-1"
                        :class="{
                            'bg-emerald-500':
                                welfare_status?.alert_level === 'safe',
                            'bg-amber-500':
                                welfare_status?.alert_level === 'warning',
                            'bg-destructive':
                                welfare_status?.alert_level === 'danger',
                        }"
                    />
                    <CardHeader class="px-4 pt-4 pb-2 sm:px-6">
                        <div
                            class="text-muted-foreground flex items-center justify-between"
                        >
                            <span
                                class="text-xs font-semibold tracking-wider uppercase"
                                >{{ __('Status Kesejahteraan') }}</span
                            >
                            <div
                                class="rounded-lg p-2"
                                :class="{
                                    'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60':
                                        welfare_status?.alert_level === 'safe',
                                    'bg-amber-100 text-amber-600 dark:bg-amber-950/60':
                                        welfare_status?.alert_level ===
                                        'warning',
                                    'bg-destructive/15 text-destructive':
                                        welfare_status?.alert_level ===
                                        'danger',
                                }"
                            >
                                <component
                                    :is="welfareAlertConfig.icon"
                                    class="h-4 w-4"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="px-4 pb-4 sm:px-6">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <Badge
                                    variant="outline"
                                    :class="welfareAlertConfig.badgeClass"
                                    class="px-2.5 py-0.5 text-xs"
                                    data-testid="welfare-alert-badge"
                                >
                                    {{ welfareAlertConfig.label }}
                                </Badge>
                                <div class="text-muted-foreground mt-1 text-xs">
                                    {{ __('Skor Keselamatan') }}:
                                    <span
                                        class="text-foreground font-mono font-semibold tabular-nums"
                                        >{{ safetyScore }}%</span
                                    >
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-muted-foreground text-xs">
                                    {{ __('Minggu Ini:') }}
                                </div>
                                <div
                                    class="text-foreground font-mono text-sm font-bold tabular-nums"
                                >
                                    {{
                                        (
                                            welfare_status?.current_week_hours ??
                                            0
                                        ).toFixed(1)
                                    }}
                                    /
                                    {{ welfare_status?.weekly_limit ?? 14 }} jam
                                </div>
                            </div>
                        </div>

                        <div
                            class="border-border/50 text-muted-foreground mt-3 flex items-center justify-between border-t pt-2 text-xs"
                        >
                            <span
                                v-if="
                                    (welfare_status?.consecutive_weeks ?? 0) > 0
                                "
                                class="font-medium text-amber-600 dark:text-amber-400"
                            >
                                {{ welfare_status?.consecutive_weeks }} minggu
                                berturut-turut
                            </span>
                            <span
                                v-else
                                class="font-medium text-emerald-600 dark:text-emerald-400"
                            >
                                Beban kerja normal
                            </span>
                            <span class="text-muted-foreground text-[11px]"
                                >Batas:
                                {{ welfare_status?.weekly_limit ?? 14 }}
                                jam/mgg</span
                            >
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- 3. Visual Breakdown Bar (Category & Day-Type Pills) -->
            <Card
                class="border-border/70 shadow-sm"
                data-testid="breakdown-card"
            >
                <CardHeader class="px-4 pb-3 sm:px-6">
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <CardTitle class="text-base font-semibold">{{
                                __('Distribusi Kategori Lembur')
                            }}</CardTitle>
                            <CardDescription class="text-xs">
                                Rincian jam kerja lembur bulan
                                {{ currentMonthLabel }} {{ fiscal_year }}
                            </CardDescription>
                        </div>
                        <!-- Day type mini stats -->
                        <div class="flex items-center gap-2 text-xs">
                            <span
                                class="bg-muted text-foreground inline-flex items-center gap-1 rounded px-2 py-0.5 font-medium"
                            >
                                <span
                                    class="h-2 w-2 rounded-full bg-blue-500"
                                ></span>
                                HKN:
                                <strong class="font-mono tabular-nums"
                                    >{{
                                        (
                                            summary?.day_type_breakdown
                                                ?.hkn_hours ?? 0
                                        ).toFixed(1)
                                    }}
                                    jam</strong
                                >
                            </span>
                            <span
                                class="bg-muted text-foreground inline-flex items-center gap-1 rounded px-2 py-0.5 font-medium"
                            >
                                <span
                                    class="h-2 w-2 rounded-full bg-emerald-500"
                                ></span>
                                HLR:
                                <strong class="font-mono tabular-nums"
                                    >{{
                                        (
                                            summary?.day_type_breakdown
                                                ?.hlr_hours ?? 0
                                        ).toFixed(1)
                                    }}
                                    jam</strong
                                >
                            </span>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3 px-4 pb-5 sm:px-6">
                    <!-- Progress bar -->
                    <div
                        class="bg-muted flex h-3 w-full overflow-hidden rounded-full"
                    >
                        <div
                            class="bg-blue-500 transition-all duration-500"
                            :style="{
                                width: `${summary?.category_breakdown?.production_pct ?? 0}%`,
                            }"
                            :title="`Produksi: ${summary?.category_breakdown?.production ?? 0} jam (${summary?.category_breakdown?.production_pct ?? 0}%)`"
                        />
                        <div
                            class="bg-amber-500 transition-all duration-500"
                            :style="{
                                width: `${summary?.category_breakdown?.tpm_pct ?? 0}%`,
                            }"
                            :title="`TPM: ${summary?.category_breakdown?.tpm ?? 0} jam (${summary?.category_breakdown?.tpm_pct ?? 0}%)`"
                        />
                        <div
                            class="bg-purple-500 transition-all duration-500"
                            :style="{
                                width: `${summary?.category_breakdown?.project_pct ?? 0}%`,
                            }"
                            :title="`Proyek / CapEx: ${summary?.category_breakdown?.project ?? 0} jam (${summary?.category_breakdown?.project_pct ?? 0}%)`"
                        />
                        <div
                            class="bg-slate-400 transition-all duration-500 dark:bg-slate-600"
                            :style="{
                                width: `${summary?.category_breakdown?.others_pct ?? 0}%`,
                            }"
                            :title="`Lainnya: ${summary?.category_breakdown?.others ?? 0} jam (${summary?.category_breakdown?.others_pct ?? 0}%)`"
                        />
                    </div>

                    <!-- Legend Pills -->
                    <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                        <div
                            class="bg-muted/40 flex items-center gap-1.5 rounded-md p-2"
                        >
                            <span
                                class="h-2.5 w-2.5 shrink-0 rounded-full bg-blue-500"
                            ></span>
                            <span class="text-muted-foreground truncate"
                                >Produksi:</span
                            >
                            <span
                                class="text-foreground ml-auto font-mono font-semibold tabular-nums"
                            >
                                {{
                                    (
                                        summary?.category_breakdown
                                            ?.production ?? 0
                                    ).toFixed(1)
                                }}j
                            </span>
                        </div>
                        <div
                            class="bg-muted/40 flex items-center gap-1.5 rounded-md p-2"
                        >
                            <span
                                class="h-2.5 w-2.5 shrink-0 rounded-full bg-amber-500"
                            ></span>
                            <span class="text-muted-foreground truncate"
                                >TPM:</span
                            >
                            <span
                                class="text-foreground ml-auto font-mono font-semibold tabular-nums"
                            >
                                {{
                                    (
                                        summary?.category_breakdown?.tpm ?? 0
                                    ).toFixed(1)
                                }}j
                            </span>
                        </div>
                        <div
                            class="bg-muted/40 flex items-center gap-1.5 rounded-md p-2"
                        >
                            <span
                                class="h-2.5 w-2.5 shrink-0 rounded-full bg-purple-500"
                            ></span>
                            <span class="text-muted-foreground truncate"
                                >CapEx / Proyek:</span
                            >
                            <span
                                class="text-foreground ml-auto font-mono font-semibold tabular-nums"
                            >
                                {{
                                    (
                                        summary?.category_breakdown?.project ??
                                        0
                                    ).toFixed(1)
                                }}j
                            </span>
                        </div>
                        <div
                            class="bg-muted/40 flex items-center gap-1.5 rounded-md p-2"
                        >
                            <span
                                class="h-2.5 w-2.5 shrink-0 rounded-full bg-slate-400 dark:bg-slate-600"
                            ></span>
                            <span class="text-muted-foreground truncate"
                                >Lainnya:</span
                            >
                            <span
                                class="text-foreground ml-auto font-mono font-semibold tabular-nums"
                            >
                                {{
                                    (
                                        summary?.category_breakdown?.others ?? 0
                                    ).toFixed(1)
                                }}j
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 4. Recent Overtime Submissions (5 latest entries) -->
            <Card
                class="border-border/70 shadow-sm"
                data-testid="recent-submissions-card"
            >
                <CardHeader
                    class="flex flex-col gap-3 px-4 pb-3 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-2 text-base font-semibold"
                        >
                            <FileText class="text-primary h-4 w-4" />
                            {{ __('Pengajuan Lembur Terakhir') }}
                        </CardTitle>
                        <CardDescription class="text-xs">
                            {{
                                __(
                                    '5 pengajuan lembur terbaru Anda beserta status persetujuannya',
                                )
                            }}
                        </CardDescription>
                    </div>
                    <Link
                        :href="
                            showEmployeeDossier.url(
                                { npk: employee.npk },
                                { query: { tab: 'timesheet' } },
                            )
                        "
                        class="text-primary inline-flex items-center gap-1 text-xs font-semibold hover:underline"
                        data-testid="link-view-all-timesheet-top"
                    >
                        <span>Lihat Semua Riwayat</span>
                        <ArrowRight class="h-3 w-3" />
                    </Link>
                </CardHeader>
                <CardContent class="px-4 pb-4 sm:px-6">
                    <!-- Empty State -->
                    <div
                        v-if="
                            !recent_timesheet || recent_timesheet.length === 0
                        "
                        class="text-muted-foreground space-y-2 rounded-lg border border-dashed py-8 text-center text-sm"
                        data-testid="recent-timesheet-empty"
                    >
                        <Clock
                            class="text-muted-foreground/50 mx-auto h-8 w-8"
                        />
                        <p>{{ __('Belum ada riwayat pengajuan lembur.') }}</p>
                    </div>

                    <!-- Submissions List (Mobile-first stacked cards) -->
                    <div
                        v-else
                        class="space-y-3"
                        data-testid="recent-timesheet-list"
                    >
                        <div
                            v-for="item in recent_timesheet"
                            :key="item.id"
                            class="border-border/60 bg-card hover:bg-muted/20 space-y-2 rounded-lg border p-3.5 transition-colors"
                            :data-testid="`recent-item-${item.id}`"
                        >
                            <!-- Top row: Date, Day Type, Hours, Status -->
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-foreground text-sm font-semibold"
                                    >
                                        {{ item.formatted_date }}
                                    </span>
                                    <span
                                        class="text-muted-foreground hidden text-xs sm:inline"
                                    >
                                        ({{ item.day_name }})
                                    </span>
                                    <Badge
                                        variant="outline"
                                        class="px-1.5 py-0 font-mono text-[10px] font-semibold"
                                        :class="
                                            item.day_type === 'HLR'
                                                ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300'
                                                : 'border-blue-300 bg-blue-50 text-blue-700 dark:bg-blue-950/30 dark:text-blue-300'
                                        "
                                    >
                                        {{ item.day_type }}
                                    </Badge>
                                </div>

                                <div class="ml-auto flex items-center gap-2.5">
                                    <span
                                        class="text-foreground font-mono text-sm font-bold tabular-nums"
                                    >
                                        {{ item.total_hours.toFixed(1) }} jam
                                    </span>
                                    <Badge
                                        variant="outline"
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            getStatusBadge(item.status).class
                                        "
                                        :data-testid="`status-badge-${item.id}`"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="
                                                getStatusBadge(item.status)
                                                    .dotClass
                                            "
                                        />
                                        {{ getStatusBadge(item.status).label }}
                                    </Badge>
                                </div>
                            </div>

                            <!-- Middle row: Task & Code -->
                            <div
                                class="text-muted-foreground flex flex-wrap items-center justify-between gap-1 text-xs"
                            >
                                <p
                                    class="text-foreground/90 line-clamp-1 font-medium"
                                >
                                    {{
                                        item.task_description ||
                                        'Pekerjaan lembur operasional'
                                    }}
                                </p>
                                <span
                                    class="text-muted-foreground/80 font-mono text-[11px]"
                                >
                                    {{ item.submission_code }}
                                </span>
                            </div>

                            <!-- Inline Rejection Callout (E06-06 requirement) -->
                            <div
                                v-if="
                                    item.status === 'REJECTED' &&
                                    item.rejection_reason
                                "
                                class="bg-destructive/10 border-destructive/30 text-destructive mt-2 space-y-0.5 rounded-md border p-2.5 text-xs"
                                :data-testid="`rejection-callout-${item.id}`"
                            >
                                <div
                                    class="text-destructive flex items-center gap-1 font-semibold"
                                >
                                    <AlertCircle class="h-3.5 w-3.5 shrink-0" />
                                    <span>{{ __('Alasan Penolakan:') }}</span>
                                </div>
                                <p
                                    class="text-destructive/90 pl-4.5 text-xs leading-relaxed"
                                >
                                    {{ item.rejection_reason }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 5. Quick Action Touch Buttons (Ergonomic Shopfloor Navigation) -->
            <div
                class="grid grid-cols-1 gap-3.5 pt-1 sm:grid-cols-2"
                data-testid="quick-action-buttons"
            >
                <Button
                    as-child
                    size="lg"
                    class="bg-primary hover:bg-primary/90 text-primary-foreground min-h-12 w-full justify-between text-sm font-semibold shadow-sm"
                    data-testid="btn-view-full-timesheet"
                >
                    <Link
                        data-test="btn-view-full-timesheet"
                        :href="
                            showEmployeeDossier.url(
                                { npk: employee.npk },
                                { query: { tab: 'timesheet' } },
                            )
                        "
                    >
                        <span class="inline-flex items-center gap-2">
                            <FileSpreadsheet class="h-4 w-4" />
                            {{
                                __('Lihat Buku Lembur Lengkap (Buka Timesheet)')
                            }}
                        </span>
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                </Button>

                <Button
                    as-child
                    variant="outline"
                    size="lg"
                    class="text-foreground border-border hover:bg-muted min-h-12 w-full justify-between text-sm font-semibold"
                    data-testid="btn-view-welfare-overview"
                >
                    <Link
                        data-test="btn-view-welfare-overview"
                        :href="
                            showEmployeeDossier.url(
                                { npk: employee.npk },
                                { query: { tab: 'overview' } },
                            )
                        "
                    >
                        <span class="inline-flex items-center gap-2">
                            <HeartPulse class="text-primary h-4 w-4" />
                            {{ __('Lihat Grafik Kesejahteraan & Beban Kerja') }}
                        </span>
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                </Button>
            </div>
        </template>
    </div>
</template>
