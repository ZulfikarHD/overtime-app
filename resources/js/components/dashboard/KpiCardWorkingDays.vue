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
        class="border-border/70 relative overflow-hidden shadow-xs transition-shadow hover:shadow-sm"
        data-slot="kpi-card-working-days"
    >
        <CardHeader class="flex flex-row items-center justify-between pb-2">
            <div class="flex items-center gap-2">
                <div
                    class="rounded-md bg-emerald-500/10 p-1.5 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400"
                >
                    <CalendarDays class="size-4" />
                </div>
                <h3
                    class="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                >
                    {{ __('Hari Kerja (HKN)') }}
                </h3>
            </div>

            <span
                class="font-mono text-xs font-medium text-slate-500 tabular-nums dark:text-slate-400"
            >
                {{ data?.progress_pct }}%
            </span>
        </CardHeader>

        <CardContent class="space-y-3 pt-0">
            <template v-if="loading">
                <ChartSkeleton height-class="h-16" variant="sparkline" />
            </template>

            <template v-else>
                <div>
                    <div class="flex items-baseline gap-1.5">
                        <span
                            class="font-mono text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white"
                        >
                            {{ data?.completed_hkn_days }} /
                            {{ data?.total_hkn_days }}
                        </span>
                        <span
                            class="text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ __('Hari') }}
                        </span>
                    </div>

                    <div
                        class="mt-1 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400"
                    >
                        <span class="flex items-center gap-1 font-medium">
                            <Clock class="size-3 text-slate-400" />
                            {{
                                __('Sisa: :days Hari Kerja', {
                                    days: data?.remaining_hkn_days ?? 0,
                                })
                            }}
                        </span>
                        <span
                            class="font-mono text-[11px] text-slate-400 tabular-nums"
                        >
                            {{ data?.total_calendar_days }} hari kalender
                        </span>
                    </div>
                </div>

                <!-- Mini Bar Sparkline of HKN days per week -->
                <div>
                    <BaseMiniSparkline
                        :data="data?.weekly_hkn || []"
                        :labels="data?.labels || []"
                        type="bar"
                        :color="chartColors.hkn"
                        height-class="h-9"
                        unit="hari"
                    />

                    <!-- Progress Bar -->
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
