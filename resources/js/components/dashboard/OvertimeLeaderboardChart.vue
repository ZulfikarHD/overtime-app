<script setup lang="ts">
import { Award, Crown, Medal, ShieldAlert, Users } from '@lucide/vue';
import { computed } from 'vue';
import ChartSkeleton from '@/components/charts/ChartSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

export interface LeaderboardItem {
    employee_id: number;
    npk: string;
    name: string;
    full_name: string;
    section_code: string;
    total_hours: number;
    soft_limit_hours: number;
    percentage_of_limit: number;
    zone: 'safe' | 'warning' | 'danger';
    zone_color: string;
}

export interface LeaderboardData {
    items: LeaderboardItem[];
    fiscal_year: number;
    fiscal_month: number;
    month_name: string;
    soft_limit_hours: number;
    scope: {
        department_id: number | null;
        section_id: number | null;
    };
}

interface Props {
    data?: LeaderboardData;
    loading?: boolean;
}

interface PodiumSlot {
    rank: 1 | 2 | 3;
    item: LeaderboardItem;
    barHeightClass: string;
    barClass: string;
    badgeClass: string;
    rankLabelClass: string;
}

const props = withDefaults(defineProps<Props>(), {
    data: undefined,
    loading: false,
});

const { __ } = useTrans();

const softLimit = computed(() => props.data?.soft_limit_hours ?? 80);

const hasOverLimitEmployees = computed(() => {
    return props.data?.items?.some((i) => i.zone !== 'safe') ?? false;
});

const isEmpty = computed(() => !props.data?.items?.length);

const topThree = computed(() => props.data?.items?.slice(0, 3) ?? []);

const restRanks = computed(() => props.data?.items?.slice(3, 10) ?? []);

/**
 * Kahoot-style podium order: 2nd | 1st | 3rd (champion centered).
 */
const podiumSlots = computed<PodiumSlot[]>(() => {
    const slots: PodiumSlot[] = [];
    const configs: Array<{
        rank: 1 | 2 | 3;
        barHeightClass: string;
        barClass: string;
        badgeClass: string;
        rankLabelClass: string;
    }> = [
        {
            rank: 2,
            barHeightClass: 'h-28 sm:h-32',
            barClass:
                'bg-slate-300 text-slate-800 dark:bg-slate-500 dark:text-slate-50',
            badgeClass:
                'bg-slate-200 text-slate-700 ring-slate-400/40 dark:bg-slate-600 dark:text-slate-100',
            rankLabelClass: 'text-slate-600 dark:text-slate-300',
        },
        {
            rank: 1,
            barHeightClass: 'h-40 sm:h-44',
            barClass:
                'bg-amber-400 text-amber-950 dark:bg-amber-500 dark:text-amber-950',
            badgeClass:
                'bg-amber-300 text-amber-950 ring-amber-500/50 dark:bg-amber-400',
            rankLabelClass: 'text-amber-600 dark:text-amber-400',
        },
        {
            rank: 3,
            barHeightClass: 'h-20 sm:h-24',
            barClass:
                'bg-orange-700/80 text-orange-50 dark:bg-orange-800 dark:text-orange-50',
            badgeClass:
                'bg-orange-100 text-orange-900 ring-orange-600/30 dark:bg-orange-900 dark:text-orange-100',
            rankLabelClass: 'text-orange-700 dark:text-orange-400',
        },
    ];

    for (const config of configs) {
        const item = topThree.value[config.rank - 1];
        if (!item) {
            continue;
        }
        slots.push({ ...config, item });
    }

    return slots;
});

function formatHours(hours: number): string {
    return `${hours.toFixed(1)} ${__('jam')}`;
}
</script>

<template>
    <Card
        class="border-border/70 flex flex-col shadow-2xs"
        data-test="overtime-leaderboard-card"
    >
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100"
                    >
                        <Award class="size-4 text-amber-500" />
                        <span>{{
                            __('Peringkat Lembur Karyawan (Top 10)')
                        }}</span>
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{
                            __(
                                'Karyawan dengan jam lembur tertinggi pada :month',
                                { month: data?.month_name || '' },
                            )
                        }}
                    </CardDescription>
                </div>

                <Badge
                    v-if="hasOverLimitEmployees"
                    variant="outline"
                    class="flex items-center gap-1 border-amber-500/30 bg-amber-500/10 text-[11px] font-semibold text-amber-700 dark:text-amber-300"
                >
                    <ShieldAlert class="size-3" />
                    <span>{{ __('Melewati Batas Soft') }}</span>
                </Badge>
                <Badge
                    v-else
                    variant="outline"
                    class="flex items-center gap-1 border-emerald-500/30 bg-emerald-500/10 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300"
                >
                    <Users class="size-3" />
                    <span>{{ __('Dalam Batas Kebijakan') }}</span>
                </Badge>
            </div>
        </CardHeader>

        <CardContent class="space-y-3 pt-0">
            <div
                class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-1.5 text-xs text-slate-600 dark:bg-slate-900/50 dark:text-slate-400"
            >
                <span>{{ __('Batas Kebijakan Bulanan (Soft Limit):') }}</span>
                <span
                    class="font-mono font-bold text-slate-900 tabular-nums dark:text-slate-100"
                >
                    {{ softLimit }} {{ __('jam / bulan') }}
                </span>
            </div>

            <ChartSkeleton v-if="loading" height-class="h-72" variant="bar" />

            <div
                v-else-if="isEmpty"
                class="flex h-72 w-full flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 p-6 text-center dark:border-slate-800"
            >
                <p
                    class="text-sm font-medium text-slate-500 dark:text-slate-400"
                >
                    {{
                        __(
                            'Belum ada data lembur yang disetujui pada periode ini',
                        )
                    }}
                </p>
            </div>

            <div
                v-else
                class="space-y-4"
                data-test="overtime-leaderboard-podium"
            >
                <!-- Kahoot podium: #2 | #1 | #3 -->
                <div
                    class="flex items-end justify-center gap-2 sm:gap-3"
                    data-test="overtime-leaderboard-top3"
                >
                    <div
                        v-for="slot in podiumSlots"
                        :key="slot.rank"
                        class="flex w-full max-w-30 flex-col items-center sm:max-w-36"
                        :data-test="`overtime-leaderboard-rank-${slot.rank}`"
                    >
                        <div
                            class="mb-2 flex flex-col items-center gap-1 px-1 text-center"
                        >
                            <div
                                class="flex size-8 items-center justify-center rounded-full ring-2 sm:size-9"
                                :class="slot.badgeClass"
                            >
                                <Crown
                                    v-if="slot.rank === 1"
                                    class="size-4 text-amber-800 dark:text-amber-950"
                                />
                                <Medal
                                    v-else
                                    class="size-4"
                                    :class="slot.rankLabelClass"
                                />
                            </div>
                            <p
                                class="line-clamp-2 max-w-full text-[11px] leading-tight font-bold text-slate-900 sm:text-xs dark:text-slate-100"
                                :title="slot.item.full_name"
                            >
                                {{ slot.item.name }}
                            </p>
                            <p
                                class="font-mono text-[10px] text-slate-500 tabular-nums dark:text-slate-400"
                            >
                                {{ slot.item.section_code }}
                            </p>
                        </div>

                        <div
                            class="flex w-full flex-col items-center justify-start rounded-t-lg pt-3 shadow-xs transition-all"
                            :class="[slot.barHeightClass, slot.barClass]"
                        >
                            <span
                                class="text-2xl font-black tabular-nums sm:text-3xl"
                                :class="slot.rank === 1 ? 'scale-110' : ''"
                            >
                                {{ slot.rank }}
                            </span>
                            <span
                                class="mt-1 font-mono text-[11px] font-bold tabular-nums opacity-90 sm:text-xs"
                            >
                                {{ formatHours(slot.item.total_hours) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Ranks 4–10: name + number only -->
                <ol
                    v-if="restRanks.length"
                    class="divide-y divide-slate-100 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-800"
                    data-test="overtime-leaderboard-rest"
                >
                    <li
                        v-for="(item, index) in restRanks"
                        :key="item.employee_id"
                        class="flex items-center gap-3 px-3 py-2 text-sm"
                        :data-test="`overtime-leaderboard-rank-${index + 4}`"
                    >
                        <span
                            class="w-5 shrink-0 font-mono text-xs font-bold text-slate-400 tabular-nums dark:text-slate-500"
                        >
                            {{ index + 4 }}
                        </span>
                        <span
                            class="min-w-0 flex-1 truncate font-medium text-slate-800 dark:text-slate-200"
                            :title="item.full_name"
                        >
                            {{ item.name }}
                        </span>
                        <span
                            class="shrink-0 font-mono text-xs font-semibold text-slate-600 tabular-nums dark:text-slate-300"
                        >
                            {{ formatHours(item.total_hours) }}
                        </span>
                    </li>
                </ol>
            </div>
        </CardContent>
    </Card>
</template>
