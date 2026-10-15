<script setup lang="ts">
import { CalendarDays, Flame } from '@lucide/vue';
import { computed } from 'vue';
import AlertZoneBadge from '@/components/early-warning/AlertZoneBadge.vue';
import { Card, CardContent } from '@/components/ui/card';
import { useBurnZone } from '@/composables/useBurnZone';
import { useTrans } from '@/composables/useTrans';
import { formatAlertDate } from '@/lib/earlyWarning';
import type { EarlyWarningAlert } from '@/lib/earlyWarning';

const props = defineProps<{
    section: { name: string; department_name: string | null };
    date: string;
    /** Null when the date is not (or no longer) an alert. */
    alert: EarlyWarningAlert | null;
}>();

const { __, locale } = useTrans();
const { styleOf } = useBurnZone();

const accentClass = computed(() => {
    if (!props.alert) {
        return 'border-l-slate-300 dark:border-l-slate-600';
    }

    return props.alert.zone === 'red'
        ? 'border-l-[#cc0000]'
        : 'border-l-amber-500';
});
</script>

<template>
    <Card
        class="border-l-4 shadow-2xs"
        :class="accentClass"
        data-test="alert-header-card"
        :data-zone="alert?.zone ?? 'none'"
    >
        <CardContent class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 space-y-1">
                <h2
                    class="text-lg font-bold text-slate-900 dark:text-white"
                    data-test="alert-header-title"
                >
                    {{
                        __('Month-to-Date Index — :section', {
                            section: section.name,
                        })
                    }}
                </h2>
                <div
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500"
                >
                    <span class="flex items-center gap-1">
                        <CalendarDays class="size-3.5" />
                        <span
                            class="font-mono tabular-nums"
                            data-test="alert-header-date"
                            >{{ formatAlertDate(date, locale) }}</span
                        >
                    </span>
                    <span v-if="section.department_name">
                        {{ section.department_name }}
                    </span>
                </div>
            </div>

            <div
                v-if="alert"
                class="flex flex-col items-end gap-2 text-right"
                data-test="alert-header-status"
            >
                <AlertZoneBadge :zone="alert.zone" />
                <div
                    class="font-mono text-3xl leading-none font-bold tabular-nums"
                    :class="styleOf(alert.zone).text"
                    data-test="alert-header-index"
                >
                    {{ alert.index_pct.toFixed(1) }}%
                </div>
                <div
                    class="flex items-center gap-1 text-xs text-slate-600 dark:text-slate-300"
                    data-test="alert-header-streak"
                >
                    <Flame class="size-3.5 text-amber-500" />
                    <span class="font-mono font-semibold tabular-nums">{{
                        alert.streak_days
                    }}</span>
                    {{ __('consecutive days') }}
                </div>
            </div>
        </CardContent>
    </Card>
</template>
