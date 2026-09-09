<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    ExternalLink,
    FileText,
    Flame,
    Layers,
    Shield,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import MiniProgressBar from '@/components/dashboard/MiniProgressBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTrans } from '@/composables/useTrans';
import { show as showEmployeeDossier } from '@/routes/reports/employees';

export interface RecentShiftItem {
    submission_id: number;
    submission_code: string;
    operational_date: string;
    formatted_date: string;
    day_type: string;
    hours: number;
    spkl_number: string;
    spkl_status: string;
}

export interface EmployeeCategoryItem {
    key: 'production' | 'tpm' | 'project' | 'others';
    label: string;
    hours: number;
    percentage: number;
    color: string;
}

export interface EmployeeSummaryItem {
    id: number;
    employee_id: number;
    npk: string;
    name: string;
    full_name: string;
    job_position: string;
    department_id: number | null;
    department_name: string;
    section_id: number | null;
    section_code: string;
    section_name: string;
    total_hours: number;
    hours_production: number;
    hours_tpm: number;
    hours_project: number;
    hours_others: number;
    capex_hours: number;
    opex_hours: number;
    categories: EmployeeCategoryItem[];
    planned_hours: number;
    burn_index: number;
    burn_zone: 'safe' | 'on_track' | 'warning' | 'danger';
    burn_zone_label: string;
    burn_zone_color: string;
    spkl_status: 'approved' | 'grace_period' | 'overdue' | 'none';
    spkl_status_label: string;
    recent_shifts: RecentShiftItem[];
    consecutive_alert: boolean;
    consecutive_weeks: number;
    weekly_hours: number;
    weekly_limit_hours: number;
}

interface Props {
    open: boolean;
    employee: EmployeeSummaryItem | null;
    monthName?: string;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    employee: null,
    monthName: '',
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const { __ } = useTrans();

const capexPct = computed(() => {
    if (!props.employee || props.employee.total_hours <= 0) {
        return 0;
    }
    return Math.round(
        (props.employee.capex_hours / props.employee.total_hours) * 100,
    );
});

const opexPct = computed(() => {
    if (!props.employee || props.employee.total_hours <= 0) {
        return 0;
    }
    return Math.round(
        (props.employee.opex_hours / props.employee.total_hours) * 100,
    );
});

const zoneBadgeClass = computed(() => {
    switch (props.employee?.burn_zone) {
        case 'danger':
            return 'bg-red-50 text-[#cc0000] border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900';
        case 'warning':
            return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900';
        case 'on_track':
            return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900';
        case 'safe':
        default:
            return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900';
    }
});

const spklBadgeClass = computed(() => {
    switch (props.employee?.spkl_status) {
        case 'overdue':
            return 'bg-red-100 text-red-800 border-red-300 dark:bg-red-950/50 dark:text-red-300 dark:border-red-800';
        case 'grace_period':
            return 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800';
        case 'approved':
            return 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800';
        case 'none':
        default:
            return 'bg-slate-100 text-slate-600 border-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700';
    }
});
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent
            side="right"
            class="w-full overflow-y-auto p-6 sm:max-w-xl"
            data-test="employee-quick-dossier-drawer"
        >
            <div v-if="employee" class="space-y-6">
                <!-- Drawer Header -->
                <SheetHeader class="space-y-2 text-left">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded-md border border-slate-300 bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    data-test="drawer-npk-badge"
                                >
                                    {{ employee.npk }}
                                </span>
                                <Badge
                                    variant="outline"
                                    class="text-xs font-semibold"
                                >
                                    {{ employee.job_position }}
                                </Badge>
                            </div>
                            <SheetTitle
                                class="text-foreground mt-1 text-xl font-bold tracking-tight"
                                data-test="drawer-employee-name"
                            >
                                {{ employee.name }}
                            </SheetTitle>
                        </div>
                    </div>

                    <SheetDescription
                        class="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs"
                    >
                        <span class="flex items-center gap-1">
                            <Building2 class="size-3.5 text-slate-400" />
                            {{ employee.department_name }}
                        </span>
                        <span>•</span>
                        <span class="flex items-center gap-1">
                            <Layers class="size-3.5 text-slate-400" />
                            {{ employee.section_name }} ({{
                                employee.section_code
                            }})
                        </span>
                        <span v-if="monthName">•</span>
                        <span v-if="monthName" class="flex items-center gap-1">
                            <Calendar class="size-3.5 text-slate-400" />
                            {{ monthName }}
                        </span>
                    </SheetDescription>
                </SheetHeader>

                <!-- Current Month Summary Card -->
                <Card class="border-border/70 shadow-2xs">
                    <CardHeader class="pb-3">
                        <CardTitle
                            class="flex items-center justify-between text-sm font-semibold"
                        >
                            <span class="flex items-center gap-1.5">
                                <Clock class="text-primary size-4" />
                                {{ __('Ringkasan Lembur Bulan Ini') }}
                            </span>
                            <Badge
                                variant="outline"
                                :class="['text-xs font-bold', zoneBadgeClass]"
                                data-test="drawer-burn-zone-badge"
                            >
                                {{ employee.burn_zone_label }}
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <!-- Hours & Burn Index Row -->
                        <div
                            class="grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-900/60"
                        >
                            <div>
                                <span
                                    class="text-muted-foreground block text-xs"
                                >
                                    {{ __('Total Jam Disetujui') }}
                                </span>
                                <span
                                    class="text-foreground font-mono text-xl font-bold tabular-nums"
                                    data-test="drawer-total-hours"
                                >
                                    {{ employee.total_hours.toFixed(1) }}
                                    <span
                                        class="text-muted-foreground text-xs font-normal"
                                        >jam</span
                                    >
                                </span>
                            </div>
                            <div>
                                <span
                                    class="text-muted-foreground block text-xs"
                                >
                                    {{ __('Alokasi Rencana') }}
                                </span>
                                <span
                                    class="text-foreground font-mono text-xl font-bold tabular-nums"
                                >
                                    {{ employee.planned_hours.toFixed(1) }}
                                    <span
                                        class="text-muted-foreground text-xs font-normal"
                                        >jam</span
                                    >
                                </span>
                            </div>
                        </div>

                        <!-- Mini Progress Bar for Burn Index -->
                        <div class="space-y-1.5">
                            <div
                                class="flex items-center justify-between text-xs"
                            >
                                <span
                                    class="text-muted-foreground flex items-center gap-1"
                                >
                                    <Flame class="size-3.5 text-amber-500" />
                                    {{ __('Indeks Burn Individu') }}
                                </span>
                                <span
                                    class="font-mono text-xs font-bold tabular-nums"
                                >
                                    {{ employee.burn_index.toFixed(1) }}%
                                </span>
                            </div>
                            <MiniProgressBar
                                :percentage="employee.burn_index"
                                :zone="employee.burn_zone"
                                :show-label="false"
                                height-class="h-2.5"
                            />
                        </div>

                        <!-- CapEx vs OpEx Split Bar -->
                        <div class="border-border/50 space-y-2 border-t pt-1">
                            <div
                                class="flex items-center justify-between text-xs font-medium"
                            >
                                <span class="text-muted-foreground">{{
                                    __('Pemisahan CapEx vs OpEx')
                                }}</span>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1 font-mono text-xs font-semibold text-sky-700 tabular-nums dark:text-sky-300"
                                    >
                                        <span
                                            class="size-2 rounded-full bg-sky-500"
                                        ></span>
                                        CapEx:
                                        {{ employee.capex_hours.toFixed(1) }}j
                                        ({{ capexPct }}%)
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1 font-mono text-xs font-semibold text-slate-700 tabular-nums dark:text-slate-300"
                                    >
                                        <span
                                            class="size-2 rounded-full bg-slate-400"
                                        ></span>
                                        OpEx:
                                        {{ employee.opex_hours.toFixed(1) }}j
                                        ({{ opexPct }}%)
                                    </span>
                                </div>
                            </div>
                            <div
                                class="flex h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                            >
                                <div
                                    class="h-full bg-sky-500 transition-all duration-300"
                                    :style="{ width: `${capexPct}%` }"
                                    :title="`CapEx: ${employee.capex_hours}j`"
                                />
                                <div
                                    class="h-full bg-slate-400 transition-all duration-300"
                                    :style="{ width: `${opexPct}%` }"
                                    :title="`OpEx: ${employee.opex_hours}j`"
                                />
                            </div>
                        </div>

                        <!-- 4-Category Breakdown -->
                        <div class="border-border/50 space-y-1.5 border-t pt-1">
                            <span
                                class="text-muted-foreground block text-xs font-medium"
                            >
                                {{ __('Distribusi Jam per Kategori') }}
                            </span>
                            <div
                                class="grid grid-cols-2 gap-2 font-mono text-xs"
                            >
                                <div
                                    v-for="cat in employee.categories"
                                    :key="cat.key"
                                    class="flex items-center justify-between rounded-md bg-slate-50 p-2 dark:bg-slate-800/60"
                                >
                                    <span
                                        class="flex items-center gap-1.5 font-sans text-slate-600 dark:text-slate-400"
                                    >
                                        <span
                                            class="size-2 rounded-full"
                                            :style="{
                                                backgroundColor: cat.color,
                                            }"
                                        />
                                        {{ cat.label }}
                                    </span>
                                    <span
                                        class="text-foreground font-bold tabular-nums"
                                    >
                                        {{ cat.hours.toFixed(1) }}j
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Policy Compliance & Fatigue Indicators -->
                <Card
                    :class="[
                        'border shadow-2xs',
                        employee.consecutive_alert
                            ? 'border-amber-300 bg-amber-50/40 dark:border-amber-800 dark:bg-amber-950/20'
                            : 'border-border/70',
                    ]"
                >
                    <CardHeader class="pb-2">
                        <CardTitle
                            class="flex items-center justify-between text-sm font-semibold"
                        >
                            <span class="flex items-center gap-1.5">
                                <Shield class="text-primary size-4" />
                                {{ __('Kepatuhan Batas Kebijakan') }}
                            </span>
                            <Badge
                                variant="outline"
                                :class="[
                                    'text-xs font-semibold',
                                    spklBadgeClass,
                                ]"
                                data-test="drawer-spkl-status-badge"
                            >
                                <FileText class="mr-1 size-3" />
                                {{ employee.spkl_status_label }}
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-xs">
                        <div
                            v-if="employee.consecutive_alert"
                            class="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-100/70 p-2.5 text-amber-900 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-200"
                            data-test="drawer-fatigue-alert"
                        >
                            <AlertTriangle
                                class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                            />
                            <div>
                                <span class="block font-bold">
                                    {{
                                        __(
                                            'Peringatan Kelelahan (Fatigue Alert)',
                                        )
                                    }}
                                </span>
                                <span>
                                    {{
                                        __(
                                            'Karyawan telah bekerja lembur melampaui batas aman mingguan selama :weeks minggu berturut-turut.',
                                            {
                                                weeks: employee.consecutive_weeks,
                                            },
                                        )
                                    }}
                                </span>
                            </div>
                        </div>

                        <div
                            v-else
                            class="flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50/60 p-2.5 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                        >
                            <CheckCircle2
                                class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                            />
                            <span>
                                {{
                                    __(
                                        'Kepatuhan kebijakan jam lembur dalam status aman.',
                                    )
                                }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between pt-1 text-slate-600 dark:text-slate-400"
                        >
                            <span>{{ __('Lembur Minggu Ini') }}:</span>
                            <span
                                class="text-foreground font-mono font-bold tabular-nums"
                            >
                                {{ employee.weekly_hours.toFixed(1) }} /
                                {{ employee.weekly_limit_hours.toFixed(1) }} jam
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Shift History (Last 5 Shifts) -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4
                            class="text-muted-foreground flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase"
                        >
                            <Activity class="size-3.5" />
                            {{ __('Riwayat 5 Shift Lembur Terakhir') }}
                        </h4>
                        <span class="text-muted-foreground text-xs">
                            {{ employee.recent_shifts.length }}
                            {{ __('shift tercatat') }}
                        </span>
                    </div>

                    <div
                        v-if="employee.recent_shifts.length > 0"
                        class="border-border/70 overflow-hidden rounded-lg border"
                    >
                        <table
                            class="w-full text-left text-xs"
                            data-test="drawer-recent-shifts-table"
                        >
                            <thead
                                class="text-muted-foreground bg-slate-50 dark:bg-slate-800/80"
                            >
                                <tr>
                                    <th class="px-3 py-2 font-medium">
                                        {{ __('Tanggal') }}
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        {{ __('Tipe') }}
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        {{ __('Jam') }}
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        {{ __('No. SPKL') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-border/60 divide-y">
                                <tr
                                    v-for="shift in employee.recent_shifts"
                                    :key="shift.submission_id"
                                    class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50"
                                >
                                    <td
                                        class="text-foreground px-3 py-2 font-medium whitespace-nowrap"
                                    >
                                        {{ shift.formatted_date }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <Badge
                                            variant="outline"
                                            :class="[
                                                'px-1.5 py-0 text-[10px] font-bold',
                                                shift.day_type === 'HLR'
                                                    ? 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
                                                    : 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300',
                                            ]"
                                        >
                                            {{ shift.day_type }}
                                        </Badge>
                                    </td>
                                    <td
                                        class="text-foreground px-3 py-2 font-mono font-bold whitespace-nowrap tabular-nums"
                                    >
                                        {{ shift.hours.toFixed(1) }}j
                                    </td>
                                    <td
                                        class="text-muted-foreground px-3 py-2 font-mono text-[11px] whitespace-nowrap"
                                    >
                                        {{ shift.spkl_number }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-else
                        class="border-border text-muted-foreground rounded-lg border border-dashed p-4 text-center text-xs"
                    >
                        {{
                            __(
                                'Belum ada riwayat shift lembur disetujui pada bulan ini.',
                            )
                        }}
                    </div>
                </div>

                <!-- Footer Navigation -->
                <SheetFooter
                    class="border-border/70 flex flex-col-reverse gap-2 border-t pt-2 sm:flex-row"
                >
                    <SheetClose as-child>
                        <Button variant="outline" class="w-full sm:w-auto">
                            {{ __('Tutup') }}
                        </Button>
                    </SheetClose>
                    <Link
                        :href="showEmployeeDossier({ npk: employee.npk }).url"
                        class="inline-flex w-full sm:w-auto"
                    >
                        <Button
                            class="w-full bg-[#cc0000] text-white shadow-xs transition-all hover:bg-[#b30000] active:scale-95"
                            data-test="open-full-dossier-button"
                        >
                            <ExternalLink class="mr-1.5 size-4" />
                            {{ __('Buka Dossier Lengkap') }}
                        </Button>
                    </Link>
                </SheetFooter>
            </div>
        </SheetContent>
    </Sheet>
</template>
