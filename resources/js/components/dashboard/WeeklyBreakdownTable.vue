<script setup lang="ts">
import { Calendar } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { useTrans } from '@/composables/useTrans';

export interface WeekTableRow {
    week_number: number;
    label: string;
    date_range: string;
    planned_hours: number;
    actual_hours: number | null;
    cumulative_planned_hours: number;
    cumulative_actual_hours: number | null;
    hkn_hours: number | null;
    hlr_hours: number | null;
    burn_pct: number | null;
    deviation_hours: number | null;
    is_future: boolean;
    is_current: boolean;
}

defineProps<{
    weeks: WeekTableRow[];
}>();

const { __ } = useTrans();

function getBurnColorClass(pct: number | null): string {
    if (pct === null) return 'text-slate-400';
    if (pct > 115) return 'text-[#cc0000] font-bold';
    if (pct > 100) return 'text-amber-600 font-bold';
    if (pct >= 85) return 'text-sky-600 font-semibold';
    return 'text-emerald-600 font-semibold';
}

function getDeviationClass(dev: number | null): string {
    if (dev === null) return 'text-slate-400';
    if (dev > 0) return 'text-amber-600 font-semibold';
    if (dev < 0) return 'text-emerald-600 font-semibold';
    return 'text-slate-600 dark:text-slate-400';
}
</script>

<template>
    <div
        class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800"
        data-test="weekly-breakdown-table-container"
    >
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead
                    class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300"
                >
                    <tr>
                        <th class="px-3 py-2.5">{{ __('Minggu') }}</th>
                        <th class="px-3 py-2.5">{{ __('Rentang Tanggal') }}</th>
                        <th class="px-3 py-2.5 text-right">
                            {{ __('Rencana') }}
                        </th>
                        <th class="px-3 py-2.5 text-right">
                            {{ __('Realisasi') }}
                        </th>
                        <th class="px-3 py-2.5 text-right">
                            {{ __('Jam HKN') }}
                        </th>
                        <th class="px-3 py-2.5 text-right">
                            {{ __('Jam HLR') }}
                        </th>
                        <th class="px-3 py-2.5 text-right">
                            {{ __('Akumulasi Burn') }}
                        </th>
                        <th class="px-3 py-2.5 text-right">
                            {{ __('Deviasi') }}
                        </th>
                    </tr>
                </thead>
                <tbody
                    class="divide-y divide-slate-100 text-slate-800 dark:divide-slate-800 dark:text-slate-200"
                >
                    <tr
                        v-for="week in weeks"
                        :key="week.week_number"
                        class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                        :class="[
                            week.is_current
                                ? 'bg-sky-50/30 dark:bg-sky-950/20'
                                : '',
                            week.is_future ? 'opacity-60' : '',
                        ]"
                        :data-test="`weekly-row-${week.week_number}`"
                    >
                        <!-- Week label + active indicator -->
                        <td class="px-3 py-2 font-medium">
                            <div class="flex items-center gap-1.5">
                                <span>{{ week.label }}</span>
                                <Badge
                                    v-if="week.is_current"
                                    variant="outline"
                                    class="border-sky-300 bg-sky-50 px-1 py-0 text-[9px] font-semibold text-sky-700 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-300"
                                >
                                    {{ __('Aktif') }}
                                </Badge>
                                <span
                                    v-else-if="week.is_future"
                                    class="text-muted-foreground text-[10px]"
                                >
                                    ({{ __('Akan Datang') }})
                                </span>
                            </div>
                        </td>

                        <!-- Date range -->
                        <td
                            class="px-3 py-2 font-mono text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            <div class="flex items-center gap-1">
                                <Calendar
                                    class="size-3 shrink-0 text-slate-400"
                                />
                                <span>{{ week.date_range }}</span>
                            </div>
                        </td>

                        <!-- Planned hours -->
                        <td class="px-3 py-2 text-right font-mono tabular-nums">
                            {{ week.planned_hours.toFixed(1) }}
                            <span class="text-muted-foreground text-[10px]"
                                >j</span
                            >
                        </td>

                        <!-- Actual hours -->
                        <td
                            class="px-3 py-2 text-right font-mono font-semibold tabular-nums"
                        >
                            <template v-if="week.actual_hours !== null">
                                {{ week.actual_hours.toFixed(1) }}
                                <span class="text-muted-foreground text-[10px]"
                                    >j</span
                                >
                            </template>
                            <span
                                v-else
                                class="text-muted-foreground font-normal"
                                >-</span
                            >
                        </td>

                        <!-- HKN hours -->
                        <td
                            class="px-3 py-2 text-right font-mono text-slate-600 tabular-nums dark:text-slate-400"
                        >
                            <template v-if="week.hkn_hours !== null">
                                {{ week.hkn_hours.toFixed(1) }}
                                <span class="text-muted-foreground text-[10px]"
                                    >j</span
                                >
                            </template>
                            <span v-else class="text-muted-foreground">-</span>
                        </td>

                        <!-- HLR hours -->
                        <td
                            class="px-3 py-2 text-right font-mono text-slate-600 tabular-nums dark:text-slate-400"
                        >
                            <template v-if="week.hlr_hours !== null">
                                <span
                                    :class="
                                        week.hlr_hours > 0
                                            ? 'font-medium text-amber-700 dark:text-amber-400'
                                            : ''
                                    "
                                >
                                    {{ week.hlr_hours.toFixed(1) }}
                                </span>
                                <span class="text-muted-foreground text-[10px]"
                                    >j</span
                                >
                            </template>
                            <span v-else class="text-muted-foreground">-</span>
                        </td>

                        <!-- Cumulative Burn % -->
                        <td class="px-3 py-2 text-right font-mono tabular-nums">
                            <span
                                v-if="week.burn_pct !== null"
                                :class="getBurnColorClass(week.burn_pct)"
                            >
                                {{ week.burn_pct.toFixed(1) }}%
                            </span>
                            <span v-else class="text-muted-foreground">-</span>
                        </td>

                        <!-- Deviation -->
                        <td class="px-3 py-2 text-right font-mono tabular-nums">
                            <template v-if="week.deviation_hours !== null">
                                <span
                                    :class="
                                        getDeviationClass(week.deviation_hours)
                                    "
                                >
                                    {{
                                        week.deviation_hours > 0
                                            ? `+${week.deviation_hours.toFixed(1)}`
                                            : week.deviation_hours.toFixed(1)
                                    }}
                                </span>
                                <span class="text-muted-foreground text-[10px]"
                                    >j</span
                                >
                            </template>
                            <span v-else class="text-muted-foreground">-</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
