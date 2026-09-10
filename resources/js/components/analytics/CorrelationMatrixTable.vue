<script setup lang="ts">
import {
    Activity,
    AlertCircle,
    CheckCircle2,
    Grid,
    HelpCircle,
    Info,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

export interface MatrixVariable {
    key: string;
    label: string;
}

export interface MatrixCell {
    r: number | null;
    strength: 'strong' | 'moderate' | 'weak' | 'erp_pending';
    color: 'green' | 'blue' | 'gray' | 'amber';
}

export interface CorrelationMatrixData {
    variables?: MatrixVariable[];
    matrix?: MatrixCell[][];
    legend?: Array<{ label: string; color: string; description: string }>;
}

interface Props {
    data?: CorrelationMatrixData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: () => ({
        variables: [
            { key: 'overtime', label: 'Jam Lembur' },
            { key: 'production', label: 'Volume Produksi' },
            { key: 'quality', label: 'Metrik Kualitas' },
            { key: 'efficiency', label: 'Efisiensi Output' },
            { key: 'cost', label: 'Biaya Lembur' },
        ],
        matrix: [],
        legend: [],
    }),
    loading: false,
});

const { __ } = useTrans();

function getCellBgClass(cell: MatrixCell): string {
    if (cell.strength === 'erp_pending' || cell.r === null) {
        return 'bg-amber-50/70 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900';
    }
    if (cell.color === 'green') {
        return 'bg-emerald-100/90 text-emerald-900 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-200 dark:border-emerald-800 font-bold';
    }
    if (cell.color === 'blue') {
        return 'bg-sky-100/80 text-sky-900 border-sky-300 dark:bg-sky-950/60 dark:text-sky-200 dark:border-sky-800 font-semibold';
    }
    return 'bg-slate-100/70 text-slate-700 border-slate-200 dark:bg-slate-800/80 dark:text-slate-300 dark:border-slate-700';
}
</script>

<template>
    <Card
        class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        data-test="correlation-matrix-table-card"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <Grid class="size-4 text-[#cc0000]" />
                        <span>{{
                            __('Matriks Korelasi Bivariat Antar-Variabel')
                        }}</span>
                    </CardTitle>
                    <CardDescription class="mt-1 text-xs">
                        {{
                            __(
                                'Koefisien korelasi Pearson (r) mengukur kekuatan hubungan linier antar parameter operasional utama lini produksi.',
                            )
                        }}
                    </CardDescription>
                </div>

                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 dark:text-slate-400"
                    >
                        <Info class="size-3.5 text-slate-400" />
                        <span>{{ __('Rentang r: -1.00 s/d +1.00') }}</span>
                    </span>
                </div>
            </div>
        </CardHeader>

        <CardContent>
            <!-- Matrix Table -->
            <div
                class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800"
            >
                <table
                    class="w-full border-collapse text-left text-xs"
                    data-test="correlation-matrix-table"
                >
                    <thead>
                        <tr
                            class="border-b border-slate-200 bg-slate-50/70 text-slate-600 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-400"
                        >
                            <th
                                class="min-w-[140px] p-3 text-[11px] font-semibold tracking-wider uppercase"
                            >
                                {{ __('Variabel Operasional') }}
                            </th>
                            <th
                                v-for="v in data?.variables ?? []"
                                :key="v.key"
                                class="min-w-[110px] p-3 text-center text-[11px] font-semibold"
                            >
                                {{ __(v.label) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <tr
                            v-for="(row, rowIdx) in data?.matrix ?? []"
                            :key="rowIdx"
                            class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-800/30"
                        >
                            <td
                                class="bg-slate-50/40 p-3 font-semibold text-slate-800 dark:bg-slate-950/20 dark:text-slate-200"
                            >
                                {{ __(data?.variables?.[rowIdx]?.label ?? '') }}
                            </td>
                            <td
                                v-for="(cell, colIdx) in row"
                                :key="colIdx"
                                class="p-2 text-center"
                            >
                                <div
                                    class="inline-flex h-9 w-20 items-center justify-center rounded-md border font-mono text-xs tabular-nums transition-all"
                                    :class="getCellBgClass(cell)"
                                    :title="
                                        cell.r !== null
                                            ? `Pearson r = ${cell.r}`
                                            : __('Menunggu integrasi data ERP')
                                    "
                                    :data-test="`matrix-cell-${rowIdx}-${colIdx}`"
                                >
                                    <span v-if="cell.r !== null">
                                        {{
                                            cell.r > 0
                                                ? `+${cell.r.toFixed(2)}`
                                                : cell.r.toFixed(2)
                                        }}
                                    </span>
                                    <span
                                        v-else
                                        class="font-sans text-[10px] font-medium text-amber-700 dark:text-amber-400"
                                    >
                                        {{ __('ERP Pending') }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Color-Coded Legend Row -->
            <div
                class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3 text-xs text-slate-600 dark:border-slate-800 dark:text-slate-300"
                data-test="matrix-legend-row"
            >
                <span
                    class="text-[11px] font-semibold text-slate-500 dark:text-slate-400"
                >
                    {{ __('Legenda Kekuatan Korelasi') }}:
                </span>

                <div class="flex items-center gap-1.5">
                    <span class="size-3 rounded-full bg-emerald-500"></span>
                    <span class="text-[11px]">
                        <strong>{{ __('Kuat') }}</strong> (|r| &ge; 0.70)
                    </span>
                </div>

                <div class="flex items-center gap-1.5">
                    <span class="size-3 rounded-full bg-sky-500"></span>
                    <span class="text-[11px]">
                        <strong>{{ __('Sedang') }}</strong> (0.40 &le; |r| &lt;
                        0.70)
                    </span>
                </div>

                <div class="flex items-center gap-1.5">
                    <span class="size-3 rounded-full bg-slate-400"></span>
                    <span class="text-[11px]">
                        <strong>{{ __('Lemah') }}</strong> (|r| &lt; 0.40)
                    </span>
                </div>

                <div class="flex items-center gap-1.5">
                    <span class="size-3 rounded-full bg-amber-400"></span>
                    <span class="text-[11px]">
                        <strong>{{ __('Menunggu ERP') }}</strong>
                    </span>
                </div>
            </div>

            <!-- Server Calculation Footnote -->
            <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">
                {{
                    __(
                        'Catatan: Koefisien korelasi dihitung secara server-side menggunakan rumus Pearson pada data bulanan historis masing-masing seksi perakitan.',
                    )
                }}
            </p>
        </CardContent>
    </Card>
</template>
