<script setup lang="ts">
import { ClipboardList } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import FollowUpStatusPill from '@/components/early-warning/FollowUpStatusPill.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { formatAlertDate } from '@/lib/earlyWarning';
import type { AlertFollowUpItem } from '@/lib/earlyWarning';
import { formatRupiah } from '@/lib/formatters';

const props = defineProps<{
    /** Follow-ups of this alert, newest first. */
    followUps: AlertFollowUpItem[];
}>();

const { __, locale } = useTrans();

const selectedId = ref<number | null>(props.followUps[0]?.id ?? null);

watch(
    () => props.followUps,
    (items) => {
        if (!items.some((item) => item.id === selectedId.value)) {
            selectedId.value = items[0]?.id ?? null;
        }
    },
);

const selected = computed(
    () => props.followUps.find((item) => item.id === selectedId.value) ?? null,
);

/** Fields of the selected follow-up, in 5W2H order. Empty values show a dash. */
const fields = computed(() => {
    const item = selected.value;

    if (!item) {
        return [];
    }

    return [
        {
            key: 'what',
            label: __('What — apa masalahnya'),
            value: item.what_text,
        },
        { key: 'why', label: __('Why — kenapa terjadi'), value: item.why_text },
        {
            key: 'when',
            label: __('When'),
            value: item.when_date
                ? formatAlertDate(item.when_date, locale.value)
                : null,
        },
        { key: 'where', label: __('Where'), value: item.where_location },
        {
            key: 'who',
            label: __('Who — PIC terkait (departemen)'),
            value: item.who_department_name,
        },
        {
            key: 'how',
            label: __('How — rencana tindak lanjut'),
            value: item.how_text,
        },
        {
            key: 'how-much-cost',
            label: __('How much — biaya (Rp)'),
            value: formatRupiah(item.how_much_cost_idr, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            }),
        },
        {
            key: 'how-much-hours',
            label: __('How much — jam lembur'),
            value: `${item.how_much_hours} ${__('jam')}`,
        },
    ];
});

function chipLabel(item: AlertFollowUpItem): string {
    const state = item.status === 'sent' ? __('Terkirim') : __('Draft');

    return `${state} · ${item.created_at?.slice(0, 5) ?? ''}`.trim();
}
</script>

<template>
    <Card class="shadow-2xs" data-test="alert-follow-up-summary">
        <CardHeader class="pb-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <CardTitle
                    class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100"
                >
                    <ClipboardList class="size-4 text-[#cc0000]" />
                    <span>{{ __('Tindak Lanjut Alert (5W2H)') }}</span>
                </CardTitle>
                <FollowUpStatusPill :status="selected?.status ?? 'none'" />
            </div>
        </CardHeader>

        <CardContent class="space-y-4 pt-0">
            <p
                v-if="selected?.status === 'sent'"
                class="text-xs text-slate-600 dark:text-slate-300"
                data-test="alert-follow-up-sent-info"
            >
                {{
                    __(
                        'Terkirim via email pada :time oleh :name (read-only).',
                        {
                            time: selected.sent_at ?? '-',
                            name: selected.sent_by_name ?? '-',
                        },
                    )
                }}
            </p>

            <p
                v-if="followUps.length === 0"
                class="text-sm text-slate-500"
                data-test="alert-follow-up-empty"
            >
                {{ __('No follow-up has been recorded for this alert yet.') }}
            </p>

            <div
                v-if="followUps.length > 1"
                class="flex flex-wrap items-center gap-2"
                data-test="alert-follow-up-history"
            >
                <span class="text-xs text-slate-500">
                    {{ __('Follow-up history') }}
                </span>
                <button
                    v-for="item in followUps"
                    :key="item.id"
                    type="button"
                    class="rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors"
                    :class="
                        item.id === selectedId
                            ? 'border-[#cc0000] bg-red-50 text-[#cc0000] dark:bg-red-950/40'
                            : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'
                    "
                    :aria-pressed="item.id === selectedId"
                    :data-test="`alert-follow-up-chip-${item.id}`"
                    @click="selectedId = item.id"
                >
                    {{ chipLabel(item) }}
                </button>
            </div>

            <dl
                v-if="selected"
                class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm md:grid-cols-2"
                data-test="alert-follow-up-fields"
            >
                <div
                    v-for="field in fields"
                    :key="field.key"
                    class="min-w-0"
                    :data-test="`alert-follow-up-${field.key}`"
                >
                    <dt class="text-xs font-semibold text-slate-500">
                        {{ field.label }}
                    </dt>
                    <dd
                        class="mt-0.5 break-words text-slate-900 dark:text-white"
                    >
                        {{ field.value || '-' }}
                    </dd>
                </div>
            </dl>
        </CardContent>
    </Card>
</template>
