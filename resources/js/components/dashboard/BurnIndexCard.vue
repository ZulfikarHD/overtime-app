<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    ArrowRight,
    ArrowUp,
    ArrowUpRight,
    Clock,
    LineChart,
    MoveRight,
    Sparkles,
    TrendingUp,
} from '@lucide/vue';
import { computed } from 'vue';
import CapexOpexSplitBar from '@/components/dashboard/CapexOpexSplitBar.vue';
import { Badge } from '@/components/ui/badge';
import { useTrans } from '@/composables/useTrans';
import { planning } from '@/routes/budgets';

export interface SectionSnapshotData {
    id: number;
    section_id: number;
    section_code: string;
    section_name: string;
    department_id: number;
    department_name: string;
    department_code: string;
    planned_budget_hours: number;
    cumulative_actual_hours: number;
    remaining_budget_hours: number;
    burn_index_pct: number;
    burn_velocity: number;
    projected_total_hours: number;
    trajectory: 'on_pace' | 'trending_over' | 'will_overrun';
    ml_forecast?: {
        predicted_value: number;
        confidence_interval_lower: number | null;
        confidence_interval_upper: number | null;
        confidence_delta: number | null;
        risk_level: string | null;
        fallback_used: boolean;
    } | null;
    burn_zone:
        | 'ZONE_1_EXCELLENT'
        | 'ZONE_2_GOOD'
        | 'ZONE_3_WARNING'
        | 'ZONE_4_POOR';
    cumulative_opex_hours: number;
    cumulative_capex_hours: number;
    capex_ratio_pct: number;
    opex_ratio_pct: number;
    is_budget_configured: boolean;
    last_recalculated_at: string;
}

const props = defineProps<{
    snapshot: SectionSnapshotData;
}>();

const emit = defineEmits<{
    (e: 'select-section', sectionId: number): void;
}>();

const { __ } = useTrans();

const burnStatus = computed(() => {
    const pct = props.snapshot.burn_index_pct;

    if (!props.snapshot.is_budget_configured) {
        return {
            label: __('Belum Dikonfigurasi'),
            colorText: 'text-slate-500 dark:text-slate-400',
            bgBadge:
                'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            barBg: 'bg-slate-400',
            cardBorder: 'border-slate-200 dark:border-slate-800',
        };
    }

    if (pct > 115) {
        return {
            label: __('Defisit Kritis'),
            colorText: 'text-[#cc0000] dark:text-red-400',
            bgBadge:
                'bg-red-50 text-[#cc0000] border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900',
            barBg: 'bg-[#cc0000]',
            cardBorder:
                'burn-card--danger animate-pulse border-red-500 shadow-md shadow-red-500/20',
        };
    }

    if (pct > 100) {
        return {
            label: __('Peringatan'),
            colorText: 'text-amber-600 dark:text-amber-400',
            bgBadge:
                'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900',
            barBg: 'bg-amber-500',
            cardBorder:
                'burn-card--warning animate-pulse border-amber-500 shadow-md shadow-amber-500/20',
        };
    }

    if (pct >= 85) {
        return {
            label: __('Terkendali'),
            colorText: 'text-sky-600 dark:text-sky-400',
            bgBadge:
                'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-900',
            barBg: 'bg-sky-500',
            cardBorder: 'border-slate-200 dark:border-slate-800',
        };
    }

    return {
        label: __('Aman'),
        colorText: 'text-emerald-600 dark:text-emerald-400',
        bgBadge:
            'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900',
        barBg: 'bg-emerald-500',
        cardBorder: 'border-slate-200 dark:border-slate-800',
    };
});

const zoneBadge = computed(() => {
    switch (props.snapshot.burn_zone) {
        case 'ZONE_1_EXCELLENT':
            return {
                label: __('Zona 1: Sangat Baik (Aman)'),
                class: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900',
            };
        case 'ZONE_2_GOOD':
            return {
                label: __('Zona 2: Baik (Terkendali)'),
                class: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-900',
            };
        case 'ZONE_3_WARNING':
            return {
                label: __('Zona 3: Peringatan (Burn Cepat)'),
                class: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900',
            };
        case 'ZONE_4_POOR':
        default:
            return {
                label: __('Zona 4: Defisit (Melebihi Anggaran)'),
                class: 'bg-red-50 text-[#cc0000] border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900',
            };
    }
});

const trajectoryBadge = computed(() => {
    switch (props.snapshot.trajectory) {
        case 'trending_over':
            return {
                label: __('↗ Waspada (Trending Over)'),
                class: 'text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800',
                icon: ArrowUpRight,
            };
        case 'will_overrun':
            return {
                label: __('↑ Kritis (Will Overrun)'),
                class: 'text-[#cc0000] dark:text-red-400 bg-red-50 dark:bg-red-950/40 border-red-200 dark:border-red-900',
                icon: ArrowUp,
            };
        case 'on_pace':
        default:
            return {
                label: __('→ Aman (On Pace)'),
                class: 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800',
                icon: MoveRight,
            };
    }
});

const progressWidth = computed(() => {
    if (!props.snapshot.is_budget_configured) {
        return 0;
    }
    return Math.min(100, Math.max(0, props.snapshot.burn_index_pct));
});

const formattedLastRecalculated = computed(() => {
    if (!props.snapshot.last_recalculated_at) {
        return '-';
    }
    const d = new Date(props.snapshot.last_recalculated_at);
    return (
        d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) +
        ' WIB'
    );
});

const mlComparisonTooltip = computed(() => {
    if (!props.snapshot.ml_forecast) {
        return '';
    }
    const heur = `${props.snapshot.projected_total_hours.toFixed(1)} ${__('jam')}`;
    const mlVal = props.snapshot.ml_forecast.predicted_value.toFixed(1);
    const delta =
        props.snapshot.ml_forecast.confidence_delta !== null
            ? ` ±${props.snapshot.ml_forecast.confidence_delta.toFixed(1)} ${__('jam')}`
            : ` ${__('jam')}`;
    return `${__('Heuristik')}: ${heur} | ${__('Prediksi AI')}: ${mlVal}${delta}`;
});
</script>

<template>
    <div
        class="bg-card text-card-foreground flex cursor-pointer flex-col justify-between rounded-xl border p-4 transition-all duration-200 hover:shadow-md"
        :class="burnStatus.cardBorder"
        :data-test="`burn-card-${snapshot.section_code}`"
        @click="$emit('select-section', snapshot.section_id)"
    >
        <!-- Card Top Header -->
        <div class="space-y-2">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <h3
                            class="text-sm font-bold text-slate-900 dark:text-white"
                        >
                            {{ snapshot.section_name }}
                        </h3>
                        <Badge
                            variant="outline"
                            class="px-1.5 py-0 font-mono text-[10px]"
                        >
                            {{ snapshot.section_code }}
                        </Badge>
                    </div>
                    <p class="text-muted-foreground text-xs">
                        {{ snapshot.department_name }}
                    </p>
                </div>

                <!-- Burn Status Pill -->
                <Badge
                    v-if="snapshot.is_budget_configured"
                    variant="outline"
                    class="border px-2 py-0.5 text-[11px] font-semibold"
                    :class="burnStatus.bgBadge"
                >
                    {{ burnStatus.label }}
                </Badge>
            </div>

            <!-- UNCONFIGURED BUDGET EMPTY STATE -->
            <div
                v-if="!snapshot.is_budget_configured"
                class="my-3 space-y-2 rounded-lg border border-dashed border-slate-300 bg-slate-50/60 p-4 text-center dark:border-slate-700 dark:bg-slate-900/40"
                data-test="card-unconfigured-budget"
            >
                <div class="flex justify-center">
                    <AlertCircle class="text-muted-foreground size-7" />
                </div>
                <h4
                    class="text-xs font-bold text-slate-800 dark:text-slate-200"
                >
                    {{ __('Anggaran Belum Dikonfigurasi') }}
                </h4>
                <p class="text-muted-foreground text-[11px] leading-relaxed">
                    {{
                        __(
                            'Target kuota jam lembur belum ditentukan untuk periode ini. Indeks burn tidak dapat dihitung.',
                        )
                    }}
                </p>
                <Link
                    :href="planning()"
                    class="inline-flex items-center gap-1 pt-1 text-xs font-semibold text-[#cc0000] hover:underline dark:text-red-400"
                    @click.stop
                >
                    <span>{{ __('Atur Anggaran di Planning') }}</span>
                    <ArrowRight class="size-3" />
                </Link>
            </div>

            <!-- CONFIGURED BUDGET VIEW -->
            <div v-else class="space-y-3 pt-1">
                <!-- Large Burn Index % & Progress Dial -->
                <div class="flex items-baseline justify-between gap-2">
                    <div>
                        <div
                            class="text-muted-foreground text-[11px] font-medium"
                        >
                            {{ __('Indeks Burn') }}
                        </div>
                        <div
                            class="font-mono text-3xl font-extrabold tracking-tight tabular-nums"
                            :class="burnStatus.colorText"
                            data-test="burn-index-value"
                        >
                            {{ snapshot.burn_index_pct.toFixed(1) }}%
                        </div>
                    </div>

                    <!-- Quota & Remaining Details -->
                    <div class="space-y-0.5 text-right">
                        <div class="text-xs text-slate-700 dark:text-slate-300">
                            <span class="font-mono font-bold tabular-nums">
                                {{
                                    snapshot.cumulative_actual_hours.toFixed(1)
                                }}
                            </span>
                            <span class="text-muted-foreground font-normal">
                                /
                            </span>
                            <span
                                class="text-muted-foreground font-mono font-medium tabular-nums"
                            >
                                {{ snapshot.planned_budget_hours.toFixed(1) }}
                                {{ __('jam') }}
                            </span>
                        </div>
                        <div class="text-[11px] font-medium">
                            <span class="text-muted-foreground"
                                >{{ __('Sisa Kuota') }}:
                            </span>
                            <span
                                class="font-mono font-semibold tabular-nums"
                                :class="
                                    snapshot.remaining_budget_hours < 0
                                        ? 'text-[#cc0000]'
                                        : 'text-slate-900 dark:text-white'
                                "
                            >
                                {{ snapshot.remaining_budget_hours.toFixed(1) }}
                                {{ __('jam') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Horizontal Burn Progress Bar -->
                <div
                    class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                >
                    <div
                        class="h-full rounded-full transition-all duration-300"
                        :class="burnStatus.barBg"
                        :style="{ width: `${progressWidth}%` }"
                    />
                </div>

                <!-- Matrix Zone & Trajectory Pill -->
                <div class="flex items-center justify-between gap-2 pt-0.5">
                    <Badge
                        variant="outline"
                        class="max-w-[55%] truncate border px-2 py-0.5 text-[10px] font-semibold"
                        :class="zoneBadge.class"
                        :title="zoneBadge.label"
                    >
                        {{ zoneBadge.label }}
                    </Badge>

                    <div
                        class="inline-flex shrink-0 items-center gap-1 rounded-full border px-2 py-0.5 font-mono text-[10px] font-semibold"
                        :class="trajectoryBadge.class"
                        data-test="trajectory-badge"
                    >
                        <component
                            :is="trajectoryBadge.icon"
                            class="size-3 shrink-0"
                            data-test="trajectory-icon"
                        />
                        <span>{{ trajectoryBadge.label }}</span>
                    </div>
                </div>

                <!-- Velocity & Projected Period End -->
                <div
                    class="grid grid-cols-2 gap-2 rounded-lg border border-slate-100 bg-slate-50 p-2 text-[11px] dark:border-slate-800 dark:bg-slate-900/60"
                >
                    <div>
                        <div
                            class="text-muted-foreground flex items-center gap-1"
                        >
                            <Activity class="size-3 text-slate-400" />
                            <span>{{ __('Kecepatan Burn') }}</span>
                        </div>
                        <div
                            class="pt-0.5 font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                            data-test="burn-velocity-value"
                        >
                            {{ snapshot.burn_velocity.toFixed(1) }}
                            <span
                                class="text-muted-foreground text-[10px] font-normal"
                                >{{ __('jam/mgg') }}</span
                            >
                        </div>
                    </div>
                    <div>
                        <div
                            class="text-muted-foreground flex items-center justify-between gap-1"
                        >
                            <div class="flex items-center gap-1">
                                <TrendingUp class="size-3 text-slate-400" />
                                <span>{{ __('Proyeksi Akhir') }}</span>
                            </div>
                            <span
                                v-if="snapshot.ml_forecast"
                                class="inline-flex items-center gap-0.5 rounded border border-violet-200 bg-violet-100 px-1 py-0 text-[9px] font-bold text-violet-800 dark:border-violet-800 dark:bg-violet-950/60 dark:text-violet-300"
                                :title="__('Prediksi AI (Supervised ML)')"
                                data-test="ml-forecast-pill"
                            >
                                <Sparkles
                                    class="size-2.5 text-violet-600 dark:text-violet-400"
                                />
                                <span>AI</span>
                            </span>
                        </div>
                        <div
                            class="pt-0.5 font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                            data-test="projected-total-value"
                        >
                            {{ snapshot.projected_total_hours.toFixed(1) }}
                            <span
                                class="text-muted-foreground text-[10px] font-normal"
                                >{{ __('jam') }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- ML Forecast Comparison Display (When ML prediction exists) -->
                <div
                    v-if="snapshot.ml_forecast"
                    class="flex items-center justify-between rounded-md border border-violet-100 bg-violet-50/60 px-2 py-1 text-[10px] text-violet-900 dark:border-violet-900/40 dark:bg-violet-950/30 dark:text-violet-300"
                    data-test="ml-forecast-comparison"
                    :title="mlComparisonTooltip"
                >
                    <div class="flex items-center gap-1">
                        <Sparkles
                            class="size-3 shrink-0 text-violet-600 dark:text-violet-400"
                        />
                        <span class="font-medium"
                            >{{ __('Prediksi AI') }}:</span
                        >
                        <span class="font-mono font-bold tabular-nums">
                            {{
                                snapshot.ml_forecast.predicted_value.toFixed(1)
                            }}
                            {{ __('jam') }}
                            <template
                                v-if="
                                    snapshot.ml_forecast.confidence_delta !==
                                    null
                                "
                            >
                                &plusmn;{{
                                    snapshot.ml_forecast.confidence_delta.toFixed(
                                        1,
                                    )
                                }}
                                {{ __('jam') }}
                            </template>
                        </span>
                    </div>
                    <span
                        v-if="snapshot.ml_forecast.risk_level"
                        class="rounded px-1 py-0.5 font-mono text-[9px] font-bold uppercase"
                        :class="
                            snapshot.ml_forecast.risk_level === 'HIGH'
                                ? 'border border-red-200 bg-red-100 text-[#cc0000] dark:border-red-900 dark:bg-red-950/60 dark:text-red-300'
                                : 'border border-slate-200 bg-slate-100 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                        "
                        data-test="ml-risk-badge"
                    >
                        {{ snapshot.ml_forecast.risk_level }}
                    </span>
                </div>

                <!-- CapEx vs OpEx Split Bar -->
                <div class="pt-1">
                    <CapexOpexSplitBar
                        :capex-hours="snapshot.cumulative_capex_hours"
                        :opex-hours="snapshot.cumulative_opex_hours"
                        :capex-ratio="snapshot.capex_ratio_pct"
                        :opex-ratio="snapshot.opex_ratio_pct"
                    />
                </div>
            </div>

            <!-- Action to open Burndown Drawer -->
            <button
                type="button"
                class="mt-3 flex w-full cursor-pointer items-center justify-between border-t border-slate-100 pt-2 text-xs font-semibold text-slate-700 transition-colors hover:text-[#cc0000] dark:border-slate-800 dark:text-slate-300 dark:hover:text-red-400"
                data-test="btn-open-burndown"
                @click.stop="$emit('select-section', snapshot.section_id)"
            >
                <span class="flex items-center gap-1.5">
                    <LineChart class="size-3.5 text-slate-400" />
                    <span>{{ __('Lihat Burndown & Matriks') }}</span>
                </span>
                <ArrowRight class="size-3.5 text-slate-400" />
            </button>
        </div>

        <!-- Card Footer: Last Recalculated -->
        <div
            class="text-muted-foreground mt-4 flex items-center justify-between border-t border-slate-100 pt-2 text-[10px] dark:border-slate-800"
        >
            <div class="flex items-center gap-1">
                <Clock class="size-3 text-slate-400" />
                <span>{{ __('Pembaruan') }}:</span>
                <span class="font-mono tabular-nums">{{
                    formattedLastRecalculated
                }}</span>
            </div>
            <span class="font-mono text-[10px] text-slate-400">
                #{{ snapshot.id }}
            </span>
        </div>
    </div>
</template>
