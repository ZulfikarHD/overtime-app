<script setup lang="ts">
import {
    AlertTriangle,
    Clock,
    DollarSign,
    Flame,
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
import { formatRupiah } from '@/lib/formatters';

export interface ProjectBurnMetrics {
    allocated_hours: number;
    consumed_hours: number;
    remaining_hours: number;
    allocated_budget_idr: number;
    consumed_cost_idr: number;
    remaining_budget_idr: number;
    burn_index_pct: number;
    physical_progress_pct: number;
    milestone_burn_ratio: number;
    days_remaining: number;
    is_at_risk: boolean;
    is_overdue: boolean;
}

const props = defineProps<{
    metrics: ProjectBurnMetrics;
}>();

const { __ } = useTrans();

function getBurnIndexColor(index: number) {
    if (index > 115) return 'text-[#cc0000]';
    if (index > 100) return 'text-amber-600';
    if (index >= 85) return 'text-sky-600';
    return 'text-emerald-600';
}
</script>

<template>
    <div
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
        data-test="capex-burn-index-panel"
    >
        <!-- Card 1: Jam Tenaga Kerja -->
        <Card class="border-border bg-card">
            <CardHeader class="pb-2">
                <CardDescription
                    class="text-muted-foreground flex items-center justify-between text-xs font-semibold tracking-wider uppercase"
                >
                    <span>{{ __('Jam Tenaga Kerja') }}</span>
                    <Clock class="size-4 text-sky-600" />
                </CardDescription>
                <CardTitle
                    class="text-foreground font-mono text-xl font-bold tabular-nums"
                >
                    {{
                        metrics.consumed_hours.toLocaleString('id-ID', {
                            minimumFractionDigits: 1,
                            maximumFractionDigits: 1,
                        })
                    }}
                    <span class="text-muted-foreground text-xs font-normal"
                        >/
                        {{
                            metrics.allocated_hours.toLocaleString('id-ID', {
                                minimumFractionDigits: 1,
                                maximumFractionDigits: 1,
                            })
                        }}
                        jam</span
                    >
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-xs">
                <div class="bg-muted h-2 w-full overflow-hidden rounded-full">
                    <div
                        class="h-2 rounded-full transition-all"
                        :class="
                            metrics.burn_index_pct > 100
                                ? 'bg-[#cc0000]'
                                : 'bg-sky-600'
                        "
                        :style="{
                            width: `${Math.min(100, metrics.burn_index_pct)}%`,
                        }"
                    ></div>
                </div>
                <div
                    class="text-muted-foreground flex items-center justify-between font-mono text-[11px]"
                >
                    <span
                        >{{ __('Sisa alokasi:') }}
                        {{ metrics.remaining_hours.toFixed(1) }} jam</span
                    >
                    <span class="font-semibold"
                        >{{ metrics.burn_index_pct.toFixed(1) }}%</span
                    >
                </div>
            </CardContent>
        </Card>

        <!-- Card 2: Biaya Tenaga Kerja Terkapitalisasi -->
        <Card class="border-border bg-card">
            <CardHeader class="pb-2">
                <CardDescription
                    class="text-muted-foreground flex items-center justify-between text-xs font-semibold tracking-wider uppercase"
                >
                    <span>{{ __('Biaya Terkapitalisasi') }}</span>
                    <DollarSign class="size-4 text-emerald-600" />
                </CardDescription>
                <CardTitle
                    class="text-foreground font-mono text-lg font-bold tabular-nums"
                >
                    {{
                        formatRupiah(metrics.consumed_cost_idr, {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0,
                        })
                    }}
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-1 text-xs">
                <p class="text-muted-foreground text-[11px]">
                    {{ __('Anggaran:') }}
                    {{
                        formatRupiah(metrics.allocated_budget_idr, {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0,
                        })
                    }}
                </p>
                <p
                    class="font-mono text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                >
                    {{ __('Sisa Anggaran:') }}
                    {{
                        formatRupiah(metrics.remaining_budget_idr, {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0,
                        })
                    }}
                </p>
            </CardContent>
        </Card>

        <!-- Card 3: Indeks Burn CapEx -->
        <Card class="border-border bg-card">
            <CardHeader class="pb-2">
                <CardDescription
                    class="text-muted-foreground flex items-center justify-between text-xs font-semibold tracking-wider uppercase"
                >
                    <span>{{ __('Indeks Burn CapEx') }}</span>
                    <Flame
                        class="size-4"
                        :class="getBurnIndexColor(metrics.burn_index_pct)"
                    />
                </CardDescription>
                <CardTitle
                    class="font-mono text-2xl font-bold tabular-nums"
                    :class="getBurnIndexColor(metrics.burn_index_pct)"
                    data-test="kpi-burn-index-value"
                >
                    {{ metrics.burn_index_pct.toFixed(1) }}%
                </CardTitle>
            </CardHeader>
            <CardContent class="text-muted-foreground text-xs">
                <span
                    v-if="metrics.burn_index_pct > 115"
                    class="font-semibold text-[#cc0000]"
                >
                    🚨 {{ __('Overrun Defisit: Melebihi 115% alokasi') }}
                </span>
                <span
                    v-else-if="metrics.burn_index_pct > 100"
                    class="font-semibold text-amber-600"
                >
                    ⚠️ {{ __('Peringatan: Melebihi alokasi jam kerja') }}
                </span>
                <span
                    v-else-if="metrics.burn_index_pct >= 85"
                    class="font-medium text-sky-600"
                >
                    {{ __('Mendekati batas alokasi (85 - 100%)') }}
                </span>
                <span v-else class="font-medium text-emerald-600">
                    {{ __('Konsumsi dalam batas normal (< 85%)') }}
                </span>
            </CardContent>
        </Card>

        <!-- Card 4: Kemajuan Fisik & Rasio Milestone -->
        <Card class="border-border bg-card">
            <CardHeader class="pb-2">
                <CardDescription
                    class="text-muted-foreground flex items-center justify-between text-xs font-semibold tracking-wider uppercase"
                >
                    <span>{{ __('Kemajuan Fisik & Milestone') }}</span>
                    <TrendingUp class="size-4 text-sky-600" />
                </CardDescription>
                <CardTitle
                    class="text-foreground font-mono text-xl font-bold tabular-nums"
                    data-test="kpi-physical-progress-value"
                >
                    {{ metrics.physical_progress_pct.toFixed(1) }}%
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-1 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground text-[11px]">{{
                        __('Rasio Burn Milestone:')
                    }}</span>
                    <span
                        class="font-mono text-xs font-bold"
                        :class="
                            metrics.milestone_burn_ratio > 1.2
                                ? 'text-amber-600'
                                : 'text-foreground'
                        "
                        data-test="kpi-milestone-ratio-value"
                    >
                        {{
                            metrics.milestone_burn_ratio > 0
                                ? metrics.milestone_burn_ratio.toFixed(2)
                                : 'N/A'
                        }}
                    </span>
                </div>
                <div
                    v-if="metrics.milestone_burn_ratio > 1.2"
                    class="rounded-md border border-amber-200 bg-amber-50 p-1.5 text-[10px] leading-tight font-semibold text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300"
                    data-test="badge-milestone-warning"
                >
                    ⚠️
                    {{
                        __(
                            'Pembakaran jam lebih cepat dibanding kemajuan fisik!',
                        )
                    }}
                </div>
            </CardContent>
        </Card>
    </div>
</template>
