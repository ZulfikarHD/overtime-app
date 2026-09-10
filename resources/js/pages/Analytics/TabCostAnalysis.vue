<script setup lang="ts">
import {
    Banknote,
    CircleDollarSign,
    DollarSign,
    PieChart,
    Wallet,
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
    <div class="space-y-6" data-test="tab-cost-content">
        <!-- Cost KPI Cards Row (E09-08) -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-xs font-medium text-slate-500">
                        {{ __('Total Biaya Lembur') }}
                    </CardTitle>
                    <Banknote class="size-4 text-[#cc0000]" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white"
                    >
                        Rp 125,5 Jt
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Akumulasi realisasi bulan berjalan') }}
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
                        {{ __('Sisa Anggaran') }}
                    </CardTitle>
                    <Wallet class="size-4 text-emerald-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-emerald-600 tabular-nums dark:text-emerald-400"
                    >
                        Rp 32,8 Jt
                    </div>
                    <div
                        class="mt-2 h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800"
                    >
                        <div
                            class="h-1.5 rounded-full bg-emerald-500"
                            style="width: 78%"
                        />
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500">
                        {{ __('78.2% anggaran terkonsumsi') }}
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
                        {{ __('Rata-rata Biaya / Karyawan') }}
                    </CardTitle>
                    <CircleDollarSign class="size-4 text-sky-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white"
                    >
                        Rp 2,45 Jt
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Berdasarkan headcount lembur aktif') }}
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
                        {{ __('Rasio Biaya CapEx') }}
                    </CardTitle>
                    <DollarSign class="size-4 text-violet-600" />
                </CardHeader>
                <CardContent>
                    <div
                        class="font-mono text-2xl font-bold tracking-tight text-violet-600 tabular-nums dark:text-violet-400"
                    >
                        24.8%
                    </div>
                    <p
                        class="mt-1 flex items-center gap-1 text-xs text-slate-500"
                    >
                        <Badge
                            variant="secondary"
                            class="bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300"
                        >
                            {{ __('Terkapitalisasi') }}
                        </Badge>
                        <span>{{ __('Proyek CIP Aktif') }}</span>
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Cost Breakdown Canvas (E09-08 Shell) -->
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader>
                <CardTitle
                    class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                >
                    <PieChart class="size-4 text-[#cc0000]" />
                    <span>{{
                        __('Struktur & Audit Keuangan Lembur (OpEx vs CapEx)')
                    }}</span>
                </CardTitle>
                <CardDescription>
                    {{
                        __(
                            'Evaluasi realisasi anggaran vs pagu biaya departemen dengan pemisahan akun belanja modal.',
                        )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div
                    class="flex h-64 flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center dark:border-slate-800 dark:bg-slate-950/30"
                >
                    <Banknote
                        class="mb-2 size-10 text-slate-400 dark:text-slate-600"
                    />
                    <h3
                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ __('Modul Analisis Biaya Aktif') }}
                    </h3>
                    <p
                        class="mt-1 max-w-md text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{
                            __(
                                'Grafik area bertumpuk 6 bulan, perbandingan anggaran vs realisasi, dan tabel audit biaya per departemen disiapkan pada Story E09-08.',
                            )
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
