<script setup lang="ts">
import { Flame, ShieldAlert, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import ChartSkeleton from '@/components/charts/ChartSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { BURN_ZONE_STYLES, normalizeBurnZone } from '@/lib/burnZone';
import type { BurnZone } from '@/lib/burnZone';

export interface BurnIndexCardData {
    plan_pct: number;
    actual_pct: number;
    planned_hours: number;
    actual_hours: number;
    burn_zone: BurnZone;
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
        burn_zone: 'green',
        burn_zone_label: 'Hijau (≤70%)',
    }),
    loading: false,
});

const { __ } = useTrans();

const zone = computed(() => normalizeBurnZone(props.data?.burn_zone));

const zoneBadgeClass = computed(() => BURN_ZONE_STYLES[zone.value].badge);

const actualBarColor = computed(() => BURN_ZONE_STYLES[zone.value].solid);

const heroPctClass = computed(() =>
    zone.value === 'green'
        ? 'text-slate-900 dark:text-white'
        : BURN_ZONE_STYLES[zone.value].text,
);

const actualBarWidth = computed(() => {
    const pct = props.data?.actual_pct ?? 0;

    return `${Math.min(100, Math.max(0, pct))}%`;
});
</script>

<template>
    <Card
        class="border-border/70 @container gap-3 py-4 shadow-xs transition-shadow hover:shadow-sm sm:gap-4 sm:py-5"
        data-slot="kpi-card-burn-index"
        data-test="kpi-card-burn-index"
    >
        <CardHeader class="px-4 pb-1 sm:px-6">
            <div class="flex min-w-0 items-center gap-2">
                <div
                    class="shrink-0 rounded-md p-1.5"
                    :class="
                        zone === 'red'
                            ? 'bg-red-500/10 text-[#cc0000] dark:bg-red-500/20'
                            : zone === 'yellow'
                              ? 'bg-amber-500/10 text-amber-600 dark:bg-amber-500/20'
                              : 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20'
                    "
                >
                    <Flame class="size-3.5 sm:size-4" />
                </div>
                <h3
                    class="min-w-0 flex-1 text-[10px] leading-snug font-semibold tracking-wider text-slate-500 uppercase sm:text-[11px] dark:text-slate-400"
                    data-test="burn-index-title"
                >
                    {{ __('Index Burn Up (Day to Date)') }}
                </h3>
            </div>
        </CardHeader>

        <CardContent class="space-y-2 px-4 pt-0 sm:space-y-2.5 sm:px-6">
            <template v-if="loading">
                <ChartSkeleton height-class="h-16" variant="sparkline" />
            </template>

            <template v-else>
                <div class="min-w-0" data-test="burn-index-hero">
                    <div
                        class="font-mono text-2xl leading-none font-bold tracking-tight tabular-nums @[14rem]:text-3xl @[20rem]:text-[2rem]"
                        :class="heroPctClass"
                        data-test="burn-index-pct"
                    >
                        {{ data?.actual_pct }}%
                    </div>
                    <p
                        class="mt-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                        data-test="burn-index-subtitle"
                    >
                        {{ __('Realisasi') }}
                    </p>
                    <p
                        class="text-[10px] text-slate-500 sm:text-[11px] dark:text-slate-400"
                    >
                        {{ __('Konsumsi Budget Lembur Bulan Ini') }}
                    </p>
                    <div
                        class="mt-2 flex flex-wrap items-center gap-1.5 sm:gap-2"
                        data-test="burn-index-meta"
                    >
                        <Badge
                            variant="outline"
                            class="max-w-full text-[10px] font-semibold"
                            :class="zoneBadgeClass"
                        >
                            <ShieldAlert
                                v-if="zone !== 'green'"
                                class="mr-1 size-2.5 shrink-0"
                            />
                            <ShieldCheck
                                v-else
                                class="mr-1 size-2.5 shrink-0"
                            />
                            <span class="truncate">{{
                                data?.burn_zone_label
                            }}</span>
                        </Badge>
                        <span
                            class="font-mono text-[10px] text-slate-500 tabular-nums dark:text-slate-400"
                        >
                            {{ data?.actual_hours }} /
                            {{ data?.planned_hours }}
                            {{ __('jam') }}
                        </span>
                    </div>
                </div>

                <div class="min-w-0 space-y-1.5 pt-0.5">
                    <div class="space-y-0.5">
                        <div
                            class="flex justify-between gap-2 text-[10px] font-medium text-slate-500"
                        >
                            <span class="min-w-0 truncate">{{
                                __('Rencana Budget (Plafon)')
                            }}</span>
                            <span class="shrink-0 font-mono tabular-nums"
                                >100%</span
                            >
                        </div>
                        <div
                            class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                        >
                            <div
                                class="h-full w-full rounded-full bg-blue-500/70"
                            />
                        </div>
                    </div>

                    <div class="space-y-0.5">
                        <div
                            class="flex justify-between gap-2 text-[10px] font-medium text-slate-500"
                        >
                            <span class="min-w-0 truncate">{{
                                __('Realisasi Saat Ini')
                            }}</span>
                            <span
                                class="shrink-0 font-mono font-semibold tabular-nums"
                            >
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
