<script setup lang="ts">
import {
    ArrowDownRight,
    ArrowUpRight,
    CalendarClock,
    CircleCheck,
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
import type { AlertVitals, VitalSource } from '@/lib/earlyWarning';

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

/**
 * Actual and forecast figures are told apart by three cues, never by color alone: an icon, a text label and the
 * card border (dashed = estimate). Amber is the app's "caution" color, emerald its "safe" color.
 */
const SOURCE_STYLES: Record<
    VitalSource,
    { badge: string; card: string; hint: () => string }
> = {
    actual: {
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
        card: '',
        hint: () =>
            __('Based on the actual monthly data entered in ML Data Training.'),
    },
    forecast: {
        badge: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
        card: 'border-dashed border-amber-300 dark:border-amber-800',
        hint: () =>
            __(
                'No actual data is entered for this period yet, so the planned figure from Forecasting is used. It is an estimate: enter the actual figures in ML Data Training to replace it.',
            ),
    },
};

const cards = computed(() => [
    {
        key: 'volume',
        label: __('Production volume'),
        icon: Factory,
        value: props.vitals.volume_change_pct,
        period: props.vitals.volume_period_label,
        source: props.vitals.volume_source,
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
        source: props.vitals.manpower_source,
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
            :class="card.source ? SOURCE_STYLES[card.source].card : ''"
            :data-test="`alert-vital-${card.key}`"
            :data-source="card.source ?? 'none'"
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

                <div
                    v-if="card.period"
                    class="flex flex-wrap items-center gap-1.5"
                >
                    <p
                        class="text-[11px] text-slate-500"
                        :data-test="`alert-vital-${card.key}-period`"
                    >
                        {{ card.period }}
                    </p>

                    <TooltipProvider v-if="card.source">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <button
                                    type="button"
                                    class="inline-flex cursor-help items-center gap-1 rounded-full border px-2 py-px text-[10px] font-semibold"
                                    :class="SOURCE_STYLES[card.source].badge"
                                    :aria-label="
                                        SOURCE_STYLES[card.source].hint()
                                    "
                                    :data-source="card.source"
                                    :data-test="`alert-vital-${card.key}-source`"
                                >
                                    <component
                                        :is="
                                            card.source === 'forecast'
                                                ? CalendarClock
                                                : CircleCheck
                                        "
                                        class="size-3"
                                    />
                                    {{
                                        card.source === 'forecast'
                                            ? __('Forecast')
                                            : __('Aktual')
                                    }}
                                </button>
                            </TooltipTrigger>
                            <TooltipContent class="max-w-xs text-xs">
                                <p>{{ SOURCE_STYLES[card.source].hint() }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
