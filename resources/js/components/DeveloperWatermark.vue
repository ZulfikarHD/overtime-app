<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';

withDefaults(
    defineProps<{
        /** Visual density for header strip vs hero placement */
        variant?: 'strip' | 'inline';
        /** Light text for dark overlays */
        tone?: 'default' | 'inverse';
    }>(),
    {
        variant: 'strip',
        tone: 'default',
    },
);

const { __ } = useTrans();
</script>

<template>
    <div
        :class="[
            variant === 'strip'
                ? 'flex w-full items-center justify-start border-b px-3 py-1.5 text-left sm:px-4'
                : 'text-left',
            variant === 'strip' && tone === 'default'
                ? 'border-border/60 bg-muted/40 text-muted-foreground'
                : '',
            variant === 'strip' && tone === 'inverse'
                ? 'border-white/15 bg-black/40 text-white/80'
                : '',
            variant === 'inline' && tone === 'default'
                ? 'text-muted-foreground'
                : '',
            variant === 'inline' && tone === 'inverse' ? 'text-white/85' : '',
        ]"
        data-test="developer-watermark"
        role="note"
    >
        <p
            :class="
                variant === 'strip'
                    ? 'text-xs font-medium tracking-wide sm:text-sm'
                    : 'text-sm font-medium tracking-wide sm:text-base'
            "
        >
            {{ __('Developer system by: KARLINA IBRAHIM (PCD Department)') }}
        </p>
    </div>
</template>
