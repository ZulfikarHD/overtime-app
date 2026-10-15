<script setup lang="ts">
import { CalendarRange, ChevronLeft, ChevronRight } from '@lucide/vue';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import {
    dateRangePresets,
    daysBetween,
    formatDisplayDate,
    formatMonthLabel,
    monthGrid,
    shiftMonth,
    todayInJakarta,
    weekdayLabels,
    yearMonthOf,
} from '@/lib/dateRange';
import type {
    DateRangePresetKey,
    DateRangeValue,
    YearMonth,
} from '@/lib/dateRange';

const props = withDefaults(
    defineProps<{
        from: string;
        to: string;
        /** Longest range in days; omit for no limit. Days beyond it are disabled. */
        maxDays?: number;
        /** Show the quick ranges (today, last 7 days, this month, ...). */
        presets?: boolean;
        /** Prefix of the `data-test` hooks: `{testId}-trigger`, `-day-{date}`, `-preset-{key}`. */
        testId?: string;
    }>(),
    {
        maxDays: undefined,
        presets: true,
        testId: 'date-range',
    },
);

const emit = defineEmits<{
    /** Fired once a complete range is chosen. */
    change: [range: DateRangeValue];
}>();

const { __, locale } = useTrans();

const open = ref(false);
/** First click of a range that is waiting for its second click. */
const pendingStart = ref<string | null>(null);
const hovered = ref<string | null>(null);
const viewMonth = ref<YearMonth>(yearMonthOf(props.from));

const today = computed(() => todayInJakarta());
const weekdays = computed(() => weekdayLabels(locale.value));

watch(open, (isOpen) => {
    if (isOpen) {
        pendingStart.value = null;
        hovered.value = null;
        viewMonth.value = yearMonthOf(props.from);
    }
});

const triggerLabel = computed(
    () =>
        `${formatDisplayDate(props.from, locale.value)} – ${formatDisplayDate(props.to, locale.value)}`,
);

/** Two consecutive months are shown side by side. */
const months = computed(() =>
    [viewMonth.value, shiftMonth(viewMonth.value, 1)].map((month) => ({
        month,
        label: formatMonthLabel(month, locale.value),
        days: monthGrid(month),
    })),
);

/** The range drawn on the calendar: the saved one, or the pending click up to the hovered day. */
const shownRange = computed<DateRangeValue>(() => {
    if (pendingStart.value === null) {
        return { from: props.from, to: props.to };
    }

    const end = hovered.value ?? pendingStart.value;

    return pendingStart.value <= end
        ? { from: pendingStart.value, to: end }
        : { from: end, to: pendingStart.value };
});

const presetLabels: Record<DateRangePresetKey, string> = {
    today: 'Today',
    last7: 'Last 7 days',
    last30: 'Last 30 days',
    thisMonth: 'This month',
    lastMonth: 'Last month',
};

function isWithinLimit(from: string, to: string): boolean {
    return props.maxDays === undefined || daysBetween(from, to) < props.maxDays;
}

const availablePresets = computed(() =>
    dateRangePresets(today.value).filter((preset) =>
        isWithinLimit(preset.from, preset.to),
    ),
);

function isDisabled(day: string): boolean {
    return (
        pendingStart.value !== null &&
        !isWithinLimit(
            pendingStart.value < day ? pendingStart.value : day,
            pendingStart.value < day ? day : pendingStart.value,
        )
    );
}

function dayClasses(day: string): string[] {
    const range = shownRange.value;
    const isEdge = day === range.from || day === range.to;
    const isInside = day > range.from && day < range.to;

    return [
        'relative flex h-8 w-full items-center justify-center font-mono text-xs tabular-nums transition-colors',
        isEdge
            ? 'rounded-md bg-[#cc0000] font-bold text-white'
            : isInside
              ? 'bg-red-50 text-[#990000] dark:bg-red-950/40 dark:text-red-200'
              : 'rounded-md text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800',
        day === today.value && !isEdge
            ? 'ring-1 ring-slate-400 ring-inset'
            : '',
        isDisabled(day) ? 'cursor-not-allowed opacity-30' : 'cursor-pointer',
    ];
}

function pickDay(day: string): void {
    if (isDisabled(day)) {
        return;
    }

    if (pendingStart.value === null) {
        pendingStart.value = day;
        hovered.value = day;

        return;
    }

    const start = pendingStart.value;

    pendingStart.value = null;
    hovered.value = null;
    open.value = false;
    emit(
        'change',
        day < start ? { from: day, to: start } : { from: start, to: day },
    );
}

function applyPreset(range: DateRangeValue): void {
    open.value = false;
    emit('change', { from: range.from, to: range.to });
}
</script>

<template>
    <div class="space-y-1" :data-test="testId">
        <span class="block text-xs font-medium text-slate-500">
            {{ __('Date range') }}
        </span>
        <PopoverRoot v-model:open="open">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="outline"
                    class="h-9 justify-start gap-2 font-mono text-xs font-normal tabular-nums"
                    :aria-label="`${__('Date range')}: ${triggerLabel}`"
                    :data-test="`${testId}-trigger`"
                >
                    <CalendarRange
                        class="size-3.5 shrink-0 text-slate-500"
                        aria-hidden="true"
                    />
                    <span :data-test="`${testId}-label`">{{
                        triggerLabel
                    }}</span>
                </Button>
            </PopoverTrigger>
            <PopoverPortal>
                <PopoverContent
                    align="start"
                    :side-offset="6"
                    class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 z-50 max-w-[calc(100vw-1rem)] rounded-lg border p-3 shadow-md"
                    :data-test="`${testId}-popover`"
                >
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <div
                            v-if="presets"
                            class="flex flex-row flex-wrap gap-1 sm:w-32 sm:shrink-0 sm:flex-col sm:flex-nowrap sm:border-r sm:pr-3"
                            role="group"
                            :aria-label="__('Quick range')"
                        >
                            <button
                                v-for="preset in availablePresets"
                                :key="preset.key"
                                type="button"
                                class="rounded-md px-2 py-1.5 text-left text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                :data-test="`${testId}-preset-${preset.key}`"
                                @click="applyPreset(preset)"
                            >
                                {{ __(presetLabels[preset.key]) }}
                            </button>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-7"
                                    :aria-label="__('Previous month')"
                                    :data-test="`${testId}-prev`"
                                    @click="
                                        viewMonth = shiftMonth(viewMonth, -1)
                                    "
                                >
                                    <ChevronLeft
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-7"
                                    :aria-label="__('Next month')"
                                    :data-test="`${testId}-next`"
                                    @click="
                                        viewMonth = shiftMonth(viewMonth, 1)
                                    "
                                >
                                    <ChevronRight
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </div>

                            <div
                                class="flex flex-col gap-4 sm:flex-row"
                                @mouseleave="hovered = pendingStart"
                            >
                                <div
                                    v-for="(panel, index) in months"
                                    :key="panel.label"
                                    class="w-56"
                                    :class="
                                        index === 1 ? 'hidden sm:block' : ''
                                    "
                                >
                                    <p
                                        class="mb-2 text-center text-xs font-semibold text-slate-700 capitalize dark:text-slate-200"
                                    >
                                        {{ panel.label }}
                                    </p>
                                    <div
                                        class="grid grid-cols-7 text-center text-[10px] font-medium text-slate-400"
                                    >
                                        <span
                                            v-for="weekday in weekdays"
                                            :key="weekday"
                                            class="py-1"
                                            >{{ weekday }}</span
                                        >
                                    </div>
                                    <div class="grid grid-cols-7 gap-y-0.5">
                                        <template
                                            v-for="day in panel.days"
                                            :key="day"
                                        >
                                            <span
                                                v-if="
                                                    Number(day.slice(5, 7)) !==
                                                    panel.month.month
                                                "
                                                class="h-8"
                                                aria-hidden="true"
                                            ></span>
                                            <button
                                                v-else
                                                type="button"
                                                :class="dayClasses(day)"
                                                :disabled="isDisabled(day)"
                                                :aria-label="
                                                    formatDisplayDate(
                                                        day,
                                                        locale,
                                                    )
                                                "
                                                :aria-pressed="
                                                    day === shownRange.from ||
                                                    day === shownRange.to
                                                "
                                                :data-test="`${testId}-day-${day}`"
                                                @click="pickDay(day)"
                                                @mouseenter="
                                                    pendingStart !== null &&
                                                    (hovered = day)
                                                "
                                            >
                                                {{ Number(day.slice(8, 10)) }}
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <p
                                v-if="pendingStart !== null"
                                class="text-[11px] text-slate-500"
                                :data-test="`${testId}-hint`"
                            >
                                {{ __('Choose the end date.') }}
                            </p>
                        </div>
                    </div>
                </PopoverContent>
            </PopoverPortal>
        </PopoverRoot>
    </div>
</template>
