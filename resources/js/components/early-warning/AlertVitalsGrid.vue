<script setup lang="ts">
import {
    ArrowDownRight,
    ArrowUpRight,
    Factory,
    Info,
    Minus,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTrans } from '@/composables/useTrans';
import { formatSignedPct } from '@/lib/earlyWarning';
import type { AlertVitals } from '@/lib/earlyWarning';

const props = defineProps<{
    vitals: AlertVitals;
}>();

const { __ } = useTrans();

function trendIcon(value: number | null) {
    if (value === null || !Number.isFinite(value) || value === 0) {
        return Minus;
    }

    return value > 0 ? ArrowUpRight : ArrowDownRight;
}

const cards = computed(() => [
    {
        key: 'volume',
        label: __('Production volume'),
        icon: Factory,
        value: props.vitals.volume_change_pct,
        period: props.vitals.volume_period_label,
        emptyHint: __(
            'Production volume data is not available for this period.',
        ),
    },
    {
        key: 'manpower',
        label: __('Manpower change'),
        icon: Users,
        value: props.vitals.manpower_change_pct,
        period: props.vitals.manpower_period_label,
        emptyHint: __('Manpower data is not available for this period.'),
    },
]);
</script>

<template>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2" data-test="alert-vitals">
        <Card
            v-for="card in cards"
            :key="card.key"
            class="shadow-2xs"
            :data-test="`alert-vital-${card.key}`"
        >
            <CardContent class="space-y-1">
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <component :is="card.icon" class="size-4 text-slate-400" />
                    {{ card.label }}
                </div>

                <div
                    v-if="formatSignedPct(card.value) !== null"
                    class="flex items-center gap-2"
                >
                    <component
                        :is="trendIcon(card.value)"
                        class="size-5 text-slate-500"
                        :data-trend="
                            card.value === 0
                                ? 'flat'
                                : (card.value ?? 0) > 0
                                  ? 'up'
                                  : 'down'
                        "
                    />
                    <span
                        class="font-mono text-2xl font-bold text-slate-900 tabular-nums dark:text-white"
                        :data-test="`alert-vital-${card.key}-value`"
                    >
                        {{ formatSignedPct(card.value) }}
                    </span>
                </div>

                <div v-else class="flex items-center gap-2">
                    <span
                        class="font-mono text-2xl font-bold text-slate-400 tabular-nums"
                        :data-test="`alert-vital-${card.key}-value`"
                    >
                        N/A
                    </span>
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <button
                                    type="button"
                                    class="inline-flex cursor-help items-center text-slate-400 hover:text-slate-600"
                                    :aria-label="card.emptyHint"
                                    :data-test="`alert-vital-${card.key}-hint`"
                                >
                                    <Info class="size-4" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent class="max-w-xs text-xs">
                                <p>{{ card.emptyHint }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>

                <p
                    v-if="card.period"
                    class="text-[11px] text-slate-500"
                    :data-test="`alert-vital-${card.key}-period`"
                >
                    {{ card.period }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
