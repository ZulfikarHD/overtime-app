<script setup lang="ts">
import { Briefcase, Coffee } from '@lucide/vue';
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

export interface DayTypeBreakdownData {
    hkn_hours: number;
    hlr_hours: number;
    total: number;
    hkn_pct: number;
    hlr_pct: number;
}

const props = defineProps<{
    breakdown: DayTypeBreakdownData;
}>();

const { __ } = useTrans();

const totalHours = computed(() => {
    return Math.max(0, props.breakdown.total);
});

const safeHknPct = computed(() => {
    if (totalHours.value <= 0) return 0;
    return Math.max(0, Math.min(100, props.breakdown.hkn_pct));
});

const safeHlrPct = computed(() => {
    if (totalHours.value <= 0) return 0;
    return Math.max(0, Math.min(100, props.breakdown.hlr_pct));
});
</script>

<template>
    <Card class="border-border shadow-xs" data-test="day-type-breakdown-card">
        <CardHeader class="pb-2">
            <CardTitle class="text-sm font-semibold">
                {{ __('Distribusi Hari Kerja & Libur (HKN vs HLR)') }}
            </CardTitle>
            <CardDescription class="text-xs">
                {{
                    __(
                        'Perbandingan lembur pada hari kerja normal (HKN) vs hari libur/istirahat (HLR).',
                    )
                }}
            </CardDescription>
        </CardHeader>
        <CardContent class="flex flex-col gap-4 pt-2">
            <!-- 2 Highlight Cards: HKN & HLR -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <!-- HKN Card -->
                <div
                    class="border-border/70 bg-card flex flex-col justify-between rounded-lg border p-3.5 shadow-2xs"
                    data-test="day-type-hkn"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div
                                class="flex size-7 items-center justify-center rounded-md bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300"
                            >
                                <Briefcase class="size-3.5" />
                            </div>
                            <span
                                class="text-xs font-semibold text-slate-900 dark:text-white"
                            >
                                {{ __('Hari Kerja Normal (HKN)') }}
                            </span>
                        </div>
                        <span
                            class="font-mono text-xs font-bold text-indigo-600 tabular-nums dark:text-indigo-400"
                        >
                            {{
                                totalHours > 0
                                    ? `${safeHknPct.toFixed(1)}%`
                                    : '0%'
                            }}
                        </span>
                    </div>

                    <div
                        class="mt-3 flex items-baseline justify-between border-t border-slate-100 pt-2 dark:border-slate-800"
                    >
                        <span class="text-muted-foreground text-[11px]">
                            {{ __('Total Jam HKN') }}
                        </span>
                        <div class="flex items-baseline gap-1">
                            <span
                                class="font-mono text-lg font-bold text-slate-900 tabular-nums dark:text-white"
                            >
                                {{ breakdown.hkn_hours.toFixed(1) }}
                            </span>
                            <span
                                class="text-muted-foreground text-xs font-medium"
                            >
                                {{ __('jam') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- HLR Card -->
                <div
                    class="border-border/70 bg-card flex flex-col justify-between rounded-lg border p-3.5 shadow-2xs"
                    data-test="day-type-hlr"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div
                                class="flex size-7 items-center justify-center rounded-md bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
                            >
                                <Coffee class="size-3.5" />
                            </div>
                            <span
                                class="text-xs font-semibold text-slate-900 dark:text-white"
                            >
                                {{ __('Hari Libur / Istirahat (HLR)') }}
                            </span>
                        </div>
                        <span
                            class="font-mono text-xs font-bold text-amber-600 tabular-nums dark:text-amber-400"
                        >
                            {{
                                totalHours > 0
                                    ? `${safeHlrPct.toFixed(1)}%`
                                    : '0%'
                            }}
                        </span>
                    </div>

                    <div
                        class="mt-3 flex items-baseline justify-between border-t border-slate-100 pt-2 dark:border-slate-800"
                    >
                        <span class="text-muted-foreground text-[11px]">
                            {{ __('Total Jam HLR') }}
                        </span>
                        <div class="flex items-baseline gap-1">
                            <span
                                class="font-mono text-lg font-bold text-slate-900 tabular-nums dark:text-white"
                            >
                                {{ breakdown.hlr_hours.toFixed(1) }}
                            </span>
                            <span
                                class="text-muted-foreground text-xs font-medium"
                            >
                                {{ __('jam') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Horizontal Split Bar -->
            <div
                class="flex flex-col gap-1.5"
                data-test="day-type-progress-bar"
            >
                <div class="flex items-center justify-between text-[11px]">
                    <span class="text-muted-foreground font-medium">
                        {{ __('Visualisasi Komposisi Jam') }}
                    </span>
                    <span class="text-muted-foreground font-mono tabular-nums">
                        {{ totalHours.toFixed(1) }} {{ __('jam total') }}
                    </span>
                </div>

                <div
                    class="flex h-3 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                >
                    <div
                        v-if="safeHknPct > 0"
                        class="h-full bg-indigo-600 transition-all duration-300 dark:bg-indigo-500"
                        :style="{ width: `${safeHknPct}%` }"
                        :title="`HKN: ${breakdown.hkn_hours.toFixed(1)} jam (${safeHknPct.toFixed(1)}%)`"
                    />
                    <div
                        v-if="safeHlrPct > 0"
                        class="h-full bg-amber-500 transition-all duration-300 dark:bg-amber-600"
                        :style="{ width: `${safeHlrPct}%` }"
                        :title="`HLR: ${breakdown.hlr_hours.toFixed(1)} jam (${safeHlrPct.toFixed(1)}%)`"
                    />
                    <div
                        v-if="totalHours <= 0"
                        class="h-full w-full bg-slate-200 dark:bg-slate-700"
                        :title="__('Belum ada jam lembur disetujui')"
                    />
                </div>
            </div>

            <!-- Plain Language Ergonomic Guidance -->
            <div
                class="border-border/60 bg-muted/20 flex items-start gap-2 rounded-lg border p-2.5 text-xs text-slate-600 dark:text-slate-300"
            >
                <p>
                    <span class="font-semibold text-slate-900 dark:text-white">
                        {{ __('Ketentuan Kesejahteraan:') }}
                    </span>
                    {{
                        __(
                            'Jam kerja lembur pada hari istirahat mingguan (HLR) memiliki dampak kelelahan lebih tinggi terhadap siklus pemulihan fisik operator.',
                        )
                    }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
