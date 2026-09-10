<script setup lang="ts">
import {
    Activity,
    AlertCircle,
    CheckCircle2,
    Layers,
    RefreshCw,
    ScatterChart,
    ShieldAlert,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import CorrelationMatrixTable, {
    type CorrelationMatrixData,
} from '@/components/analytics/CorrelationMatrixTable.vue';
import OptimalLevelZoneChart, {
    type OptimalZoneData,
} from '@/components/analytics/OptimalLevelZoneChart.vue';
import OvertimeProductionScatter, {
    type OvertimeVsProductionData,
} from '@/components/analytics/OvertimeProductionScatter.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { correlation as correlationRoute } from '@/routes/analytics';

export interface CorrelationData {
    kpi: {
        sweet_spot_min: number;
        sweet_spot_max: number;
        peak_efficiency_hours: number;
        warning_threshold_hours: number;
        current_weekly_avg_hours: number;
        current_zone: 'under_utilized' | 'sweet_spot' | 'over_threshold';
        current_zone_label: string;
    };
    overtime_vs_production: OvertimeVsProductionData;
    overtime_vs_quality: {
        available: boolean;
        message: string;
        subtext: string;
        correlation_r: number | null;
        scatter_points: Array<{ x: number; y: number; label: string }>;
    };
    optimal_zone_chart: OptimalZoneData;
    correlation_matrix: CorrelationMatrixData;
    scope: {
        department_id: number | null;
        department_name: string;
        start_date: string;
        end_date: string;
    };
}

interface Props {
    filters?: {
        department_id?: string | number | null;
        start_date?: string;
        end_date?: string;
    };
    initialData?: unknown;
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({
        department_id: 'all',
        start_date: '',
        end_date: '',
    }),
    initialData: undefined,
});

const { __ } = useTrans();

const correlationData = ref<CorrelationData | null>(
    (props.initialData as CorrelationData) ?? null,
);
const isLoading = ref(false);
const errorMsg = ref<string | null>(null);

async function fetchCorrelationData() {
    isLoading.value = true;
    errorMsg.value = null;

    try {
        const queryParams: Record<string, string> = {};
        if (
            props.filters?.department_id !== undefined &&
            props.filters?.department_id !== null
        ) {
            queryParams.department_id = String(props.filters.department_id);
        }
        if (props.filters?.start_date) {
            queryParams.start_date = props.filters.start_date;
        }
        if (props.filters?.end_date) {
            queryParams.end_date = props.filters.end_date;
        }

        const url = correlationRoute.url({ query: queryParams });
        const res = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!res.ok) {
            throw new Error(`HTTP error ${res.status}`);
        }

        const data = (await res.json()) as CorrelationData;
        correlationData.value = data;
    } catch {
        errorMsg.value = __(
            'Gagal memuat data korelasi & pola. Silakan coba kembali.',
        );
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => [
        props.filters?.department_id,
        props.filters?.start_date,
        props.filters?.end_date,
    ],
    () => {
        fetchCorrelationData();
    },
);

onMounted(() => {
    if (!correlationData.value) {
        fetchCorrelationData();
    }
});
</script>

<template>
    <div class="space-y-6" data-test="tab-correlation-content">
        <!-- Error Banner with Retry -->
        <div
            v-if="errorMsg"
            class="flex items-center justify-between rounded-lg border border-red-200 bg-red-50 p-4 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
            data-test="correlation-error-banner"
        >
            <div class="flex items-center gap-2">
                <AlertCircle class="size-4 shrink-0" />
                <span>{{ errorMsg }}</span>
            </div>
            <Button
                variant="outline"
                size="sm"
                class="h-7 cursor-pointer border-red-300 bg-white text-xs hover:bg-red-50 dark:border-red-800 dark:bg-slate-900"
                @click="fetchCorrelationData"
            >
                <RefreshCw class="mr-1 size-3" />
                {{ __('Coba Lagi') }}
            </Button>
        </div>

        <!-- 3 Optimal Level Summary Cards (E09-09) -->
        <div
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            data-test="correlation-summary-cards"
        >
            <!-- Card 1: Sweet Spot Range -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-sweet-spot"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        {{ __('Zona Lembur Wajar (Sweet Spot)') }}
                    </CardTitle>
                    <CheckCircle2
                        class="size-4 text-emerald-600 dark:text-emerald-400"
                    />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-emerald-600 tabular-nums dark:text-emerald-400"
                    >
                        {{ correlationData?.kpi.sweet_spot_min ?? 12.0 }} –
                        {{ correlationData?.kpi.sweet_spot_max ?? 18.0 }}
                        <span class="text-sm font-normal text-slate-500"
                            >jam/minggu</span
                        >
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{
                            __(
                                'Batas efisiensi optimal tanpa penurunan kualitas',
                            )
                        }}
                    </p>
                </CardContent>
            </Card>

            <!-- Card 2: Peak Efficiency Point -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-peak-efficiency"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        {{ __('Titik Puncak Produktivitas') }}
                    </CardTitle>
                    <Activity class="size-4 text-sky-600 dark:text-sky-400" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white"
                    >
                        {{ correlationData?.kpi.peak_efficiency_hours ?? 15.2 }}
                        <span class="text-sm font-normal text-slate-500"
                            >jam/minggu</span
                        >
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{
                            __('Rata-rata output unit per jam kerja tertinggi')
                        }}
                    </p>
                </CardContent>
            </Card>

            <!-- Card 3: Warning Threshold -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-warning-threshold"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        {{ __('Ambang Batas Kelelahan') }}
                    </CardTitle>
                    <ShieldAlert
                        class="size-4 text-red-600 dark:text-red-400"
                    />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-red-600 tabular-nums dark:text-red-400"
                    >
                        &gt;
                        {{
                            correlationData?.kpi.warning_threshold_hours ?? 20.0
                        }}
                        <span class="text-sm font-normal text-slate-500"
                            >jam/minggu</span
                        >
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{
                            __('Korelasi peningkatan defect dan risiko insiden')
                        }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Section 1: Overtime vs Production Volume Scatter Chart (E09-09) -->
        <OvertimeProductionScatter
            :data="correlationData?.overtime_vs_production"
            :loading="isLoading"
        />

        <!-- Section 2: Overtime vs Quality Metric (ERP Degradation Guard) -->
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
            data-test="quality-scatter-guard-card"
        >
            <CardHeader class="pb-3">
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle
                            class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                        >
                            <ScatterChart class="size-4 text-slate-400" />
                            <span>{{
                                __('Lembur vs Metrik Kualitas & Tingkat Defect')
                            }}</span>
                        </CardTitle>
                        <CardDescription class="mt-1 text-xs">
                            {{
                                __(
                                    'Hubungan jam lembur teknisi dengan tingkat defect (scrap/rework) lini perakitan.',
                                )
                            }}
                        </CardDescription>
                    </div>

                    <span
                        class="inline-flex items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-300"
                        data-test="quality-erp-status-badge"
                    >
                        <AlertCircle class="size-3" />
                        <span>{{ __('ERP Integration Pending') }}</span>
                    </span>
                </div>
            </CardHeader>
            <CardContent>
                <div
                    class="flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center dark:border-slate-800 dark:bg-slate-950/30"
                    data-test="quality-erp-placeholder"
                >
                    <div
                        class="mb-2 flex size-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500"
                    >
                        <Layers class="size-5" />
                    </div>
                    <h4
                        class="text-xs font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{
                            correlationData?.overtime_vs_quality.message ||
                            __('Menunggu integrasi data kualitas dari ERP')
                        }}
                    </h4>
                    <p
                        class="mt-1 max-w-sm text-[11px] text-slate-500 dark:text-slate-400"
                    >
                        {{
                            correlationData?.overtime_vs_quality.subtext ||
                            __('Analisis korelasi produksi tetap aktif')
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Section 3: Optimal Overtime Level Zone Chart (Sweet Spot Area Chart) -->
        <OptimalLevelZoneChart
            :data="correlationData?.optimal_zone_chart"
            :warning-threshold="correlationData?.kpi.warning_threshold_hours"
            :loading="isLoading"
        />

        <!-- Section 4: Bivariate Correlation Matrix Table -->
        <CorrelationMatrixTable
            :data="correlationData?.correlation_matrix"
            :loading="isLoading"
        />
    </div>
</template>
