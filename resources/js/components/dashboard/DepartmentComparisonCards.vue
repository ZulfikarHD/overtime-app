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
import { useBurnZone } from '@/composables/useBurnZone';
import { useTrans } from '@/composables/useTrans';
import { BURN_ZONE_STYLES, normalizeBurnZone } from '@/lib/burnZone';
import type { BurnZone } from '@/lib/burnZone';

export interface DepartmentSummaryItem {
    id: number;
    code: string;
    name: string;
    total_planned_hours: number;
    total_actual_hours: number;
    total_remaining_hours: number;
    department_burn_index_pct: number;
    department_burn_zone: string;
    department_tolerance_zone?: 'green' | 'yellow' | 'red';
    green_sections_count?: number;
    yellow_sections_count?: number;
    red_sections_count?: number;
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
const { classify } = useBurnZone();

function zoneOf(dept: DepartmentSummaryItem): BurnZone {
    return dept.department_tolerance_zone
        ? normalizeBurnZone(dept.department_tolerance_zone)
        : classify(dept.department_burn_index_pct);
}

function getBurnStatusClass(dept: DepartmentSummaryItem): string {
    const style = BURN_ZONE_STYLES[zoneOf(dept)];

    return `${style.text} ${style.badge}`;
}

function getZoneBadge(dept: DepartmentSummaryItem): {
    label: string;
    class: string;
} {
    const zone = zoneOf(dept);

    return {
        label: __(BURN_ZONE_STYLES[zone].label),
        class: BURN_ZONE_STYLES[zone].badge,
    };
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
                    (dept.red_sections_count ?? 0) > 0
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
                            :class="getZoneBadge(dept).class"
                        >
                            {{ getZoneBadge(dept).label }}
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
                            :class="getBurnStatusClass(dept)"
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
                                :class="BURN_ZONE_STYLES[zoneOf(dept)].solid"
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
                                v-if="(dept.red_sections_count ?? 0) > 0"
                                class="font-bold text-[#cc0000] tabular-nums"
                            >
                                {{ dept.red_sections_count }}
                                {{ __('Merah') }}
                            </span>
                            <span
                                v-if="(dept.yellow_sections_count ?? 0) > 0"
                                class="font-bold text-amber-600 tabular-nums"
                            >
                                {{ dept.yellow_sections_count }}
                                {{ __('Kuning') }}
                            </span>
                            <span
                                class="font-semibold text-emerald-600 tabular-nums"
                            >
                                {{ dept.green_sections_count ?? 0 }}
                                {{ __('Hijau') }}
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
