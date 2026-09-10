<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    ShieldAlert,
    TrendingUp,
    UserX,
    Zap,
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

export interface RiskItem {
    id: string;
    type: string;
    severity: 'critical' | 'warning' | 'info';
    severity_label: string;
    title: string;
    description: string;
    details_url: string;
    details_label: string;
    meta?: Record<string, unknown>;
}

export interface RiskIndicatorsData {
    critical_count: number;
    warning_count: number;
    info_count: number;
    total_risks: number;
    all_normal: boolean;
    items: RiskItem[];
}

interface Props {
    data?: RiskIndicatorsData;
    loading?: boolean;
}

withDefaults(defineProps<Props>(), {
    data: () => ({
        critical_count: 0,
        warning_count: 0,
        info_count: 0,
        total_risks: 0,
        all_normal: true,
        items: [],
    }),
    loading: false,
});

const { __ } = useTrans();

function getRiskIcon(type: string) {
    switch (type) {
        case 'budget_overrun':
            return TrendingUp;
        case 'employee_burnout':
            return UserX;
        case 'efficiency_drop':
            return Zap;
        case 'spkl_overdue':
            return Clock;
        default:
            return AlertTriangle;
    }
}
</script>

<template>
    <div data-test="risk-indicators-panel">
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader
                class="flex flex-col gap-2 pb-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <ShieldAlert class="size-5 text-[#cc0000]" />
                        <span>{{
                            __('Indikator Risiko & Peringatan Otomatis')
                        }}</span>
                    </CardTitle>
                    <CardDescription>
                        {{
                            __(
                                'Sistem mengevaluasi deviasi anggaran, kelelahan karyawan, dan anomali lonjakan lembur secara real-time.',
                            )
                        }}
                    </CardDescription>
                </div>

                <div
                    v-if="!data.all_normal && data.total_risks > 0"
                    class="flex flex-wrap items-center gap-2"
                    data-test="risk-count-badge"
                >
                    <Badge
                        v-if="data.critical_count > 0"
                        variant="destructive"
                        class="border-red-200 bg-red-50 text-[#cc0000] dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                    >
                        {{ data.critical_count }} {{ __('Kritis') }}
                    </Badge>
                    <Badge
                        v-if="data.warning_count > 0"
                        variant="secondary"
                        class="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
                    >
                        {{ data.warning_count }} {{ __('Peringatan') }}
                    </Badge>
                    <Badge
                        v-if="data.info_count > 0"
                        variant="outline"
                        class="border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300"
                    >
                        {{ data.info_count }} {{ __('Info') }}
                    </Badge>
                </div>
            </CardHeader>

            <CardContent>
                <!-- All Normal State -->
                <div
                    v-if="data.all_normal || data.items.length === 0"
                    class="flex flex-col items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50/50 p-6 text-center dark:border-emerald-900/50 dark:bg-emerald-950/20"
                    data-test="all-normal-card"
                >
                    <CheckCircle2
                        class="mb-2 size-8 text-emerald-600 dark:text-emerald-400"
                    />
                    <h4
                        class="text-sm font-semibold text-emerald-900 dark:text-emerald-200"
                    >
                        {{ __('Semua metrik dalam batas normal') }}
                    </h4>
                    <p
                        class="mt-1 max-w-md text-xs text-emerald-700 dark:text-emerald-300"
                    >
                        {{
                            __(
                                'Tidak ada deviasi anggaran kritis, kelelahan karyawan berlebih, atau anomali operasional yang memerlukan tindakan mitigasi mendesak.',
                            )
                        }}
                    </p>
                </div>

                <!-- Active Risks List -->
                <div v-else class="space-y-3" data-test="risk-items-list">
                    <div
                        v-for="item in data.items"
                        :key="item.id"
                        class="flex flex-col justify-between gap-3 rounded-lg border p-3.5 transition-all sm:flex-row sm:items-center"
                        :class="
                            item.severity === 'critical'
                                ? 'border-red-200 bg-red-50/40 dark:border-red-900/40 dark:bg-red-950/20'
                                : item.severity === 'warning'
                                  ? 'border-amber-200 bg-amber-50/40 dark:border-amber-900/40 dark:bg-amber-950/20'
                                  : 'border-slate-200 bg-slate-50/40 dark:border-slate-800 dark:bg-slate-900/40'
                        "
                        data-test="risk-item"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="mt-0.5 rounded-md p-1.5"
                                :class="
                                    item.severity === 'critical'
                                        ? 'bg-red-100 text-[#cc0000] dark:bg-red-900/50 dark:text-red-300'
                                        : item.severity === 'warning'
                                          ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300'
                                          : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                "
                            >
                                <component
                                    :is="getRiskIcon(item.type)"
                                    class="size-4.5"
                                />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4
                                        class="text-xs font-bold text-slate-900 dark:text-white"
                                    >
                                        {{ item.title }}
                                    </h4>
                                    <Badge
                                        :variant="
                                            item.severity === 'critical'
                                                ? 'destructive'
                                                : item.severity === 'warning'
                                                  ? 'secondary'
                                                  : 'outline'
                                        "
                                        class="px-1.5 py-0 text-[10px] font-bold uppercase"
                                        :class="
                                            item.severity === 'critical'
                                                ? 'border-red-200 bg-red-50 text-[#cc0000] dark:border-red-900 dark:bg-red-950/40 dark:text-red-300'
                                                : item.severity === 'warning'
                                                  ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300'
                                                  : 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                        "
                                    >
                                        {{ item.severity_label }}
                                    </Badge>
                                </div>
                                <p
                                    class="mt-1 text-xs text-slate-600 dark:text-slate-300"
                                >
                                    {{ item.description }}
                                </p>
                            </div>
                        </div>

                        <div class="shrink-0 self-end sm:self-center">
                            <Link
                                v-if="item.details_url.startsWith('/')"
                                :href="item.details_url"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-[#cc0000] hover:underline dark:text-red-400"
                                data-test="risk-item-link"
                            >
                                <span>{{ item.details_label }}</span>
                            </Link>
                            <a
                                v-else
                                :href="item.details_url"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-[#cc0000] hover:underline dark:text-red-400"
                                data-test="risk-item-anchor"
                            >
                                <span>{{ item.details_label }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
