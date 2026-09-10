<script setup lang="ts">
import {
    ArrowUpDown,
    Award,
    BarChart3,
    Calendar,
    CheckCircle2,
    TrendingDown,
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
    <div class="space-y-6" data-test="tab-comparison-content">
        <!-- Comparison KPI Cards Row (E09-12) -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Perubahan Jam Lembur') }}
                    </CardTitle>
                    <TrendingDown class="size-4 text-emerald-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-emerald-600 tabular-nums dark:text-emerald-400"
                    >
                        -8.4%
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Penurunan beban lembur vs periode pembanding') }}
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
                        {{ __('Perubahan Biaya (Rp)') }}
                    </CardTitle>
                    <TrendingDown class="size-4 text-emerald-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-emerald-600 tabular-nums dark:text-emerald-400"
                    >
                        -5.2%
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Efisiensi anggaran Rp 6,8 Jt') }}
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
                        {{ __('Perubahan Efisiensi') }}
                    </CardTitle>
                    <TrendingUp class="size-4 text-sky-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-sky-600 tabular-nums dark:text-sky-400"
                    >
                        +3.8%
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Kenaikan unit kendaraan per jam lembur') }}
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
                        {{ __('Headcount Lembur') }}
                    </CardTitle>
                    <ArrowUpDown class="size-4 text-slate-500" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white"
                    >
                        +2 <span class="text-sm font-normal">orang</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Penyesuaian rotasi shift teknisi') }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Period Benchmarking Canvas (E09-12 Shell) -->
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader>
                <CardTitle
                    class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                >
                    <BarChart3 class="size-4 text-[#cc0000]" />
                    <span>{{
                        __('Perbandingan Periode & Benchmarking Departemen')
                    }}</span>
                </CardTitle>
                <CardDescription>
                    {{
                        __(
                            'Evaluasi performa historis (YoY, MoM, QoQ) dan kartu best practice efisiensi antar seksi.',
                        )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div
                    class="flex h-64 flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center dark:border-slate-800 dark:bg-slate-950/30"
                >
                    <Award
                        class="mb-2 size-10 text-slate-400 dark:text-slate-600"
                    />
                    <h3
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ __('Modul Perbandingan Periode Aktif') }}
                    </h3>
                    <p
                        class="mt-1 max-w-md text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{
                            __(
                                'Pemilih periode ganda, grafik perbandingan batang bertumpuk, dan analisis praktik terbaik disiapkan pada Story E09-12.',
                            )
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
