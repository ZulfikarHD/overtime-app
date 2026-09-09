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
        class="border-border/70 relative overflow-hidden shadow-xs transition-shadow hover:shadow-sm"
        data-slot="kpi-card-manpower"
    >
        <CardHeader class="flex flex-row items-center justify-between pb-2">
            <div class="flex items-center gap-2">
                <div
                    class="rounded-md bg-purple-500/10 p-1.5 text-purple-600 dark:bg-purple-500/20 dark:text-purple-400"
                >
                    <Users class="size-4" />
                </div>
                <h3
                    class="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                >
                    {{ __('Tenaga Kerja (Man Power)') }}
                </h3>
            </div>

            <span
                class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 dark:text-slate-400"
            >
                <span class="size-1.5 rounded-full bg-emerald-500" />
                {{ data?.active_shifts_count ?? 3 }} Shift
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
                            {{ formattedTotal }}
                        </span>
                        <span
                            class="text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ __('Karyawan Aktif') }}
                        </span>
                    </div>

                    <div
                        class="mt-1 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400"
                    >
                        <span class="flex items-center gap-1 font-medium">
                            <Building2 class="size-3 text-slate-400" />
                            {{ __('Distribusi Seksi') }}
                        </span>
                        <span
                            class="font-mono text-[11px] text-slate-400 tabular-nums"
                        >
                            {{ data?.sections?.length || 0 }} seksi terdaftar
                        </span>
                    </div>
                </div>

                <!-- Mini Bar Sparkline of Headcount per Section -->
                <div>
                    <BaseMiniSparkline
                        :data="data?.sparkline || []"
                        :labels="data?.labels || []"
                        type="bar"
                        :color="chartColors.capex"
                        height-class="h-9"
                        unit="orang"
                    />

                    <div
                        class="mt-1 flex items-center justify-between text-[11px] text-slate-400"
                    >
                        <span>Seksi Produksi</span>
                        <span class="font-mono tabular-nums">
                            {{
                                data?.sections?.length
                                    ? `${data.sections.length} Seksi`
                                    : '-'
                            }}
                        </span>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
