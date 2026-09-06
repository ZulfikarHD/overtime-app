<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Building2, Lock } from '@lucide/vue';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import sections from '@/routes/admin/sections';

export type SectionRecord = {
    id: number;
    department_id: number;
    code: string;
    name: string;
    is_active: boolean;
    employees_count?: number;
    overtime_submissions_count?: number;
};

const props = defineProps<{
    open: boolean;
    section?: SectionRecord | null;
    departmentId?: number | null;
    departmentName?: string | null;
    departmentCode?: string | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const { __ } = useTrans();

const isEdit = computed(() => !!props.section);

const form = useForm({
    department_id: props.departmentId ?? 0,
    code: '',
    name: '',
    is_active: true,
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            if (props.section) {
                form.department_id = props.section.department_id;
                form.code = props.section.code;
                form.name = props.section.name;
                form.is_active = props.section.is_active;
            } else {
                form.reset();
                form.department_id = props.departmentId ?? 0;
                form.code = '';
                form.name = '';
                form.is_active = true;
            }
            form.clearErrors();
        }
    },
    { immediate: true },
);

watch(
    () => props.departmentId,
    (newDeptId) => {
        if (!isEdit.value && newDeptId) {
            form.department_id = newDeptId;
        }
    },
);

function handleClose() {
    emit('update:open', false);
}

function handleSubmit() {
    if (isEdit.value && props.section) {
        form.put(sections.update.url(props.section.id), {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
            },
        });
    } else {
        form.post(sections.store.url(), {
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
                    {{ isEdit ? __('Edit Section') : __('Add Section') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEdit
                            ? __('Edit Section') +
                              `: ${section?.code} - ${section?.name}`
                            : __('Add Section')
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4 py-2">
                <!-- Parent Department Indicator -->
                <div class="space-y-1.5">
                    <Label class="text-muted-foreground text-xs">{{
                        __('Department')
                    }}</Label>
                    <div
                        class="bg-muted/40 flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"
                    >
                        <Building2 class="text-muted-foreground size-4" />
                        <span>{{ departmentName ?? __('Department') }}</span>
                        <Badge
                            v-if="departmentCode"
                            variant="outline"
                            class="font-mono text-xs"
                        >
                            {{ departmentCode }}
                        </Badge>
                    </div>
                </div>

                <!-- Section Code -->
                <div class="space-y-1.5">
                    <Label for="section-code" class="flex items-center gap-1.5">
                        <span>{{ __('Section Code') }}</span>
                        <span v-if="!isEdit" class="text-red-500">*</span>
                        <Lock
                            v-if="isEdit"
                            class="text-muted-foreground size-3.5"
                        />
                    </Label>
                    <Input
                        id="section-code"
                        v-model="form.code"
                        type="text"
                        :disabled="isEdit"
                        :placeholder="__('Section Code')"
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

                <!-- Section Name -->
                <div class="space-y-1.5">
                    <Label for="section-name">
                        {{ __('Section Name') }}
                        <span class="text-red-500">*</span>
                    </Label>
                    <Input
                        id="section-name"
                        v-model="form.name"
                        type="text"
                        :placeholder="__('Section Name')"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Active Status -->
                <div class="flex items-center gap-2 pt-1">
                    <Checkbox
                        id="section-active"
                        :checked="form.is_active"
                        @update:checked="
                            (val: boolean) => (form.is_active = val)
                        "
                    />
                    <Label
                        for="section-active"
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
