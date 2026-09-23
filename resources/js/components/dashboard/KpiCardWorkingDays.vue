<script setup lang="ts">
import { CalendarDays, Clock } from '@lucide/vue';
import { computed } from 'vue';
import BaseMiniSparkline from '@/components/charts/BaseMiniSparkline.vue';
import ChartSkeleton from '@/components/charts/ChartSkeleton.vue';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { chartColors } from '@/composables/useChartTheme';
import { useTrans } from '@/composables/useTrans';

export interface WorkingDaysData {
    total_hkn_days: number;
    completed_hkn_days: number;
    remaining_hkn_days: number;
    total_calendar_days: number;
    progress_pct: number;
    labels: string[];
    weekly_hkn: number[];
}

interface Props {
    data?: WorkingDaysData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: () => ({
        total_hkn_days: 22,
        completed_hkn_days: 18,
        remaining_hkn_days: 4,
        total_calendar_days: 30,
        progress_pct: 81.8,
        labels: ['M1', 'M2', 'M3', 'M4', 'M5'],
        weekly_hkn: [5, 5, 5, 5, 2],
    }),
    loading: false,
});

const { __ } = useTrans();

const progressStyle = computed(() => {
    return {
        width: `${Math.min(100, Math.max(0, props.data?.progress_pct ?? 0))}%`,
    };
});
</script>

<template>
    <Card
        class="border-border/70 @container gap-3 py-4 shadow-xs transition-shadow hover:shadow-sm sm:gap-4 sm:py-5"
        data-slot="kpi-card-working-days"
        data-test="kpi-card-working-days"
    >
        <CardHeader class="px-4 pb-1 sm:px-6">
            <div class="flex min-w-0 items-center gap-2">
                <div
                    class="shrink-0 rounded-md bg-emerald-500/10 p-1.5 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400"
                >
                    <CalendarDays class="size-3.5 sm:size-4" />
                </div>
                <h3
                    class="min-w-0 flex-1 text-[10px] leading-snug font-semibold tracking-wider text-slate-500 uppercase sm:text-[11px] dark:text-slate-400"
                >
                    {{ __('Hari Kerja (HKN)') }}
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
                        {{ data?.completed_hkn_days }} /
                        {{ data?.total_hkn_days }}
                    </div>
                    <p
                        class="mt-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        {{ __('Hari') }}
                    </p>
                    <p
                        class="flex items-center gap-1 text-[10px] text-slate-500 sm:text-[11px] dark:text-slate-400"
                    >
                        <Clock class="size-3 shrink-0 text-slate-400" />
                        <span class="min-w-0 break-words">
                            {{
                                __('Sisa: :days Hari Kerja', {
                                    days: data?.remaining_hkn_days ?? 0,
                                })
                            }}
                        </span>
                    </p>
                    <div
                        class="mt-2 flex flex-wrap items-center gap-1.5 text-[10px] text-slate-500 sm:gap-2 dark:text-slate-400"
                    >
                        <span
                            class="font-mono font-semibold text-slate-700 tabular-nums dark:text-slate-300"
                        >
                            {{ data?.progress_pct }}%
                        </span>
                        <span class="font-mono tabular-nums">
                            {{ data?.total_calendar_days }}
                            {{ __('hari kalender') }}
                        </span>
                    </div>
                </div>

                <div class="min-w-0">
                    <BaseMiniSparkline
                        :data="data?.weekly_hkn || []"
                        :labels="data?.labels || []"
                        type="bar"
                        :color="chartColors.hkn"
                        height-class="h-9"
                        unit="hari"
                    />
                    <div
                        class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                    >
                        <div
                            class="h-full rounded-full bg-emerald-500 transition-all duration-300"
                            :style="progressStyle"
                        />
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
