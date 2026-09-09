<script setup lang="ts">
import { computed } from 'vue';

export interface CategorySegment {
    key: 'production' | 'tpm' | 'project' | 'others' | string;
    label?: string;
    hours: number;
    percentage: number;
    color?: string;
}

interface Props {
    categories?: CategorySegment[];
    totalHours?: number;
    heightClass?: string;
}

const props = withDefaults(defineProps<Props>(), {
    categories: () => [],
    totalHours: 0,
    heightClass: 'h-2',
});

const defaultColors: Record<string, string> = {
    production: '#3b82f6', // Blue
    tpm: '#10b981', // Emerald
    project: '#7c3aed', // Purple (CapEx)
    others: '#94a3b8', // Slate
};

const defaultLabels: Record<string, string> = {
    production: 'Produksi',
    tpm: 'TPM',
    project: 'CapEx',
    others: 'Lain-lain',
};

const segments = computed(() => {
    if (!props.categories?.length) {
        return [];
    }

    const total =
        props.totalHours > 0
            ? props.totalHours
            : props.categories.reduce((acc, c) => acc + (c.hours || 0), 0);

    if (total <= 0) {
        return [];
    }

    return props.categories
        .filter((c) => (c.hours || 0) > 0)
        .map((c) => {
            const pct = (c.hours / total) * 100;
            return {
                key: c.key,
                label: c.label || defaultLabels[c.key] || c.key,
                hours: c.hours,
                pct: Math.max(0, Math.min(100, pct)),
                color: c.color || defaultColors[c.key] || '#94a3b8',
            };
        });
});

const tooltipText = computed(() => {
    if (!segments.value.length) {
        return '0 jam';
    }
    return segments.value
        .map((s) => `${s.label}: ${s.hours.toFixed(1)}j (${s.pct.toFixed(0)}%)`)
        .join(' | ');
});
</script>

<template>
    <div
        class="flex w-full min-w-16 items-center"
        :title="tooltipText"
        data-test="mini-category-bar"
    >
        <div
            v-if="segments.length > 0"
            :class="[
                'flex w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800',
                heightClass,
            ]"
        >
            <div
                v-for="seg in segments"
                :key="seg.key"
                :style="{ width: `${seg.pct}%`, backgroundColor: seg.color }"
                class="h-full transition-all duration-200 first:rounded-l-full last:rounded-r-full"
            />
        </div>
        <div
            v-else
            :class="[
                'w-full rounded-full bg-slate-200/80 dark:bg-slate-700/60',
                heightClass,
            ]"
        />
    </div>
</template>
