<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { ChartDataset, ChartOptions } from 'chart.js';
import {
    AlertTriangle,
    BarChart3,
    CheckCircle2,
    ExternalLink,
    Layers,
    TrendingUp,
} from '@lucide/vue';
import { computed } from 'vue';
import BaseBarChart from '@/components/charts/BaseBarChart.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { chartColors, useChartTheme } from '@/composables/useChartTheme';
import { useTrans } from '@/composables/useTrans';
import { burnIndex } from '@/routes/dashboard';

export interface SectionBurnItem {
    id: number;
    code: string;
    name: string;
    department_id: number;
    department_name: string;
    planned_hours: number;
    actual_hours: number;
    burn_index_pct: number;
    zone: 'safe' | 'on_track' | 'warning' | 'danger';
    zone_label: string;
}

export interface SectionBurnComparisonData {
    sections: SectionBurnItem[];
    fiscal_year: number;
    fiscal_month: number;
    month_name: string;
    total_sections: number;
    critical_sections_count: number;
    warning_sections_count: number;
    on_track_sections_count: number;
    safe_sections_count: number;
}

interface Props {
    data?: SectionBurnComparisonData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    data: undefined,
    loading: false,
});

const { __ } = useTrans();
const { getBurnZoneColor } = useChartTheme();

function navigateToSectionDetail(section: SectionBurnItem) {
    router.visit(
        burnIndex.url({
            query: {
                tab: 'sections',
                section: section.id,
                year: props.data?.fiscal_year,
                month: props.data?.fiscal_month,
                department_id: section.department_id,
            },
        }),
    );
}

const chartLabels = computed(() => {
    if (!props.data?.sections) {
        return [];
    }
    return props.data.sections.map((s) => `${s.code} - ${s.name}`);
});

const barColors = computed(() => {
    if (!props.data?.sections) {
        return [];
    }
    return props.data.sections.map((s) => getBurnZoneColor(s.burn_index_pct));
});

const datasets = computed<ChartDataset<'bar'>[]>(() => {
    if (!props.data?.sections) {
        return [];
    }

    return [
        {
            label: __('Indeks Burn (%)'),
            data: props.data.sections.map((s) => s.burn_index_pct),
            backgroundColor: barColors.value,
            hoverBackgroundColor: barColors.value,
            borderRadius: 4,
            barThickness: props.data.sections.length > 8 ? 16 : 24,
        },
    ];
});

const chartHeightClass = computed(() => {
    const count = props.data?.sections.length ?? 0;
    if (count > 10) {
        return 'h-96';
    }
    if (count > 5) {
        return 'h-80';
    }
    return 'h-64';
});

const chartOptions = computed<ChartOptions<'bar'>>(() => {
    const maxVal = props.data?.sections?.length
        ? Math.max(...props.data.sections.map((s) => s.burn_index_pct), 120)
        : 120;

    return {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        onClick: (_event, elements) => {
            if (!elements.length || !props.data?.sections) {
                return;
            }
            const index = elements[0].index;
            const targetSection = props.data.sections[index];
            if (targetSection) {
                navigateToSectionDetail(targetSection);
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                max: Math.ceil(maxVal * 1.15),
                grid: {
                    color: 'rgba(226, 232, 240, 0.5)',
                },
                ticks: {
                    font: {
                        family: 'monospace',
                        size: 11,
                    },
                    color: '#64748b',
                    callback: (value) => `${value}%`,
                },
                title: {
                    display: true,
                    text: __('Indeks Burn Kumulatif (%) — Target: 100%'),
                    font: {
                        size: 11,
                        weight: 'bold',
                    },
                    color: '#94a3b8',
                },
            },
            y: {
                grid: {
                    display: false,
                },
                ticks: {
                    font: {
                        family: '"Instrument Sans", sans-serif',
                        size: 11,
                        weight: 600,
                    },
                    color: '#334155',
                },
            },
        },
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                backgroundColor: '#0f172a',
                titleColor: '#ffffff',
                bodyColor: '#f1f5f9',
                padding: 10,
                cornerRadius: 8,
                callbacks: {
                    title: (items) => {
                        if (!items.length || !props.data?.sections) return '';
                        const item = props.data.sections[items[0].dataIndex];
                        return `${item.code}: ${item.name}`;
                    },
                    label: (context) => {
                        if (!props.data?.sections) return '';
                        const item = props.data.sections[context.dataIndex];
                        return `${__('Indeks Burn')}: ${item.burn_index_pct}% (${item.zone_label})`;
                    },
                    afterBody: (items) => {
                        if (!items.length || !props.data?.sections) return [];
                        const item = props.data.sections[items[0].dataIndex];
                        return [
                            `${__('Realisasi')}: ${item.actual_hours.toFixed(1)} jam`,
                            `${__('Plafon Anggaran')}: ${item.planned_hours.toFixed(1)} jam`,
                            `------------------------`,
                            `👉 ${__('Klik untuk lihat detail burndown seksi')}`,
                        ];
                    },
                },
            },
        },
    };
});
</script>

<template>
    <Card
        class="border-border/70 overflow-hidden shadow-xs"
        data-test="section-burn-comparison-card"
    >
        <CardHeader class="pb-3">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <BarChart3
                            class="size-5 text-blue-600 dark:text-blue-400"
                        />
                        <CardTitle
                            class="text-lg font-bold tracking-tight text-slate-900 dark:text-white"
                        >
                            {{ __('Section Burn Comparison') }}
                        </CardTitle>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{
                            __(
                                'Perbandingan performa konsumsi anggaran lembur antar seksi. Klik pada baris atau bar seksi untuk membuka detail burndown mingguan.',
                            )
                        }}
                    </p>
                </div>

                <!-- Zone Breakdown Badges -->
                <div
                    v-if="data"
                    class="flex flex-wrap items-center gap-1.5"
                    data-test="section-burn-zone-summary"
                >
                    <Badge
                        v-if="data.critical_sections_count > 0"
                        variant="outline"
                        class="border-red-500/20 bg-red-500/10 font-mono text-xs font-semibold text-red-700 tabular-nums dark:text-red-300"
                    >
                        <AlertTriangle class="mr-1 size-3 text-red-600" />
                        {{ data.critical_sections_count }}
                        {{ __('Kritis (>115%)') }}
                    </Badge>

                    <Badge
                        v-if="data.warning_sections_count > 0"
                        variant="outline"
                        class="border-amber-500/20 bg-amber-500/10 font-mono text-xs font-semibold text-amber-700 tabular-nums dark:text-amber-300"
                    >
                        <AlertTriangle class="mr-1 size-3 text-amber-600" />
                        {{ data.warning_sections_count }}
                        {{ __('Peringatan (101–115%)') }}
                    </Badge>

                    <Badge
                        v-if="data.on_track_sections_count > 0"
                        variant="outline"
                        class="border-blue-500/20 bg-blue-500/10 font-mono text-xs font-semibold text-blue-700 tabular-nums dark:text-blue-300"
                    >
                        <TrendingUp class="mr-1 size-3 text-blue-600" />
                        {{ data.on_track_sections_count }}
                        {{ __('Sesuai (85–100%)') }}
                    </Badge>

                    <Badge
                        v-if="data.safe_sections_count > 0"
                        variant="outline"
                        class="border-emerald-500/20 bg-emerald-500/10 font-mono text-xs font-semibold text-emerald-700 tabular-nums dark:text-emerald-300"
                    >
                        <CheckCircle2 class="mr-1 size-3 text-emerald-600" />
                        {{ data.safe_sections_count }} {{ __('Aman (<85%)') }}
                    </Badge>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4 pt-0">
            <!-- Horizontal Bar Chart -->
            <BaseBarChart
                :labels="chartLabels"
                :datasets="datasets"
                :options="chartOptions"
                :horizontal="true"
                :loading="loading"
                :empty="!data || data.sections.length === 0"
                :height-class="chartHeightClass"
                data-test="section-burn-bar-canvas"
            />

            <!-- High-Density Quick Access Section Chips (Touch Ergonomics & Direct Click Navigation) -->
            <div
                v-if="data?.sections && data.sections.length > 0"
                class="border-t border-slate-100 pt-3 dark:border-slate-800"
            >
                <div class="mb-2 flex items-center justify-between">
                    <span
                        class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                    >
                        <Layers class="size-3.5" />
                        {{ __('Daftar Seksi & Peringkat Burn Index') }}
                    </span>
                    <span class="text-[11px] text-slate-400">
                        {{ __('Klik kartu untuk navigasi langsung') }}
                    </span>
                </div>

                <div
                    class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                    data-test="section-burn-cards-grid"
                >
                    <button
                        v-for="sec in data.sections"
                        :key="sec.id"
                        type="button"
                        class="group flex items-center justify-between rounded-lg border border-slate-200/80 bg-slate-50/50 p-2.5 text-left transition-all hover:border-blue-400 hover:bg-blue-50/30 hover:shadow-2xs dark:border-slate-800 dark:bg-slate-900/40 dark:hover:border-blue-600"
                        :data-test="`section-card-${sec.code}`"
                        @click="navigateToSectionDetail(sec)"
                    >
                        <div class="min-w-0 pr-2">
                            <div class="flex items-center gap-1.5">
                                <span
                                    class="font-mono text-xs font-bold text-slate-900 dark:text-white"
                                >
                                    {{ sec.code }}
                                </span>
                                <ExternalLink
                                    class="size-3 text-slate-400 opacity-0 transition-opacity group-hover:opacity-100"
                                />
                            </div>
                            <p
                                class="truncate text-xs text-slate-500 dark:text-slate-400"
                                :title="sec.name"
                            >
                                {{ sec.name }}
                            </p>
                        </div>

                        <div class="text-right">
                            <span
                                class="font-mono text-sm font-bold tabular-nums"
                                :style="{
                                    color: getBurnZoneColor(sec.burn_index_pct),
                                }"
                            >
                                {{ sec.burn_index_pct }}%
                            </span>
                            <p
                                class="font-mono text-[10px] text-slate-400 tabular-nums"
                            >
                                {{ sec.actual_hours.toFixed(0) }}/{{
                                    sec.planned_hours.toFixed(0)
                                }}h
                            </p>
                        </div>
                    </button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
