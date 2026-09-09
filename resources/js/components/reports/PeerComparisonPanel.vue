<script setup lang="ts">
import {
    AlertCircle,
    ArrowDownRight,
    ArrowUpRight,
    Award,
    BarChart3,
    Clock,
    Minus,
    Shield,
    TrendingDown,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import SectionDistributionChart, {
    type PeerDistributionItem,
} from '@/components/reports/SectionDistributionChart.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

export interface PeerComparisonData {
    has_section: boolean;
    section_id: number | null;
    section_name: string | null;
    section_code: string | null;
    total_section_employees: number;
    section_total_hours: number;
    section_average_hours: number;
    individual_hours: number;
    variance_hours: number;
    variance_status: 'above' | 'below' | 'equal';
    is_anonymized: boolean;
    distribution: PeerDistributionItem[];
    top_5: PeerDistributionItem[];
    bottom_5: PeerDistributionItem[];
}

const props = defineProps<{
    peerComparison: PeerComparisonData;
}>();

const { __ } = useTrans();

const varianceBadgeClass = computed(() => {
    switch (props.peerComparison.variance_status) {
        case 'above':
            return 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800';
        case 'below':
            return 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800';
        case 'equal':
        default:
            return 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
    }
});

const varianceText = computed(() => {
    const v = props.peerComparison.variance_hours;
    if (v > 0) {
        return `+${v.toFixed(1)} ${__('jam di atas rata-rata seksi')}`;
    }
    if (v < 0) {
        return `${v.toFixed(1)} ${__('jam di bawah rata-rata seksi')}`;
    }
    return `0.0 ${__('jam sama dengan rata-rata seksi')}`;
});

const ratioToAverage = computed(() => {
    const avg = props.peerComparison.section_average_hours;
    const ind = props.peerComparison.individual_hours;
    if (avg <= 0) return ind > 0 ? 100 : 0;
    return Math.min(200, Math.round((ind / avg) * 100));
});
</script>

<template>
    <div class="flex flex-col gap-6" data-test="peer-comparison-panel">
        <!-- Main Peer Benchmarking Card -->
        <Card class="border-border shadow-xs">
            <CardHeader class="pb-3">
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-8 items-center justify-center rounded-lg bg-red-50 text-[#cc0000] dark:bg-red-950/60 dark:text-red-300"
                        >
                            <Users class="size-4" />
                        </div>
                        <div>
                            <CardTitle class="text-base font-semibold">
                                {{
                                    __(
                                        'Peer Benchmarking & Distribusi Beban Seksi',
                                    )
                                }}
                            </CardTitle>
                            <CardDescription class="text-xs">
                                {{
                                    __(
                                        'Perbandingan beban kerja terhadap rata-rata seksi (CALC-06) dengan histogram distribusi dan proteksi privasi.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <!-- Header Badges -->
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            v-if="peerComparison.is_anonymized"
                            variant="outline"
                            class="border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-300"
                            data-test="privacy-badge"
                        >
                            <Shield class="mr-1 size-3 text-sky-600" />
                            {{ __('Mode Privasi Operator') }}
                        </Badge>

                        <Badge
                            v-if="peerComparison.section_name"
                            variant="outline"
                            class="border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                        >
                            {{ peerComparison.section_name }}
                            <span
                                v-if="peerComparison.section_code"
                                class="ml-1 font-mono text-[10px] text-slate-500"
                            >
                                ({{ peerComparison.section_code }})
                            </span>
                        </Badge>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="flex flex-col gap-6">
                <!-- If Employee has no assigned section -->
                <div
                    v-if="!peerComparison.has_section"
                    class="border-border bg-muted/20 flex flex-col items-center justify-center rounded-lg border border-dashed py-8 text-center"
                    data-test="peer-no-section"
                >
                    <AlertCircle class="text-muted-foreground size-8" />
                    <p class="text-foreground mt-2 text-sm font-medium">
                        {{
                            __('Karyawan belum memiliki penugasan seksi kerja.')
                        }}
                    </p>
                    <p class="text-muted-foreground mt-0.5 text-xs">
                        {{
                            __(
                                'Data peer benchmarking hanya dapat dihitung untuk karyawan yang terdaftar dalam seksi operasional aktif.',
                            )
                        }}
                    </p>
                </div>

                <!-- Active Section Data -->
                <template v-else>
                    <!-- 3 Metric Stat Blocks Row -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <!-- Stat 1: Individual Hours -->
                        <div
                            class="border-border/70 bg-card flex flex-col justify-between rounded-lg border p-4 shadow-2xs"
                            data-test="peer-individual-hours"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-muted-foreground text-xs font-medium"
                                >
                                    {{ __('Jam Lembur Karyawan') }}
                                </span>
                                <span
                                    class="size-2 rounded-full bg-[#cc0000]"
                                ></span>
                            </div>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span
                                    class="font-mono text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white"
                                >
                                    {{
                                        peerComparison.individual_hours.toFixed(
                                            1,
                                        )
                                    }}
                                </span>
                                <span
                                    class="text-muted-foreground font-mono text-xs"
                                >
                                    {{ __('jam') }}
                                </span>
                            </div>
                            <div class="text-muted-foreground mt-1 text-[11px]">
                                {{ __('Bulan ini (Disetujui)') }}
                            </div>
                        </div>

                        <!-- Stat 2: Section Average -->
                        <div
                            class="border-border/70 bg-card flex flex-col justify-between rounded-lg border p-4 shadow-2xs"
                            data-test="peer-section-average"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-muted-foreground text-xs font-medium"
                                >
                                    {{ __('Rata-rata Seksi') }}
                                </span>
                                <span
                                    class="size-2 rounded-full bg-slate-400"
                                ></span>
                            </div>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span
                                    class="font-mono text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white"
                                >
                                    {{
                                        peerComparison.section_average_hours.toFixed(
                                            1,
                                        )
                                    }}
                                </span>
                                <span
                                    class="text-muted-foreground font-mono text-xs"
                                >
                                    {{ __('jam') }}
                                </span>
                            </div>
                            <div
                                class="text-muted-foreground mt-1 flex items-center gap-1 text-[11px]"
                            >
                                <span>{{ __('Dari total') }}</span>
                                <span class="font-mono font-semibold"
                                    >{{
                                        peerComparison.total_section_employees
                                    }}
                                    {{ __('karyawan') }}</span
                                >
                            </div>
                        </div>

                        <!-- Stat 3: CALC-06 Variance Pill -->
                        <div
                            class="border-border/70 bg-card flex flex-col justify-between rounded-lg border p-4 shadow-2xs"
                            data-test="peer-variance-card"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-muted-foreground text-xs font-medium"
                                >
                                    {{ __('Deviasi Beban Kerja (CALC-06)') }}
                                </span>
                                <TrendingUp
                                    v-if="
                                        peerComparison.variance_status ===
                                        'above'
                                    "
                                    class="size-4 text-amber-600 dark:text-amber-400"
                                />
                                <TrendingDown
                                    v-else-if="
                                        peerComparison.variance_status ===
                                        'below'
                                    "
                                    class="size-4 text-emerald-600 dark:text-emerald-400"
                                />
                                <Minus v-else class="size-4 text-slate-400" />
                            </div>

                            <div class="mt-2 flex items-baseline gap-1.5">
                                <Badge
                                    variant="outline"
                                    :class="[
                                        'font-mono text-xs font-semibold tabular-nums',
                                        varianceBadgeClass,
                                    ]"
                                    data-test="peer-variance-badge"
                                >
                                    {{ varianceText }}
                                </Badge>
                            </div>

                            <div class="text-muted-foreground mt-1 text-[11px]">
                                <span
                                    v-if="
                                        peerComparison.variance_status ===
                                        'above'
                                    "
                                >
                                    {{
                                        __(
                                            'Beban lembur lebih tinggi dari rekan seksi.',
                                        )
                                    }}
                                </span>
                                <span
                                    v-else-if="
                                        peerComparison.variance_status ===
                                        'below'
                                    "
                                >
                                    {{
                                        __(
                                            'Beban lembur lebih rendah dari rata-rata seksi.',
                                        )
                                    }}
                                </span>
                                <span v-else>
                                    {{
                                        __(
                                            'Beban lembur setara rata-rata seksi.',
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Privacy Notice Banner for Operator Role -->
                    <div
                        v-if="peerComparison.is_anonymized"
                        class="flex items-start gap-3 rounded-lg border border-sky-200 bg-sky-50/60 p-3 text-xs text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-200"
                        data-test="operator-privacy-notice"
                    >
                        <Shield class="mt-0.5 size-4 shrink-0 text-sky-600" />
                        <div>
                            <span class="font-semibold">{{
                                __('Mode Privasi Operator Aktif:')
                            }}</span>
                            <span class="ml-1">{{
                                __(
                                    'Data rekan kerja disamarkan untuk menjaga kerahasiaan antar-operator.',
                                )
                            }}</span>
                        </div>
                    </div>

                    <!-- Histogram Chart Component -->
                    <div
                        class="border-border bg-card rounded-xl border p-4 shadow-2xs"
                    >
                        <div class="mb-3">
                            <h4
                                class="text-sm font-semibold text-slate-900 dark:text-white"
                            >
                                {{ __('Distribusi Jam Lembur Anggota Seksi') }}
                            </h4>
                            <p class="text-muted-foreground text-xs">
                                {{
                                    __(
                                        'Grafik persebaran jam lembur seluruh karyawan di seksi bulan ini. Karyawan aktif ditandai dengan warna merah ISUZU.',
                                    )
                                }}
                            </p>
                        </div>

                        <SectionDistributionChart
                            :distribution="peerComparison.distribution"
                            :section-average-hours="
                                peerComparison.section_average_hours
                            "
                            :is-anonymized="peerComparison.is_anonymized"
                        />
                    </div>

                    <!-- Top 5 & Bottom 5 Workload Lists -->
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <!-- Top 5 Highest Hours -->
                        <div
                            class="border-border bg-card flex flex-col rounded-xl border p-4 shadow-2xs"
                            data-test="top-5-list"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex size-6 items-center justify-center rounded-md bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
                                    >
                                        <ArrowUpRight class="size-3.5" />
                                    </div>
                                    <h4
                                        class="text-xs font-bold tracking-tight text-slate-800 uppercase dark:text-slate-200"
                                    >
                                        {{ __('5 Jam Tertinggi (Top 5)') }}
                                    </h4>
                                </div>
                                <span class="text-muted-foreground text-[11px]">
                                    {{ __('Beban Paling Padat') }}
                                </span>
                            </div>

                            <div
                                v-if="peerComparison.top_5.length === 0"
                                class="text-muted-foreground py-6 text-center text-xs"
                            >
                                {{ __('Belum ada data lembur.') }}
                            </div>

                            <div
                                v-else
                                class="flex flex-col divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <div
                                    v-for="item in peerComparison.top_5"
                                    :key="`${item.rank}-${item.employee_id ?? item.name}`"
                                    :class="[
                                        'flex items-center justify-between rounded-md px-2 py-2.5 transition-colors',
                                        item.is_current_employee
                                            ? 'border border-red-200 bg-red-50/70 dark:border-red-900 dark:bg-red-950/40'
                                            : 'dark:hover:bg-slate-850 hover:bg-slate-50',
                                    ]"
                                    :data-test="`top-item-${item.rank}`"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            :class="[
                                                'flex size-5.5 items-center justify-center rounded-full font-mono text-[11px] font-bold',
                                                item.rank === 1
                                                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300'
                                                    : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                                            ]"
                                        >
                                            #{{ item.rank }}
                                        </span>
                                        <div class="flex flex-col">
                                            <span
                                                :class="[
                                                    'text-xs font-semibold',
                                                    item.is_current_employee
                                                        ? 'text-[#cc0000] dark:text-red-400'
                                                        : 'text-slate-800 dark:text-slate-200',
                                                ]"
                                            >
                                                {{ item.name }}
                                                <span
                                                    v-if="
                                                        item.is_current_employee
                                                    "
                                                    class="ml-1 text-[10px] font-bold tracking-wider text-[#cc0000] uppercase"
                                                >
                                                    ({{ __('Anda') }})
                                                </span>
                                            </span>
                                            <span
                                                class="font-mono text-[10px] text-slate-500"
                                            >
                                                {{ item.npk }}
                                                <span v-if="item.job_position">
                                                    • {{ item.job_position }}
                                                </span>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex items-baseline gap-2">
                                        <span
                                            class="font-mono text-sm font-bold text-slate-900 tabular-nums dark:text-white"
                                        >
                                            {{ item.hours.toFixed(1) }} jam
                                        </span>
                                        <span
                                            :class="[
                                                'font-mono text-[10px] tabular-nums',
                                                item.variance_hours > 0
                                                    ? 'text-amber-600 dark:text-amber-400'
                                                    : 'text-emerald-600 dark:text-emerald-400',
                                            ]"
                                        >
                                            {{
                                                item.variance_hours > 0
                                                    ? '+'
                                                    : ''
                                            }}{{
                                                item.variance_hours.toFixed(1)
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom 5 Lowest Hours -->
                        <div
                            class="border-border bg-card flex flex-col rounded-xl border p-4 shadow-2xs"
                            data-test="bottom-5-list"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex size-6 items-center justify-center rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
                                    >
                                        <ArrowDownRight class="size-3.5" />
                                    </div>
                                    <h4
                                        class="text-xs font-bold tracking-tight text-slate-800 uppercase dark:text-slate-200"
                                    >
                                        {{ __('5 Jam Terendah (Bottom 5)') }}
                                    </h4>
                                </div>
                                <span class="text-muted-foreground text-[11px]">
                                    {{ __('Beban Paling Ringan') }}
                                </span>
                            </div>

                            <div
                                v-if="peerComparison.bottom_5.length === 0"
                                class="text-muted-foreground py-6 text-center text-xs"
                            >
                                {{ __('Belum ada data lembur.') }}
                            </div>

                            <div
                                v-else
                                class="flex flex-col divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <div
                                    v-for="item in peerComparison.bottom_5"
                                    :key="`${item.rank}-${item.employee_id ?? item.name}`"
                                    :class="[
                                        'flex items-center justify-between rounded-md px-2 py-2.5 transition-colors',
                                        item.is_current_employee
                                            ? 'border border-red-200 bg-red-50/70 dark:border-red-900 dark:bg-red-950/40'
                                            : 'dark:hover:bg-slate-850 hover:bg-slate-50',
                                    ]"
                                    :data-test="`bottom-item-${item.rank}`"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            class="flex size-5.5 items-center justify-center rounded-full bg-slate-100 font-mono text-[11px] font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                        >
                                            #{{ item.rank }}
                                        </span>
                                        <div class="flex flex-col">
                                            <span
                                                :class="[
                                                    'text-xs font-semibold',
                                                    item.is_current_employee
                                                        ? 'text-[#cc0000] dark:text-red-400'
                                                        : 'text-slate-800 dark:text-slate-200',
                                                ]"
                                            >
                                                {{ item.name }}
                                                <span
                                                    v-if="
                                                        item.is_current_employee
                                                    "
                                                    class="ml-1 text-[10px] font-bold tracking-wider text-[#cc0000] uppercase"
                                                >
                                                    ({{ __('Anda') }})
                                                </span>
                                            </span>
                                            <span
                                                class="font-mono text-[10px] text-slate-500"
                                            >
                                                {{ item.npk }}
                                                <span v-if="item.job_position">
                                                    • {{ item.job_position }}
                                                </span>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex items-baseline gap-2">
                                        <span
                                            class="font-mono text-sm font-bold text-slate-900 tabular-nums dark:text-white"
                                        >
                                            {{ item.hours.toFixed(1) }} jam
                                        </span>
                                        <span
                                            :class="[
                                                'font-mono text-[10px] tabular-nums',
                                                item.variance_hours > 0
                                                    ? 'text-amber-600 dark:text-amber-400'
                                                    : 'text-emerald-600 dark:text-emerald-400',
                                            ]"
                                        >
                                            {{
                                                item.variance_hours > 0
                                                    ? '+'
                                                    : ''
                                            }}{{
                                                item.variance_hours.toFixed(1)
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </CardContent>
        </Card>
    </div>
</template>
