<script setup lang="ts">
import {
    Calculator,
    Check,
    Gauge,
    Layers,
    Play,
    Sliders,
    Users,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

defineProps<{
    filters?: {
        department_id: string | number | null;
        start_date: string;
        end_date: string;
    };
}>();

const { __ } = useTrans();
</script>

<template>
    <div class="space-y-6" data-test="tab-scenario-content">
        <!-- Dual Panel Simulation Preview (E09-10) -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Left Panel: Production Volume Planning Calculator -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
            >
                <CardHeader>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <Calculator class="size-4 text-[#cc0000]" />
                        <span>{{
                            __('Kalkulator Perencanaan Volume Produksi')
                        }}</span>
                    </CardTitle>
                    <CardDescription>
                        {{
                            __(
                                'Estimasi kebutuhan jam lembur dan penambahan tenaga kerja berdasarkan target unit kendaraan.',
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        class="flex h-56 flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/50 p-4 text-center dark:border-slate-800 dark:bg-slate-950/30"
                    >
                        <Calculator
                            class="mb-2 size-8 text-slate-400 dark:text-slate-600"
                        />
                        <h4
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            {{ __('Input Target Unit & Periode') }}
                        </h4>
                        <p class="mt-1 text-[11px] text-slate-500">
                            {{
                                __(
                                    'Perhitungan faktor tenaga kerja empiris disiapkan pada Story E09-10.',
                                )
                            }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Right Panel: Scenario Builder Cockpit -->
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
            >
                <CardHeader>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <Sliders class="size-4 text-sky-600" />
                        <span>{{ __('Simulator Skenario Beban Kerja') }}</span>
                    </CardTitle>
                    <CardDescription>
                        {{
                            __(
                                'Uji sensitivitas penyesuaian kuota lembur (-50% s/d +50%) terhadap skor risiko keselamatan.',
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        class="flex h-56 flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/50 p-4 text-center dark:border-slate-800 dark:bg-slate-950/30"
                    >
                        <Gauge
                            class="mb-2 size-8 text-slate-400 dark:text-slate-600"
                        />
                        <h4
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            {{ __('Slider Penyesuaian Lembur Aktif') }}
                        </h4>
                        <p class="mt-1 text-[11px] text-slate-500">
                            {{
                                __(
                                    'Visualisasi grafik komparasi baseline vs skenario tersimpan disiapkan pada Story E09-10.',
                                )
                            }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
