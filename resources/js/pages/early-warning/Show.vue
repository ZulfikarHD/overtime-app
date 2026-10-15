<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import AlertBurnBar from '@/components/early-warning/AlertBurnBar.vue';
import AlertFollowUpSummary from '@/components/early-warning/AlertFollowUpSummary.vue';
import AlertHeaderCard from '@/components/early-warning/AlertHeaderCard.vue';
import AlertIndexChart from '@/components/early-warning/AlertIndexChart.vue';
import AlertVitalsGrid from '@/components/early-warning/AlertVitalsGrid.vue';
import { useTrans } from '@/composables/useTrans';
import type { ZoneBoundaries } from '@/lib/burnZone';
import type {
    AlertFollowUpItem,
    AlertVitals,
    EarlyWarningAlert,
    SeriesPoint,
} from '@/lib/earlyWarning';
import { dashboard } from '@/routes';
import { index as earlyWarningIndex } from '@/routes/early-warning';

const props = defineProps<{
    section: {
        id: number;
        name: string;
        department_id: number;
        department_name: string | null;
    };
    date: string;
    /** Null when the date is not (or no longer) an alert. */
    alert: EarlyWarningAlert | null;
    series: SeriesPoint[];
    limits: ZoneBoundaries;
    vitals: AlertVitals;
    followUps: AlertFollowUpItem[];
    /** Inbox URL including the filters the user came from. */
    backUrl: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Early Warning', href: earlyWarningIndex() },
        ],
    },
});

const { __ } = useTrans();

/** The alert date inside the series; missing when the section has no budget that month or the date is in the future. */
const currentDay = computed(
    () => props.series.find((point) => point.date === props.date) ?? null,
);
</script>

<template>
    <div class="space-y-4" data-test="early-warning-show">
        <Head :title="__('Early Warning')" />

        <Link
            :href="backUrl"
            class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
            data-test="early-warning-back"
        >
            <ArrowLeft class="size-4" />
            {{ __('Back to Early Warning') }}
        </Link>

        <div
            v-if="!alert"
            class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900"
            role="status"
            data-test="alert-not-an-alert"
        >
            <ShieldCheck class="mt-0.5 size-5 shrink-0 text-emerald-500" />
            <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-white">
                    {{ __('No alert on this date') }}
                </p>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{
                        __(
                            'The daily index is within the Hijau zone or has no budget, so no follow-up can be created.',
                        )
                    }}
                </p>
            </div>
        </div>

        <AlertHeaderCard :section="section" :date="date" :alert="alert" />

        <AlertBurnBar
            v-if="currentDay"
            :index-pct="currentDay.index_pct"
            :zone="currentDay.zone"
            :limits="limits"
        />

        <AlertVitalsGrid :vitals="vitals" />

        <AlertIndexChart :series="series" :limits="limits" :alert-date="date" />

        <AlertFollowUpSummary :follow-ups="followUps" />
    </div>
</template>
