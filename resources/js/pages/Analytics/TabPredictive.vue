<script setup lang="ts">
import {
    Activity,
    ArrowDownRight,
    ArrowUpRight,
    BrainCircuit,
    Calendar,
    Minus,
    RefreshCw,
    Sparkles,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import ForecastBarChart from '@/components/analytics/ForecastBarChart.vue';
import SeasonalPatternChart from '@/components/analytics/SeasonalPatternChart.vue';
import SeasonalSummaryCards from '@/components/analytics/SeasonalSummaryCards.vue';
import TrendProjectionChart from '@/components/analytics/TrendProjectionChart.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { predictive as predictiveRoute } from '@/routes/analytics';

export interface PredictiveData {
    kpi: {
        predicted_hours: number;
        ci_lower: number;
        ci_upper: number;
        margin: number;
        formatted_prediction: string;
        model_name: string;
        fallback_used: boolean;
        accuracy_rate: number | null;
        mape: number | null;
        accuracy_label: string;
        accuracy_description: string;
        seasonal_pattern: string;
        seasonal_description: string;
        seasonal_variance_pct: number;
        trend_direction: 'increasing' | 'stable' | 'decreasing';
        trend_label: string;
        trend_growth_rate_pct: number;
        trend_description: string;
    };
    section_forecast: {
        sections: Array<{
            section_id: number;
            section_code: string;
            section_name: string;
            predicted_hours: number;
            ci_lower: number;
            ci_upper: number;
            margin: number;
            fallback_used: boolean;
        }>;
        chart_data: {
            labels: string[];
            datasets: Array<{
                label: string;
                data: number[];
                ci_lower: number[];
                ci_upper: number[];
            }>;
        };
    };
    trend_projection: {
        labels: string[];
        historical_series: (number | null)[];
        projected_series: (number | null)[];
        ci_lower_series: (number | null)[];
        ci_upper_series: (number | null)[];
    };
    seasonal_pattern: {
        labels: string[];
        monthly_averages: number[];
        grand_average: number;
        peak_quarter: string;
        peak_quarter_index: number;
    };
    seasonal_summary: {
        peak_season: {
            month: string;
            value: number;
            variance_pct: number;
        };
        low_season: {
            month: string;
            value: number;
            variance_pct: number;
        };
        cycle_pattern: string;
    };
    scope: {
        department_id: number | null;
        department_name: string;
        target_month_name: string;
        is_cold_start: boolean;
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

const predictiveData = ref<PredictiveData | null>(
    (props.initialData as PredictiveData) ?? null,
);
const isLoading = ref(false);
const errorMsg = ref<string | null>(null);

async function fetchPredictiveData() {
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

        const url = predictiveRoute.url({ query: queryParams });
        const res = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!res.ok) {
            throw new Error(`HTTP error ${res.status}`);
        }

        const data = (await res.json()) as PredictiveData;
        predictiveData.value = data;
    } catch (err) {
        errorMsg.value = __(
            'Gagal memuat data prediksi. Silakan coba kembali.',
        );
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.initialData,
    (newVal) => {
        if (newVal) {
            predictiveData.value = newVal as PredictiveData;
        }
    },
    { deep: true },
);

onMounted(() => {
    if (!predictiveData.value) {
        fetchPredictiveData();
    }
});

const trendIcon = computed(() => {
    const dir = predictiveData.value?.kpi?.trend_direction;
    if (dir === 'increasing') return TrendingUp;
    if (dir === 'decreasing') return TrendingDown;
    return Minus;
});

const trendIconColor = computed(() => {
    const dir = predictiveData.value?.kpi?.trend_direction;
    if (dir === 'increasing') return 'text-sky-600 dark:text-sky-400';
    if (dir === 'decreasing') return 'text-emerald-600 dark:text-emerald-400';
    return 'text-slate-500 dark:text-slate-400';
});
</script>

<template>
    <div class="space-y-6" data-test="tab-predictive-content">
        <!-- Error Banner if async fetch failed -->
        <div
            v-if="errorMsg"
            class="flex items-center justify-between rounded-lg border border-red-200 bg-red-50 p-4 text-xs font-medium text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
        >
            <span>{{ errorMsg }}</span>
            <Button
                variant="outline"
                size="sm"
                class="h-7 text-xs"
                @click="fetchPredictiveData"
            >
                <RefreshCw class="mr-1 size-3" />
                {{ __('Coba Lagi') }}
            </Button>
        </div>

        <!-- Predictive KPI Row (E09-07) -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Card 1: Prediksi Bulan Depan -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-forecast-next-month"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Prediksi Bulan Depan') }}
                    </CardTitle>
                    <Sparkles class="size-4 text-[#cc0000]" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white"
                        data-test="predicted-hours-value"
                    >
                        <template v-if="predictiveData">
                            {{ predictiveData.kpi.formatted_prediction }}
                        </template>
                        <template v-else> - ± - jam </template>
                    </div>
                    <p
                        class="mt-1 flex items-center gap-1.5 text-xs text-slate-500"
                    >
                        <Badge
                            variant="secondary"
                            :class="
                                predictiveData?.kpi?.fallback_used
                                    ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'
                                    : 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300'
                            "
                            data-test="forecast-model-badge"
                        >
                            {{
                                predictiveData?.kpi?.fallback_used
                                    ? __('Moving Average')
                                    : __('Supervised ML')
                            }}
                        </Badge>
                        <span class="truncate">
                            {{
                                predictiveData?.kpi?.model_name ||
                                __('Model Baseline')
                            }}
                        </span>
                    </p>
                </CardContent>
            </Card>

            <!-- Card 2: Tingkat Akurasi (MAPE) -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-forecast-accuracy"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Tingkat Akurasi (MAPE)') }}
                    </CardTitle>
                    <BrainCircuit class="size-4 text-emerald-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight tabular-nums"
                        :class="
                            predictiveData?.kpi?.fallback_used
                                ? 'text-blue-600 dark:text-blue-400'
                                : 'text-emerald-600 dark:text-emerald-400'
                        "
                        data-test="forecast-accuracy-value"
                    >
                        {{ predictiveData?.kpi?.accuracy_label || '91.8%' }}
                    </div>
                    <p class="mt-1 truncate text-xs text-slate-500">
                        {{
                            predictiveData?.kpi?.accuracy_description ||
                            __('Rata-rata deviasi riwayat 12 bulan')
                        }}
                    </p>
                </CardContent>
            </Card>

            <!-- Card 3: Pola Musiman -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-seasonal-pattern"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Pola Musiman') }}
                    </CardTitle>
                    <Calendar class="size-4 text-amber-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="text-xl font-bold tracking-tight text-slate-900 dark:text-white"
                        data-test="seasonal-pattern-value"
                    >
                        {{
                            predictiveData?.kpi?.seasonal_pattern ||
                            __('Puncak Siklus (Q4)')
                        }}
                    </div>
                    <p class="mt-1 truncate text-xs text-slate-500">
                        {{
                            predictiveData?.kpi?.seasonal_description ||
                            __('Kenaikan rata-rata akhir tahun')
                        }}
                    </p>
                </CardContent>
            </Card>

            <!-- Card 4: Arah Tren -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="card-trend-direction"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Arah Tren') }}
                    </CardTitle>
                    <ArrowUpRight class="size-4 text-sky-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="flex items-center gap-1.5 font-mono text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white"
                        data-test="trend-direction-value"
                    >
                        <component
                            :is="trendIcon"
                            class="size-5"
                            :class="trendIconColor"
                        />
                        <span>{{
                            predictiveData?.kpi?.trend_label ||
                            '↑ ' + __('Meningkat')
                        }}</span>
                    </div>
                    <p class="mt-1 truncate text-xs text-slate-500">
                        {{
                            predictiveData?.kpi?.trend_description ||
                            __('Laju pertumbuhan per kuartal')
                        }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Section Forecast Bar Chart with Confidence Whiskers (E09-07) -->
        <ForecastBarChart
            :chart-data="predictiveData?.section_forecast?.chart_data"
            :target-month-name="predictiveData?.scope?.target_month_name"
            :loading="isLoading"
            :empty="!predictiveData"
            data-test="forecast-bar-chart"
        />

        <!-- 6-Month Trend Projection & 12-Month Seasonal Pattern (2-Column Grid) -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Left: 6-Month Trend Projection Line with 90% Confidence Band -->
            <TrendProjectionChart
                :data="predictiveData?.trend_projection"
                :loading="isLoading"
                :empty="!predictiveData"
                data-test="trend-projection-chart"
            />

            <!-- Right: 12-Month Annual Seasonal Cycle with Peak Quarter Shading -->
            <SeasonalPatternChart
                :labels="predictiveData?.seasonal_pattern?.labels"
                :monthly-averages="
                    predictiveData?.seasonal_pattern?.monthly_averages
                "
                :grand-average="predictiveData?.seasonal_pattern?.grand_average"
                :peak-quarter="predictiveData?.seasonal_pattern?.peak_quarter"
                :peak-quarter-index="
                    predictiveData?.seasonal_pattern?.peak_quarter_index
                "
                :loading="isLoading"
                :empty="!predictiveData"
                data-test="seasonal-pattern-chart"
            />
        </div>

        <!-- 3 Seasonal Summary Information Cards -->
        <SeasonalSummaryCards
            :peak-season="predictiveData?.seasonal_summary?.peak_season"
            :low-season="predictiveData?.seasonal_summary?.low_season"
            :cycle-pattern="predictiveData?.seasonal_summary?.cycle_pattern"
            :loading="isLoading"
            data-test="seasonal-summary-cards"
        />
    </div>
</template>
