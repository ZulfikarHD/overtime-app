<script setup lang="ts">
import { computed } from 'vue';
import type { BurnIndicator } from '@/composables/useSectionRoster';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    burn: BurnIndicator;
}>();

const { __ } = useTrans();

const statusClass = computed(() => {
    if (props.burn.burn_zone === 'danger' || props.burn.burn_pct > 100) {
        return {
            text: 'text-[#cc0000] dark:text-red-400',
            bg: 'bg-[#cc0000]',
            badge: 'bg-red-50 text-[#cc0000] border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900',
        };
    }
    if (props.burn.burn_zone === 'caution' || props.burn.burn_pct >= 85) {
        return {
            text: 'text-amber-700 dark:text-amber-400',
            bg: 'bg-amber-500',
            badge: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900',
        };
    }
    return {
        text: 'text-emerald-700 dark:text-emerald-400',
        bg: 'bg-emerald-500',
        badge: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900',
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
                __('Anggaran Seksi')
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
