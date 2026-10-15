<script setup lang="ts">
import { RotateCcw } from '@lucide/vue';
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    /** Form field this tag belongs to (used for test ids). */
    field: string;
    /** `generated` = still the system text, `edited` = the user changed it, `null` = nothing to compare. */
    state: 'generated' | 'edited' | null;
    /** Hide the reset button (sent or non-alert follow-ups). */
    readOnly: boolean;
}>();

const emit = defineEmits<{
    reset: [];
}>();

const { __ } = useTrans();

const canReset = computed(() => props.state === 'edited' && !props.readOnly);
</script>

<template>
    <span v-if="state" class="inline-flex items-center gap-1.5">
        <span
            class="rounded-full border px-2 py-px text-[10px] font-semibold"
            :class="
                state === 'generated'
                    ? 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300'
                    : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300'
            "
            :data-state="state"
            :data-test="`followup-tag-${field}`"
        >
            {{ state === 'generated' ? __('Dibuat sistem') : __('Diubah') }}
        </span>
        <button
            v-if="canReset"
            type="button"
            class="text-muted-foreground hover:text-foreground inline-flex cursor-pointer items-center gap-1 text-[10px] font-medium underline-offset-2 hover:underline"
            :data-test="`followup-reset-${field}`"
            @click="emit('reset')"
        >
            <RotateCcw class="size-3" />
            {{ __('Reset ke saran sistem') }}
        </button>
    </span>
</template>
