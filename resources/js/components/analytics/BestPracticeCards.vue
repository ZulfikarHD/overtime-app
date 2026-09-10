<script setup lang="ts">
import {
    AlertTriangle,
    Award,
    CheckCircle2,
    Compass,
    Lightbulb,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    Target,
    TrendingDown,
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

export interface PerformerInfo {
    name: string;
    code: string;
    metric_label: string;
    metric_value: string;
    burn_zone: string;
}

export interface BestPracticeCardItem {
    title: string;
    subtitle: string;
    department_name: string;
    metric_value: string;
    metric_badge: string;
    description: string;
    strategy_category: string;
}

interface Props {
    performers?: {
        best: PerformerInfo | null;
        average: PerformerInfo | null;
        worst: PerformerInfo | null;
    };
    bestPracticeCards?: BestPracticeCardItem[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    performers: () => ({ best: null, average: null, worst: null }),
    bestPracticeCards: () => [],
    loading: false,
});

const { __ } = useTrans();
</script>

<template>
    <div class="space-y-6" data-test="best-practice-section">
        <!-- 3 Performer Summary Cards -->
        <div
            class="grid gap-4 sm:grid-cols-3"
            data-test="performer-summary-cards"
        >
            <!-- Best Performer -->
            <Card
                class="border-emerald-200/80 bg-emerald-50/20 shadow-2xs dark:border-emerald-950 dark:bg-emerald-950/10"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-xs font-semibold tracking-wider text-emerald-800 uppercase dark:text-emerald-400"
                    >
                        {{ __('Departemen Terbaik') }}
                    </CardTitle>
                    <ShieldCheck
                        class="size-4 text-emerald-600 dark:text-emerald-400"
                    />
                </CardHeader>
                <CardContent>
                    <div
                        class="text-sm font-bold text-slate-900 dark:text-white"
                    >
                        {{ performers?.best?.name || __('Data Belum Lengkap') }}
                    </div>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span
                            class="font-mono text-xl font-extrabold text-emerald-600 tabular-nums dark:text-emerald-400"
                        >
                            {{ performers?.best?.metric_value || '0.0%' }}
                        </span>
                        <span class="text-2xs text-slate-500">
                            {{
                                performers?.best?.metric_label ||
                                __('Burn Index Terendah')
                            }}
                        </span>
                    </div>
                    <p class="text-2xs mt-2 text-slate-500">
                        {{
                            __(
                                'Rasio efisiensi alokasi jam lembur dan pemenuhan pagu anggaran terbaik.',
                            )
                        }}
                    </p>
                </CardContent>
            </Card>

            <!-- Average Performer (Benchmark Median) -->
            <Card
                class="border-sky-200/80 bg-sky-50/20 shadow-2xs dark:border-sky-950 dark:bg-sky-950/10"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-xs font-semibold tracking-wider text-sky-800 uppercase dark:text-sky-400"
                    >
                        {{ __('Benchmark Median') }}
                    </CardTitle>
                    <Target class="size-4 text-sky-600 dark:text-sky-400" />
                </CardHeader>
                <CardContent>
                    <div
                        class="text-sm font-bold text-slate-900 dark:text-white"
                    >
                        {{
                            performers?.average?.name ||
                            __('Pabrik Konsolidasi')
                        }}
                    </div>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span
                            class="font-mono text-xl font-extrabold text-sky-600 tabular-nums dark:text-sky-400"
                        >
                            {{ performers?.average?.metric_value || '0.0%' }}
                        </span>
                        <span class="text-2xs text-slate-500">
                            {{
                                performers?.average?.metric_label ||
                                __('Median Pabrik')
                            }}
                        </span>
                    </div>
                    <p class="text-2xs mt-2 text-slate-500">
                        {{
                            __(
                                'Titik acuan standar lintas departemen untuk evaluasi beban lembur wajar.',
                            )
                        }}
                    </p>
                </CardContent>
            </Card>

            <!-- Needs Attention / Worst Performer -->
            <Card
                class="border-amber-200/80 bg-amber-50/20 shadow-2xs dark:border-amber-950 dark:bg-amber-950/10"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-xs font-semibold tracking-wider text-amber-800 uppercase dark:text-amber-400"
                    >
                        {{ __('Perlu Perhatian Khusus') }}
                    </CardTitle>
                    <AlertTriangle
                        class="size-4 text-amber-600 dark:text-amber-400"
                    />
                </CardHeader>
                <CardContent>
                    <div
                        class="text-sm font-bold text-slate-900 dark:text-white"
                    >
                        {{
                            performers?.worst?.name || __('Data Belum Lengkap')
                        }}
                    </div>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span
                            class="font-mono text-xl font-extrabold text-amber-600 tabular-nums dark:text-amber-400"
                        >
                            {{ performers?.worst?.metric_value || '0.0%' }}
                        </span>
                        <span class="text-2xs text-slate-500">
                            {{
                                performers?.worst?.metric_label ||
                                __('Burn Index Tertinggi')
                            }}
                        </span>
                    </div>
                    <p class="text-2xs mt-2 text-slate-500">
                        {{
                            __(
                                'Rekomendasi penyesuaian rotasi shift teknisi dan audit SPKL akhir pekan.',
                            )
                        }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- 2 Strategic Best Practice Cards -->
        <div
            class="grid gap-4 md:grid-cols-2"
            data-test="strategic-best-practice-cards"
        >
            <Card
                v-for="(card, idx) in bestPracticeCards"
                :key="idx"
                class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
                data-test="best-practice-card"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span
                                class="flex size-7 items-center justify-center rounded-lg bg-red-50 text-[#cc0000] dark:bg-red-950/40 dark:text-red-300"
                            >
                                <Sparkles class="size-4" />
                            </span>
                            <div>
                                <CardTitle
                                    class="text-sm font-bold text-slate-900 dark:text-white"
                                >
                                    {{ card.title }}
                                </CardTitle>
                                <div
                                    class="text-2xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    {{ card.subtitle }}
                                </div>
                            </div>
                        </div>
                        <Badge
                            variant="outline"
                            class="text-2xs border-sky-200 bg-sky-50 font-semibold text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300"
                        >
                            {{ card.metric_badge }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-2.5">
                    <div
                        class="flex items-center justify-between rounded-md bg-slate-50 p-2.5 dark:bg-slate-800/50"
                    >
                        <div
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            {{ card.department_name }}
                        </div>
                        <div
                            class="font-mono text-xs font-bold text-emerald-600 tabular-nums dark:text-emerald-400"
                        >
                            {{ card.metric_value }}
                        </div>
                    </div>
                    <p
                        class="text-xs leading-relaxed text-slate-600 dark:text-slate-300"
                    >
                        {{ card.description }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
