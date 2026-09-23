<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Activity, Clock, Factory } from '@lucide/vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useShiftInfo } from '@/composables/useShiftInfo';
import { useTrans } from '@/composables/useTrans';
import { home } from '@/routes';

const { __ } = useTrans();
const { timeString } = useShiftInfo();

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col bg-white text-slate-900 lg:grid lg:grid-cols-12 dark:bg-[#070b12] dark:text-slate-100"
    >
        <!-- LEFT COLUMN: Automotive Engineering Hero Panel (Desktop) -->
        <div
            class="relative hidden flex-col justify-between overflow-hidden border-r border-slate-200 bg-white p-8 lg:col-span-6 lg:flex xl:col-span-7 xl:p-12 dark:border-slate-800 dark:bg-[#070b12]"
        >
            <!-- Background Decorative Grid & Glow -->
            <div
                class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,#e2e8f080_1px,transparent_1px),linear-gradient(to_bottom,#e2e8f080_1px,transparent_1px)] bg-[size:32px_32px] dark:bg-[linear-gradient(to_right,#1e293b22_1px,transparent_1px),linear-gradient(to_bottom,#1e293b22_1px,transparent_1px)]"
            />
            <div
                class="pointer-events-none absolute -top-24 -left-24 size-96 rounded-full bg-[#cc0000]/8 blur-3xl dark:bg-[#cc0000]/15"
            />
            <div
                class="pointer-events-none absolute -right-24 -bottom-24 size-96 rounded-full bg-sky-500/8 blur-3xl dark:bg-sky-600/10"
            />

            <!-- Top Brand Bar -->
            <div class="relative z-10 flex items-center justify-between">
                <Link :href="home()" class="flex items-center gap-3">
                    <div
                        class="flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 shadow-xs dark:border-slate-700/60 dark:bg-slate-900"
                    >
                        <img
                            src="/isuzu.png"
                            alt="ISUZU"
                            class="h-7 w-auto object-contain"
                        />
                    </div>
                    <div>
                        <div
                            class="font-bold tracking-tight text-slate-900 dark:text-white"
                        >
                            {{ __('PT ISUZU ASTRA MOTOR INDONESIA') }}
                        </div>
                        <div
                            class="text-[11px] font-medium tracking-wide text-slate-500 dark:text-slate-400"
                        >
                            {{ __('Karawang Assembly Plant') }}
                        </div>
                    </div>
                </Link>

                <div class="flex items-center gap-3">
                    <LanguageSwitcher
                        size="sm"
                        :show-icon="true"
                        test-id-prefix="lang-switch-desktop"
                    />
                    <div
                        class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold text-[#cc0000] dark:border-red-500/30 dark:bg-red-950/60 dark:text-red-300"
                    >
                        <span class="size-1.5 rounded-full bg-[#cc0000]" />
                        <span>{{ __('Overtime Portal') }}</span>
                    </div>
                </div>
            </div>

            <!-- Middle Technical Presentation -->
            <div class="relative z-10 my-auto max-w-xl space-y-4 py-8">
                <div
                    class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:border-transparent dark:bg-white/10 dark:text-slate-300"
                >
                    <Factory
                        class="size-3.5 text-[#cc0000] dark:text-red-400"
                    />
                    <span>{{
                        __('Manufacturing Execution & Overtime Control')
                    }}</span>
                </div>
                <h1
                    class="text-3xl font-extrabold tracking-tight text-slate-900 xl:text-4xl dark:text-white"
                    data-test="login-hero-title"
                >
                    {{ __('Smart Monitoring Overtime versi 2.0') }}
                </h1>
                <p
                    class="text-sm leading-relaxed text-slate-600 xl:text-base dark:text-slate-400"
                    data-test="login-hero-subtitle"
                >
                    {{
                        __(
                            'Management Index Overtime berbasis Machine Learning',
                        )
                    }}
                </p>
            </div>

            <!-- Bottom Telemetry Footer Bar -->
            <div
                class="relative z-10 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-6 text-xs text-slate-500 dark:border-slate-800/80 dark:text-slate-400"
            >
                <div
                    class="inline-flex items-center gap-1.5 font-mono text-slate-700 dark:text-slate-300"
                >
                    <Clock
                        class="size-3.5 text-slate-500 dark:text-slate-400"
                    />
                    <span>{{ timeString }} WIB</span>
                </div>

                <div
                    class="flex items-center gap-2 text-slate-500 dark:text-slate-500"
                >
                    <Activity
                        class="size-3.5 text-slate-500 dark:text-slate-400"
                    />
                    <span>{{
                        __('IATF 16949 · ISO 9001 · 5S Standards')
                    }}</span>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Authentication Form Area -->
        <div
            class="flex flex-1 flex-col justify-between bg-white p-6 sm:p-10 lg:col-span-6 lg:p-12 xl:col-span-5 dark:bg-[#070b12]"
        >
            <!-- Mobile Brand Bar (< lg) -->
            <div class="mb-6 flex items-center justify-between lg:hidden">
                <Link :href="home()" class="flex items-center gap-2.5">
                    <div
                        class="flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white p-1.5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
                    >
                        <img
                            src="/isuzu.png"
                            alt="ISUZU"
                            class="h-6 w-auto object-contain"
                        />
                    </div>
                    <div>
                        <div
                            class="text-xs font-bold text-slate-900 dark:text-white"
                        >
                            {{ __('SMARTIME 2.0') }}
                        </div>
                        <div class="text-[10px] text-slate-500">
                            {{ __('PT. Isuzu Astra Motor Indonesia') }}
                        </div>
                    </div>
                </Link>

                <LanguageSwitcher
                    size="sm"
                    test-id-prefix="lang-switch-mobile"
                />
            </div>

            <!-- Form Container -->
            <div class="mx-auto my-auto w-full max-w-md py-6">
                <!-- Header Title & Subtitle -->
                <div class="mb-6 space-y-1.5">
                    <div class="hidden items-center justify-between lg:flex">
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full bg-[#cc0000]" />
                            <span
                                class="text-xs font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                            >
                                {{ __('Manufacturing Portal Access') }}
                            </span>
                        </div>
                    </div>
                    <h2
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white"
                    >
                        {{ title ? __(title) : __('Log in to System') }}
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{
                            description
                                ? __(description)
                                : __('Enter your credentials below to log in')
                        }}
                    </p>
                </div>

                <!-- Auth Page Slot (Login, ForgotPassword, etc.) -->
                <slot />
            </div>

            <!-- Right Column Footer -->
            <div
                class="pt-6 text-center text-xs text-slate-400 dark:text-slate-500"
            >
                <p>
                    {{
                        __('PT Isuzu Astra Motor Indonesia © :year.', {
                            year: 2026,
                        })
                    }}
                    {{ __('All rights reserved.') }}
                </p>
            </div>
        </div>
    </div>
</template>
