<script setup lang="ts">
import {
    ArrowDownRight,
    ArrowUpDown,
    ArrowUpRight,
    Building2,
    Minus,
    Search,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { DepartmentCostItem } from '@/components/analytics/CostByDepartmentChart.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';

interface Props {
    departmentCosts?: DepartmentCostItem[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    departmentCosts: () => [],
    loading: false,
});

const { __ } = useTrans();

type SortKey =
    | 'department_name'
    | 'total_hours'
    | 'avg_rate_per_hour'
    | 'total_cost'
    | 'budget_consumption_pct';

const sortKey = ref<SortKey>('total_cost');
const sortDirection = ref<'asc' | 'desc'>('desc');
const searchQuery = ref('');

function toggleSort(key: SortKey) {
    if (sortKey.value === key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDirection.value = 'desc';
    }
}

const filteredList = computed(() => {
    let items = [...props.departmentCosts];

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        items = items.filter(
            (d) =>
                d.department_name.toLowerCase().includes(q) ||
                d.department_code.toLowerCase().includes(q),
        );
    }

    items.sort((a, b) => {
        let valA = a[sortKey.value];
        let valB = b[sortKey.value];

        if (typeof valA === 'string' && typeof valB === 'string') {
            const cmp = valA.localeCompare(valB, 'id');
            return sortDirection.value === 'asc' ? cmp : -cmp;
        }

        const numA = Number(valA) || 0;
        const numB = Number(valB) || 0;
        return sortDirection.value === 'asc' ? numA - numB : numB - numA;
    });

    return items;
});

const grandTotals = computed(() => {
    const items = props.departmentCosts;
    let totalHours = 0;
    let totalCost = 0;
    let plannedCost = 0;

    for (const item of items) {
        totalHours += item.total_hours;
        totalCost += item.total_cost;
        plannedCost += item.planned_cost;
    }

    const avgRate = totalHours > 0 ? totalCost / totalHours : 0;
    const consumptionPct =
        plannedCost > 0 ? (totalCost / plannedCost) * 100 : 0;

    return {
        totalHours: roundTo(totalHours, 1),
        totalCost: roundTo(totalCost, 2),
        plannedCost: roundTo(plannedCost, 2),
        avgRate: roundTo(avgRate, 2),
        consumptionPct: roundTo(consumptionPct, 1),
    };
});

function roundTo(n: number, decimals: number): number {
    const factor = Math.pow(10, decimals);
    return Math.round(n * factor) / factor;
}
</script>

<template>
    <Card
        class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        data-test="cost-breakdown-table"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-1">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <Building2 class="size-4 text-[#cc0000]" />
                        <span>{{
                            __('Tabel Audit Biaya per Departemen')
                        }}</span>
                    </CardTitle>
                    <CardDescription>
                        {{
                            __(
                                'Rincian audit finansial mencakup realisasi jam, tarif rata-rata efektif, konsumsi pagu, dan arah tren per departemen.',
                            )
                        }}
                    </CardDescription>
                </div>

                <!-- Instant Search Filter -->
                <div class="relative w-full sm:w-64">
                    <Search
                        class="absolute top-2.5 left-2.5 size-4 text-slate-400"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="__('Cari nama / kode departemen...')"
                        class="h-9 pl-9 text-xs"
                        data-test="search-dept-cost"
                    />
                </div>
            </div>
        </CardHeader>

        <CardContent class="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="border-y border-slate-200 bg-slate-50/75 text-[11px] font-semibold tracking-wider text-slate-600 uppercase dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400"
                    >
                        <tr>
                            <th scope="col" class="px-4 py-3">
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1 font-semibold uppercase hover:text-slate-900 dark:hover:text-white"
                                    @click="toggleSort('department_name')"
                                >
                                    <span>{{ __('Departemen') }}</span>
                                    <ArrowUpDown
                                        class="size-3 text-slate-400"
                                    />
                                </button>
                            </th>
                            <th scope="col" class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1 font-semibold uppercase hover:text-slate-900 dark:hover:text-white"
                                    @click="toggleSort('total_hours')"
                                >
                                    <span>{{ __('Total Jam') }}</span>
                                    <ArrowUpDown
                                        class="size-3 text-slate-400"
                                    />
                                </button>
                            </th>
                            <th scope="col" class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1 font-semibold uppercase hover:text-slate-900 dark:hover:text-white"
                                    @click="toggleSort('avg_rate_per_hour')"
                                >
                                    <span>{{ __('Tarif (Rp/Jam)') }}</span>
                                    <ArrowUpDown
                                        class="size-3 text-slate-400"
                                    />
                                </button>
                            </th>
                            <th scope="col" class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1 font-semibold uppercase hover:text-slate-900 dark:hover:text-white"
                                    @click="toggleSort('total_cost')"
                                >
                                    <span>{{ __('Total Biaya (Rp)') }}</span>
                                    <ArrowUpDown
                                        class="size-3 text-slate-400"
                                    />
                                </button>
                            </th>
                            <th scope="col" class="px-4 py-3 text-center">
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1 font-semibold uppercase hover:text-slate-900 dark:hover:text-white"
                                    @click="
                                        toggleSort('budget_consumption_pct')
                                    "
                                >
                                    <span>{{ __('% Anggaran') }}</span>
                                    <ArrowUpDown
                                        class="size-3 text-slate-400"
                                    />
                                </button>
                            </th>
                            <th scope="col" class="px-4 py-3 text-center">
                                {{ __('Tren MoM') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800/60"
                    >
                        <tr v-if="loading">
                            <td
                                colspan="6"
                                class="py-10 text-center text-slate-400"
                            >
                                <span class="animate-pulse">{{
                                    __('Memuat data audit biaya...')
                                }}</span>
                            </td>
                        </tr>

                        <tr
                            v-else-if="filteredList.length === 0"
                            class="hover:bg-slate-50/50 dark:hover:bg-slate-900/40"
                        >
                            <td
                                colspan="6"
                                class="py-8 text-center text-slate-400"
                            >
                                {{
                                    __(
                                        'Belum ada data rincian biaya departemen.',
                                    )
                                }}
                            </td>
                        </tr>

                        <tr
                            v-for="dept in filteredList"
                            :key="dept.department_id"
                            class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40"
                            :data-test="`dept-cost-row-${dept.department_id}`"
                        >
                            <td class="px-4 py-3">
                                <div
                                    class="font-medium text-slate-900 dark:text-white"
                                >
                                    {{ dept.department_name }}
                                </div>
                                <div
                                    class="font-mono text-[11px] text-slate-400"
                                >
                                    {{ dept.department_code }}
                                </div>
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono font-semibold text-slate-700 tabular-nums dark:text-slate-300"
                            >
                                {{ dept.total_hours.toLocaleString('id-ID') }}
                                <span class="text-[10px] text-slate-400"
                                    >jam</span
                                >
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono text-slate-600 tabular-nums dark:text-slate-400"
                            >
                                {{
                                    formatRupiah(dept.avg_rate_per_hour, {
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    })
                                }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                            >
                                {{
                                    formatRupiah(dept.total_cost, {
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    })
                                }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <Badge
                                    v-if="
                                        dept.is_over_budget ||
                                        dept.burn_zone === 'danger'
                                    "
                                    variant="outline"
                                    class="border-red-200 bg-red-50 font-mono font-bold text-red-700 tabular-nums dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                                >
                                    {{ dept.budget_consumption_pct }}%
                                </Badge>
                                <Badge
                                    v-else-if="dept.burn_zone === 'warning'"
                                    variant="outline"
                                    class="border-amber-200 bg-amber-50 font-mono font-bold text-amber-700 tabular-nums dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
                                >
                                    {{ dept.budget_consumption_pct }}%
                                </Badge>
                                <Badge
                                    v-else-if="dept.burn_zone === 'on_track'"
                                    variant="outline"
                                    class="border-blue-200 bg-blue-50 font-mono font-semibold text-blue-700 tabular-nums dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300"
                                >
                                    {{ dept.budget_consumption_pct }}%
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="border-emerald-200 bg-emerald-50 font-mono font-semibold text-emerald-700 tabular-nums dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
                                >
                                    {{ dept.budget_consumption_pct }}%
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div
                                    v-if="dept.trend === 'up'"
                                    class="inline-flex items-center gap-0.5 text-xs font-semibold text-red-600 dark:text-red-400"
                                    :title="`+${dept.trend_variance_pct}% ${__('dibandingkan bulan lalu')}`"
                                >
                                    <ArrowUpRight class="size-4" />
                                    <span
                                        class="font-mono text-[11px] tabular-nums"
                                        >+{{ dept.trend_variance_pct }}%</span
                                    >
                                </div>
                                <div
                                    v-else-if="dept.trend === 'down'"
                                    class="inline-flex items-center gap-0.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                                    :title="`${dept.trend_variance_pct}% ${__('dibandingkan bulan lalu')}`"
                                >
                                    <ArrowDownRight class="size-4" />
                                    <span
                                        class="font-mono text-[11px] tabular-nums"
                                        >{{ dept.trend_variance_pct }}%</span
                                    >
                                </div>
                                <div
                                    v-else
                                    class="inline-flex items-center gap-0.5 text-xs text-slate-400"
                                    :title="__('Stabil / fluktuasi < 5%')"
                                >
                                    <Minus class="size-4" />
                                    <span class="text-[11px]">{{
                                        __('Stabil')
                                    }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot
                        v-if="filteredList.length > 0"
                        class="border-t-2 border-slate-300 bg-slate-50 font-semibold text-slate-800 dark:border-slate-700 dark:bg-slate-950/60 dark:text-slate-200"
                    >
                        <tr>
                            <td
                                class="px-4 py-3.5 font-bold text-slate-900 dark:text-white"
                            >
                                {{ __('Total Konsolidasi') }}
                            </td>
                            <td
                                class="px-4 py-3.5 text-right font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                            >
                                {{
                                    grandTotals.totalHours.toLocaleString(
                                        'id-ID',
                                    )
                                }}
                                <span class="text-[10px] text-slate-500"
                                    >jam</span
                                >
                            </td>
                            <td
                                class="px-4 py-3.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-300"
                            >
                                {{
                                    formatRupiah(grandTotals.avgRate, {
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    })
                                }}
                            </td>
                            <td
                                class="px-4 py-3.5 text-right font-mono font-extrabold text-[#cc0000] tabular-nums"
                            >
                                {{
                                    formatRupiah(grandTotals.totalCost, {
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    })
                                }}
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <Badge
                                    variant="outline"
                                    class="border-slate-300 bg-white font-mono font-bold text-slate-800 tabular-nums dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                >
                                    {{ grandTotals.consumptionPct }}%
                                </Badge>
                            </td>
                            <td
                                class="px-4 py-3.5 text-center text-[11px] text-slate-400"
                            >
                                &mdash;
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
