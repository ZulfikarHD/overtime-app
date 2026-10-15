<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { BURN_ZONE_STYLES, describeZoneRange } from '@/lib/burnZone';
import type { BurnZone, ZoneBoundaries } from '@/lib/burnZone';

/** The plan itself: 100% of the daily pacing target. */
const PLAN_PCT = 100;

const props = defineProps<{
    indexPct: number;
    zone: BurnZone;
    limits: ZoneBoundaries;
}>();

const { __ } = useTrans();

/**
 * Right edge of the bar: at least 130% so the limits stay readable, more when the index is higher.
 */
const scaleMax = computed(() =>
    Math.max(130, Math.ceil(Math.max(props.indexPct, PLAN_PCT) * 1.1)),
);

function position(value: number): number {
    return Math.min(100, Math.max(0, (value / scaleMax.value) * 100));
}

const fillWidth = computed(() => position(props.indexPct));

const markers = computed(() => [
    {
        key: 'green',
        label: __('Batas Hijau'),
        value: props.limits.green_max_pct,
    },
    {
        key: 'yellow',
        label: __('Batas Kuning'),
        value: props.limits.yellow_max_pct,
    },
]);
</script>

<template>
    <Card class="shadow-2xs" data-test="alert-burn-bar">
        <CardContent class="space-y-3">
            <div class="flex items-baseline justify-between gap-2">
                <span class="text-xs font-semibold text-slate-500 uppercase">
                    {{ __('Month-to-date index') }}
                </span>
                <span
                    class="font-mono text-sm font-bold tabular-nums"
                    :class="BURN_ZONE_STYLES[zone].text"
                    data-test="alert-burn-bar-value"
                >
                    {{ indexPct.toFixed(1) }}%
                </span>
            </div>

            <div class="relative pt-1 pb-6">
                <div
                    class="relative h-3 w-full rounded-full bg-slate-100 dark:bg-slate-800"
                    role="progressbar"
                    :aria-valuenow="Math.round(indexPct)"
                    aria-valuemin="0"
                    :aria-valuemax="Math.round(scaleMax)"
                    :aria-label="__('Month-to-date index')"
                >
                    <div
                        class="h-full rounded-full transition-all"
                        :class="BURN_ZONE_STYLES[zone].solid"
                        :style="{ width: `${fillWidth}%` }"
                        data-test="alert-burn-bar-fill"
                    />
                    <span
                        v-for="marker in markers"
                        :key="marker.key"
                        class="absolute -top-1 h-5 w-0.5 bg-slate-500 dark:bg-slate-300"
                        :style="{ left: `${position(marker.value)}%` }"
                        :title="`${marker.label}: ${marker.value}%`"
                        :data-test="`alert-burn-bar-marker-${marker.key}`"
                    />
                    <span
                        class="absolute -top-1 h-5 w-0.5 border-l border-dashed border-slate-500"
                        :style="{ left: `${position(PLAN_PCT)}%` }"
                        data-test="alert-burn-bar-target"
                    />
                </div>
            </div>

            <div
                class="flex flex-wrap items-center gap-x-4 gap-y-1 font-mono text-[11px] text-slate-600 dark:text-slate-300"
                data-test="alert-burn-bar-legend"
            >
                <span
                    v-for="item in ['green', 'yellow', 'red'] as const"
                    :key="item"
                    class="flex items-center gap-1"
                >
                    <span
                        class="inline-block size-2.5 rounded-xs"
                        :class="BURN_ZONE_STYLES[item].solid"
                    />
                    {{ __(BURN_ZONE_STYLES[item].label) }}
                    {{ describeZoneRange(item, limits) }}
                </span>
                <span class="text-slate-500" data-test="alert-burn-bar-caption">
                    {{ __('Target :pct%', { pct: PLAN_PCT }) }}
                </span>
            </div>
        </CardContent>
    </Card>
</template>
