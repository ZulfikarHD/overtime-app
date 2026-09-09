<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    ArrowUp,
    ArrowUpRight,
    Calendar,
    Clock,
    Flame,
    LineChart as LineChartIcon,
    MoveRight,
    RefreshCw,
    Sparkles,
    Table,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BudgetMatrixScatter, {
    type ScatterPlotData,
} from '@/components/dashboard/BudgetMatrixScatter.vue';
import BurndownLineChart, {
    type WeekBurndownItem,
} from '@/components/dashboard/BurndownLineChart.vue';
import WeeklyBreakdownTable from '@/components/dashboard/WeeklyBreakdownTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/composables/useTrans';
import { planning } from '@/routes/budgets';
import { show as showBurndownRoute } from '@/routes/dashboard/burn-index';

export interface SectionWeeklyDetail {
    section: {
        id: number;
        code: string;
        name: string;
        department_id: number;
        department_name: string;
        department_code: string;
    };
    fiscal_year: number;
    fiscal_month: number;
    is_budget_configured: boolean;
    summary: {
        planned_hours: number;
        actual_hours: number;
        remaining_hours: number;
        burn_index_pct: number;
        burn_zone:
            | 'ZONE_1_EXCELLENT'
            | 'ZONE_2_GOOD'
            | 'ZONE_3_WARNING'
            | 'ZONE_4_POOR';
        burn_velocity: number;
        projected_total_hours: number;
        trajectory: 'on_pace' | 'trending_over' | 'will_overrun';
        last_recalculated_at: string | null;
    };
    weeks: WeekBurndownItem[];
    ml_trajectory: (number | null)[] | null;
    ml_forecast: {
        predicted_value: number;
        confidence_interval_lower: number | null;
        confidence_interval_upper: number | null;
        confidence_delta: number | null;
        risk_level: string | null;
        fallback_used: boolean;
    } | null;
    scatter_plot: ScatterPlotData;
}

const props = defineProps<{
    open: boolean;
    sectionId: number | null;
    fiscalYear: number;
    fiscalMonth: number;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const { __ } = useTrans();

const isLoading = ref(false);
const error = ref<string | null>(null);
const detailData = ref<SectionWeeklyDetail | null>(null);

async function loadSectionBurndown() {
    if (!props.sectionId) {
        detailData.value = null;
        return;
    }

    isLoading.value = true;
    error.value = null;

    try {
        const url = showBurndownRoute.url(
            { section: props.sectionId },
            {
                query: {
                    year: props.fiscalYear,
                    month: props.fiscalMonth,
                },
            },
        );

        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(
                __('Gagal memuat data burndown seksi. Status: :status', {
                    status: response.status,
                }),
            );
        }

        const data: SectionWeeklyDetail = await response.json();
        detailData.value = data;
    } catch (err: any) {
        error.value = err?.message || __('Terjadi kesalahan jaringan.');
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => [props.open, props.sectionId, props.fiscalYear, props.fiscalMonth],
    ([isOpen]) => {
        if (isOpen && props.sectionId) {
            loadSectionBurndown();
        } else if (!isOpen) {
            detailData.value = null;
            error.value = null;
        }
    },
    { immediate: true },
);

const burnBadge = computed(() => {
    if (!detailData.value) {
        return { label: '', class: '' };
    }
    const pct = detailData.value.summary.burn_index_pct;
    if (!detailData.value.is_budget_configured) {
        return {
            label: __('Belum Dikonfigurasi'),
            class: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        };
    }
    if (pct > 115) {
        return {
            label: `${pct.toFixed(1)}% • ${__('Defisit')}`,
            class: 'bg-red-50 text-[#cc0000] border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900',
        };
    }
    if (pct > 100) {
        return {
            label: `${pct.toFixed(1)}% • ${__('Peringatan')}`,
            class: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900',
        };
    }
    if (pct >= 85) {
        return {
            label: `${pct.toFixed(1)}% • ${__('Terkendali')}`,
            class: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-900',
        };
    }
    return {
        label: `${pct.toFixed(1)}% • ${__('Aman')}`,
        class: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900',
    };
});

const trajectoryBadge = computed(() => {
    if (!detailData.value) {
        return { label: '', class: '', icon: MoveRight };
    }
    switch (detailData.value.summary.trajectory) {
        case 'trending_over':
            return {
                label: __('↗ Waspada (Trending Over)'),
                class: 'text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800',
                icon: ArrowUpRight,
            };
        case 'will_overrun':
            return {
                label: __('↑ Kritis (Will Overrun)'),
                class: 'text-[#cc0000] dark:text-red-400 bg-red-50 dark:bg-red-950/40 border-red-200 dark:border-red-900',
                icon: ArrowUp,
            };
        case 'on_pace':
        default:
            return {
                label: __('→ Aman (On Pace)'),
                class: 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800',
                icon: MoveRight,
            };
    }
});

const formattedLastRecalculated = computed(() => {
    if (!detailData.value?.summary.last_recalculated_at) {
        return '-';
    }
    try {
        const d = new Date(detailData.value.summary.last_recalculated_at);
        return (
            d.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                timeZone: 'Asia/Jakarta',
            }) + ' WIB'
        );
    } catch {
        return '-';
    }
});
</script>

<template>
    <Sheet :open="open" @update:open="(val) => emit('update:open', val)">
        <SheetContent
            side="right"
            class="flex max-h-screen w-full flex-col overflow-y-auto p-4 sm:max-w-2xl sm:p-6 lg:max-w-3xl"
            data-test="section-burndown-sheet"
        >
            <!-- Drawer Header -->
            <SheetHeader
                class="space-y-1.5 border-b border-slate-200 pb-4 dark:border-slate-800"
            >
                <div class="flex items-start justify-between gap-3 pr-6">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <SheetTitle
                                class="text-lg font-bold text-slate-900 dark:text-white"
                            >
                                {{
                                    detailData?.section.name ||
                                    __('Detail Burndown Seksi')
                                }}
                            </SheetTitle>
                            <Badge
                                v-if="detailData?.section.code"
                                variant="outline"
                                class="px-1.5 py-0 font-mono text-xs"
                            >
                                {{ detailData.section.code }}
                            </Badge>
                        </div>
                        <SheetDescription class="text-muted-foreground text-xs">
                            {{ detailData?.section.department_name }} •
                            {{ __('Periode') }}:
                            <span class="font-mono font-semibold"
                                >{{ fiscalMonth }}/{{ fiscalYear }}</span
                            >
                        </SheetDescription>
                    </div>

                    <!-- Burn Index Badge -->
                    <Badge
                        v-if="detailData"
                        variant="outline"
                        class="border px-2.5 py-1 text-xs font-bold"
                        :class="burnBadge.class"
                        data-test="sheet-burn-badge"
                    >
                        {{ burnBadge.label }}
                    </Badge>
                </div>
            </SheetHeader>

            <!-- Loading State -->
            <div
                v-if="isLoading"
                class="flex flex-1 flex-col items-center justify-center space-y-3 py-20"
                data-test="sheet-loading-spinner"
            >
                <Spinner class="size-8 text-[#cc0000]" />
                <p class="text-muted-foreground text-xs font-medium">
                    {{
                        __('Memuat data kurva burndown dan matriks kontrol...')
                    }}
                </p>
            </div>

            <!-- Error State -->
            <div
                v-else-if="error"
                class="my-8 rounded-lg border border-red-200 bg-red-50/60 p-6 text-center text-xs dark:border-red-900 dark:bg-red-950/40"
                data-test="sheet-error-state"
            >
                <AlertCircle class="mx-auto size-8 text-[#cc0000]" />
                <h4
                    class="mt-2 text-sm font-bold text-red-900 dark:text-red-200"
                >
                    {{ __('Gagal Memuat Data') }}
                </h4>
                <p class="mt-1 text-red-700 dark:text-red-300">
                    {{ error }}
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    class="mt-4 gap-1.5 text-xs"
                    @click="loadSectionBurndown"
                >
                    <RefreshCw class="size-3.5" />
                    {{ __('Coba Lagi') }}
                </Button>
            </div>

            <!-- Loaded Content -->
            <div
                v-else-if="detailData"
                class="flex-1 space-y-6 py-2"
                data-test="sheet-content-loaded"
            >
                <!-- Unconfigured Budget Notice -->
                <div
                    v-if="!detailData.is_budget_configured"
                    class="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50/70 p-3.5 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                    data-test="sheet-unconfigured-budget-banner"
                >
                    <AlertCircle
                        class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                    />
                    <div class="space-y-1">
                        <p class="font-bold">
                            {{ __('Anggaran Belum Dikonfigurasi') }}
                        </p>
                        <p
                            class="text-[11px] leading-relaxed text-amber-800 dark:text-amber-300"
                        >
                            {{
                                __(
                                    'Seksi ini belum memiliki target kuota lembur untuk bulan ini. Garis rencana pada grafik bernilai 0 jam.',
                                )
                            }}
                        </p>
                        <Link
                            :href="
                                planning.url({
                                    query: {
                                        department_id:
                                            detailData.section.department_id,
                                        year: fiscalYear,
                                        month: fiscalMonth,
                                    },
                                })
                            "
                            class="inline-flex items-center gap-1 font-semibold text-amber-900 underline hover:text-amber-950 dark:text-amber-200"
                        >
                            {{ __('Atur Anggaran di Planning →') }}
                        </Link>
                    </div>
                </div>

                <!-- KPI Summary Bar (4 Cards) -->
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                    <!-- Planned vs Actual -->
                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <span class="text-muted-foreground text-[10px]">{{
                            __('Rencana vs Realisasi')
                        }}</span>
                        <div
                            class="mt-0.5 font-mono text-xs font-bold tabular-nums"
                        >
                            {{ detailData.summary.actual_hours.toFixed(1) }} /
                            {{ detailData.summary.planned_hours.toFixed(1) }}
                            <span
                                class="text-muted-foreground text-[10px] font-normal"
                                >j</span
                            >
                        </div>
                    </div>

                    <!-- Remaining Hours -->
                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <span class="text-muted-foreground text-[10px]">{{
                            __('Sisa Kuota')
                        }}</span>
                        <div
                            class="mt-0.5 font-mono text-xs font-bold tabular-nums"
                            :class="
                                detailData.summary.remaining_hours < 0
                                    ? 'text-[#cc0000]'
                                    : 'text-emerald-600'
                            "
                        >
                            {{ detailData.summary.remaining_hours.toFixed(1) }}
                            <span
                                class="text-muted-foreground text-[10px] font-normal"
                                >j</span
                            >
                        </div>
                    </div>

                    <!-- Velocity -->
                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <span class="text-muted-foreground text-[10px]">{{
                            __('Kecepatan Burn')
                        }}</span>
                        <div
                            class="mt-0.5 font-mono text-xs font-bold tabular-nums"
                        >
                            {{ detailData.summary.burn_velocity.toFixed(1) }}
                            <span
                                class="text-muted-foreground text-[10px] font-normal"
                                >j/mgg</span
                            >
                        </div>
                    </div>

                    <!-- Trajectory -->
                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <span class="text-muted-foreground text-[10px]">{{
                            __('Proyeksi Akhir')
                        }}</span>
                        <div class="mt-0.5 flex items-center gap-1.5">
                            <span
                                class="font-mono text-xs font-bold tabular-nums"
                            >
                                {{
                                    detailData.summary.projected_total_hours.toFixed(
                                        1,
                                    )
                                }}j
                            </span>
                            <span
                                class="inline-flex items-center rounded-sm border px-1 py-0 text-[9px] font-semibold"
                                :class="trajectoryBadge.class"
                            >
                                <component
                                    :is="trajectoryBadge.icon"
                                    class="mr-0.5 size-2.5"
                                />
                                {{
                                    detailData.summary.trajectory === 'on_pace'
                                        ? __('Aman')
                                        : detailData.summary.trajectory ===
                                            'trending_over'
                                          ? __('Waspada')
                                          : __('Kritis')
                                }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- SECTION 1: 5-WEEK BURNDOWN LINE CHART -->
                <div
                    class="space-y-2 rounded-xl border border-slate-200 p-4 dark:border-slate-800"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-md bg-red-50 p-1 text-[#cc0000] dark:bg-red-950/60"
                            >
                                <LineChartIcon class="size-4" />
                            </div>
                            <div>
                                <h3
                                    class="text-xs font-bold text-slate-900 dark:text-white"
                                >
                                    {{ __('Kurva Burndown 5-Minggu') }}
                                </h3>
                                <p class="text-muted-foreground text-[10px]">
                                    {{
                                        __(
                                            'Perbandingan akumulasi jam rencana vs realisasi disetujui',
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- ML Badge if model present -->
                        <div
                            v-if="detailData.ml_forecast"
                            class="flex items-center gap-1 rounded border border-violet-200 bg-violet-50 px-2 py-0.5 font-mono text-[10px] font-medium text-violet-800 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-300"
                        >
                            <Sparkles class="size-3 text-violet-600" />
                            <span
                                >AI:
                                {{
                                    detailData.ml_forecast.predicted_value.toFixed(
                                        1,
                                    )
                                }}j</span
                            >
                        </div>
                    </div>

                    <BurndownLineChart
                        :weeks="detailData.weeks"
                        :planned-budget-hours="detailData.summary.planned_hours"
                        :burn-index-pct="detailData.summary.burn_index_pct"
                        :ml-trajectory="detailData.ml_trajectory"
                    />
                </div>

                <!-- SECTION 2: TABULAR WEEKLY BREAKDOWN -->
                <div
                    class="space-y-2 rounded-xl border border-slate-200 p-4 dark:border-slate-800"
                >
                    <div
                        class="flex items-center gap-2 border-b border-slate-100 pb-2 dark:border-slate-800"
                    >
                        <div
                            class="rounded-md bg-slate-100 p-1 text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            <Table class="size-4" />
                        </div>
                        <div>
                            <h3
                                class="text-xs font-bold text-slate-900 dark:text-white"
                            >
                                {{ __('Rincian Jam Lembur Mingguan') }}
                            </h3>
                            <p class="text-muted-foreground text-[10px]">
                                {{
                                    __(
                                        'Distribusi jam rencana, realisasi, lembur normal (HKN), dan hari libur (HLR)',
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <WeeklyBreakdownTable :weeks="detailData.weeks" />
                </div>

                <!-- SECTION 3: 4-QUADRANT BUDGET CONTROL MATRIX -->
                <div
                    class="space-y-2 rounded-xl border border-slate-200 p-4 dark:border-slate-800"
                >
                    <div
                        class="flex items-center gap-2 border-b border-slate-100 pb-2 dark:border-slate-800"
                    >
                        <div
                            class="rounded-md bg-emerald-50 p-1 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
                        >
                            <Activity class="size-4" />
                        </div>
                        <div>
                            <h3
                                class="text-xs font-bold text-slate-900 dark:text-white"
                            >
                                {{ __('Matriks Kontrol Anggaran (4-Kuadran)') }}
                            </h3>
                            <p class="text-muted-foreground text-[10px]">
                                {{
                                    __(
                                        'Posisi koordinat burn index (%) terhadap realisasi jam kerja kumulatif',
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <BudgetMatrixScatter
                        :scatter-data="detailData.scatter_plot"
                        :section-name="detailData.section.name"
                    />
                </div>
            </div>

            <!-- Drawer Footer -->
            <SheetFooter
                v-if="detailData"
                class="flex flex-row items-center justify-between border-t border-slate-200 pt-3 dark:border-slate-800"
            >
                <div
                    class="text-muted-foreground flex items-center gap-1.5 text-[11px]"
                >
                    <Clock class="size-3 text-slate-400" />
                    <span>{{ __('Terakhir diperbarui') }}:</span>
                    <span class="font-mono tabular-nums">{{
                        formattedLastRecalculated
                    }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="gap-1 text-xs"
                        :disabled="isLoading"
                        @click="loadSectionBurndown"
                    >
                        <RefreshCw
                            class="size-3"
                            :class="isLoading ? 'animate-spin' : ''"
                        />
                        {{ __('Segarkan') }}
                    </Button>
                    <SheetClose as-child>
                        <Button
                            variant="secondary"
                            size="sm"
                            class="text-xs"
                            data-test="btn-close-sheet"
                        >
                            {{ __('Tutup') }}
                        </Button>
                    </SheetClose>
                </div>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
