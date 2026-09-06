<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    CheckCircle2,
    Info,
    Shield,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { useTrans } from '@/composables/useTrans';
import policyThresholdsRoute from '@/routes/admin/policy-thresholds';

export type PolicyThresholdRecord = {
    id?: number;
    department_id?: number | null;
    department_code?: string;
    department_name?: string;
    weekly_soft_limit_hours: number | string;
    consecutive_weeks_alert: number | string;
    spkl_grace_period_days: number | string;
    burn_warning_pct: number | string;
    burn_danger_pct: number | string;
};

export type DepartmentOption = {
    id: number;
    code: string;
    name: string;
};

const props = defineProps<{
    open: boolean;
    isPlantDefault: boolean;
    threshold: PolicyThresholdRecord | null;
    availableDepartments: DepartmentOption[];
    existingOverrideDeptIds?: number[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const { __ } = useTrans();

const form = useForm({
    department_id: '' as string | number,
    weekly_soft_limit_hours: 20.0,
    consecutive_weeks_alert: 3,
    spkl_grace_period_days: 2,
    burn_warning_pct: 100.0,
    burn_danger_pct: 115.0,
});

const isSubmitting = ref(false);
const formError = ref<string | null>(null);

// Sync prop values to form state on open
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.threshold) {
            form.department_id = props.isPlantDefault
                ? ''
                : (props.threshold.department_id ?? '');
            form.weekly_soft_limit_hours = Number(
                props.threshold.weekly_soft_limit_hours ?? 20.0,
            );
            form.consecutive_weeks_alert = Number(
                props.threshold.consecutive_weeks_alert ?? 3,
            );
            form.spkl_grace_period_days = Number(
                props.threshold.spkl_grace_period_days ?? 2,
            );
            form.burn_warning_pct = Number(
                props.threshold.burn_warning_pct ?? 100.0,
            );
            form.burn_danger_pct = Number(
                props.threshold.burn_danger_pct ?? 115.0,
            );
            form.clearErrors();
            formError.value = null;
        } else if (isOpen && !props.threshold) {
            form.department_id = '';
            form.weekly_soft_limit_hours = 20.0;
            form.consecutive_weeks_alert = 3;
            form.spkl_grace_period_days = 2;
            form.burn_warning_pct = 100.0;
            form.burn_danger_pct = 115.0;
            form.clearErrors();
            formError.value = null;
        }
    },
    { immediate: true },
);

// Determine selectable departments for new overrides
const selectableDepartments = computed(() => {
    if (props.isPlantDefault) {
        return [];
    }
    // If editing existing override, show current department even if in existingOverrideDeptIds
    const currentDeptId = props.threshold?.department_id;
    return props.availableDepartments.filter((dept) => {
        if (currentDeptId && dept.id === currentDeptId) {
            return true;
        }
        return !(props.existingOverrideDeptIds ?? []).includes(dept.id);
    });
});

// Real-time validation warning if danger < warning
const hasLogicalPercentageMismatch = computed(() => {
    const warning = Number(form.burn_warning_pct);
    const danger = Number(form.burn_danger_pct);
    return !isNaN(warning) && !isNaN(danger) && danger < warning;
});

const isSaveDisabled = computed(() => {
    if (isSubmitting.value) {
        return true;
    }
    if (!props.isPlantDefault && !form.department_id) {
        return true;
    }
    if (hasLogicalPercentageMismatch.value) {
        return true;
    }
    if (
        Number(form.weekly_soft_limit_hours) < 0 ||
        Number(form.consecutive_weeks_alert) < 1 ||
        Number(form.spkl_grace_period_days) < 0 ||
        Number(form.burn_warning_pct) < 0 ||
        Number(form.burn_danger_pct) < 0
    ) {
        return true;
    }
    return false;
});

function handleClose() {
    emit('update:open', false);
}

function handleSubmit() {
    if (isSaveDisabled.value) {
        return;
    }

    isSubmitting.value = true;
    formError.value = null;

    const payload = {
        department_id: props.isPlantDefault ? null : Number(form.department_id),
        weekly_soft_limit_hours: Number(form.weekly_soft_limit_hours),
        consecutive_weeks_alert: Number(form.consecutive_weeks_alert),
        spkl_grace_period_days: Number(form.spkl_grace_period_days),
        burn_warning_pct: Number(form.burn_warning_pct),
        burn_danger_pct: Number(form.burn_danger_pct),
    };

    const hasId = !!props.threshold?.id;

    if (hasId && props.threshold?.id) {
        router.put(
            policyThresholdsRoute.update({
                policy_threshold: props.threshold.id,
            }).url,
            payload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    isSubmitting.value = false;
                    emit('saved');
                    emit('update:open', false);
                },
                onError: (errors) => {
                    isSubmitting.value = false;
                    const firstError = Object.values(errors)[0];
                    formError.value =
                        firstError || __('Failed to update policy threshold.');
                },
            },
        );
    } else {
        router.post(policyThresholdsRoute.store().url, payload, {
            preserveScroll: true,
            onSuccess: () => {
                isSubmitting.value = false;
                emit('saved');
                emit('update:open', false);
            },
            onError: (errors) => {
                isSubmitting.value = false;
                const firstError = Object.values(errors)[0];
                formError.value =
                    firstError || __('Failed to save policy threshold.');
            },
        });
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent
            side="right"
            class="flex w-full flex-col sm:max-w-xl md:max-w-2xl"
        >
            <SheetHeader class="space-y-2 border-b pb-4">
                <div class="flex items-center gap-2">
                    <div
                        class="flex size-9 items-center justify-center rounded-lg"
                        :class="
                            isPlantDefault
                                ? 'bg-primary/10 text-primary'
                                : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400'
                        "
                    >
                        <Shield v-if="isPlantDefault" class="size-5" />
                        <Building2 v-else class="size-5" />
                    </div>
                    <div>
                        <SheetTitle class="text-lg font-bold">
                            {{
                                isPlantDefault
                                    ? __('Edit Plant Default')
                                    : threshold?.id
                                      ? __('Edit Department Override')
                                      : __('Add Department Override')
                            }}
                        </SheetTitle>
                        <SheetDescription class="text-xs">
                            {{
                                isPlantDefault
                                    ? __(
                                          'Standard baseline policies automatically applied to all departments unless a custom override is configured.',
                                      )
                                    : __(
                                          'Configure specific limits for this department to override standard plant baseline policies.',
                                      )
                            }}
                        </SheetDescription>
                    </div>
                </div>
            </SheetHeader>

            <!-- Sheet Scrollable Form Body -->
            <div class="flex-1 space-y-5 overflow-y-auto py-4 pr-1">
                <!-- Informational Regulatory Banner -->
                <div
                    class="rounded-lg border border-blue-200 bg-blue-50/70 p-3.5 text-xs text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-300"
                >
                    <div class="flex items-start gap-2.5">
                        <Info
                            class="mt-0.5 size-4 shrink-0 text-blue-600 dark:text-blue-400"
                        />
                        <div class="space-y-1">
                            <span class="block font-semibold">
                                {{ __('Operational Regulatory Notice') }}
                            </span>
                            <p class="leading-relaxed">
                                {{
                                    __(
                                        'Policy threshold changes take effect immediately for new overtime submissions and burn calculations. Existing overtime records retain their historical values.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Error Alert Banner -->
                <div
                    v-if="formError"
                    class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300"
                >
                    <div class="flex items-center gap-2">
                        <AlertCircle
                            class="size-4 shrink-0 text-red-600 dark:text-red-400"
                        />
                        <span>{{ formError }}</span>
                    </div>
                </div>

                <!-- Target Department (Override Only) -->
                <div v-if="!isPlantDefault" class="space-y-2">
                    <Label for="select-department" class="text-sm font-medium">
                        {{ __('Department') }}
                        <span class="text-red-500">*</span>
                    </Label>

                    <!-- Locked department if editing existing override -->
                    <div
                        v-if="threshold?.id && threshold.department_name"
                        class="bg-muted/50 flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                    >
                        <div class="flex items-center gap-2">
                            <Building2 class="text-muted-foreground size-4" />
                            <span class="font-medium">{{
                                threshold.department_name
                            }}</span>
                            <Badge variant="outline" class="font-mono text-xs">
                                {{ threshold.department_code }}
                            </Badge>
                        </div>
                        <span class="text-muted-foreground text-xs">
                            {{
                                __(
                                    'Code is permanently locked after creation (BR-03).',
                                )
                            }}
                        </span>
                    </div>

                    <!-- Dropdown for new override -->
                    <select
                        v-else
                        id="select-department"
                        v-model="form.department_id"
                        class="border-input focus-visible:ring-ring flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isSubmitting"
                    >
                        <option value="" disabled>
                            -- {{ __('Select Department') }} --
                        </option>
                        <option
                            v-for="dept in selectableDepartments"
                            :key="dept.id"
                            :value="dept.id"
                        >
                            {{ dept.code }} — {{ dept.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.department_id" />
                </div>

                <!-- Metric 1: Weekly Soft Limit -->
                <div class="bg-card space-y-3 rounded-lg border p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <Label
                                for="input-weekly-soft-limit"
                                class="text-sm font-semibold"
                            >
                                {{ __('Weekly Soft Limit (Hours)') }}
                                <span class="text-red-500">*</span>
                            </Label>
                            <p class="text-muted-foreground mt-0.5 text-xs">
                                {{
                                    __(
                                        'Alert threshold when an employee exceeds this weekly overtime hours threshold',
                                    )
                                }}
                            </p>
                        </div>
                        <Badge variant="outline" class="font-mono text-xs">
                            {{ form.weekly_soft_limit_hours }}
                            {{ __('hrs/wk') }}
                        </Badge>
                    </div>
                    <div class="flex items-center gap-3">
                        <Input
                            id="input-weekly-soft-limit"
                            v-model.number="form.weekly_soft_limit_hours"
                            type="number"
                            min="0"
                            max="168"
                            step="0.5"
                            class="h-9 font-mono"
                            :disabled="isSubmitting"
                        />
                        <span class="text-muted-foreground shrink-0 text-xs">
                            {{ __('hrs/wk') }}
                        </span>
                    </div>
                    <InputError
                        :message="form.errors.weekly_soft_limit_hours"
                    />
                </div>

                <!-- Metric 2: Consecutive Weeks Alert -->
                <div class="bg-card space-y-3 rounded-lg border p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <Label
                                for="input-consecutive-weeks"
                                class="text-sm font-semibold"
                            >
                                {{ __('Consecutive Weeks Alert (Weeks)') }}
                                <span class="text-red-500">*</span>
                            </Label>
                            <p class="text-muted-foreground mt-0.5 text-xs">
                                {{
                                    __(
                                        'Alert triggered when overtime continues across consecutive weeks',
                                    )
                                }}
                            </p>
                        </div>
                        <Badge variant="outline" class="font-mono text-xs">
                            {{ form.consecutive_weeks_alert }} {{ __('weeks') }}
                        </Badge>
                    </div>
                    <div class="flex items-center gap-3">
                        <Input
                            id="input-consecutive-weeks"
                            v-model.number="form.consecutive_weeks_alert"
                            type="number"
                            min="1"
                            max="52"
                            step="1"
                            class="h-9 font-mono"
                            :disabled="isSubmitting"
                        />
                        <span class="text-muted-foreground shrink-0 text-xs">
                            {{ __('weeks') }}
                        </span>
                    </div>
                    <InputError
                        :message="form.errors.consecutive_weeks_alert"
                    />
                </div>

                <!-- Metric 3: SPKL Grace Period -->
                <div class="bg-card space-y-3 rounded-lg border p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <Label
                                for="input-spkl-grace-period"
                                class="text-sm font-semibold"
                            >
                                {{ __('SPKL Grace Period (Days)') }}
                                <span class="text-red-500">*</span>
                            </Label>
                            <p class="text-muted-foreground mt-0.5 text-xs">
                                {{
                                    __(
                                        'Maximum days allowed between overtime execution and physical SPKL submission',
                                    )
                                }}
                            </p>
                        </div>
                        <Badge variant="outline" class="font-mono text-xs">
                            {{ form.spkl_grace_period_days }} {{ __('days') }}
                        </Badge>
                    </div>
                    <div class="flex items-center gap-3">
                        <Input
                            id="input-spkl-grace-period"
                            v-model.number="form.spkl_grace_period_days"
                            type="number"
                            min="0"
                            max="30"
                            step="1"
                            class="h-9 font-mono"
                            :disabled="isSubmitting"
                        />
                        <span class="text-muted-foreground shrink-0 text-xs">
                            {{ __('days') }}
                        </span>
                    </div>
                    <InputError :message="form.errors.spkl_grace_period_days" />
                </div>

                <!-- Metric 4 & 5: Budget Burn Alert Levels -->
                <div class="bg-card space-y-4 rounded-lg border p-4">
                    <div>
                        <div class="text-sm font-semibold">
                            {{ __('Budget Burn Alert Levels') }}
                        </div>
                        <p class="text-muted-foreground mt-0.5 text-xs">
                            {{
                                __(
                                    'Define percentage thresholds for department overtime budget consumption warnings and danger limits.',
                                )
                            }}
                        </p>
                    </div>

                    <!-- Logical Mismatch Error Badge -->
                    <div
                        v-if="hasLogicalPercentageMismatch"
                        class="rounded-md border border-amber-300 bg-amber-50 p-2.5 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300"
                    >
                        <div class="flex items-center gap-2">
                            <AlertCircle
                                class="size-4 shrink-0 text-amber-600 dark:text-amber-400"
                            />
                            <span>
                                {{
                                    __(
                                        'Burn danger percentage must be greater than or equal to burn warning percentage.',
                                    )
                                }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Burn Warning -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label
                                    for="input-burn-warning"
                                    class="text-xs font-medium text-amber-700 dark:text-amber-400"
                                >
                                    {{ __('Burn Warning Percentage (%)') }}
                                    <span class="text-red-500">*</span>
                                </Label>
                                <span class="font-mono text-xs font-semibold">
                                    {{ form.burn_warning_pct }}%
                                </span>
                            </div>
                            <Input
                                id="input-burn-warning"
                                v-model.number="form.burn_warning_pct"
                                type="number"
                                min="0"
                                max="500"
                                step="0.5"
                                class="h-9 font-mono"
                                :disabled="isSubmitting"
                            />
                            <p class="text-muted-foreground text-[11px]">
                                {{
                                    __(
                                        'Budget burn index threshold triggering amber warning alerts',
                                    )
                                }}
                            </p>
                            <InputError
                                :message="form.errors.burn_warning_pct"
                            />
                        </div>

                        <!-- Burn Danger -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label
                                    for="input-burn-danger"
                                    class="text-xs font-medium text-red-700 dark:text-red-400"
                                >
                                    {{ __('Burn Danger Percentage (%)') }}
                                    <span class="text-red-500">*</span>
                                </Label>
                                <span class="font-mono text-xs font-semibold">
                                    {{ form.burn_danger_pct }}%
                                </span>
                            </div>
                            <Input
                                id="input-burn-danger"
                                v-model.number="form.burn_danger_pct"
                                type="number"
                                min="0"
                                max="500"
                                step="0.5"
                                class="h-9 font-mono"
                                :disabled="isSubmitting"
                            />
                            <p class="text-muted-foreground text-[11px]">
                                {{
                                    __(
                                        'Budget burn index threshold triggering red critical alert status',
                                    )
                                }}
                            </p>
                            <InputError
                                :message="form.errors.burn_danger_pct"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sheet Footer -->
            <SheetFooter
                class="flex flex-row items-center justify-end gap-2 border-t pt-4"
            >
                <Button
                    type="button"
                    variant="outline"
                    :disabled="isSubmitting"
                    @click="handleClose"
                >
                    {{ __('Cancel') }}
                </Button>
                <Button
                    type="button"
                    data-test="btn-save-policy"
                    :disabled="isSaveDisabled"
                    @click="handleSubmit"
                >
                    <span v-if="isSubmitting">{{ __('Saving...') }}</span>
                    <span v-else>{{ __('Save Policy') }}</span>
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
