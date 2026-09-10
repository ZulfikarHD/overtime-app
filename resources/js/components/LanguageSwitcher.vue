<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Globe } from '@lucide/vue';
import { ref } from 'vue';
import { useTrans } from '@/composables/useTrans';
import { update as updateLocale } from '@/routes/locale';

withDefaults(
    defineProps<{
        size?: 'sm' | 'md';
        showIcon?: boolean;
        testIdPrefix?: string;
    }>(),
    {
        size: 'sm',
        showIcon: false,
        testIdPrefix: 'lang-switch',
    },
);

const { locale, __ } = useTrans();
const isSwitching = ref(false);

const switchLocale = (newLocale: 'id' | 'en') => {
    if (newLocale === locale.value || isSwitching.value) {
        return;
    }

    isSwitching.value = true;
    router.post(
        updateLocale.url(),
        { locale: newLocale },
        {
            preserveScroll: true,
            onFinish: () => {
                isSwitching.value = false;
            },
        },
    );
};
</script>

<template>
    <div
        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100/90 p-0.5 text-xs font-semibold shadow-2xs dark:border-slate-800 dark:bg-slate-900/90"
        :title="__('Switch language')"
    >
        <div
            v-if="showIcon"
            class="flex items-center pl-1.5 text-slate-400 dark:text-slate-500"
        >
            <Globe class="size-3.5" />
        </div>
        <button
            type="button"
            :class="[
                'cursor-pointer rounded-md transition-all',
                size === 'sm'
                    ? 'px-2 py-0.5 text-[11px]'
                    : 'px-2.5 py-1 text-xs',
                locale === 'id'
                    ? 'bg-white font-bold text-slate-900 shadow-2xs dark:bg-slate-800 dark:text-white'
                    : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
            ]"
            :disabled="isSwitching"
            @click="switchLocale('id')"
            :data-test="`${testIdPrefix}-id`"
            :aria-label="__('Indonesian')"
        >
            ID
        </button>
        <button
            type="button"
            :class="[
                'cursor-pointer rounded-md transition-all',
                size === 'sm'
                    ? 'px-2 py-0.5 text-[11px]'
                    : 'px-2.5 py-1 text-xs',
                locale === 'en'
                    ? 'bg-white font-bold text-slate-900 shadow-2xs dark:bg-slate-800 dark:text-white'
                    : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
            ]"
            :disabled="isSwitching"
            @click="switchLocale('en')"
            :data-test="`${testIdPrefix}-en`"
            :aria-label="__('English')"
        >
            EN
        </button>
    </div>
</template>
