<script setup lang="ts">
import {
    Activity,
    AlertCircle,
    Calendar,
    CircleHelp,
    Coins,
    FolderKanban,
    Layers,
    PieChart,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CapexOpexDonutChart from '@/components/dashboard/CapexOpexDonutChart.vue';
import CapexOpexSectionBarChart, {
    type SectionCapexOpexItem,
} from '@/components/dashboard/CapexOpexSectionBarChart.vue';
import CapexProjectTable, {
    type CapexProjectItem,
} from '@/components/dashboard/CapexProjectTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';

export interface CapexOpexSummary {
    total_hours: number;
    capex_hours: number;
    opex_hours: number;
    capex_ratio_pct: number;
    opex_ratio_pct: number;
    capex_cost_idr: number;
    opex_cost_idr: number;
    total_cost_idr: number;
    range_type: string;
    start_date: string;
    end_date: string;
}

export interface CapexOpexData {
    summary: CapexOpexSummary;
    sections: SectionCapexOpexItem[];
    projects: CapexProjectItem[];
}

const props = defineProps<{
    capexOpex: CapexOpexData;
}>();

const emit = defineEmits<{
    (
        e: 'filterRange',
        payload: { rangeType: string; startDate?: string; endDate?: string },
    ): void;
}>();

const { __ } = useTrans();

const activeRangeType = ref(props.capexOpex.summary.range_type || 'month');
const customStartDate = ref(props.capexOpex.summary.start_date || '');
const customEndDate = ref(props.capexOpex.summary.end_date || '');

watch(
    () => props.capexOpex.summary.range_type,
    (val) => {
        if (val) activeRangeType.value = val;
    },
);

function handleSelectRange(type: 'month' | 'ytd' | 'custom') {
    activeRangeType.value = type;
    if (type !== 'custom') {
        emit('filterRange', { rangeType: type });
    }
}

function handleApplyCustomRange() {
    if (customStartDate.value && customEndDate.value) {
        emit('filterRange', {
            rangeType: 'custom',
            startDate: customStartDate.value,
            endDate: customEndDate.value,
        });
    }
}
</script>

<template>
    <div class="space-y-6" data-test="capex-opex-tab">
        <!-- Date Range Filter Selector Toolbar -->
        <div
            class="bg-card flex flex-col gap-3 rounded-xl border border-slate-200 p-3 shadow-2xs sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
            data-test="capex-opex-range-toolbar"
        >
            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <span class="text-muted-foreground mr-1 text-xs font-medium">
                    {{ __('Rentang Waktu') }}:
                </span>
                <button
                    type="button"
                    class="cursor-pointer rounded-full border px-3 py-1 text-xs font-semibold transition-colors"
                    :class="
                        activeRangeType === 'month'
                            ? 'border-transparent bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                            : 'bg-card text-muted-foreground border-slate-200 hover:bg-slate-50 dark:border-slate-800'
                    "
                    @click="handleSelectRange('month')"
                    data-test="btn-range-month"
                >
                    {{ __('Bulan Ini (Default)') }}
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-full border px-3 py-1 text-xs font-semibold transition-colors"
                    :class="
                        activeRangeType === 'ytd'
                            ? 'border-transparent bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                            : 'bg-card text-muted-foreground border-slate-200 hover:bg-slate-50 dark:border-slate-800'
                    "
                    @click="handleSelectRange('ytd')"
                    data-test="btn-range-ytd"
                >
                    {{ __('Tahun Berjalan (YTD)') }}
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-full border px-3 py-1 text-xs font-semibold transition-colors"
                    :class="
                        activeRangeType === 'custom'
                            ? 'border-transparent bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                            : 'bg-card text-muted-foreground border-slate-200 hover:bg-slate-50 dark:border-slate-800'
                    "
                    @click="handleSelectRange('custom')"
                    data-test="btn-range-custom"
                >
                    {{ __('Kustom') }}
                </button>
            </div>

            <!-- Custom Date Inputs (Active only when Kustom is chosen) -->
            <div
                v-if="activeRangeType === 'custom'"
                class="flex flex-wrap items-center gap-2 pt-2 sm:pt-0"
                data-test="custom-date-inputs"
            >
                <Input
                    v-model="customStartDate"
                    type="date"
                    class="h-8 w-36 font-mono text-xs"
                    data-test="input-custom-start-date"
                />
                <span class="text-xs text-slate-400">-</span>
                <Input
                    v-model="customEndDate"
                    type="date"
                    class="h-8 w-36 font-mono text-xs"
                    data-test="input-custom-end-date"
                />
                <Button
                    size="sm"
                    class="h-8 cursor-pointer bg-[#cc0000] px-3 text-xs text-white hover:bg-[#b30000]"
                    @click="handleApplyCustomRange"
                    data-test="btn-apply-custom-range"
                >
                    {{ __('Terapkan') }}
                </Button>
            </div>

            <!-- Active Date Range Display -->
            <div
                v-else
                class="text-muted-foreground flex items-center gap-1.5 font-mono text-xs"
            >
                <Calendar class="size-3.5 text-slate-400" />
                <span
                    >{{ capexOpex.summary.start_date }} s/d
                    {{ capexOpex.summary.end_date }}</span
                >
            </div>
        </div>

        <!-- Top-Level Capitalization Summary Cards -->
        <div
            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
            data-test="capitalization-kpi-bar"
        >
            <!-- Total Overtime Hours -->
            <Card class="shadow-2xs">
                <CardContent class="space-y-1 p-4">
                    <div
                        class="text-muted-foreground flex items-center justify-between text-xs font-medium"
                    >
                        <span>{{ __('Total Jam Lembur') }}</span>
                        <Layers class="size-3.5 text-slate-400" />
                    </div>
                    <div class="flex items-baseline gap-1">
                        <span
                            class="font-mono text-2xl font-black text-slate-900 tabular-nums dark:text-white"
                        >
                            {{ capexOpex.summary.total_hours.toFixed(1) }}
                        </span>
                        <span class="text-muted-foreground text-xs font-normal">
                            {{ __('jam') }}
                        </span>
                    </div>
                    <div class="text-muted-foreground text-[11px]">
                        <span>{{ __('Biaya') }}: </span>
                        <span
                            class="font-mono font-semibold text-slate-700 tabular-nums dark:text-slate-300"
                        >
                            {{
                                formatRupiah(capexOpex.summary.total_cost_idr, {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                })
                            }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- CapEx Project Hours & Ratio with Formula Tooltip -->
            <Card class="border-sky-200/80 shadow-2xs dark:border-sky-900/60">
                <CardContent class="space-y-1 p-4">
                    <div
                        class="flex items-center justify-between text-xs font-medium text-sky-800 dark:text-sky-300"
                    >
                        <span>{{ __('Jam CapEx Proyek') }}</span>
                        <FolderKanban
                            class="size-3.5 text-sky-600 dark:text-sky-400"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex items-baseline gap-1">
                            <span
                                class="font-mono text-2xl font-black text-sky-700 tabular-nums dark:text-sky-400"
                                data-test="capex-hours-val"
                            >
                                {{ capexOpex.summary.capex_hours.toFixed(1) }}
                            </span>
                            <span
                                class="text-muted-foreground text-xs font-normal"
                            >
                                {{ __('jam') }}
                            </span>
                        </div>

                        <!-- Hover Formula Tooltip (CALC-07) -->
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Badge
                                        variant="outline"
                                        class="cursor-help border-sky-300 bg-sky-50 px-1.5 py-0.5 text-[10px] font-bold text-sky-800 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-300"
                                        data-test="capex-ratio-badge"
                                    >
                                        {{
                                            capexOpex.summary.capex_ratio_pct.toFixed(
                                                1,
                                            )
                                        }}%
                                        <CircleHelp
                                            class="ml-1 size-2.5 opacity-70"
                                        />
                                    </Badge>
                                </TooltipTrigger>
                                <TooltipContent
                                    class="max-w-xs font-mono text-xs"
                                    data-test="capex-formula-tooltip"
                                >
                                    <p
                                        class="font-sans font-bold text-slate-900 dark:text-white"
                                    >
                                        {{ __('Rumus Rasio CapEx (CALC-07)') }}
                                    </p>
                                    <p
                                        class="mt-1 text-slate-600 dark:text-slate-300"
                                    >
                                        {{
                                            __(
                                                '(Total Jam Proyek ÷ Total Seluruh Jam) × 100%',
                                            )
                                        }}
                                    </p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div
                        class="text-[11px] text-sky-700/80 dark:text-sky-400/80"
                    >
                        <span>{{ __('Nilai Aset') }}: </span>
                        <span class="font-mono font-semibold tabular-nums">
                            {{
                                formatRupiah(capexOpex.summary.capex_cost_idr, {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                })
                            }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- OpEx Routine Hours & Ratio -->
            <Card class="shadow-2xs">
                <CardContent class="space-y-1 p-4">
                    <div
                        class="text-muted-foreground flex items-center justify-between text-xs font-medium"
                    >
                        <span>{{ __('Jam OpEx Rutin') }}</span>
                        <Coins class="size-3.5 text-slate-400" />
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex items-baseline gap-1">
                            <span
                                class="font-mono text-2xl font-black text-slate-700 tabular-nums dark:text-slate-300"
                                data-test="opex-hours-val"
                            >
                                {{ capexOpex.summary.opex_hours.toFixed(1) }}
                            </span>
                            <span
                                class="text-muted-foreground text-xs font-normal"
                            >
                                {{ __('jam') }}
                            </span>
                        </div>
                        <Badge
                            variant="outline"
                            class="border-slate-300 bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ capexOpex.summary.opex_ratio_pct.toFixed(1) }}%
                        </Badge>
                    </div>
                    <div class="text-muted-foreground text-[11px]">
                        <span>{{ __('Biaya Operasional') }}: </span>
                        <span
                            class="font-mono font-semibold text-slate-700 tabular-nums dark:text-slate-300"
                        >
                            {{
                                formatRupiah(capexOpex.summary.opex_cost_idr, {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                })
                            }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Capitalization Ratio Compliance Status -->
            <Card class="shadow-2xs">
                <CardContent class="space-y-1 p-4">
                    <div
                        class="text-muted-foreground flex items-center justify-between text-xs font-medium"
                    >
                        <span>{{ __('Kepatuhan Kapitalisasi') }}</span>
                        <Activity class="size-3.5 text-slate-400" />
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="font-mono text-2xl font-black text-slate-900 tabular-nums dark:text-white"
                        >
                            {{ capexOpex.summary.capex_ratio_pct.toFixed(1) }}%
                        </span>
                        <Badge
                            variant="outline"
                            class="px-1.5 py-0.5 text-[10px] font-semibold"
                            :class="
                                capexOpex.summary.capex_hours > 0
                                    ? 'border-sky-300 bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300'
                                    : 'border-slate-200 bg-slate-50 text-slate-500'
                            "
                        >
                            {{
                                capexOpex.summary.capex_hours > 0
                                    ? __('Terisi CapEx')
                                    : __('Nol Proyek')
                            }}
                        </Badge>
                    </div>
                    <div class="text-muted-foreground text-[11px]">
                        {{ __('Pemisahan audit biaya tenaga kerja') }}
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Empty State Notice if no CapEx labor recorded in period -->
        <div
            v-if="capexOpex.summary.capex_hours <= 0"
            class="flex items-center gap-3 rounded-xl border border-sky-200 bg-sky-50/60 p-4 text-xs text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-200"
            data-test="capex-empty-state-banner"
        >
            <AlertCircle
                class="size-5 shrink-0 text-sky-600 dark:text-sky-400"
            />
            <div>
                <span class="font-semibold">{{
                    __('Belum ada jam lembur CapEx pada periode ini.')
                }}</span>
                <span class="ml-1 text-sky-700 dark:text-sky-300">
                    {{
                        __(
                            'Seluruh jam lembur yang disetujui tergolong sebagai OpEx (biaya operasional rutin pabrik).',
                        )
                    }}
                </span>
            </div>
        </div>

        <!-- Visual Distribution Grid: Donut Chart + Section Comparison Bar Chart -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            <!-- Donut Chart Card (4 cols) -->
            <Card class="shadow-2xs lg:col-span-5">
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-sm font-bold"
                    >
                        <span class="flex items-center gap-2">
                            <PieChart class="size-4 text-slate-500" />
                            {{ __('Distribusi CapEx vs OpEx') }}
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <CapexOpexDonutChart
                        :capex-hours="capexOpex.summary.capex_hours"
                        :opex-hours="capexOpex.summary.opex_hours"
                        :capex-cost-idr="capexOpex.summary.capex_cost_idr"
                        :opex-cost-idr="capexOpex.summary.opex_cost_idr"
                        :capex-ratio-pct="capexOpex.summary.capex_ratio_pct"
                        :opex-ratio-pct="capexOpex.summary.opex_ratio_pct"
                    />
                </CardContent>
            </Card>

            <!-- Section Breakdown Bar Chart Card (7 cols) -->
            <Card class="shadow-2xs lg:col-span-7">
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-sm font-bold"
                    >
                        <span class="flex items-center gap-2">
                            <Layers class="size-4 text-slate-500" />
                            {{ __('Perbandingan Jam per Seksi') }}
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <CapexOpexSectionBarChart :sections="capexOpex.sections" />
                </CardContent>
            </Card>
        </div>

        <!-- CapEx Projects Performance Table Card -->
        <Card class="shadow-2xs">
            <CardContent class="p-4 sm:p-5">
                <CapexProjectTable :projects="capexOpex.projects" />
            </CardContent>
        </Card>
    </div>
</template>
