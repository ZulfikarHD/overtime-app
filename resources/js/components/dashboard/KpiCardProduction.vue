<script setup lang="ts">
import { AlertCircle, Box, CheckCircle2, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';
import BaseMiniSparkline from '@/components/charts/BaseMiniSparkline.vue';
import ChartSkeleton from '@/components/charts/ChartSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { chartColors } from '@/composables/useChartTheme';
import { useTrans } from '@/composables/useTrans';

export interface ProductionVolumeData {
    erp_connected: boolean;
    message?: string | null;
    current_volume?: number | null;
    target_volume: number;
    unit: string;
    labels: string[];
    sparkline_12m: number[];
}

interface Props {
    data?: ProductionVolumeData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: () => ({
        erp_connected: false,
        message: 'N/A — Integrasi data produksi ERP belum terhubung',
        current_volume: null,
        target_volume: 43500,
        unit: 'unit',
        labels: [],
        sparkline_12m: [],
    }),
    loading: false,
});

const { __ } = useTrans();

const formattedTarget = computed(() => {
    return new Intl.NumberFormat('id-ID').format(
        props.data?.target_volume ?? 43500,
    );
});

const formattedCurrent = computed(() => {
    if (
        props.data?.current_volume === null ||
        props.data?.current_volume === undefined
    ) {
        return null;
    }

    return new Intl.NumberFormat('id-ID').format(props.data.current_volume);
});

const variancePct = computed(() => {
    if (
        !props.data?.current_volume ||
        !props.data?.target_volume ||
        props.data.target_volume <= 0
    ) {
        return null;
    }
    const diff =
        ((props.data.current_volume - props.data.target_volume) /
            props.data.target_volume) *
        100;

    return diff.toFixed(1);
});
</script>

<template>
    <Card
        class="border-border/70 @container gap-3 py-4 shadow-xs transition-shadow hover:shadow-sm sm:gap-4 sm:py-5"
        data-slot="kpi-card-production"
        data-test="kpi-card-production"
    >
        <CardHeader class="px-4 pb-1 sm:px-6">
            <div class="flex min-w-0 items-center gap-2">
                <div
                    class="shrink-0 rounded-md bg-blue-500/10 p-1.5 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400"
                >
                    <Box class="size-3.5 sm:size-4" />
                </div>
                <h3
                    class="min-w-0 flex-1 text-[10px] leading-snug font-semibold tracking-wider text-slate-500 uppercase sm:text-[11px] dark:text-slate-400"
                >
                    {{ __('Volume Produksi') }}
                </h3>
            </div>
        </CardHeader>

        <CardContent class="space-y-2 px-4 pt-0 sm:space-y-2.5 sm:px-6">
            <template v-if="loading">
                <ChartSkeleton height-class="h-16" variant="sparkline" />
            </template>

            <template v-else>
                <div class="min-w-0">
                    <div
                        class="font-mono text-2xl leading-none font-bold tracking-tight text-slate-900 tabular-nums @[14rem]:text-3xl @[20rem]:text-[2rem] dark:text-white"
                    >
                        {{ formattedCurrent ?? formattedTarget }}
                    </div>
                    <p
                        class="mt-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        {{ __('unit') }}
                    </p>
                    <p
                        class="text-[10px] text-slate-500 sm:text-[11px] dark:text-slate-400"
                    >
                        <span v-if="data?.erp_connected && formattedCurrent">
                            {{
                                __('Target: :target unit', {
                                    target: formattedTarget,
                                })
                            }}
                        </span>
                        <span v-else>
                            {{ __('Target Bulanan Plant') }}
                        </span>
                    </p>
                    <div
                        class="mt-2 flex flex-wrap items-center gap-1.5 sm:gap-2"
                    >
                        <Badge
                            v-if="data?.erp_connected"
                            variant="outline"
                            class="border-emerald-200 bg-emerald-50 text-[10px] text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                        >
                            <CheckCircle2 class="mr-1 size-2.5" />
                            ERP
                        </Badge>
                        <Badge
                            v-else
                            variant="outline"
                            class="border-slate-200 bg-slate-100 text-[10px] text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
                        >
                            ERP N/A
                        </Badge>
                        <span
                            v-if="data?.erp_connected && variancePct !== null"
                            class="inline-flex items-center gap-1 font-mono text-[10px] font-semibold tabular-nums"
                            :class="
                                Number(variancePct) >= 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-amber-600 dark:text-amber-400'
                            "
                        >
                            <TrendingUp class="size-3" />
                            {{ Number(variancePct) >= 0 ? '+' : ''
                            }}{{ variancePct }}%
                        </span>
                    </div>
                </div>

                <div
                    v-if="data?.erp_connected && data.sparkline_12m.length > 0"
                    class="min-w-0"
                >
                    <BaseMiniSparkline
                        :data="data.sparkline_12m"
                        :labels="data.labels"
                        type="line"
                        :color="chartColors.primary"
                        fill-color="rgba(37, 99, 235, 0.08)"
                        height-class="h-9"
                        unit="unit"
                    />
                    <div
                        class="mt-1 flex items-center justify-between gap-2 text-[10px] text-slate-400"
                    >
                        <span class="truncate">{{ __('12 bulan lalu') }}</span>
                        <span class="shrink-0">{{ __('Bulan ini') }}</span>
                    </div>
                </div>

                <div
                    v-else
                    class="flex items-start gap-2 rounded-md border border-slate-200/80 bg-slate-50 p-2 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400"
                >
                    <AlertCircle
                        class="mt-0.5 size-3.5 shrink-0 text-slate-400"
                    />
                    <span
                        class="line-clamp-2 text-[11px] leading-tight font-medium sm:line-clamp-1"
                    >
                        {{
                            data?.message || __('Integrasi ERP belum terhubung')
                        }}
                    </span>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
