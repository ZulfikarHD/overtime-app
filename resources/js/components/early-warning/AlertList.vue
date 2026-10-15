<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, ShieldCheck } from '@lucide/vue';
import AlertZoneBadge from '@/components/early-warning/AlertZoneBadge.vue';
import FollowUpStatusPill from '@/components/early-warning/FollowUpStatusPill.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useTrans } from '@/composables/useTrans';
import { formatAlertDate } from '@/lib/earlyWarning';
import type { AlertPage, EarlyWarningAlert } from '@/lib/earlyWarning';
import { show } from '@/routes/early-warning';

defineProps<{
    /** Undefined while the deferred alerts are still loading. */
    alerts?: AlertPage;
}>();

const emit = defineEmits<{
    page: [page: number];
}>();

const { __, locale } = useTrans();

function detailUrl(alert: EarlyWarningAlert): string {
    return show.url({ section: alert.section_id, date: alert.alert_date });
}

function openAlert(alert: EarlyWarningAlert, event: MouseEvent): void {
    if ((event.target as HTMLElement).closest('a, button')) {
        return;
    }

    router.visit(detailUrl(alert));
}

function rowKey(alert: EarlyWarningAlert): string {
    return `${alert.section_id}|${alert.alert_date}`;
}
</script>

<template>
    <div class="space-y-3" data-test="alert-list">
        <div
            v-if="alerts === undefined"
            class="space-y-2"
            data-test="alert-list-skeleton"
            aria-busy="true"
        >
            <Skeleton v-for="row in 5" :key="row" class="h-14 w-full" />
        </div>

        <div
            v-else-if="alerts.data.length === 0"
            class="rounded-lg border border-slate-200 bg-white px-4 py-14 text-center dark:border-slate-700 dark:bg-slate-900"
            data-test="alert-empty-state"
        >
            <ShieldCheck class="mx-auto mb-3 size-10 text-emerald-500" />
            <p class="text-sm font-semibold text-slate-900 dark:text-white">
                {{ __('No alerts in this period') }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
                {{
                    __('Widen the date range or relax the filters to see more.')
                }}
            </p>
        </div>

        <template v-else>
            <div
                class="hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm md:block dark:border-slate-700 dark:bg-slate-900"
            >
                <table class="w-full text-sm" data-test="alert-table">
                    <thead
                        class="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase dark:border-slate-700 dark:bg-slate-950"
                    >
                        <tr>
                            <th class="px-4 py-2.5 text-left">
                                {{ __('Section') }}
                            </th>
                            <th class="px-3 py-2.5 text-left">
                                {{ __('Date') }}
                            </th>
                            <th class="px-3 py-2.5 text-left">
                                {{ __('Zone') }}
                            </th>
                            <th class="px-3 py-2.5 text-right">
                                {{ __('Index') }}
                            </th>
                            <th class="px-3 py-2.5 text-left">
                                {{ __('Streak') }}
                            </th>
                            <th class="px-3 py-2.5 text-left">
                                {{ __('Follow-up') }}
                            </th>
                            <th class="px-4 py-2.5 text-right">
                                <span class="sr-only">{{ __('Action') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <tr
                            v-for="alert in alerts.data"
                            :key="rowKey(alert)"
                            class="cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40"
                            data-test="alert-row"
                            :data-section="alert.section_name"
                            :data-zone="alert.zone"
                            :data-follow-up="alert.follow_up_status"
                            @click="openAlert(alert, $event)"
                        >
                            <td class="px-4 py-3">
                                <div
                                    class="font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ alert.section_name }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ alert.department_name ?? '-' }}
                                </div>
                            </td>
                            <td
                                class="px-3 py-3 font-mono text-xs whitespace-nowrap tabular-nums"
                            >
                                {{ formatAlertDate(alert.alert_date, locale) }}
                            </td>
                            <td class="px-3 py-3">
                                <AlertZoneBadge :zone="alert.zone" />
                            </td>
                            <td
                                class="px-3 py-3 text-right font-mono font-semibold tabular-nums"
                            >
                                {{ alert.index_pct.toFixed(1) }}%
                            </td>
                            <td class="px-3 py-3 text-xs whitespace-nowrap">
                                <span class="font-mono tabular-nums">{{
                                    alert.streak_days
                                }}</span>
                                {{ __('consecutive days') }}
                            </td>
                            <td class="px-3 py-3">
                                <FollowUpStatusPill
                                    :status="alert.follow_up_status"
                                />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    as-child
                                    size="sm"
                                    class="h-8 bg-[#cc0000] text-xs text-white hover:bg-[#b30000]"
                                >
                                    <Link
                                        :href="detailUrl(alert)"
                                        data-test="alert-action"
                                    >
                                        {{ __('Follow Up') }}
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="space-y-2 md:hidden" data-test="alert-cards">
                <li
                    v-for="alert in alerts.data"
                    :key="rowKey(alert)"
                    class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    data-test="alert-card"
                    :data-section="alert.section_name"
                    :data-zone="alert.zone"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div
                                class="truncate text-sm font-semibold text-slate-900 dark:text-white"
                            >
                                {{ alert.section_name }}
                            </div>
                            <div class="truncate text-xs text-slate-500">
                                {{ alert.department_name ?? '-' }}
                            </div>
                        </div>
                        <AlertZoneBadge :zone="alert.zone" />
                    </div>
                    <div
                        class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-600 dark:text-slate-300"
                    >
                        <span class="font-mono tabular-nums">{{
                            formatAlertDate(alert.alert_date, locale)
                        }}</span>
                        <span class="font-mono font-semibold tabular-nums"
                            >{{ alert.index_pct.toFixed(1) }}%</span
                        >
                        <span>
                            <span class="font-mono tabular-nums">{{
                                alert.streak_days
                            }}</span>
                            {{ __('consecutive days') }}
                        </span>
                    </div>
                    <div class="mt-3 flex items-center justify-between gap-2">
                        <FollowUpStatusPill :status="alert.follow_up_status" />
                        <Button
                            as-child
                            size="sm"
                            class="h-8 bg-[#cc0000] text-xs text-white hover:bg-[#b30000]"
                        >
                            <Link :href="detailUrl(alert)">
                                {{ __('Follow Up') }}
                            </Link>
                        </Button>
                    </div>
                </li>
            </ul>

            <div
                class="flex flex-wrap items-center justify-between gap-2"
                data-test="alert-pagination"
            >
                <span
                    class="font-mono text-xs text-slate-500 tabular-nums"
                    data-test="alert-pagination-range"
                >
                    {{ alerts.pagination.from }}–{{ alerts.pagination.to }} /
                    {{ alerts.pagination.total }}
                </span>
                <div
                    v-if="alerts.pagination.last_page > 1"
                    class="flex items-center gap-1"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 w-8 p-0"
                        :disabled="alerts.pagination.current_page <= 1"
                        :aria-label="__('Previous page')"
                        data-test="alert-page-prev"
                        @click="
                            emit('page', alerts.pagination.current_page - 1)
                        "
                    >
                        <ChevronLeft class="size-4" />
                    </Button>
                    <span class="px-2 font-mono text-xs tabular-nums">
                        {{ alerts.pagination.current_page }} /
                        {{ alerts.pagination.last_page }}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 w-8 p-0"
                        :disabled="
                            alerts.pagination.current_page >=
                            alerts.pagination.last_page
                        "
                        :aria-label="__('Next page')"
                        data-test="alert-page-next"
                        @click="
                            emit('page', alerts.pagination.current_page + 1)
                        "
                    >
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
            </div>
        </template>
    </div>
</template>
