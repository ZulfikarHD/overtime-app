<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    Calendar,
    Flame,
    Repeat,
    Snowflake,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

interface SeasonItem {
    month: string;
    value: number;
    variance_pct: number;
}

interface Props {
    peakSeason?: SeasonItem;
    lowSeason?: SeasonItem;
    cyclePattern?: string;
    loading?: boolean;
}

withDefaults(defineProps<Props>(), {
    peakSeason: () => ({
        month: 'Desember',
        value: 1250,
        variance_pct: 18.5,
    }),
    lowSeason: () => ({
        month: 'Mei',
        value: 710,
        variance_pct: -14.2,
    }),
    cyclePattern: '12 Bulan (Tahunan)',
    loading: false,
});

const { __ } = useTrans();
</script>

<template>
    <div
        class="grid gap-4 sm:grid-cols-3"
        data-test="seasonal-summary-cards-grid"
    >
        <!-- Card 1: Musim Puncak -->
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <CardTitle class="text-xs font-medium text-slate-500">
                    {{ __('Musim Puncak') }}
                </CardTitle>
                <Flame class="size-4 text-amber-600" />
            </CardHeader>
            <CardContent>
                <div class="flex items-baseline justify-between gap-2">
                    <span
                        class="text-xl font-bold tracking-tight text-slate-900 dark:text-white"
                        data-test="peak-season-month"
                    >
                        {{ peakSeason.month }}
                    </span>
                    <Badge
                        variant="outline"
                        class="border-amber-300 bg-amber-50 font-mono text-xs text-amber-700 tabular-nums dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                    >
                        <ArrowUp class="mr-0.5 size-3" />
                        +{{ peakSeason.variance_pct.toFixed(1) }}%
                    </Badge>
                </div>
                <p class="mt-1 font-mono text-xs text-slate-500 tabular-nums">
                    {{ __('Rata-rata') }}: {{ peakSeason.value.toFixed(1) }}
                    {{ __('jam/bln') }}
                </p>
            </CardContent>
        </Card>

        <!-- Card 2: Musim Rendah -->
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <CardTitle class="text-xs font-medium text-slate-500">
                    {{ __('Musim Rendah') }}
                </CardTitle>
                <Snowflake class="size-4 text-sky-600" />
            </CardHeader>
            <CardContent>
                <div class="flex items-baseline justify-between gap-2">
                    <span
                        class="text-xl font-bold tracking-tight text-slate-900 dark:text-white"
                        data-test="low-season-month"
                    >
                        {{ lowSeason.month }}
                    </span>
                    <Badge
                        variant="outline"
                        class="border-sky-300 bg-sky-50 font-mono text-xs text-sky-700 tabular-nums dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300"
                    >
                        <ArrowDown class="mr-0.5 size-3" />
                        {{ lowSeason.variance_pct.toFixed(1) }}%
                    </Badge>
                </div>
                <p class="mt-1 font-mono text-xs text-slate-500 tabular-nums">
                    {{ __('Rata-rata') }}: {{ lowSeason.value.toFixed(1) }}
                    {{ __('jam/bln') }}
                </p>
            </CardContent>
        </Card>

        <!-- Card 3: Siklus Pola -->
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <CardTitle class="text-xs font-medium text-slate-500">
                    {{ __('Siklus Pola') }}
                </CardTitle>
                <Repeat class="size-4 text-emerald-600" />
            </CardHeader>
            <CardContent>
                <div
                    class="text-xl font-bold tracking-tight text-slate-900 dark:text-white"
                    data-test="cycle-pattern-text"
                >
                    {{ cyclePattern }}
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    {{
                        __('Pola musiman berulang sesuai kalender operasional')
                    }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
