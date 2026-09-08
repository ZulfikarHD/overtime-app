<script setup lang="ts">
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    capexHours: number;
    opexHours: number;
    capexRatio: number;
    opexRatio: number;
}>();

const { __ } = useTrans();

const safeCapexRatio = computed(() => {
    if (props.capexHours + props.opexHours <= 0) {
        return 0;
    }
    return Math.max(0, Math.min(100, props.capexRatio));
});

const safeOpexRatio = computed(() => {
    if (props.capexHours + props.opexHours <= 0) {
        return 0;
    }
    return Math.max(0, Math.min(100, props.opexRatio));
});
</script>

<template>
    <div class="space-y-1.5" data-test="capex-opex-split-bar">
        <div class="flex items-center justify-between text-[11px]">
            <div
                class="flex items-center gap-1.5 text-sky-700 dark:text-sky-300"
            >
                <span class="size-2 rounded-full bg-sky-500" />
                <span class="font-medium">{{ __('CapEx Proyek') }}:</span>
                <span class="font-mono font-semibold tabular-nums"
                    >{{ capexHours.toFixed(1) }} {{ __('jam') }} ({{
                        safeCapexRatio.toFixed(1)
                    }}%)</span
                >
            </div>
            <div
                class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300"
            >
                <span
                    class="size-2 rounded-full bg-slate-400 dark:bg-slate-500"
                />
                <span class="font-medium">{{ __('OpEx Rutin') }}:</span>
                <span class="font-mono font-semibold tabular-nums"
                    >{{ opexHours.toFixed(1) }} {{ __('jam') }} ({{
                        safeOpexRatio.toFixed(1)
                    }}%)</span
                >
            </div>
        </div>

        <!-- Stacked Horizontal Bar -->
        <div
            class="flex h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
        >
            <div
                v-if="safeCapexRatio > 0"
                class="h-full bg-sky-500 transition-all duration-300 dark:bg-sky-600"
                :style="{ width: `${safeCapexRatio}%` }"
                :title="`CapEx: ${capexHours.toFixed(1)} jam (${safeCapexRatio.toFixed(1)}%)`"
            />
            <div
                v-if="safeOpexRatio > 0"
                class="h-full bg-slate-400 transition-all duration-300 dark:bg-slate-500"
                :style="{ width: `${safeOpexRatio}%` }"
                :title="`OpEx: ${opexHours.toFixed(1)} jam (${safeOpexRatio.toFixed(1)}%)`"
            />
            <div
                v-if="capexHours + opexHours <= 0"
                class="h-full w-full bg-slate-200 dark:bg-slate-700"
                :title="__('Belum ada jam lembur')"
            />
        </div>
    </div>
</template>
