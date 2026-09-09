<script setup lang="ts">
import {
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    Clock,
    HeartPulse,
    Info,
    Shield,
    ShieldAlert,
    ShieldCheck,
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

export interface WelfareBadge {
    type: 'safe' | 'warning' | 'danger';
    label: string;
    message: string;
}

const props = defineProps<{
    safetyScorePct: number;
    alertLevel: 'safe' | 'warning' | 'danger';
    currentWeekHours: number;
    weeklyLimit: number;
    consecutiveWeeks: number;
    consecutiveWeeksAlert: number;
    exceededWeeksCount: number;
    badges: WelfareBadge[];
    advisoryMessage?: string;
}>();

const { __ } = useTrans();

const radius = 54;
const strokeWidth = 10;
const normalizedRadius = radius - strokeWidth / 2;
const circumference = normalizedRadius * 2 * Math.PI;

const strokeDashoffset = computed(() => {
    const clampedScore = Math.min(100, Math.max(0, props.safetyScorePct));
    return circumference - (clampedScore / 100) * circumference;
});

const statusColor = computed(() => {
    if (props.safetyScorePct >= 75) {
        return {
            stroke: '#059669', // Emerald
            text: 'text-emerald-700 dark:text-emerald-400',
            bg: 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800',
            label: __('Tingkat Istirahat Terjaga'),
        };
    }
    if (props.safetyScorePct >= 50) {
        return {
            stroke: '#d97706', // Amber
            text: 'text-amber-700 dark:text-amber-400',
            bg: 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800',
            label: __('Perlu Rotasi Istirahat'),
        };
    }
    return {
        stroke: '#cc0000', // ISUZU Red
        text: 'text-red-700 dark:text-red-400',
        bg: 'bg-red-50 text-[#cc0000] border-red-300 dark:bg-red-950/50 dark:text-red-300 dark:border-red-800',
        label: __('Risiko Kelelahan Tinggi'),
    };
});
</script>

<template>
    <Card class="border-border shadow-xs" data-test="safety-score-gauge-card">
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <CardTitle
                    class="flex items-center gap-2 text-base font-semibold"
                >
                    <HeartPulse class="text-primary size-4" />
                    <span>{{ __('Skor Keselamatan & Kelelahan') }}</span>
                </CardTitle>

                <Badge
                    variant="outline"
                    class="text-[11px] font-semibold transition-colors"
                    :class="statusColor.bg"
                    data-test="welfare-status-pill"
                >
                    {{ statusColor.label }}
                </Badge>
            </div>
            <CardDescription class="text-xs">
                {{
                    __(
                        'Indeks pemulihan fisik berbasis rotasi kerja 4 minggu terakhir.',
                    )
                }}
            </CardDescription>
        </CardHeader>

        <CardContent class="flex flex-col gap-4">
            <!-- Gauge + Key Indicators Row -->
            <div
                class="flex flex-col items-center justify-around gap-6 sm:flex-row"
            >
                <!-- SVG Circular Progress Gauge -->
                <div
                    class="relative flex size-36 shrink-0 items-center justify-center"
                    data-test="gauge-svg-container"
                >
                    <svg
                        class="size-full -rotate-90 transform"
                        viewBox="0 0 108 108"
                    >
                        <!-- Background Circle Track -->
                        <circle
                            cx="54"
                            cy="54"
                            :r="normalizedRadius"
                            fill="transparent"
                            stroke="currentColor"
                            class="text-slate-100 dark:text-slate-800"
                            :stroke-width="strokeWidth"
                        />
                        <!-- Animated Value Circle -->
                        <circle
                            cx="54"
                            cy="54"
                            :r="normalizedRadius"
                            fill="transparent"
                            :stroke="statusColor.stroke"
                            :stroke-width="strokeWidth"
                            stroke-linecap="round"
                            class="transition-all duration-700 ease-out"
                            :stroke-dasharray="`${circumference} ${circumference}`"
                            :style="{ strokeDashoffset: strokeDashoffset }"
                        />
                    </svg>

                    <!-- Center Metric Text -->
                    <div
                        class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center"
                    >
                        <span
                            class="font-mono text-2xl font-black tracking-tight tabular-nums"
                            :class="statusColor.text"
                            data-test="safety-score-value"
                        >
                            {{ safetyScorePct.toFixed(0) }}%
                        </span>
                        <span
                            class="text-muted-foreground font-sans text-[10px] font-medium"
                        >
                            {{ __('Skor Aman') }}
                        </span>
                    </div>
                </div>

                <!-- Welfare Soft Metrics Grid -->
                <div class="grid w-full flex-1 grid-cols-2 gap-2.5 text-xs">
                    <!-- Current Week Hours -->
                    <div
                        class="border-border/60 bg-muted/20 flex flex-col justify-between rounded-lg border p-2.5"
                    >
                        <span class="text-muted-foreground text-[11px]">{{
                            __('Jam Minggu Ini')
                        }}</span>
                        <div class="mt-1 flex items-baseline gap-1">
                            <span
                                class="font-mono text-base font-bold tabular-nums"
                                :class="[
                                    currentWeekHours > weeklyLimit
                                        ? 'text-amber-600 dark:text-amber-400'
                                        : 'text-foreground',
                                ]"
                                data-test="welfare-current-week-hours"
                            >
                                {{ currentWeekHours.toFixed(1) }}
                            </span>
                            <span
                                class="text-muted-foreground font-mono text-[11px] tabular-nums"
                            >
                                / {{ weeklyLimit.toFixed(1) }}h
                            </span>
                        </div>
                    </div>

                    <!-- Consecutive Overloaded Weeks Streak -->
                    <div
                        class="border-border/60 bg-muted/20 flex flex-col justify-between rounded-lg border p-2.5"
                    >
                        <span class="text-muted-foreground text-[11px]">{{
                            __('Streak Berturut-turut')
                        }}</span>
                        <div class="mt-1 flex items-baseline gap-1">
                            <span
                                class="font-mono text-base font-bold tabular-nums"
                                :class="[
                                    consecutiveWeeks >= consecutiveWeeksAlert
                                        ? 'text-[#cc0000] dark:text-red-400'
                                        : consecutiveWeeks > 0
                                          ? 'text-amber-600 dark:text-amber-400'
                                          : 'text-foreground',
                                ]"
                                data-test="welfare-consecutive-weeks"
                            >
                                {{ consecutiveWeeks }}
                            </span>
                            <span
                                class="text-muted-foreground font-sans text-[11px]"
                            >
                                {{ __('minggu') }}
                            </span>
                        </div>
                    </div>

                    <!-- Overloaded Weeks in Past 4 Weeks -->
                    <div
                        class="border-border/60 bg-muted/20 flex flex-col justify-between rounded-lg border p-2.5"
                    >
                        <span class="text-muted-foreground text-[11px]">{{
                            __('Overload (4 Minggu)')
                        }}</span>
                        <div class="mt-1 flex items-baseline gap-1">
                            <span
                                class="font-mono text-base font-bold tabular-nums"
                                :class="[
                                    exceededWeeksCount > 0
                                        ? 'text-amber-600 dark:text-amber-400'
                                        : 'text-foreground',
                                ]"
                                data-test="welfare-exceeded-weeks-count"
                            >
                                {{ exceededWeeksCount }}
                            </span>
                            <span
                                class="text-muted-foreground font-mono text-[11px] tabular-nums"
                            >
                                / 4 {{ __('minggu') }}
                            </span>
                        </div>
                    </div>

                    <!-- Consecutive Threshold Target -->
                    <div
                        class="border-border/60 bg-muted/20 flex flex-col justify-between rounded-lg border p-2.5"
                    >
                        <span class="text-muted-foreground text-[11px]">{{
                            __('Batas Peringatan')
                        }}</span>
                        <div class="mt-1 flex items-baseline gap-1">
                            <span
                                class="text-foreground font-mono text-base font-bold tabular-nums"
                            >
                                {{ consecutiveWeeksAlert }}
                            </span>
                            <span
                                class="text-muted-foreground font-sans text-[11px]"
                            >
                                {{ __('minggu berturut') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prominent Dynamic Welfare Badges -->
            <div
                v-if="badges.length > 0"
                class="flex flex-col gap-2"
                data-test="welfare-badges-list"
            >
                <div
                    v-for="(badge, idx) in badges"
                    :key="idx"
                    class="flex items-center gap-2.5 rounded-lg border p-3 text-xs"
                    :class="[
                        badge.type === 'danger'
                            ? 'border-red-300 bg-red-50 text-[#cc0000] dark:border-red-900 dark:bg-red-950/50 dark:text-red-300'
                            : badge.type === 'warning'
                              ? 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-300'
                              : 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
                    ]"
                    data-test="welfare-alert-badge"
                >
                    <ShieldAlert
                        v-if="badge.type === 'danger'"
                        class="size-4 shrink-0"
                    />
                    <AlertTriangle
                        v-else-if="badge.type === 'warning'"
                        class="size-4 shrink-0"
                    />
                    <ShieldCheck v-else class="size-4 shrink-0" />

                    <div class="flex-1 font-medium">
                        {{ badge.message }}
                    </div>
                </div>
            </div>

            <!-- Industrial Advisory Disclaimer Banner (Zero Operational Roadblocks) -->
            <div
                class="border-border/80 bg-muted/30 flex items-start gap-2 rounded-lg border border-dashed p-3 text-[11px]"
                data-test="welfare-advisory-disclaimer"
            >
                <Info class="text-muted-foreground mt-0.5 size-3.5 shrink-0" />
                <p class="text-muted-foreground leading-relaxed">
                    <strong class="text-foreground font-semibold">{{
                        __('Peringatan Anjuran (Advisory):')
                    }}</strong>
                    {{
                        advisoryMessage ||
                        __(
                            'Indikator ini bersifat anjuran keselamatan untuk pencegahan kelelahan kerja dan tidak memblokir penugasan lembur darurat.',
                        )
                    }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
