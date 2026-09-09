<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    percentage: number;
    zone?: 'safe' | 'on_track' | 'warning' | 'danger';
    showLabel?: boolean;
    heightClass?: string;
}

const props = withDefaults(defineProps<Props>(), {
    zone: undefined,
    showLabel: true,
    heightClass: 'h-2',
});

const resolvedZone = computed<'safe' | 'on_track' | 'warning' | 'danger'>(
    () => {
        if (props.zone) {
            return props.zone;
        }
        const pct = props.percentage;
        if (pct > 115) {
            return 'danger';
        }
        if (pct > 100) {
            return 'warning';
        }
        if (pct >= 85) {
            return 'on_track';
        }
        return 'safe';
    },
);

const barColorClass = computed(() => {
    switch (resolvedZone.value) {
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

const textColorClass = computed(() => {
    switch (resolvedZone.value) {
        case 'danger':
            return 'text-[#cc0000] dark:text-red-400 font-semibold';
        case 'warning':
            return 'text-amber-600 dark:text-amber-400 font-medium';
        case 'on_track':
            return 'text-blue-600 dark:text-blue-400 font-medium';
        case 'safe':
        default:
            return 'text-emerald-600 dark:text-emerald-400 font-medium';
    }
});

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
