<script setup lang="ts">
import { computed } from 'vue';
import type { BurnIndicator } from '@/composables/useSectionRoster';
import { useTrans } from '@/composables/useTrans';
import { BURN_ZONE_STYLES, normalizeBurnZone } from '@/lib/burnZone';

const props = defineProps<{
    burn: BurnIndicator;
}>();

const { __ } = useTrans();

const statusClass = computed(() => {
    const style = BURN_ZONE_STYLES[normalizeBurnZone(props.burn.burn_zone)];

    return {
        text: style.text,
        bg: style.solid,
        badge: style.badge,
    };
});

const progressWidth = computed(() => {
    return Math.min(100, Math.max(0, props.burn.burn_pct));
});
</script>

<template>
    <div
        class="flex flex-col gap-1 rounded-lg border px-3 py-2 text-xs shadow-2xs"
        :class="statusClass.badge"
        data-test="section-burn-indicator"
    >
        <div class="flex items-center justify-between gap-3 font-medium">
            <span class="text-slate-600 dark:text-slate-400">{{
                __('Plafon Jam Seksi')
            }}</span>
            <span
                class="font-mono font-semibold tabular-nums"
                :class="statusClass.text"
            >
                {{ burn.actual_hours.toFixed(1) }} /
                {{ burn.planned_hours.toFixed(1) }} {{ __('jam') }} ({{
                    burn.burn_pct.toFixed(1)
                }}%)
            </span>
        </div>
        <!-- Progress Bar -->
        <div
            class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200/80 dark:bg-slate-700"
        >
            <div
                class="h-full transition-all duration-300"
                :class="statusClass.bg"
                :style="{ width: `${progressWidth}%` }"
            />
        </div>
    </div>
</template>
