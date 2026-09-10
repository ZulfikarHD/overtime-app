<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';

const { __ } = useTrans();
</script>

<template>
    <div class="flex items-center gap-3 overflow-hidden">
        <div
            class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white p-1 shadow-xs dark:border-slate-800 dark:bg-slate-900"
        >
            <img
                src="/isuzu.png"
                alt="ISUZU"
                class="h-6 w-auto object-contain"
            />
        </div>
        <div
            class="grid flex-1 text-left text-xs leading-tight group-data-[collapsible=icon]:hidden"
        >
            <span
                class="truncate font-bold tracking-tight text-slate-900 dark:text-white"
            >
                ISUZU OT-CapEx
            </span>
            <span
                class="truncate font-mono text-[10px] font-medium text-slate-500 dark:text-slate-400"
            >
                {{ __('Karawang Assembly') }}
            </span>
        </div>
    </div>
</template>
