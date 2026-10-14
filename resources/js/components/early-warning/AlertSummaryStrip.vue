<script setup lang="ts">
import { BellRing, CircleAlert, ClipboardX, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { useTrans } from '@/composables/useTrans';
import { BURN_ZONE_STYLES } from '@/lib/burnZone';
import type { AlertSummary } from '@/lib/earlyWarning';

const props = defineProps<{
    /** Undefined while the deferred summary is still loading. */
    summary?: AlertSummary;
}>();

const { __ } = useTrans();

const cards = computed(() => [
    {
        key: 'total',
        label: __('Total alerts'),
        value: props.summary?.total,
        icon: BellRing,
        valueClass: 'text-slate-900 dark:text-white',
    },
    {
        key: 'red',
        label: __('Merah'),
        value: props.summary?.red,
        icon: CircleAlert,
        valueClass: BURN_ZONE_STYLES.red.text,
    },
    {
        key: 'yellow',
        label: __('Kuning'),
        value: props.summary?.yellow,
        icon: TriangleAlert,
        valueClass: BURN_ZONE_STYLES.yellow.text,
    },
    {
        key: 'without-follow-up',
        label: __('Without follow-up'),
        value: props.summary?.without_follow_up,
        icon: ClipboardX,
        valueClass: 'text-slate-900 dark:text-white',
    },
]);
</script>

<template>
    <div
        class="grid grid-cols-2 gap-3 lg:grid-cols-4"
        data-test="alert-summary-strip"
    >
        <div
            v-for="card in cards"
            :key="card.key"
            class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900"
        >
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <component :is="card.icon" class="size-4 text-slate-400" />
                {{ card.label }}
            </div>
            <Skeleton
                v-if="card.value === undefined"
                class="mt-2 h-7 w-12"
                :data-test="`summary-${card.key}-loading`"
            />
            <div
                v-else
                class="mt-1 font-mono text-2xl font-bold tabular-nums"
                :class="card.valueClass"
                :data-test="`summary-${card.key}`"
            >
                {{ card.value }}
            </div>
        </div>
    </div>
</template>
