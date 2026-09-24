<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Activity, Clock } from '@lucide/vue';
import DeveloperWatermark from '@/components/DeveloperWatermark.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useShiftInfo } from '@/composables/useShiftInfo';
import { useTrans } from '@/composables/useTrans';
import { home } from '@/routes';

const { __ } = useTrans();
const { timeString, currentShift } = useShiftInfo();

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col bg-white text-slate-900 dark:bg-[#070b12] dark:text-slate-100"
    >
        <!-- Client-requested developer watermark (top) -->
        <DeveloperWatermark variant="strip" />

        <div class="flex min-h-0 flex-1 flex-col lg:grid lg:grid-cols-12">
            <!--
                LEFT brand panel (user intent): art + welcome copy.
                Text protection = Material/NN/g "floor fade" scrim on the
                bottom only — not a full black wash, not a frosted card.
                Refs: material.io imagery scrims, nngroup.com/text-over-images
            -->
            <div
                class="relative hidden min-h-0 overflow-hidden border-r border-slate-200 bg-white lg:col-span-6 lg:flex lg:flex-col xl:col-span-7 dark:border-slate-800"
                data-test="login-brand-panel"
            >
                <img
                    src="/welcome-img.jpg"
                    alt=""
                    class="pointer-events-none absolute inset-0 size-full object-contain object-center"
                    data-test="login-welcome-image"
                    aria-hidden="true"
                />

                <!-- Top: light fade so brand chrome stays dark-on-white -->
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 z-10 h-28 bg-gradient-to-b from-white via-white/80 to-transparent"
                    aria-hidden="true"
                />

                <!--
                    Bottom floor-fade scrim (targeted text protection).
                    Taller + stronger mid stops so the eyebrow ("Selamat datang")
                    sits inside the protected zone, not on the white truck roof.
                -->
                <div
                    class="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-[68%] bg-[linear-gradient(to_top,rgba(0,0,0,0.82)_0%,rgba(0,0,0,0.62)_32%,rgba(0,0,0,0.38)_58%,rgba(0,0,0,0.12)_82%,rgba(0,0,0,0)_100%)]"
                    aria-hidden="true"
                />

                <!-- Top Brand Bar -->
                <div
                    class="relative z-20 flex items-center justify-between px-8 pt-8 xl:px-12 xl:pt-10"
                >
                    <Link :href="home()" class="flex items-center gap-3">
                        <div
                            class="flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 shadow-xs"
                            data-test="isuzu-logo-plate"
                        >
                            <img
                                src="/isuzu.jpg"
                                alt="ISUZU"
                                class="h-7 w-auto bg-white object-contain"
                            />
                        </div>
                        <div>
                            <div
                                class="font-bold tracking-tight text-slate-900"
                            >
                                {{ __('PT ISUZU ASTRA MOTOR INDONESIA') }}
                            </div>
                            <div
                                class="text-[11px] font-medium tracking-wide text-slate-600"
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
                            class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold text-[#cc0000] shadow-xs"
                        >
                            <span class="size-1.5 rounded-full bg-[#cc0000]" />
                            <span>{{ __('Overtime Portal') }}</span>
                        </div>
                    </div>
                </div>

                <div class="relative z-20 min-h-0 flex-1" aria-hidden="true" />

                <!-- Welcome copy on left (over floor-fade, white type) -->
                <div
                    class="relative z-20 space-y-3 px-8 pb-6 xl:px-12 xl:pb-8"
                    data-test="login-welcome-block"
                >
                    <!--
                        Eyebrow uses a tiny strip highlight (Smashing Mag)
                        so white type stays readable over the truck roof.
                    -->
                    <p
                        class="inline-flex rounded-md bg-black/45 px-2.5 py-1 text-base font-semibold tracking-wide text-white uppercase [text-shadow:0_1px_2px_rgba(0,0,0,0.55)]"
                        data-test="login-hero-eyebrow"
                    >
                        {{ __('Welcome to') }}
                    </p>
                    <h1
                        class="max-w-xl text-4xl font-extrabold tracking-tight text-white [text-shadow:0_1px_3px_rgba(0,0,0,0.5)] xl:text-5xl"
                        data-test="login-hero-title"
                    >
                        {{ __('SMARTIME (Smart Overtime) 2.0') }}
                    </h1>
                    <p
                        class="max-w-xl text-base leading-relaxed text-white/90 [text-shadow:0_1px_2px_rgba(0,0,0,0.45)] xl:text-lg"
                        data-test="login-hero-subtitle"
                    >
                        {{
                            __(
                                'Machine Learning-Based Decision Support System (M-DSS)',
                            )
                        }}
                    </p>
                    <DeveloperWatermark variant="inline" tone="inverse" />

                    <div
                        class="flex flex-wrap items-center justify-between gap-4 border-t border-white/25 pt-4 text-xs text-white/80"
                    >
                        <div
                            class="flex flex-col gap-0.5"
                            data-test="login-live-clock"
                        >
                            <div
                                class="inline-flex items-center gap-1.5 font-mono text-xs font-semibold text-white tabular-nums"
                            >
                                <Clock class="size-3.5 text-white/80" />
                                <span>{{ timeString }} WIB</span>
                            </div>
                            <span
                                class="pl-5 text-[10px] font-medium tracking-wide text-white/70"
                                data-test="login-active-shift"
                            >
                                {{ currentShift.name }} ·
                                {{ currentShift.hours }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2 text-white/80">
                            <Activity class="size-3.5 text-white/80" />
                            <span>{{
                                __('IATF 16949 · ISO 9001 · 5S Standards')
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: auth form only -->
            <div
                class="flex flex-1 flex-col justify-between bg-white p-6 sm:p-10 lg:col-span-6 lg:p-12 xl:col-span-5 dark:bg-[#070b12]"
            >
                <!-- Mobile Brand Bar (< lg) -->
                <div class="mb-6 flex items-center justify-between lg:hidden">
                    <Link :href="home()" class="flex items-center gap-2.5">
                        <div
                            class="flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white p-1.5 shadow-xs dark:border-slate-300"
                            data-test="isuzu-logo-plate-mobile"
                        >
                            <img
                                src="/isuzu.jpg"
                                alt="ISUZU"
                                class="h-6 w-auto bg-white object-contain"
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

                <!-- Mobile fallback welcome (left panel is desktop-only) -->
                <div
                    class="mb-6 space-y-2 lg:hidden"
                    data-test="login-welcome-block-mobile"
                >
                    <p
                        class="text-sm font-medium tracking-wide text-slate-500 uppercase"
                    >
                        {{ __('Welcome to') }}
                    </p>
                    <h1
                        class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white"
                    >
                        {{ __('SMARTIME (Smart Overtime) 2.0') }}
                    </h1>
                    <p
                        class="text-sm leading-relaxed text-slate-600 dark:text-slate-400"
                    >
                        {{
                            __(
                                'Machine Learning-Based Decision Support System (M-DSS)',
                            )
                        }}
                    </p>
                    <DeveloperWatermark variant="inline" />
                </div>

                <div class="mx-auto my-auto w-full max-w-md py-6">
                    <div class="mb-6 space-y-1.5">
                        <div class="hidden items-center gap-2 lg:flex">
                            <span class="size-2 rounded-full bg-[#cc0000]" />
                            <span
                                class="text-xs font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                            >
                                {{ __('Manufacturing Portal Access') }}
                            </span>
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
                                    : __(
                                          'Enter your credentials below to log in',
                                      )
                            }}
                        </p>
                    </div>

                    <slot />
                </div>

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
    </div>
</template>
