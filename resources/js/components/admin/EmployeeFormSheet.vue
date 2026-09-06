<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Building2, Layers, Lock } from '@lucide/vue';
import { computed, watch } from 'vue';
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
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';
import employeesRoute from '@/routes/admin/employees';

export type DepartmentWithSections = {
    id: number;
    code: string;
    name: string;
    cost_center_code: string;
    default_hourly_rate: string | number;
    is_active: boolean;
    sections: {
        id: number;
        department_id: number;
        code: string;
        name: string;
        is_active: boolean;
    }[];
};

export type EmployeeRecord = {
    id: number;
    npk: string;
    department_id: number;
    section_id: number;
    full_name: string;
    job_position: string;
    hourly_rate: string | number | null;
    effective_hourly_rate?: number;
    is_active: boolean;
    department?: {
        id: number;
        code: string;
        name: string;
        default_hourly_rate: string | number;
    };
    section?: {
        id: number;
        code: string;
        name: string;
        department_id: number;
    };
    overtime_items_count?: number;
};

const props = defineProps<{
    open: boolean;
    employee?: EmployeeRecord | null;
    departments: DepartmentWithSections[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const { __ } = useTrans();

const isEdit = computed(() => !!props.employee);

const form = useForm({
    npk: '',
    full_name: '',
    department_id: '' as string | number,
    section_id: '' as string | number,
    job_position: '',
    hourly_rate: '' as string | number,
    is_active: true,
});

// Selected department object
const selectedDepartment = computed(() => {
    const deptId = Number(form.department_id);
    if (!deptId) {
        return null;
    }
    return props.departments.find((d) => d.id === deptId) ?? null;
});

// Available sections filtered by selected department
const availableSections = computed(() => {
    if (!selectedDepartment.value) {
        return [];
    }
    return selectedDepartment.value.sections || [];
});

// Helper for department rate fallback preview
const departmentDefaultRateFormatted = computed(() => {
    if (!selectedDepartment.value) {
        return null;
    }
    return formatRupiah(selectedDepartment.value.default_hourly_rate);
});

// Watch department change: clear section if invalid
watch(
    () => form.department_id,
    (newDeptId, oldDeptId) => {
        if (
            oldDeptId !== undefined &&
            oldDeptId !== '' &&
            newDeptId !== oldDeptId
        ) {
            const currentSecId = Number(form.section_id);
            const isValidForNewDept = availableSections.value.some(
                (s) => s.id === currentSecId,
            );
            if (!isValidForNewDept) {
                form.section_id = '';
            }
        }
    },
);

// Reset form on open
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            if (props.employee) {
                form.npk = props.employee.npk;
                form.full_name = props.employee.full_name;
                form.department_id = props.employee.department_id;
                form.section_id = props.employee.section_id;
                form.job_position = props.employee.job_position;
                form.hourly_rate =
                    props.employee.hourly_rate !== null
                        ? props.employee.hourly_rate
                        : '';
                form.is_active = props.employee.is_active;
            } else {
                form.reset();
                form.npk = '';
                form.full_name = '';
                form.department_id =
                    props.departments.length > 0 ? props.departments[0].id : '';
                form.section_id = '';
                form.job_position = '';
                form.hourly_rate = '';
                form.is_active = true;
            }
            form.clearErrors();
        }
    },
    { immediate: true },
);

function handleClose() {
    emit('update:open', false);
}

function handleSubmit() {
    if (isEdit.value && props.employee) {
        form.put(employeesRoute.update.url(props.employee.id), {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
            },
        });
    } else {
        form.post(employeesRoute.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
            },
        });
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent class="w-full overflow-y-auto sm:max-w-lg" side="right">
            <SheetHeader>
                <SheetTitle>
                    {{ isEdit ? __('Edit Employee') : __('Add New Employee') }}
                </SheetTitle>
                <SheetDescription>
                    {{
                        isEdit
                            ? __('Update details for :npk - :name', {
                                  npk: employee?.npk ?? '',
                                  name: employee?.full_name ?? '',
                              })
                            : __(
                                  'Register a new employee into the factory roster.',
                              )
                    }}
                </SheetDescription>
            </SheetHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4 py-4">
                <!-- NPK Identifier (BR-03: Immutable on Edit) -->
                <div class="space-y-1.5">
                    <Label
                        for="emp-npk"
                        class="flex items-center justify-between"
                    >
                        <span class="flex items-center gap-1.5 font-medium">
                            <span>{{ __('NPK (Employee Number)') }}</span>
                            <span v-if="!isEdit" class="text-red-500">*</span>
                        </span>
                        <Badge
                            v-if="isEdit"
                            variant="secondary"
                            class="flex items-center gap-1 text-[11px] font-normal"
                        >
                            <Lock class="text-muted-foreground size-3" />
                            <span>{{ __('Locked (BR-03)') }}</span>
                        </Badge>
                    </Label>
                    <Input
                        id="emp-npk"
                        v-model="form.npk"
                        type="text"
                        :disabled="isEdit"
                        :placeholder="__('e.g. EMP-10023')"
                        class="disabled:bg-muted/70 font-mono uppercase disabled:cursor-not-allowed"
                        required
                        maxlength="20"
                        data-test="input-emp-npk"
                    />
                    <p v-if="isEdit" class="text-muted-foreground text-xs">
                        {{
                            __(
                                'NPK is permanently locked after registration per system business rule (BR-03).',
                            )
                        }}
                    </p>
                    <InputError :message="form.errors.npk" />
                </div>

                <!-- Full Name -->
                <div class="space-y-1.5">
                    <Label for="emp-name" class="font-medium">
                        {{ __('Full Name') }}
                        <span class="text-red-500">*</span>
                    </Label>
                    <Input
                        id="emp-name"
                        v-model="form.full_name"
                        type="text"
                        :placeholder="__('Full Name')"
                        required
                        maxlength="150"
                        data-test="input-emp-name"
                    />
                    <InputError :message="form.errors.full_name" />
                </div>

                <!-- Department Selector -->
                <div class="space-y-1.5">
                    <Label
                        for="emp-dept"
                        class="flex items-center gap-1.5 font-medium"
                    >
                        <Building2 class="text-muted-foreground size-4" />
                        <span>{{ __('Department') }}</span>
                        <span class="text-red-500">*</span>
                    </Label>
                    <select
                        id="emp-dept"
                        v-model="form.department_id"
                        class="border-input bg-background focus:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus:ring-2 focus:outline-hidden"
                        required
                        data-test="select-emp-dept"
                    >
                        <option value="" disabled>
                            {{ __('Select Department') }}
                        </option>
                        <option
                            v-for="dept in departments"
                            :key="dept.id"
                            :value="dept.id"
                        >
                            {{ dept.code }} — {{ dept.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.department_id" />
                </div>

                <!-- Section Selector (Cascading) -->
                <div class="space-y-1.5">
                    <Label
                        for="emp-section"
                        class="flex items-center gap-1.5 font-medium"
                    >
                        <Layers class="text-muted-foreground size-4" />
                        <span>{{ __('Section') }}</span>
                        <span class="text-red-500">*</span>
                    </Label>
                    <select
                        id="emp-section"
                        v-model="form.section_id"
                        class="border-input bg-background focus:ring-ring disabled:bg-muted/60 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus:ring-2 focus:outline-hidden disabled:cursor-not-allowed"
                        :disabled="availableSections.length === 0"
                        required
                        data-test="select-emp-section"
                    >
                        <option value="" disabled>
                            {{
                                availableSections.length === 0
                                    ? __(
                                          'No sections available in this department',
                                      )
                                    : __('Select Section')
                            }}
                        </option>
                        <option
                            v-for="sec in availableSections"
                            :key="sec.id"
                            :value="sec.id"
                        >
                            {{ sec.code }} — {{ sec.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.section_id" />
                </div>

                <!-- Job Position -->
                <div class="space-y-1.5">
                    <Label for="emp-position" class="font-medium">
                        {{ __('Job Position') }}
                        <span class="text-red-500">*</span>
                    </Label>
                    <Input
                        id="emp-position"
                        v-model="form.job_position"
                        type="text"
                        :placeholder="
                            __(
                                'e.g. Line Operator, Team Leader, Die Technician',
                            )
                        "
                        required
                        maxlength="100"
                        data-test="input-emp-position"
                    />
                    <InputError :message="form.errors.job_position" />
                </div>

                <!-- Custom Hourly Rate (Optional) -->
                <div class="space-y-1.5">
                    <Label for="emp-hourly-rate" class="font-medium">
                        {{ __('Hourly Rate (Rp)') }}
                        <span class="text-muted-foreground text-xs font-normal"
                            >({{ __('Optional') }})</span
                        >
                    </Label>
                    <Input
                        id="emp-hourly-rate"
                        v-model="form.hourly_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        :placeholder="
                            selectedDepartment
                                ? String(selectedDepartment.default_hourly_rate)
                                : '0'
                        "
                        data-test="input-emp-rate"
                    />
                    <p class="text-muted-foreground text-xs">
                        {{
                            __(
                                'Leave empty to use the parent department default rate.',
                            )
                        }}
                        <span
                            v-if="departmentDefaultRateFormatted"
                            class="text-foreground font-medium"
                        >
                            ({{ departmentDefaultRateFormatted }})
                        </span>
                    </p>
                    <InputError :message="form.errors.hourly_rate" />
                </div>

                <!-- Active Status -->
                <div class="flex items-center gap-2 pt-2">
                    <Checkbox
                        id="emp-active"
                        :checked="form.is_active"
                        data-test="checkbox-emp-active"
                        @update:checked="
                            (val: boolean) => (form.is_active = val)
                        "
                    />
                    <Label
                        for="emp-active"
                        class="cursor-pointer text-sm font-medium"
                    >
                        {{ __('Active Status') }}
                    </Label>
                </div>
                <InputError :message="form.errors.is_active" />

                <SheetFooter class="pt-6">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="handleClose"
                    >
                        {{ __('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="btn-save-employee"
                    >
                        {{
                            form.processing
                                ? __('Saving...')
                                : __('Save Employee')
                        }}
                    </Button>
                </SheetFooter>
            </form>
        </SheetContent>
    </Sheet>
</template>
