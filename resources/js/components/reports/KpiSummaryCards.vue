<script setup lang="ts">
import {
    Award,
    Banknote,
    Calendar,
    Clock,
    Flame,
    HelpCircle,
    Info,
} from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';

export interface EmployeeSummaryMetrics {
    current_month_hours: number;
    ytd_hours: number;
    burn_index: number | null;
    individual_planned_hours: number | null;
    dept_rank: {
        rank: number;
        total_employees: number;
    };
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
        total: number;
        hkn_pct: number;
        hlr_pct: number;
    };
    total_cost_idr: number;
}

const props = defineProps<{
    summary: EmployeeSummaryMetrics;
    fiscalYear: number;
    fiscalMonth: number;
}>();

const { __ } = useTrans();

const burnStatus = computed(() => {
    if (props.summary.burn_index === null) {
        return {
            label: 'N/A',
            badgeClass:
                'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
            indicatorText: __(
                'Target kuota lembur diatur pada tingkat seksi, bukan per individu.',
            ),
        };
    }

    const val = props.summary.burn_index;
    if (val > 100) {
        return {
            label: `${val.toFixed(1)}%`,
            badgeClass:
                'bg-red-50 text-[#cc0000] border-red-300 dark:bg-red-950/60 dark:text-red-300 dark:border-red-800',
            indicatorText: __('Melebihi alokasi rencana'),
        };
    }
    if (val >= 85) {
        return {
            label: `${val.toFixed(1)}%`,
            badgeClass:
                'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
            indicatorText: __('Mendekati batas alokasi'),
        };
    }
    return {
        label: `${val.toFixed(1)}%`,
        badgeClass:
            'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
        indicatorText: __('Dalam alokasi aman'),
    };
});
</script>

<template>
    <div class="flex flex-col gap-4" data-test="kpi-cards">
        <!-- 4 KPI Cards Grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- 1. Jam Lembur Bulan Ini -->
            <Card class="border-border shadow-xs" data-test="kpi-current-month">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle
                        class="text-muted-foreground text-xs font-medium"
                    >
                        {{ __('Jam Lembur Bulan Ini') }}
                    </CardTitle>
                    <div
                        class="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-lg"
                    >
                        <Clock class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-baseline gap-1.5">
                        <span
                            class="font-mono text-2xl font-black tracking-tight text-slate-900 tabular-nums dark:text-white"
                        >
                            {{ summary.current_month_hours.toFixed(1) }}
                        </span>
                        <span
                            class="text-muted-foreground text-xs font-semibold"
                        >
                            {{ __('jam') }}
                        </span>
                    </div>
                    <p class="text-muted-foreground mt-1 text-[11px]">
                        {{ __('Item lembur yang telah disetujui') }}
                    </p>
                </CardContent>
            </Card>

            <!-- 2. Jam Lembur Tahun Berjalan (YTD) -->
            <Card class="border-border shadow-xs" data-test="kpi-ytd">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle
                        class="text-muted-foreground text-xs font-medium"
                    >
                        {{ __('Jam Lembur Tahun Berjalan (YTD)') }}
                    </CardTitle>
                    <div
                        class="flex size-8 items-center justify-center rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300"
                    >
                        <Calendar class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-baseline gap-1.5">
                        <span
                            class="font-mono text-2xl font-black tracking-tight text-slate-900 tabular-nums dark:text-white"
                        >
                            {{ summary.ytd_hours.toFixed(1) }}
                        </span>
                        <span
                            class="text-muted-foreground text-xs font-semibold"
                        >
                            {{ __('jam') }}
                        </span>
                    </div>
                    <p class="text-muted-foreground mt-1 text-[11px]">
                        {{ __('Akumulasi tahun :year', { year: fiscalYear }) }}
                    </p>
                </CardContent>
            </Card>

            <!-- 3. Indeks Burn Individu -->
            <Card class="border-border shadow-xs" data-test="kpi-burn-index">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <div class="flex items-center gap-1.5">
                        <CardTitle
                            class="text-muted-foreground text-xs font-medium"
                        >
                            {{ __('Indeks Burn Individu') }}
                        </CardTitle>
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <button
                                        type="button"
                                        class="text-muted-foreground/70 hover:text-foreground inline-flex cursor-help items-center"
                                        :aria-label="
                                            __('Informasi Indeks Burn Individu')
                                        "
                                    >
                                        <HelpCircle class="size-3.5" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent class="max-w-xs text-xs">
                                    <p>
                                        {{
                                            summary.burn_index !== null
                                                ? __(
                                                      'Dihitung dari rasio jam lembur individu terhadap rata-rata kuota anggaran seksi (:hours jam).',
                                                      {
                                                          hours:
                                                              summary.individual_planned_hours?.toFixed(
                                                                  1,
                                                              ) ?? '0.0',
                                                      },
                                                  )
                                                : __(
                                                      'Target kuota lembur diatur pada tingkat seksi, bukan per individu.',
                                                  )
                                        }}
                                    </p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div
                        class="flex size-8 items-center justify-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
                    >
                        <Flame class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center justify-between">
                        <div class="flex items-baseline gap-1.5">
                            <span
                                class="font-mono text-2xl font-black tracking-tight text-slate-900 tabular-nums dark:text-white"
                            >
                                {{
                                    summary.burn_index !== null
                                        ? `${summary.burn_index.toFixed(1)}%`
                                        : 'N/A'
                                }}
                            </span>
                        </div>
                        <Badge
                            variant="outline"
                            :class="[
                                'text-xs font-semibold',
                                burnStatus.badgeClass,
                            ]"
                        >
                            {{
                                summary.burn_index !== null
                                    ? burnStatus.indicatorText
                                    : 'N/A'
                            }}
                        </Badge>
                    </div>
                    <p class="text-muted-foreground mt-1 truncate text-[11px]">
                        {{
                            summary.individual_planned_hours !== null
                                ? __('Alokasi per orang: :hours jam', {
                                      hours: summary.individual_planned_hours.toFixed(
                                          1,
                                      ),
                                  })
                                : __('Belum ada kuota seksi')
                        }}
                    </p>
                </CardContent>
            </Card>

            <!-- 4. Peringkat Seksi -->
            <Card class="border-border shadow-xs" data-test="kpi-dept-rank">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle
                        class="text-muted-foreground text-xs font-medium"
                    >
                        {{ __('Peringkat Beban Seksi') }}
                    </CardTitle>
                    <div
                        class="flex size-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
                    >
                        <Award class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-baseline gap-1.5">
                        <span
                            class="text-muted-foreground text-xs font-semibold"
                        >
                            {{ __('Ke-') }}
                        </span>
                        <span
                            class="font-mono text-2xl font-black tracking-tight text-slate-900 tabular-nums dark:text-white"
                        >
                            {{ summary.dept_rank.rank }}
                        </span>
                        <span
                            class="text-muted-foreground font-mono text-xs tabular-nums"
                        >
                            / {{ summary.dept_rank.total_employees }}
                        </span>
                    </div>
                    <p class="text-muted-foreground mt-1 text-[11px]">
                        {{
                            __('Dari :total karyawan di seksi bulan ini', {
                                total: summary.dept_rank.total_employees,
                            })
                        }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Financial Cost Snapshot Banner -->
        <div
            data-test="kpi-cost-snapshot"
            class="border-border bg-card flex flex-col justify-between gap-3 rounded-xl border p-4 shadow-2xs sm:flex-row sm:items-center"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
                >
                    <Banknote class="size-5" />
                </div>
                <div>
                    <div class="text-muted-foreground text-xs font-medium">
                        {{ __('Total Estimasi Biaya Lembur') }}
                    </div>
                    <div
                        class="font-mono text-lg font-bold text-emerald-700 tabular-nums sm:text-xl dark:text-emerald-400"
                    >
                        {{
                            formatRupiah(summary.total_cost_idr, {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0,
                            })
                        }}
                    </div>
                </div>
            </div>

            <div
                class="text-muted-foreground/80 flex items-center gap-1.5 text-xs sm:self-center"
            >
                <Info class="size-3.5 shrink-0" />
                <span>
                    {{
                        __(
                            'Berdasarkan akumulasi snapshot tarif saat pengajuan disetujui.',
                        )
                    }}
                </span>
            </div>
        </div>
    </div>
</template>
