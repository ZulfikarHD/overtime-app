<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    FileSpreadsheet,
    Filter,
    Layers,
    Plus,
    TrendingUp,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import BudgetFormSheet, {
    type DepartmentInfo,
    type SectionBudgetRecord,
} from '@/components/budgets/BudgetFormSheet.vue';
import BudgetImportSheet from '@/components/budgets/BudgetImportSheet.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';
import { dashboard } from '@/routes';
import { planning } from '@/routes/budgets';
import type { User } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Budget Planning',
                href: planning(),
            },
        ],
    },
});

type DepartmentOption = {
    id: number;
    code: string;
    name: string;
};

type PlanningSummary = {
    total_planned_hours: number;
    total_estimated_cost_idr: number;
    configured_sections_count: number;
    total_sections_count: number;
    coverage_percentage: number;
};

const props = defineProps<{
    departments: DepartmentOption[];
    selected_department: DepartmentInfo | null;
    fiscal_year: number;
    fiscal_month: number;
    sections: SectionBudgetRecord[];
    summary: PlanningSummary;
}>();

const { __ } = useTrans();
const page = usePage();
const currentUser = computed(() => page.props.auth?.user as User | undefined);

const isFormSheetOpen = ref(false);
const isImportSheetOpen = ref(false);
const selectedSectionForEdit = ref<SectionBudgetRecord | null>(null);

const filterYear = ref<number>(props.fiscal_year);
const filterMonth = ref<number>(props.fiscal_month);
const filterDeptId = ref<number | ''>(props.selected_department?.id ?? '');

const monthOptions = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

const yearOptions = [2025, 2026, 2027, 2028];

function onFilterChange() {
    router.get(
        planning.url(),
        {
            year: filterYear.value,
            month: filterMonth.value,
            department_id:
                filterDeptId.value !== '' ? filterDeptId.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function openEditSheet(section: SectionBudgetRecord) {
    selectedSectionForEdit.value = section;
    isFormSheetOpen.value = true;
}

function openImportSheet() {
    isImportSheetOpen.value = true;
}

function refreshData() {
    router.reload({
        only: ['sections', 'summary', 'selected_department'],
    });
}
</script>

<template>
    <Head :title="__('Overtime Budget Planning')" />

    <div class="space-y-6">
        <!-- Header & Action Toolbar -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                :title="__('Overtime Budget Plan')"
                :description="
                    __(
                        'Manage section overtime hour targets and monitor estimated labor costs as the foundation for the factory Burn Index.',
                    )
                "
            />
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    @click="openImportSheet"
                    class="cursor-pointer text-xs"
                    data-test="btn-open-csv-import"
                >
                    <FileSpreadsheet class="text-primary mr-1.5 size-4" />
                    <span>{{ __('Import CSV') }}</span>
                </Button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="border-border bg-card rounded-xl border p-4 shadow-xs">
            <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                <div
                    class="text-muted-foreground flex items-center gap-1.5 text-xs font-medium"
                >
                    <Filter class="size-3.5" />
                    <span>{{ __('Filter Period & Department:') }}</span>
                </div>

                <!-- Year Filter -->
                <div class="flex items-center gap-1">
                    <select
                        v-model.number="filterYear"
                        @change="onFilterChange"
                        class="border-input bg-background text-foreground focus:ring-ring h-8 cursor-pointer rounded-md border px-2.5 py-1 text-xs font-medium shadow-xs outline-none focus:ring-1"
                        data-test="select-fiscal-year"
                    >
                        <option v-for="y in yearOptions" :key="y" :value="y">
                            {{ y }}
                        </option>
                    </select>
                </div>

                <!-- Month Filter -->
                <div class="flex items-center gap-1">
                    <select
                        v-model.number="filterMonth"
                        @change="onFilterChange"
                        class="border-input bg-background text-foreground focus:ring-ring h-8 cursor-pointer rounded-md border px-2.5 py-1 text-xs font-medium shadow-xs outline-none focus:ring-1"
                        data-test="select-fiscal-month"
                    >
                        <option
                            v-for="m in monthOptions"
                            :key="m.value"
                            :value="m.value"
                        >
                            {{ __(m.label) }}
                        </option>
                    </select>
                </div>

                <!-- Department Selector -->
                <div class="flex flex-1 items-center gap-1 sm:max-w-xs">
                    <select
                        v-model="filterDeptId"
                        @change="onFilterChange"
                        :disabled="currentUser?.role === 'manager'"
                        class="border-input bg-background text-foreground focus:ring-ring h-8 w-full cursor-pointer rounded-md border px-2.5 py-1 text-xs font-medium shadow-xs outline-none focus:ring-1 disabled:cursor-not-allowed disabled:opacity-75"
                        data-test="select-department"
                    >
                        <option
                            v-for="dept in departments"
                            :key="dept.id"
                            :value="dept.id"
                        >
                            {{ dept.code }} - {{ dept.name }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- KPI Metric Summary Cards -->
        <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-3"
            data-test="summary-cards"
        >
            <!-- Total Planned Hours -->
            <Card class="border-border shadow-xs">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <CardTitle
                            class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                        >
                            {{ __('Total Planned Hours') }}
                        </CardTitle>
                        <Clock class="text-primary size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-foreground font-mono text-2xl font-bold"
                        data-test="metric-total-hours"
                    >
                        {{ summary.total_planned_hours }}
                        <span
                            class="text-muted-foreground text-sm font-normal"
                            >{{ __('Hours') }}</span
                        >
                    </div>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {{
                            __(
                                'Total accumulated target across all sections for this month',
                            )
                        }}
                    </p>
                </CardContent>
            </Card>

            <!-- Total Estimated Cost -->
            <Card class="border-border shadow-xs">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <CardTitle
                            class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                        >
                            {{ __('Estimated Overtime Cost') }}
                        </CardTitle>
                        <DollarSign
                            class="size-4 text-emerald-600 dark:text-emerald-400"
                        />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-foreground font-mono text-2xl font-bold"
                        data-test="metric-total-cost"
                    >
                        {{ formatRupiah(summary.total_estimated_cost_idr) }}
                    </div>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {{
                            __('Based on department standard rate: :rate/hr', {
                                rate: formatRupiah(
                                    selected_department?.default_hourly_rate,
                                ),
                            })
                        }}
                    </p>
                </CardContent>
            </Card>

            <!-- Section Configuration Coverage -->
            <Card class="border-border shadow-xs">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <CardTitle
                            class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                        >
                            {{ __('Section Coverage Configured') }}
                        </CardTitle>
                        <Layers
                            class="size-4 text-blue-600 dark:text-blue-400"
                        />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-baseline gap-2">
                        <div
                            class="text-foreground font-mono text-2xl font-bold"
                            data-test="metric-coverage-count"
                        >
                            {{ summary.configured_sections_count }} /
                            {{ summary.total_sections_count }}
                        </div>
                        <Badge
                            :variant="
                                summary.coverage_percentage >= 100
                                    ? 'default'
                                    : 'secondary'
                            "
                            class="font-mono text-xs"
                            data-test="metric-coverage-pct"
                        >
                            {{ summary.coverage_percentage }}%
                        </Badge>
                    </div>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {{ __('Sections with configured overtime budget') }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Section Budgets Table -->
        <Card class="border-border shadow-xs">
            <CardHeader class="border-border border-b pb-3">
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle
                            class="text-foreground text-base font-semibold"
                        >
                            {{ __('Section Overtime Budgets') }}
                        </CardTitle>
                        <p class="text-muted-foreground mt-0.5 text-xs">
                            {{ selected_department?.name }} •
                            {{
                                __('Period :month :year', {
                                    month: __(
                                        monthOptions[fiscal_month - 1]?.label ??
                                            '',
                                    ),
                                    year: fiscal_year,
                                })
                            }}
                        </p>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <div
                    v-if="sections.length === 0"
                    class="p-12 text-center"
                    data-test="empty-sections-alert"
                >
                    <AlertCircle
                        class="text-muted-foreground/60 mx-auto mb-3 size-10"
                    />
                    <h3 class="text-foreground text-sm font-semibold">
                        {{ __('No sections found in this department') }}
                    </h3>
                    <p
                        class="text-muted-foreground mx-auto mt-1 max-w-sm text-xs"
                    >
                        {{
                            __(
                                'Please register new sections in the Master Data Hub first to set up overtime budgets.',
                            )
                        }}
                    </p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table
                        class="w-full text-left text-xs"
                        data-test="sections-budget-table"
                    >
                        <thead
                            class="bg-muted/50 text-muted-foreground border-border border-b"
                        >
                            <tr>
                                <th
                                    class="px-4 py-3 font-semibold tracking-wider uppercase"
                                >
                                    {{ __('Code') }}
                                </th>
                                <th
                                    class="px-4 py-3 font-semibold tracking-wider uppercase"
                                >
                                    {{ __('Section Name') }}
                                </th>
                                <th
                                    class="px-4 py-3 font-semibold tracking-wider uppercase"
                                >
                                    {{ __('Monthly Target') }}
                                </th>
                                <th
                                    class="px-4 py-3 font-semibold tracking-wider uppercase"
                                >
                                    {{ __('5-Week Breakdown (Hours)') }}
                                </th>
                                <th
                                    class="px-4 py-3 font-semibold tracking-wider uppercase"
                                >
                                    {{ __('Estimated Cost') }}
                                </th>
                                <th
                                    class="px-4 py-3 font-semibold tracking-wider uppercase"
                                >
                                    {{ __('Status') }}
                                </th>
                                <th
                                    class="px-4 py-3 text-right font-semibold tracking-wider uppercase"
                                >
                                    {{ __('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-border divide-y">
                            <tr
                                v-for="sec in sections"
                                :key="sec.section_id"
                                class="hover:bg-muted/30 transition-colors"
                                :data-test="`section-row-${sec.section_code}`"
                            >
                                <!-- Section Code -->
                                <td
                                    class="text-foreground px-4 py-3.5 font-mono font-semibold"
                                >
                                    <Badge
                                        variant="outline"
                                        class="font-mono text-xs"
                                    >
                                        {{ sec.section_code }}
                                    </Badge>
                                </td>

                                <!-- Section Name -->
                                <td
                                    class="text-foreground px-4 py-3.5 font-medium"
                                >
                                    {{ sec.section_name }}
                                </td>

                                <!-- Monthly Planned Hours -->
                                <td
                                    class="text-foreground px-4 py-3.5 font-mono font-semibold"
                                >
                                    <span v-if="sec.has_budget" class="text-sm">
                                        {{ sec.planned_hours }}
                                        {{ __('Hours') }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-muted-foreground italic"
                                    >
                                        -
                                    </span>
                                </td>

                                <!-- 5-Week Breakdown Pills -->
                                <td class="px-4 py-3.5">
                                    <div
                                        v-if="sec.has_budget"
                                        class="flex flex-wrap items-center gap-1.5"
                                    >
                                        <Badge
                                            variant="secondary"
                                            class="px-1.5 py-0.5 font-mono text-[11px]"
                                        >
                                            W1: {{ sec.week1_planned_hours }}
                                        </Badge>
                                        <Badge
                                            variant="secondary"
                                            class="px-1.5 py-0.5 font-mono text-[11px]"
                                        >
                                            W2: {{ sec.week2_planned_hours }}
                                        </Badge>
                                        <Badge
                                            variant="secondary"
                                            class="px-1.5 py-0.5 font-mono text-[11px]"
                                        >
                                            W3: {{ sec.week3_planned_hours }}
                                        </Badge>
                                        <Badge
                                            variant="secondary"
                                            class="px-1.5 py-0.5 font-mono text-[11px]"
                                        >
                                            W4: {{ sec.week4_planned_hours }}
                                        </Badge>
                                        <Badge
                                            variant="secondary"
                                            class="px-1.5 py-0.5 font-mono text-[11px]"
                                        >
                                            W5: {{ sec.week5_planned_hours }}
                                        </Badge>

                                        <!-- Mismatch Indicator -->
                                        <Badge
                                            v-if="sec.has_mismatch"
                                            variant="destructive"
                                            class="border-amber-500/30 bg-amber-500/15 px-1.5 py-0.5 text-[10px] text-amber-700 dark:text-amber-300"
                                            :title="
                                                __(
                                                    'Weekly total differs from monthly target',
                                                )
                                            "
                                        >
                                            ≠ Σ {{ sec.weekly_sum }}h
                                        </Badge>
                                    </div>
                                    <span
                                        v-else
                                        class="text-muted-foreground text-[11px] italic"
                                    >
                                        {{ __('Not detailed') }}
                                    </span>
                                </td>

                                <!-- Estimated Cost -->
                                <td
                                    class="text-foreground px-4 py-3.5 font-mono"
                                >
                                    {{ formatRupiah(sec.planned_cost_idr) }}
                                </td>

                                <!-- Status Badge -->
                                <td class="px-4 py-3.5">
                                    <Badge
                                        v-if="sec.has_budget"
                                        class="border-emerald-500/30 bg-emerald-500/15 text-xs font-medium text-emerald-700 dark:text-emerald-400"
                                        data-test="badge-status-set"
                                    >
                                        <CheckCircle2 class="mr-1 size-3" />
                                        {{ __('Configured') }}
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="secondary"
                                        class="border-amber-500/20 bg-amber-500/10 text-xs font-medium text-amber-700 dark:text-amber-400"
                                        data-test="badge-status-unset"
                                    >
                                        {{ __('Not Configured') }}
                                    </Badge>
                                </td>

                                <!-- Actions -->
                                <td class="px-4 py-3.5 text-right">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="openEditSheet(sec)"
                                        class="h-7 cursor-pointer text-xs"
                                        :data-test="`btn-edit-budget-${sec.section_code}`"
                                    >
                                        <span>{{
                                            sec.has_budget
                                                ? __('Edit Budget')
                                                : __('Set Budget')
                                        }}</span>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Budget Form Sheet Drawer -->
        <BudgetFormSheet
            v-model:open="isFormSheetOpen"
            :section="selectedSectionForEdit"
            :department="selected_department"
            :fiscal-year="fiscal_year"
            :fiscal-month="fiscal_month"
            @saved="refreshData"
        />

        <!-- Budget CSV Import Sheet Drawer -->
        <BudgetImportSheet
            v-model:open="isImportSheetOpen"
            @success="refreshData"
        />
    </div>
</template>
