<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { BellRing, Save } from '@lucide/vue';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { update } from '@/routes/admin/alert-cause-mappings';

export type AlertCauseMappingRow = {
    cause: string;
    label: string;
    description: string;
    pic_department_id: number | null;
    is_active: boolean;
    /** True while no admin has saved this cause yet (the configured default is shown). */
    is_default: boolean;
};

type DepartmentChoice = { id: number; name: string };

const props = defineProps<{
    mappings: AlertCauseMappingRow[];
    departments: DepartmentChoice[];
}>();

const { __ } = useTrans();

function rowsFromProps() {
    return props.mappings.map((row) => ({
        cause: row.cause,
        pic_department_id: (row.pic_department_id ?? '') as number | string,
        is_active: row.is_active,
    }));
}

const form = useForm({ mappings: rowsFromProps() });

watch(
    () => props.mappings,
    () => {
        form.mappings = rowsFromProps();
    },
);

function errorFor(index: number): string | undefined {
    const errors = form.errors as Record<string, string | undefined>;

    return (
        errors[`mappings.${index}.pic_department_id`] ??
        errors[`mappings.${index}.cause`]
    );
}

function save(): void {
    form.transform((data) => ({
        mappings: data.mappings.map((row) => ({
            ...row,
            pic_department_id:
                row.pic_department_id === '' ? null : row.pic_department_id,
        })),
    })).put(update.url(), { preserveScroll: true });
}

const selectClass =
    'border-input dark:bg-input/30 h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-60';
</script>

<template>
    <Card class="border-border/70 shadow-2xs" data-test="alert-cause-pic-card">
        <CardHeader class="pb-3">
            <CardTitle
                class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100"
            >
                <BellRing class="size-4 text-[#cc0000]" />
                <span>{{ __('PIC per Penyebab') }}</span>
            </CardTitle>
            <CardDescription class="text-xs">
                {{
                    __(
                        'The Early Warning follow-up proposes this department as "Who" for the main cause it detects. Without a PIC (or when it is switched off) the alert’s own department is used. Changes apply to new suggestions only.',
                    )
                }}
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-4 pt-0">
            <div
                v-for="(row, index) in form.mappings"
                :key="row.cause"
                class="grid items-start gap-3 border-b pb-3 last:border-b-0 last:pb-0 md:grid-cols-[1fr_minmax(0,16rem)_auto]"
                :data-test="`cause-row-${row.cause}`"
            >
                <div class="space-y-0.5">
                    <p
                        class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{ mappings[index]?.label }}
                    </p>
                    <p class="text-muted-foreground text-xs">
                        {{ mappings[index]?.description }}
                    </p>
                </div>

                <div class="space-y-1">
                    <select
                        v-model="row.pic_department_id"
                        :class="selectClass"
                        :aria-label="__('PIC department')"
                        :data-test="`cause-pic-${row.cause}`"
                    >
                        <option value="">
                            {{ __('Own department of the alert') }}
                        </option>
                        <option
                            v-for="department in departments"
                            :key="department.id"
                            :value="department.id"
                        >
                            {{ department.name }}
                        </option>
                    </select>
                    <InputError :message="errorFor(index)" />
                </div>

                <label
                    class="flex items-center gap-2 pt-2 text-xs font-medium whitespace-nowrap"
                >
                    <input
                        v-model="row.is_active"
                        type="checkbox"
                        class="size-4 cursor-pointer accent-[#cc0000]"
                        :data-test="`cause-active-${row.cause}`"
                    />
                    {{ __('Active') }}
                </label>
            </div>

            <div class="flex justify-end border-t pt-3">
                <Button
                    type="button"
                    class="cursor-pointer gap-1.5 bg-[#cc0000] text-white hover:bg-[#b30000]"
                    :disabled="form.processing"
                    data-test="cause-pic-save"
                    @click="save"
                >
                    <Save class="size-4" />
                    {{ __('Save') }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
