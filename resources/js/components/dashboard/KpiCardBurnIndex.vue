<script setup lang="ts">
import { Flame, ShieldAlert, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import ChartSkeleton from '@/components/charts/ChartSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

export interface BurnIndexCardData {
    plan_pct: number;
    actual_pct: number;
    planned_hours: number;
    actual_hours: number;
    burn_zone: 'safe' | 'on_track' | 'warning' | 'danger';
    burn_zone_label: string;
}

interface Props {
    data?: BurnIndexCardData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: () => ({
        plan_pct: 100.0,
        actual_pct: 0.0,
        planned_hours: 0.0,
        actual_hours: 0.0,
        burn_zone: 'safe',
        burn_zone_label: 'Aman (<85%)',
    }),
    loading: false,
});

const { __ } = useTrans();

const zoneBadgeClass = computed(() => {
    switch (props.data?.burn_zone) {
        case 'danger':
            return 'bg-red-50 text-[#cc0000] border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900';
        case 'warning':
            return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900';
        case 'on_track':
            return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900';
        case 'safe':
        default:
            return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900';
    }
});

const actualBarColor = computed(() => {
    switch (props.data?.burn_zone) {
        case 'danger':
            return 'bg-[#cc0000]';
        case 'warning':
            return 'bg-amber-500';
        case 'on_track':
            return 'bg-blue-600';
        case 'safe':
        default:
            return 'bg-emerald-500';
    }
});

const actualBarWidth = computed(() => {
    const pct = props.data?.actual_pct ?? 0;
    // Cap visual bar width to 100% so it fits in the container, but show real percentage
    return `${Math.min(100, Math.max(0, pct))}%`;
});
</script>

<template>
    <Card
        class="border-border/70 relative overflow-hidden shadow-xs transition-shadow hover:shadow-sm"
        data-slot="kpi-card-burn-index"
    >
        <CardHeader class="flex flex-row items-center justify-between pb-2">
            <div class="flex items-center gap-2">
                <div
                    class="rounded-md p-1.5"
                    :class="
                        data?.burn_zone === 'danger'
                            ? 'bg-red-500/10 text-[#cc0000] dark:bg-red-500/20'
                            : data?.burn_zone === 'warning'
                              ? 'bg-amber-500/10 text-amber-600 dark:bg-amber-500/20'
                              : 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20'
                    "
                >
                    <Flame class="size-4" />
                </div>
                <h3
                    class="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                >
                    {{ __('Indeks Burn (BBI)') }}
                </h3>
            </div>

            <!-- Zone Badge -->
            <Badge
                variant="outline"
                class="text-[10px] font-semibold"
                :class="zoneBadgeClass"
            >
                <ShieldAlert
                    v-if="
                        data?.burn_zone === 'danger' ||
                        data?.burn_zone === 'warning'
                    "
                    class="mr-1 size-2.5"
                />
                <ShieldCheck v-else class="mr-1 size-2.5" />
                {{ data?.burn_zone_label }}
            </Badge>
        </CardHeader>

        <CardContent class="space-y-3 pt-0">
            <template v-if="loading">
                <ChartSkeleton height-class="h-16" variant="sparkline" />
            </template>

            <template v-else>
                <div>
                    <div class="flex items-baseline justify-between gap-2">
                        <div class="flex items-baseline gap-1.5">
                            <span
                                class="font-mono text-2xl font-bold tracking-tight tabular-nums"
                                :class="
                                    data?.burn_zone === 'danger'
                                        ? 'text-[#cc0000] dark:text-red-400'
                                        : data?.burn_zone === 'warning'
                                          ? 'text-amber-600 dark:text-amber-400'
                                          : 'text-slate-900 dark:text-white'
                                "
                            >
                                {{ data?.actual_pct }}%
                            </span>
                            <span
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{ __('Realisasi') }}
                            </span>
                        </div>

                        <span
                            class="font-mono text-xs text-slate-500 tabular-nums dark:text-slate-400"
                        >
                            {{ data?.actual_hours }} /
                            {{ data?.planned_hours }} jam
                        </span>
                    </div>

                    <p
                        class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{ __('Konsumsi Anggaran Lembur Bulan Ini') }}
                    </p>
                </div>

                <!-- Dual Progress Bars: Plan vs Actual -->
                <div class="space-y-1.5 pt-1">
                    <!-- Plan Bar (Baseline 100%) -->
                    <div class="space-y-0.5">
                        <div
                            class="flex justify-between text-[10px] font-medium text-slate-500"
                        >
                            <span>{{ __('Rencana Anggaran (Plafon)') }}</span>
                            <span class="font-mono tabular-nums">100%</span>
                        </div>
                        <div
                            class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                        >
                            <div
                                class="h-full w-full rounded-full bg-blue-500/70"
                            />
                        </div>
                    </div>

                    <!-- Actual Bar (Current Month Realization) -->
                    <div class="space-y-0.5">
                        <div
                            class="flex justify-between text-[10px] font-medium text-slate-500"
                        >
                            <span>{{ __('Realisasi Saat Ini') }}</span>
                            <span class="font-mono font-semibold tabular-nums">
                                {{ data?.actual_pct }}%
                            </span>
                        </div>
                        <div
                            class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-300"
                                :class="actualBarColor"
                                :style="{ width: actualBarWidth }"
                            />
                        </div>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
