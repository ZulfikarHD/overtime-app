<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';
import departments from '@/routes/admin/departments';

export type DepartmentRecord = {
    id: number;
    code: string;
    name: string;
    cost_center_code: string;
    default_hourly_rate: string | number;
    is_active: boolean;
    sections_count?: number;
    employees_count?: number;
    overtime_submissions_count?: number;
};

const props = defineProps<{
    open: boolean;
    department?: DepartmentRecord | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const { __ } = useTrans();

const isEdit = computed(() => !!props.department);

const form = useForm({
    code: '',
    name: '',
    cost_center_code: '',
    default_hourly_rate: '' as string | number,
    is_active: true,
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            if (props.department) {
                form.code = props.department.code;
                form.name = props.department.name;
                form.cost_center_code = props.department.cost_center_code;
                form.default_hourly_rate = props.department.default_hourly_rate;
                form.is_active = props.department.is_active;
            } else {
                form.reset();
                form.code = '';
                form.name = '';
                form.cost_center_code = '';
                form.default_hourly_rate = '';
                form.is_active = true;
            }
            form.clearErrors();
        }
    },
    { immediate: true },
);

const formattedRatePreview = computed(() => {
    return formatRupiah(form.default_hourly_rate);
});

function handleClose() {
    emit('update:open', false);
}

function handleSubmit() {
    if (isEdit.value && props.department) {
        form.put(departments.update.url(props.department.id), {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
            },
        });
    } else {
        form.post(departments.store.url(), {
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
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ isEdit ? __('Edit Department') : __('Add Department') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEdit
                            ? __('Edit Department') +
                              `: ${department?.code} - ${department?.name}`
                            : __('Add Department')
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4 py-2">
                <!-- Department Code -->
                <div class="space-y-1.5">
                    <Label for="dept-code" class="flex items-center gap-1.5">
                        <span>{{ __('Department Code') }}</span>
                        <span v-if="!isEdit" class="text-red-500">*</span>
                        <Lock
                            v-if="isEdit"
                            class="text-muted-foreground size-3.5"
                        />
                    </Label>
                    <Input
                        id="dept-code"
                        v-model="form.code"
                        type="text"
                        :disabled="isEdit"
                        :placeholder="__('Department Code')"
                        class="disabled:bg-muted/70 font-mono uppercase disabled:cursor-not-allowed"
                        required
                    />
                    <p v-if="isEdit" class="text-muted-foreground text-xs">
                        {{
                            __(
                                'Code is permanently locked after creation (BR-03).',
                            )
                        }}
                    </p>
                    <InputError :message="form.errors.code" />
                </div>

                <!-- Department Name -->
                <div class="space-y-1.5">
                    <Label for="dept-name">
                        {{ __('Department Name') }}
                        <span class="text-red-500">*</span>
                    </Label>
                    <Input
                        id="dept-name"
                        v-model="form.name"
                        type="text"
                        :placeholder="__('Department Name')"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Cost Center Code -->
                <div class="space-y-1.5">
                    <Label for="dept-cost-center">
                        {{ __('Cost Center Code') }}
                        <span class="text-red-500">*</span>
                    </Label>
                    <Input
                        id="dept-cost-center"
                        v-model="form.cost_center_code"
                        type="text"
                        :placeholder="__('Cost Center Code')"
                        class="font-mono uppercase"
                        required
                    />
                    <InputError :message="form.errors.cost_center_code" />
                </div>

                <!-- Default Hourly Rate (Rp) -->
                <div class="space-y-1.5">
                    <Label for="dept-rate">
                        {{ __('Default Hourly Rate (Rp)') }}
                        <span class="text-red-500">*</span>
                    </Label>
                    <Input
                        id="dept-rate"
                        v-model="form.default_hourly_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="25000"
                        required
                    />
                    <div
                        class="text-muted-foreground flex items-center justify-between text-xs"
                    >
                        <span>{{ __('Standard Rate') }}:</span>
                        <span class="text-foreground font-mono font-medium">{{
                            formattedRatePreview
                        }}</span>
                    </div>
                    <InputError :message="form.errors.default_hourly_rate" />
                </div>

                <!-- Active Status -->
                <div class="flex items-center gap-2 pt-1">
                    <Checkbox
                        id="dept-active"
                        :checked="form.is_active"
                        @update:checked="
                            (val: boolean) => (form.is_active = val)
                        "
                    />
                    <Label
                        for="dept-active"
                        class="cursor-pointer text-sm font-medium"
                    >
                        {{ __('Active Status') }}
                    </Label>
                </div>
                <InputError :message="form.errors.is_active" />

                <DialogFooter class="pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="handleClose"
                    >
                        {{ __('Cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? __('Saving...') : __('Save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
