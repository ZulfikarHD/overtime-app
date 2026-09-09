<script setup lang="ts">
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    Building2,
    CheckCircle2,
    Flame,
    Layers,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

export interface DepartmentSummaryItem {
    id: number;
    code: string;
    name: string;
    total_planned_hours: number;
    total_actual_hours: number;
    total_remaining_hours: number;
    department_burn_index_pct: number;
    department_burn_zone: string;
    warning_sections_count: number;
    danger_sections_count: number;
    configured_sections_count: number;
    total_sections_count: number;
}

defineProps<{
    departmentsSummary: DepartmentSummaryItem[];
}>();

const emit = defineEmits<{
    (e: 'selectDepartment', departmentId: number): void;
}>();

const { __ } = useTrans();

function getBurnStatusClass(pct: number): string {
    if (pct > 115) {
        return 'text-[#cc0000] bg-red-50 dark:bg-red-950/40 border-red-200 dark:border-red-900';
    }
    if (pct > 100) {
        return 'text-amber-700 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-900';
    }
    if (pct >= 85) {
        return 'text-sky-700 bg-sky-50 dark:bg-sky-950/40 border-sky-200 dark:border-sky-900';
    }
    return 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-900';
}

function getZoneBadge(zone: string): { label: string; class: string } {
    switch (zone) {
        case 'ZONE_1_EXCELLENT':
            return {
                label: __('Zona 1: Sangat Baik'),
                class: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800',
            };
        case 'ZONE_2_GOOD':
            return {
                label: __('Zona 2: Baik'),
                class: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/50 dark:text-sky-300 dark:border-sky-800',
            };
        case 'ZONE_3_WARNING':
            return {
                label: __('Zona 3: Peringatan'),
                class: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800',
            };
        case 'ZONE_4_POOR':
        default:
            return {
                label: __('Zona 4: Defisit'),
                class: 'bg-red-50 text-[#cc0000] border-red-200 dark:bg-red-950/50 dark:text-red-300 dark:border-red-800',
            };
    }
}
</script>

<template>
    <div class="space-y-3" data-test="cross-department-comparison-container">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <Building2 class="size-4 text-slate-500" />
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                    {{ __('Konsolidasi Lintas Departemen') }}
                </h3>
            </div>
            <span class="text-muted-foreground text-xs">
                {{ departmentsSummary.length }} {{ __('departemen aktif') }}
            </span>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="dept in departmentsSummary"
                :key="dept.id"
                class="shadow-2xs transition-shadow hover:shadow-md"
                :class="
                    dept.danger_sections_count > 0
                        ? 'border-red-300 dark:border-red-900/60'
                        : ''
                "
                :data-test="`dept-card-${dept.code}`"
            >
                <CardHeader class="p-4 pb-2">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <CardTitle
                                class="text-sm font-bold text-slate-900 dark:text-white"
                            >
                                {{ dept.name }}
                            </CardTitle>
                            <span
                                class="text-muted-foreground font-mono text-xs font-semibold"
                            >
                                {{ dept.code }}
                            </span>
                        </div>
                        <Badge
                            variant="outline"
                            class="px-2 py-0.5 text-[10px] font-semibold"
                            :class="
                                getZoneBadge(dept.department_burn_zone).class
                            "
                        >
                            {{ getZoneBadge(dept.department_burn_zone).label }}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent class="space-y-3 p-4 pt-2 text-xs">
                    <!-- Burn Index & Progress -->
                    <div class="flex items-baseline justify-between">
                        <div class="flex items-center gap-1.5">
                            <Flame class="size-3.5 text-slate-400" />
                            <span class="text-muted-foreground">{{
                                __('Indeks Burn')
                            }}</span>
                        </div>
                        <Badge
                            variant="outline"
                            class="font-mono text-xs font-bold tabular-nums"
                            :class="
                                getBurnStatusClass(
                                    dept.department_burn_index_pct,
                                )
                            "
                        >
                            {{ dept.department_burn_index_pct.toFixed(1) }}%
                        </Badge>
                    </div>

                    <!-- Hours Allocation vs Actual -->
                    <div class="space-y-1">
                        <div
                            class="text-muted-foreground flex items-center justify-between"
                        >
                            <span class="flex items-center gap-1">
                                <Layers class="size-3" />
                                {{ __('Realisasi / Anggaran') }}
                            </span>
                            <span
                                class="font-mono font-semibold text-slate-900 tabular-nums dark:text-white"
                            >
                                {{ dept.total_actual_hours.toFixed(1) }} /
                                {{ dept.total_planned_hours.toFixed(1) }}
                                {{ __('jam') }}
                            </span>
                        </div>
                        <div
                            class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                        >
                            <div
                                class="h-full rounded-full transition-all"
                                :class="
                                    dept.department_burn_index_pct > 115
                                        ? 'bg-[#cc0000]'
                                        : dept.department_burn_index_pct > 100
                                          ? 'bg-amber-500'
                                          : dept.department_burn_index_pct >= 85
                                            ? 'bg-sky-500'
                                            : 'bg-emerald-500'
                                "
                                :style="{
                                    width: `${Math.min(100, Math.max(0, dept.department_burn_index_pct))}%`,
                                }"
                            />
                        </div>
                        <div
                            class="text-muted-foreground flex justify-between text-[11px]"
                        >
                            <span>{{ __('Sisa kuota') }}:</span>
                            <span
                                class="font-mono font-semibold tabular-nums"
                                :class="
                                    dept.total_remaining_hours < 0
                                        ? 'text-[#cc0000]'
                                        : 'text-slate-700 dark:text-slate-300'
                                "
                            >
                                {{ dept.total_remaining_hours.toFixed(1) }}
                                {{ __('jam') }}
                            </span>
                        </div>
                    </div>

                    <!-- Risk Sections Counter -->
                    <div
                        class="flex items-center justify-between rounded-md bg-slate-50 p-2 dark:bg-slate-900/60"
                    >
                        <span
                            class="text-muted-foreground flex items-center gap-1 text-[11px]"
                        >
                            <AlertTriangle class="size-3" />
                            {{ __('Status Seksi') }}
                        </span>
                        <div
                            class="flex items-center gap-2 font-mono text-[11px]"
                        >
                            <span
                                v-if="dept.danger_sections_count > 0"
                                class="font-bold text-[#cc0000] tabular-nums"
                            >
                                {{ dept.danger_sections_count }}
                                {{ __('Defisit') }}
                            </span>
                            <span
                                v-if="dept.warning_sections_count > 0"
                                class="font-bold text-amber-600 tabular-nums"
                            >
                                {{ dept.warning_sections_count }}
                                {{ __('Peringatan') }}
                            </span>
                            <span
                                class="font-semibold text-emerald-600 tabular-nums"
                            >
                                {{
                                    Math.max(
                                        0,
                                        dept.total_sections_count -
                                            dept.warning_sections_count -
                                            dept.danger_sections_count,
                                    )
                                }}
                                {{ __('Aman') }}
                            </span>
                        </div>
                    </div>

                    <!-- Action: Drill Down to Department -->
                    <Button
                        variant="ghost"
                        size="sm"
                        class="w-full cursor-pointer justify-between text-xs text-slate-700 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
                        @click="emit('selectDepartment', dept.id)"
                        :data-test="`btn-filter-dept-${dept.id}`"
                    >
                        <span>{{ __('Tampilkan Detail Seksi') }}</span>
                        <ArrowRight class="size-3.5" />
                    </Button>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
