<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';

withDefaults(
    defineProps<{
        /** Visual density for header strip vs hero placement */
        variant?: 'strip' | 'inline';
    }>(),
    {
        variant: 'strip',
    },
);

const { __ } = useTrans();
</script>

<template>
    <div
        :class="
            variant === 'strip'
                ? 'border-border/60 bg-muted/40 text-muted-foreground flex w-full items-center justify-center border-b px-3 py-1 text-center'
                : 'text-muted-foreground text-center'
        "
        data-test="developer-watermark"
        role="note"
    >
        <p
            :class="
                variant === 'strip'
                    ? 'text-[10px] font-medium tracking-wide sm:text-[11px]'
                    : 'text-xs font-medium tracking-wide sm:text-sm'
            "
        >
            {{ __('Developer system by: KARLINA IBRAHIM (PCD Department)') }}
        </p>
    </div>
</template>
