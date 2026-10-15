<script setup lang="ts">
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';
import type { FollowUpStatus } from '@/lib/earlyWarning';

const props = defineProps<{
    status: FollowUpStatus;
}>();

const { __ } = useTrans();

const config = computed(() => {
    switch (props.status) {
        case 'sent':
            return {
                label: __('Terkirim'),
                classes:
                    'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
            };
        case 'draft':
            return {
                label: __('Draft'),
                classes:
                    'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300',
            };
        default:
            return {
                label: __('Not followed up yet'),
                classes:
                    'border-slate-200 bg-slate-100 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
            };
    }
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap"
        :class="config.classes"
        :data-status="status"
        data-test="follow-up-status-pill"
    >
        {{ config.label }}
    </span>
</template>
