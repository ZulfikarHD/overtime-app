<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    Info,
    KeyRound,
    Layers,
    Lock,
    Shield,
    UserCheck,
} from '@lucide/vue';
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
import usersRoute from '@/routes/admin/users';

export type DepartmentWithSections = {
    id: number;
    code: string;
    name: string;
    sections: {
        id: number;
        code: string;
        name: string;
        department_id: number;
    }[];
};

export type RoleOption = {
    value: string;
    label: string;
    badgeColor: string;
};

export type UserRecord = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    role_badge_color: string;
    npk?: string | null;
    department_id?: number | null;
    section_id?: number | null;
    is_active: boolean;
    last_login_at?: string | null;
    department?: {
        id: number;
        code: string;
        name: string;
    } | null;
    section?: {
        id: number;
        code: string;
        name: string;
    } | null;
    is_self?: boolean;
    can_delete?: boolean;
};

const props = defineProps<{
    open: boolean;
    user?: UserRecord | null;
    departments: DepartmentWithSections[];
    roles: RoleOption[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const { __ } = useTrans();

const isEdit = computed(() => !!props.user);
const isSelf = computed(() => Boolean(props.user?.is_self));

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'user',
    department_id: '' as string | number,
    section_id: '' as string | number,
    npk: '',
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

// Watch department change: clear section if no longer valid
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

// Populate form when sheet opens
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            if (props.user) {
                form.name = props.user.name;
                form.email = props.user.email;
                form.password = '';
                form.role = props.user.role;
                form.department_id = props.user.department_id ?? '';
                form.section_id = props.user.section_id ?? '';
                form.npk = props.user.npk ?? '';
                form.is_active = props.user.is_active;
            } else {
                form.reset();
                form.name = '';
                form.email = '';
                form.password = '';
                form.role = 'user';
                form.department_id = '';
                form.section_id = '';
                form.npk = '';
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
    if (isEdit.value && props.user) {
        form.put(usersRoute.update.url(props.user.id), {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
            },
        });
    } else {
        form.post(usersRoute.store.url(), {
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
                <div class="flex items-center gap-2">
                    <div
                        class="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-lg"
                    >
                        <UserCheck class="size-4" />
                    </div>
                    <div>
                        <SheetTitle>
                            {{
                                isEdit
                                    ? __('Edit User Account')
                                    : __('Add New User Account')
                            }}
                        </SheetTitle>
                    </div>
                </div>
                <SheetDescription>
                    {{
                        isEdit
                            ? __(
                                  'Update credentials, role permissions, and organizational assignment for this account.',
                              )
                            : __(
                                  'Provision a new system user login and assign their operational role.',
                              )
                    }}
                </SheetDescription>
            </SheetHeader>

            <!-- Self Protection Notice -->
            <div
                v-if="isEdit && isSelf"
                class="mt-3 flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300"
            >
                <Lock class="mt-0.5 size-4 shrink-0 text-amber-600" />
                <div>
                    <span class="font-semibold">{{
                        __('Editing Your Own Account')
                    }}</span>
                    <p class="text-muted-foreground mt-0.5">
                        {{
                            __(
                                'Role and active status are protected from modification to prevent accidental administrator lockout.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-4 py-4">
                <!-- Full Name -->
                <div class="space-y-1.5">
                    <Label for="user-name">
                        {{ __('Full Name') }}
                        <span class="text-red-500">*</span>
                    </Label>
                    <Input
                        id="user-name"
                        v-model="form.name"
                        type="text"
                        :placeholder="__('e.g. Agus Supriyanto')"
                        required
                        data-test="input-user-name"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Email Address -->
                <div class="space-y-1.5">
                    <Label for="user-email">
                        {{ __('Email Address') }}
                        <span class="text-red-500">*</span>
                    </Label>
                    <Input
                        id="user-email"
                        v-model="form.email"
                        type="email"
                        :placeholder="__('e.g. agus.stamping@plant.local')"
                        required
                        data-test="input-user-email"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <!-- Password -->
                <div class="space-y-1.5">
                    <Label
                        for="user-password"
                        class="flex items-center justify-between"
                    >
                        <span>
                            {{ __('Password') }}
                            <span v-if="!isEdit" class="text-red-500">*</span>
                        </span>
                        <span
                            v-if="isEdit"
                            class="text-muted-foreground text-xs"
                        >
                            {{ __('Leave blank to keep current') }}
                        </span>
                    </Label>
                    <div class="relative">
                        <Input
                            id="user-password"
                            v-model="form.password"
                            type="password"
                            :placeholder="
                                isEdit ? '••••••••' : __('Minimum 8 characters')
                            "
                            :required="!isEdit"
                            autocomplete="new-password"
                            data-test="input-user-password"
                        />
                        <KeyRound
                            class="text-muted-foreground pointer-events-none absolute top-2.5 right-3 size-4"
                        />
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Role Selection -->
                <div class="space-y-1.5">
                    <Label for="user-role">
                        {{ __('System Role (RBAC)') }}
                        <span class="text-red-500">*</span>
                    </Label>
                    <select
                        id="user-role"
                        v-model="form.role"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isEdit && isSelf"
                        required
                        data-test="select-user-role"
                    >
                        <option
                            v-for="roleOpt in roles"
                            :key="roleOpt.value"
                            :value="roleOpt.value"
                        >
                            {{ roleOpt.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.role" />

                    <!-- Role Explanation Box -->
                    <div
                        class="bg-muted/50 text-muted-foreground mt-2 rounded-lg border p-2.5 text-xs"
                    >
                        <div
                            v-if="form.role === 'admin'"
                            class="flex items-start gap-2"
                        >
                            <Shield
                                class="mt-0.5 size-4 shrink-0 text-purple-600"
                            />
                            <span>
                                <strong>{{ __('Administrator') }}:</strong>
                                {{
                                    __(
                                        'Full system authority: master data, user accounts, policy thresholds, and all overtime data.',
                                    )
                                }}
                            </span>
                        </div>
                        <div
                            v-else-if="form.role === 'manager'"
                            class="flex items-start gap-2"
                        >
                            <Shield
                                class="mt-0.5 size-4 shrink-0 text-blue-600"
                            />
                            <span>
                                <strong>{{ __('Manager') }}:</strong>
                                {{
                                    __(
                                        'Department-level verification, approvals, and monthly overtime budget planning.',
                                    )
                                }}
                            </span>
                        </div>
                        <div
                            v-else-if="form.role === 'team_leader'"
                            class="flex items-start gap-2"
                        >
                            <Shield
                                class="mt-0.5 size-4 shrink-0 text-green-600"
                            />
                            <span>
                                <strong>{{ __('Team Leader') }}:</strong>
                                {{
                                    __(
                                        'Section-level overtime creation, roster selection, and SPKL document attachment.',
                                    )
                                }}
                            </span>
                        </div>
                        <div v-else class="flex items-start gap-2">
                            <Info
                                class="mt-0.5 size-4 shrink-0 text-slate-500"
                            />
                            <span>
                                <strong>{{ __('Operator / User') }}:</strong>
                                {{
                                    __(
                                        'Read-only personal timesheet overview, profile details, and UI preferences.',
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- NPK (Optional identifier) -->
                <div class="space-y-1.5">
                    <Label
                        for="user-npk"
                        class="flex items-center justify-between"
                    >
                        <span>{{ __('Employee NPK (Optional)') }}</span>
                        <span class="text-muted-foreground text-xs">{{
                            __('Links to roster record')
                        }}</span>
                    </Label>
                    <Input
                        id="user-npk"
                        v-model="form.npk"
                        type="text"
                        :placeholder="__('e.g. EMP-00201')"
                        maxlength="20"
                        data-test="input-user-npk"
                    />
                    <InputError :message="form.errors.npk" />
                </div>

                <!-- Department Selector (Optional Scoping) -->
                <div class="space-y-1.5">
                    <Label
                        for="user-department"
                        class="flex items-center justify-between"
                    >
                        <span>{{ __('Assigned Department') }}</span>
                        <span class="text-muted-foreground text-xs">{{
                            __('Optional, for scoping')
                        }}</span>
                    </Label>
                    <div class="relative">
                        <select
                            id="user-department"
                            v-model="form.department_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
                            data-test="select-user-department"
                        >
                            <option value="">
                                -- {{ __('None (Plant-wide / Unassigned)') }} --
                            </option>
                            <option
                                v-for="dept in departments"
                                :key="dept.id"
                                :value="dept.id"
                            >
                                {{ dept.code }} - {{ dept.name }}
                            </option>
                        </select>
                    </div>
                    <InputError :message="form.errors.department_id" />
                </div>

                <!-- Section Selector (Filtered by Department) -->
                <div class="space-y-1.5">
                    <Label
                        for="user-section"
                        class="flex items-center justify-between"
                    >
                        <span>{{ __('Assigned Section') }}</span>
                        <span
                            v-if="!form.department_id"
                            class="text-muted-foreground text-xs"
                        >
                            {{ __('Select department first') }}
                        </span>
                    </Label>
                    <select
                        id="user-section"
                        v-model="form.section_id"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!form.department_id"
                        data-test="select-user-section"
                    >
                        <option value="">
                            -- {{ __('None / Section Unassigned') }} --
                        </option>
                        <option
                            v-for="sec in availableSections"
                            :key="sec.id"
                            :value="sec.id"
                        >
                            {{ sec.code }} - {{ sec.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.section_id" />
                </div>

                <!-- Active Status Toggle -->
                <div
                    class="bg-muted/30 flex items-start gap-3 rounded-lg border p-3"
                >
                    <Checkbox
                        id="user-is-active"
                        :checked="form.is_active"
                        :disabled="isEdit && isSelf"
                        data-test="checkbox-user-active"
                        @update:checked="form.is_active = $event"
                    />
                    <div class="space-y-0.5">
                        <Label
                            for="user-is-active"
                            class="cursor-pointer text-sm font-medium"
                        >
                            {{ __('Account Active (Can log in)') }}
                        </Label>
                        <p class="text-muted-foreground text-xs">
                            {{
                                __(
                                    'Inactive users are prevented from authenticating and cannot access the system.',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <SheetFooter class="pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        data-test="btn-cancel-user-sheet"
                        @click="handleClose"
                    >
                        {{ __('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="btn-save-user"
                    >
                        {{
                            form.processing
                                ? __('Saving...')
                                : __('Save Account')
                        }}
                    </Button>
                </SheetFooter>
            </form>
        </SheetContent>
    </Sheet>
</template>
