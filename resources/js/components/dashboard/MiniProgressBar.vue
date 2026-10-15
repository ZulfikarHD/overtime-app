<script setup lang="ts">
import { computed } from 'vue';
import { useBurnZone } from '@/composables/useBurnZone';
import { BURN_ZONE_STYLES, normalizeBurnZone } from '@/lib/burnZone';
import type { BurnZone } from '@/lib/burnZone';

interface Props {
    percentage: number;
    zone?: string;
    showLabel?: boolean;
    heightClass?: string;
}

const props = withDefaults(defineProps<Props>(), {
    zone: undefined,
    showLabel: true,
    heightClass: 'h-2',
});

const { classify } = useBurnZone();

const resolvedZone = computed<BurnZone>(() =>
    props.zone ? normalizeBurnZone(props.zone) : classify(props.percentage),
);

const barColorClass = computed(
    () => BURN_ZONE_STYLES[resolvedZone.value].solid,
);

const textColorClass = computed(
    () =>
        `${BURN_ZONE_STYLES[resolvedZone.value].text} ${
            resolvedZone.value === 'red' ? 'font-semibold' : 'font-medium'
        }`,
);

const barWidth = computed(() => {
    const clamped = Math.min(100, Math.max(0, props.percentage));
    return `${clamped}%`;
});
</script>

<template>
    <div class="flex items-center gap-2" data-test="mini-progress-bar">
        <div
            :class="[
                'w-full min-w-16 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800',
                heightClass,
            ]"
        >
            <div
                :class="[
                    'h-full rounded-full transition-all duration-300',
                    barColorClass,
                ]"
                :style="{ width: barWidth }"
            />
        </div>
        <span
            v-if="showLabel"
            :class="['font-mono text-xs tabular-nums', textColorClass]"
        >
            {{ percentage.toFixed(1) }}%
        </span>
    </div>
</template>
