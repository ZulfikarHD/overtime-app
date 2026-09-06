<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Calculator,
    CheckCircle2,
    Clock,
    DollarSign,
    Layers,
    Sparkles,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';
import { store as storeBudgetRoute } from '@/routes/budgets';

export type SectionBudgetRecord = {
    section_id: number;
    section_code: string;
    section_name: string;
    has_budget: boolean;
    budget_id: number | null;
    planned_hours: number;
    planned_cost_idr: number;
    week1_planned_hours: number;
    week2_planned_hours: number;
    week3_planned_hours: number;
    week4_planned_hours: number;
    week5_planned_hours: number;
    weekly_sum: number;
    has_mismatch: boolean;
    updated_at: string | null;
};

export type DepartmentInfo = {
    id: number;
    code: string;
    name: string;
    default_hourly_rate: number;
};

const props = defineProps<{
    open: boolean;
    section: SectionBudgetRecord | null;
    department: DepartmentInfo | null;
    fiscalYear: number;
    fiscalMonth: number;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const { __ } = useTrans();

const showWeeklyBreakdown = ref(false);
const isManuallyAdjusted = ref(false);

const form = useForm({
    department_id: props.department?.id ?? 0,
    section_id: props.section?.section_id ?? 0,
    fiscal_year: props.fiscalYear,
    fiscal_month: props.fiscalMonth,
    planned_hours: 0 as number | string,
    week1_planned_hours: 0 as number | string,
    week2_planned_hours: 0 as number | string,
    week3_planned_hours: 0 as number | string,
    week4_planned_hours: 0 as number | string,
    week5_planned_hours: 0 as number | string,
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.section && props.department) {
            form.clearErrors();
            form.department_id = props.department.id;
            form.section_id = props.section.section_id;
            form.fiscal_year = props.fiscalYear;
            form.fiscal_month = props.fiscalMonth;
            form.planned_hours = props.section.planned_hours || '';

            if (props.section.has_budget) {
                form.week1_planned_hours = props.section.week1_planned_hours;
                form.week2_planned_hours = props.section.week2_planned_hours;
                form.week3_planned_hours = props.section.week3_planned_hours;
                form.week4_planned_hours = props.section.week4_planned_hours;
                form.week5_planned_hours = props.section.week5_planned_hours;
                showWeeklyBreakdown.value = true;
                isManuallyAdjusted.value = true;
            } else {
                showWeeklyBreakdown.value = false;
                isManuallyAdjusted.value = false;
                distributeWeeklyHours(Number(props.section.planned_hours) || 0);
            }
        }
    },
    { immediate: true },
);

function distributeWeeklyHours(totalHours: number) {
    if (!totalHours || totalHours <= 0) {
        form.week1_planned_hours = 0;
        form.week2_planned_hours = 0;
        form.week3_planned_hours = 0;
        form.week4_planned_hours = 0;
        form.week5_planned_hours = 0;
        return;
    }

    const weeklyAvg = Math.round((totalHours / 4.3) * 100) / 100;
    const w1 = weeklyAvg;
    const w2 = weeklyAvg;
    const w3 = weeklyAvg;
    const w4 = weeklyAvg;
    const w5 = Math.round(Math.max(0, totalHours - weeklyAvg * 4) * 100) / 100;

    form.week1_planned_hours = w1;
    form.week2_planned_hours = w2;
    form.week3_planned_hours = w3;
    form.week4_planned_hours = w4;
    form.week5_planned_hours = w5;
}

function onPlannedHoursInput() {
    const total = Number(form.planned_hours) || 0;
    if (!isManuallyAdjusted.value) {
        distributeWeeklyHours(total);
    }
}

function autoDistribute() {
    isManuallyAdjusted.value = false;
    distributeWeeklyHours(Number(form.planned_hours) || 0);
}

function onWeeklyInput() {
    isManuallyAdjusted.value = true;
}

const weeklySum = computed(() => {
    const w1 = Number(form.week1_planned_hours) || 0;
    const w2 = Number(form.week2_planned_hours) || 0;
    const w3 = Number(form.week3_planned_hours) || 0;
    const w4 = Number(form.week4_planned_hours) || 0;
    const w5 = Number(form.week5_planned_hours) || 0;
    return Math.round((w1 + w2 + w3 + w4 + w5) * 100) / 100;
});

const sumMismatch = computed(() => {
    const target = Number(form.planned_hours) || 0;
    return target > 0 && Math.abs(weeklySum.value - target) > 0.05;
});

const estimatedCost = computed(() => {
    const hours = Number(form.planned_hours) || 0;
    const rate = props.department?.default_hourly_rate || 0;
    return Math.round(hours * rate * 100) / 100;
});

const monthNames = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

const periodLabel = computed(() => {
    const m = monthNames[props.fiscalMonth - 1] ?? props.fiscalMonth;
    return `${m} ${props.fiscalYear}`;
});

function submit() {
    if (!props.department || !props.section) {
        return;
    }

    form.department_id = props.department.id;
    form.section_id = props.section.section_id;
    form.fiscal_year = props.fiscalYear;
    form.fiscal_month = props.fiscalMonth;

    form.post(storeBudgetRoute.url(), {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            emit('saved');
        },
    });
}

function close() {
    emit('update:open', false);
}
</script>

<template>
    <Sheet :open="open" @update:open="(val) => emit('update:open', val)">
        <SheetContent
            side="right"
            class="flex w-full flex-col overflow-y-auto p-0 sm:max-w-lg"
        >
            <SheetHeader class="border-border border-b px-6 pt-6 pb-4">
                <div class="mb-1 flex items-center gap-2">
                    <Badge variant="outline" class="font-mono text-xs">
                        {{ section?.section_code }}
                    </Badge>
                    <Badge variant="secondary" class="text-xs">
                        {{ periodLabel }}
                    </Badge>
                </div>
                <SheetTitle
                    class="text-foreground flex items-center gap-2 text-lg font-semibold"
                >
                    <Calculator class="text-primary size-5" />
                    {{ __('Configure Section Overtime Budget') }}
                </SheetTitle>
                <SheetDescription class="text-muted-foreground text-xs">
                    {{ section?.section_name }} • {{ department?.name }}
                </SheetDescription>
            </SheetHeader>

            <form
                @submit.prevent="submit"
                class="flex flex-1 flex-col justify-between"
                data-test="budget-form"
            >
                <div class="space-y-6 p-6">
                    <!-- Planned Hours Input -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label
                                for="input-planned-hours"
                                class="text-sm font-medium"
                            >
                                {{ __('Monthly Target Hours') }}
                                <span class="text-destructive">*</span>
                            </Label>
                            <span class="text-muted-foreground text-xs">
                                {{ __('Max 10,000 Hours') }}
                            </span>
                        </div>
                        <div class="relative">
                            <Input
                                id="input-planned-hours"
                                data-test="input-planned-hours"
                                v-model="form.planned_hours"
                                type="number"
                                step="0.5"
                                min="0"
                                max="10000"
                                placeholder="e.g. 120"
                                class="pr-12 text-base font-semibold"
                                @input="onPlannedHoursInput"
                                required
                            />
                            <div
                                class="text-muted-foreground pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-xs font-medium"
                            >
                                {{ __('Hours') }}
                            </div>
                        </div>
                        <InputError :message="form.errors.planned_hours" />
                    </div>

                    <!-- Estimated Cost Widget -->
                    <div
                        class="border-border/70 bg-muted/40 space-y-2 rounded-lg border p-3.5"
                    >
                        <div
                            class="text-muted-foreground flex items-center justify-between text-xs"
                        >
                            <div class="flex items-center gap-1.5">
                                <DollarSign class="text-primary size-3.5" />
                                <span>{{ __('Estimated Overtime Cost') }}</span>
                            </div>
                            <span>{{
                                __('Standard Rate: :rate/hr', {
                                    rate: formatRupiah(
                                        department?.default_hourly_rate,
                                    ),
                                })
                            }}</span>
                        </div>
                        <div
                            class="text-foreground font-mono text-xl font-bold"
                            data-test="text-estimated-cost"
                        >
                            {{ formatRupiah(estimatedCost) }}
                        </div>
                        <p
                            class="text-muted-foreground text-[11px] leading-normal"
                        >
                            {{
                                __(
                                    'Calculated automatically by multiplying target hours by current department standard overtime rate.',
                                )
                            }}
                        </p>
                    </div>

                    <!-- 5-Week Breakdown Collapsible Toggle -->
                    <div class="space-y-4 pt-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    id="toggle-breakdown"
                                    data-test="toggle-weekly-breakdown"
                                    :checked="showWeeklyBreakdown"
                                    @update:checked="
                                        (val: boolean) =>
                                            (showWeeklyBreakdown = val)
                                    "
                                />
                                <Label
                                    for="toggle-breakdown"
                                    class="cursor-pointer text-sm font-medium"
                                >
                                    {{
                                        __(
                                            'Configure 5-Week Breakdown (Optional)',
                                        )
                                    }}
                                </Label>
                            </div>

                            <Button
                                v-if="showWeeklyBreakdown"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-primary hover:bg-primary/10 h-7 cursor-pointer text-xs"
                                @click="autoDistribute"
                                data-test="btn-auto-distribute"
                            >
                                <Sparkles class="mr-1 size-3" />
                                {{ __('Distribute Evenly (4.3 Weeks)') }}
                            </Button>
                        </div>

                        <!-- 5-Week Grid Inputs -->
                        <div
                            v-if="showWeeklyBreakdown"
                            class="border-border bg-card space-y-3 rounded-lg border p-4"
                            data-test="weekly-inputs-section"
                        >
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <div class="space-y-1.5">
                                    <Label
                                        for="w1"
                                        class="text-muted-foreground text-xs font-medium"
                                        >{{ __('Week 1') }}</Label
                                    >
                                    <Input
                                        id="w1"
                                        data-test="input-w1"
                                        v-model="form.week1_planned_hours"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        @input="onWeeklyInput"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <Label
                                        for="w2"
                                        class="text-muted-foreground text-xs font-medium"
                                        >{{ __('Week 2') }}</Label
                                    >
                                    <Input
                                        id="w2"
                                        data-test="input-w2"
                                        v-model="form.week2_planned_hours"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        @input="onWeeklyInput"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <Label
                                        for="w3"
                                        class="text-muted-foreground text-xs font-medium"
                                        >{{ __('Week 3') }}</Label
                                    >
                                    <Input
                                        id="w3"
                                        data-test="input-w3"
                                        v-model="form.week3_planned_hours"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        @input="onWeeklyInput"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <Label
                                        for="w4"
                                        class="text-muted-foreground text-xs font-medium"
                                        >{{ __('Week 4') }}</Label
                                    >
                                    <Input
                                        id="w4"
                                        data-test="input-w4"
                                        v-model="form.week4_planned_hours"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        @input="onWeeklyInput"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <Label
                                        for="w5"
                                        class="text-muted-foreground text-xs font-medium"
                                        >{{ __('Week 5') }}</Label
                                    >
                                    <Input
                                        id="w5"
                                        data-test="input-w5"
                                        v-model="form.week5_planned_hours"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        @input="onWeeklyInput"
                                    />
                                </div>
                            </div>

                            <!-- Weekly Calculator & Sum Mismatch Pill -->
                            <div
                                class="border-border/60 flex flex-col gap-2 border-t pt-2"
                            >
                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span class="text-muted-foreground">{{
                                        __('Total Weekly Breakdown:')
                                    }}</span>
                                    <span
                                        class="font-mono font-semibold"
                                        data-test="text-weekly-sum"
                                        >{{ weeklySum }} {{ __('Hours') }}</span
                                    >
                                </div>

                                <div
                                    v-if="sumMismatch"
                                    class="flex items-start gap-2 rounded-md border border-amber-500/30 bg-amber-500/10 p-2.5 text-xs text-amber-900 dark:text-amber-200"
                                    data-test="badge-sum-mismatch"
                                >
                                    <AlertTriangle
                                        class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                                    />
                                    <div class="leading-relaxed">
                                        <div class="font-medium">
                                            {{
                                                __(
                                                    'Weekly breakdown (:sum hrs) differs from monthly target (:target hrs).',
                                                    {
                                                        sum: weeklySum,
                                                        target:
                                                            form.planned_hours ||
                                                            0,
                                                    },
                                                )
                                            }}
                                        </div>
                                        <p
                                            class="mt-0.5 text-[11px] opacity-90"
                                        >
                                            {{
                                                __(
                                                    'The system will still store this as an operational non-linear estimate.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <SheetFooter
                    class="border-border bg-background mt-auto flex items-center justify-end gap-2 border-t p-6"
                >
                    <Button
                        type="button"
                        variant="outline"
                        @click="close"
                        class="cursor-pointer"
                        data-test="btn-cancel-budget"
                    >
                        {{ __('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="cursor-pointer"
                        data-test="btn-save-budget"
                    >
                        <Spinner v-if="form.processing" class="mr-2 size-4" />
                        <span>{{ __('Save Budget') }}</span>
                    </Button>
                </SheetFooter>
            </form>
        </SheetContent>
    </Sheet>
</template>
