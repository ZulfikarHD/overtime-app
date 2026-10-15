<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Sparkles } from '@lucide/vue';
import DeveloperWatermark from '@/components/DeveloperWatermark.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import { login } from '@/routes';

const { __ } = useTrans();
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col overflow-hidden bg-slate-50 text-slate-900 dark:bg-[#070b12] dark:text-slate-100"
        data-test="welcome-page"
    >
        <Head :title="__('Welcome')" />

        <DeveloperWatermark variant="strip" />

        <!-- Ambient brand glow -->
        <div
            class="pointer-events-none absolute top-1/4 left-1/4 size-80 rounded-full bg-red-500/15 blur-3xl dark:bg-red-600/15"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute right-1/4 bottom-1/4 size-96 rounded-full bg-slate-400/15 blur-3xl dark:bg-slate-500/10"
            aria-hidden="true"
        />

        <!-- Top brand bar -->
        <header
            class="relative z-10 flex items-center justify-between px-6 pt-6 sm:px-10"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 shadow-xs"
                    data-test="isuzu-logo-plate"
                >
                    <img
                        src="/isuzu.jpg"
                        alt="ISUZU"
                        class="h-6 w-auto bg-white object-contain"
                    />
                </div>
                <div class="hidden sm:block">
                    <div
                        class="text-sm font-bold tracking-tight text-slate-900 dark:text-white"
                    >
                        {{ __('PT ISUZU ASTRA MOTOR INDONESIA') }}
                    </div>
                    <div
                        class="text-[11px] font-medium tracking-wide text-slate-500 dark:text-slate-400"
                    >
                        {{ __('Karawang Assembly Plant') }}
                    </div>
                </div>
            </div>

            <LanguageSwitcher
                size="sm"
                :show-icon="true"
                test-id-prefix="lang-switch-welcome"
            />
        </header>

        <!-- Welcome card -->
        <main
            class="relative z-10 flex flex-1 items-center justify-center px-4 py-10 sm:px-6"
        >
            <div
                class="border-border bg-card w-full max-w-xl rounded-2xl border p-8 text-center shadow-lg sm:p-12"
            >
                <div
                    class="mb-6 inline-flex items-center gap-2 rounded-full border border-red-200 bg-red-50 px-4 py-1.5 text-xs font-semibold tracking-widest text-[#cc0000] uppercase dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                >
                    <Sparkles class="size-3.5" />
                    {{ __('Overtime Portal') }}
                </div>

                <!-- Photo with brand ring -->
                <div
                    class="mx-auto mb-6 size-32 rounded-full bg-gradient-to-tr from-[#cc0000] to-amber-500 p-1 sm:size-36"
                >
                    <img
                        src="/karlina.jpeg"
                        alt="Karlina Ibrahim"
                        class="size-full rounded-full bg-slate-900 object-cover"
                        data-test="welcome-photo"
                    />
                </div>

                <h1
                    class="mb-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white"
                    data-test="welcome-title"
                >
                    {{ __('Hello, Welcome!') }}
                </h1>
                <p
                    class="mb-2 text-sm font-medium text-slate-600 sm:text-base dark:text-slate-300"
                >
                    Karlina Ibrahim
                    <span class="text-[#cc0000] dark:text-red-400"
                        >&bull; {{ __('PCD Department') }}</span
                    >
                </p>
                <p
                    class="mb-6 text-sm font-semibold text-slate-800 dark:text-slate-200"
                >
                    {{ __('SMARTIME (Smart Overtime) 2.0') }}
                </p>

                <blockquote
                    class="mb-8 rounded-xl border border-slate-200/70 bg-slate-50 p-4 text-xs leading-relaxed text-slate-600 italic sm:p-5 sm:text-sm dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400"
                >
                    “{{
                        __(
                            'Integrity, discipline, and strong dedication are the keys to our daily operational success and work safety.',
                        )
                    }}”
                </blockquote>

                <Button
                    as-child
                    class="h-12 w-full cursor-pointer bg-[#cc0000] px-8 text-base font-semibold text-white shadow-xs transition-all hover:bg-[#b30000] active:scale-[0.99] sm:w-auto"
                >
                    <Link :href="login()" data-test="welcome-enter-button">
                        {{ __('Enter the System') }}
                        <ArrowRight class="ml-2 size-5" />
                    </Link>
                </Button>

                <p class="mt-8 text-xs text-slate-400 dark:text-slate-500">
                    {{
                        __('PT Isuzu Astra Motor Indonesia © :year.', {
                            year: 2026,
                        })
                    }}
                    {{ __('All rights reserved.') }}
                </p>
            </div>
        </main>
    </div>
</template>
