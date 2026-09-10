<script setup lang="ts">
import {
    Activity,
    CheckCircle2,
    GitCommit,
    Layers,
    ScatterChart,
    ShieldAlert,
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
    <div class="space-y-6" data-test="tab-correlation-content">
        <!-- Correlation Summary Cards Row (E09-09) -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Zona Lembur Wajar (Sweet Spot)') }}
                    </CardTitle>
                    <CheckCircle2 class="size-4 text-emerald-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-emerald-600 tabular-nums dark:text-emerald-400"
                    >
                        12.0 – 18.0
                        <span class="text-sm font-normal">jam/minggu</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{
                            __(
                                'Batas efisiensi optimal tanpa penurunan kualitas',
                            )
                        }}
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
                        {{ __('Titik Puncak Produktivitas') }}
                    </CardTitle>
                    <Activity class="size-4 text-sky-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white"
                    >
                        15.2 <span class="text-sm font-normal">jam/minggu</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{
                            __('Rata-rata output unit per jam kerja tertinggi')
                        }}
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
                        {{ __('Ambang Batas Kelelahan') }}
                    </CardTitle>
                    <ShieldAlert class="size-4 text-amber-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-amber-600 tabular-nums dark:text-amber-400"
                    >
                        &gt; 20.0
                        <span class="text-sm font-normal">jam/minggu</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{
                            __('Korelasi peningkatan defect dan risiko insiden')
                        }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Correlation Canvas (E09-09 Shell) -->
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader>
                <CardTitle
                    class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                >
                    <ScatterChart class="size-4 text-[#cc0000]" />
                    <span>{{
                        __('Korelasi Bivariat: Jam Lembur vs Output Produksi')
                    }}</span>
                </CardTitle>
                <CardDescription>
                    {{
                        __(
                            'Sebaran data empiris jam lembur terhadap volume unit kendaraan dan metrik kualitas lini perakitan.',
                        )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div
                    class="flex h-64 flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center dark:border-slate-800 dark:bg-slate-950/30"
                >
                    <GitCommit
                        class="mb-2 size-10 text-slate-400 dark:text-slate-600"
                    />
                    <h3
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ __('Modul Korelasi & Pola Aktif') }}
                    </h3>
                    <p
                        class="mt-1 max-w-md text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{
                            __(
                                'Scatter plot regresi linear, matriks korelasi Pearson, dan zona Sweet Spot efisiensi disiapkan pada Story E09-09.',
                            )
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
