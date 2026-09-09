<script setup lang="ts">
import { type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

interface Props {
    class?: HTMLAttributes['class'];
    heightClass?: string;
    variant?: 'chart' | 'sparkline' | 'bar' | 'donut';
}

withDefaults(defineProps<Props>(), {
    class: '',
    heightClass: 'h-64',
    variant: 'chart',
});
</script>

<template>
    <div
        data-slot="chart-skeleton"
        :class="
            cn(
                'relative flex w-full animate-pulse flex-col justify-end overflow-hidden rounded-lg bg-slate-100/70 p-4 dark:bg-slate-800/40',
                heightClass,
                $props.class,
            )
        "
    >
        <!-- Sparkline variant -->
        <template v-if="variant === 'sparkline'">
            <div class="flex h-full w-full items-end gap-1.5">
                <div
                    v-for="i in 8"
                    :key="i"
                    class="flex-1 rounded-xs bg-slate-200/80 dark:bg-slate-700/60"
                    :style="{ height: `${20 + (i % 5) * 15}%` }"
                />
            </div>
        </template>

        <!-- Donut variant -->
        <template v-else-if="variant === 'donut'">
            <div class="flex h-full w-full items-center justify-center">
                <div
                    class="size-32 rounded-full border-8 border-slate-200/80 border-t-slate-300 dark:border-slate-700/60 dark:border-t-slate-600"
                />
            </div>
        </template>

        <!-- Default chart / bar variant -->
        <template v-else>
            <!-- Simulated grid lines -->
            <div class="absolute inset-0 flex flex-col justify-between p-4">
                <div class="h-px w-full bg-slate-200/60 dark:bg-slate-700/40" />
                <div class="h-px w-full bg-slate-200/60 dark:bg-slate-700/40" />
                <div class="h-px w-full bg-slate-200/60 dark:bg-slate-700/40" />
                <div class="h-px w-full bg-slate-200/60 dark:bg-slate-700/40" />
            </div>

            <!-- Simulated chart bars / silhouette -->
            <div class="relative z-10 flex h-3/4 w-full items-end gap-2 px-2">
                <div
                    v-for="i in 12"
                    :key="i"
                    class="flex-1 rounded-t-sm bg-slate-200/90 dark:bg-slate-700/70"
                    :style="{ height: `${25 + ((i * 7) % 65)}%` }"
                />
            </div>
        </template>
    </div>
</template>
