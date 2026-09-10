<script setup lang="ts">
import {
    Activity,
    ArrowUpRight,
    BrainCircuit,
    Calendar,
    Sparkles,
    TrendingUp,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
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
    <div class="space-y-6" data-test="tab-predictive-content">
        <!-- Predictive KPI Row (E09-07) -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Prediksi Bulan Depan') }}
                    </CardTitle>
                    <Sparkles class="size-4 text-[#cc0000]" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white"
                    >
                        1.250 ± 45 <span class="text-sm font-normal">jam</span>
                    </div>
                    <p
                        class="mt-1 flex items-center gap-1 text-xs text-slate-500"
                    >
                        <Badge
                            variant="secondary"
                            class="bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"
                        >
                            {{ __('Moving Average') }}
                        </Badge>
                        <span>{{ __('Model Baseline') }}</span>
                    </p>
                </CardContent>
            </Card>

            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Tingkat Akurasi (MAPE)') }}
                    </CardTitle>
                    <BrainCircuit class="size-4 text-emerald-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-emerald-600 tabular-nums dark:text-emerald-400"
                    >
                        91.8%
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Rata-rata deviasi riwayat 12 bulan') }}
                    </p>
                </CardContent>
            </Card>

            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Pola Musiman') }}
                    </CardTitle>
                    <Calendar class="size-4 text-amber-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="text-xl font-bold tracking-tight text-slate-900 dark:text-white"
                    >
                        {{ __('Puncak Siklus (Q4)') }}
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Kenaikan rata-rata +18% pada akhir tahun') }}
                    </p>
                </CardContent>
            </Card>

            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Arah Tren') }}
                    </CardTitle>
                    <ArrowUpRight class="size-4 text-sky-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="flex items-center gap-1.5 font-mono text-2xl font-bold tracking-tight text-slate-900 dark:text-white"
                    >
                        <TrendingUp class="size-5 text-sky-600" />
                        <span>↑ {{ __('Meningkat') }}</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Laju pertumbuhan +4.2% per kuartal') }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Predictive Placeholder Canvas (E09-07 Shell) -->
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader>
                <CardTitle
                    class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                >
                    <Activity class="size-4 text-[#cc0000]" />
                    <span>{{
                        __('Prediksi Kebutuhan Lembur Lintas Seksi')
                    }}</span>
                </CardTitle>
                <CardDescription>
                    {{
                        __(
                            'Proyeksi jam lembur bulan depan berdasarkan histori produksi dan machine learning forecast.',
                        )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div
                    class="flex h-64 flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center dark:border-slate-800 dark:bg-slate-950/30"
                >
                    <BrainCircuit
                        class="mb-2 size-10 text-slate-400 dark:text-slate-600"
                    />
                    <h3
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ __('Modul Prediksi Lembur Aktif') }}
                    </h3>
                    <p
                        class="mt-1 max-w-md text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{
                            __(
                                'Visualisasi proyeksi 6 bulan, interval kepercayaan, dan dekomposisi musiman disiapkan pada Story E09-07.',
                            )
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
