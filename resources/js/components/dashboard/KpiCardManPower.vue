<script setup lang="ts">
import { Building2, Users } from '@lucide/vue';
import { computed } from 'vue';
import BaseMiniSparkline from '@/components/charts/BaseMiniSparkline.vue';
import ChartSkeleton from '@/components/charts/ChartSkeleton.vue';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { chartColors } from '@/composables/useChartTheme';
import { useTrans } from '@/composables/useTrans';

export interface SectionManpowerItem {
    code: string;
    name: string;
    count: number;
}

export interface ManpowerData {
    total_active_employees: number;
    active_shifts_count: number;
    sections: SectionManpowerItem[];
    sparkline: number[];
    labels: string[];
}

interface Props {
    data?: ManpowerData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: () => ({
        total_active_employees: 384,
        active_shifts_count: 3,
        sections: [],
        sparkline: [],
        labels: [],
    }),
    loading: false,
});

const { __ } = useTrans();

const formattedTotal = computed(() => {
    return new Intl.NumberFormat('id-ID').format(
        props.data?.total_active_employees ?? 0,
    );
});
</script>

<template>
    <Card
        class="border-border/70 @container gap-3 py-4 shadow-xs transition-shadow hover:shadow-sm sm:gap-4 sm:py-5"
        data-slot="kpi-card-manpower"
        data-test="kpi-card-manpower"
    >
        <CardHeader class="px-4 pb-1 sm:px-6">
            <div class="flex min-w-0 items-center gap-2">
                <div
                    class="shrink-0 rounded-md bg-purple-500/10 p-1.5 text-purple-600 dark:bg-purple-500/20 dark:text-purple-400"
                >
                    <Users class="size-3.5 sm:size-4" />
                </div>
                <h3
                    class="min-w-0 flex-1 text-[10px] leading-snug font-semibold tracking-wider text-slate-500 uppercase sm:text-[11px] dark:text-slate-400"
                >
                    {{ __('Tenaga Kerja (Man Power)') }}
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
                        {{ formattedTotal }}
                    </div>
                    <p
                        class="mt-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        {{ __('Karyawan Aktif') }}
                    </p>
                    <p
                        class="flex items-center gap-1 text-[10px] text-slate-500 sm:text-[11px] dark:text-slate-400"
                    >
                        <Building2 class="size-3 shrink-0 text-slate-400" />
                        <span class="min-w-0 truncate">{{
                            __('Distribusi Seksi')
                        }}</span>
                    </p>
                    <div
                        class="mt-2 flex flex-wrap items-center gap-1.5 text-[10px] text-slate-500 sm:gap-2 dark:text-slate-400"
                        data-test="manpower-shift-meta"
                    >
                        <span
                            class="inline-flex items-center gap-1 font-medium text-slate-700 dark:text-slate-300"
                        >
                            <span
                                class="size-1.5 shrink-0 rounded-full bg-emerald-500"
                            />
                            {{ data?.active_shifts_count ?? 3 }}
                            {{ __('Shift') }}
                        </span>
                        <span class="font-mono tabular-nums">
                            {{ data?.sections?.length || 0 }}
                            {{ __('seksi terdaftar') }}
                        </span>
                    </div>
                </div>

                <div class="min-w-0">
                    <BaseMiniSparkline
                        :data="data?.sparkline || []"
                        :labels="data?.labels || []"
                        type="bar"
                        :color="chartColors.capex"
                        height-class="h-9"
                        unit="orang"
                    />
                    <div
                        class="mt-1 flex items-center justify-between gap-2 text-[10px] text-slate-400"
                    >
                        <span class="min-w-0 truncate">{{
                            __('Seksi Produksi')
                        }}</span>
                        <span class="shrink-0 font-mono tabular-nums">
                            {{
                                data?.sections?.length
                                    ? `${data.sections.length} ${__('Seksi')}`
                                    : '-'
                            }}
                        </span>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
