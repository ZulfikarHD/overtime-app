<script setup lang="ts">
import { RotateCcw } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTrans } from '@/composables/useTrans';
import { MAX_RANGE_DAYS, daysBetween } from '@/lib/earlyWarning';
import type {
    DepartmentOption,
    InboxFilters,
    SectionOption,
} from '@/lib/earlyWarning';

const ALL = 'all';

const props = defineProps<{
    filters: InboxFilters;
    departments: DepartmentOption[];
    sections: SectionOption[];
    /** Non-admins are locked to their own department. */
    departmentLocked: boolean;
}>();

const emit = defineEmits<{
    change: [filters: InboxFilters];
    reset: [];
}>();

const { __ } = useTrans();

const dateFrom = ref(props.filters.date_from);
const dateTo = ref(props.filters.date_to);

watch(
    () => [props.filters.date_from, props.filters.date_to],
    ([from, to]) => {
        dateFrom.value = from;
        dateTo.value = to;
    },
);

const dateError = computed<string | null>(() => {
    if (!dateFrom.value || !dateTo.value) {
        return __('Choose both dates.');
    }

    const span = daysBetween(dateFrom.value, dateTo.value);

    if (span < 0) {
        return __('The start date must not be after the end date.');
    }

    if (span >= MAX_RANGE_DAYS) {
        return __('The date range may not exceed :days days.', {
            days: MAX_RANGE_DAYS,
        });
    }

    return null;
});

const zoneOptions = computed(() => [
    { value: 'all', label: __('All zones') },
    { value: 'yellow', label: __('Kuning') },
    { value: 'red', label: __('Merah') },
]);

const statusOptions = computed(() => [
    { value: 'all', label: __('All statuses') },
    { value: 'none', label: __('Not followed up yet') },
    { value: 'draft', label: __('Draft') },
    { value: 'sent', label: __('Terkirim') },
]);

function change(patch: Partial<InboxFilters>): void {
    emit('change', { ...props.filters, ...patch, page: 1 });
}

function changeDepartment(value: unknown): void {
    change({
        department_id: value === ALL ? null : Number(value),
        section_id: null,
    });
}

function changeSection(value: unknown): void {
    change({ section_id: value === ALL ? null : Number(value) });
}

function reset(): void {
    dateFrom.value = props.filters.date_from;
    dateTo.value = props.filters.date_to;
    emit('reset');
}

function changeDates(): void {
    if (dateError.value !== null) {
        return;
    }

    change({ date_from: dateFrom.value, date_to: dateTo.value });
}
</script>

<template>
    <div
        class="space-y-3 rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900"
        data-test="alert-filter-bar"
    >
        <div class="flex flex-wrap items-end gap-3">
            <div class="space-y-1">
                <label class="block text-xs font-medium text-slate-500">
                    {{ __('Department') }}
                </label>
                <Select
                    :model-value="
                        filters.department_id === null
                            ? ALL
                            : String(filters.department_id)
                    "
                    :disabled="departmentLocked"
                    @update:model-value="changeDepartment"
                >
                    <SelectTrigger
                        class="h-9 w-52 text-xs"
                        data-test="filter-department"
                    >
                        <SelectValue :placeholder="__('Department')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-if="!departmentLocked" :value="ALL">
                            {{ __('All departments') }}
                        </SelectItem>
                        <SelectItem
                            v-for="department in departments"
                            :key="department.id"
                            :value="String(department.id)"
                        >
                            {{ department.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-medium text-slate-500">
                    {{ __('Section') }}
                </label>
                <Select
                    :model-value="
                        filters.section_id === null
                            ? ALL
                            : String(filters.section_id)
                    "
                    :disabled="filters.department_id === null"
                    @update:model-value="changeSection"
                >
                    <SelectTrigger
                        class="h-9 w-48 text-xs"
                        data-test="filter-section"
                    >
                        <SelectValue :placeholder="__('All sections')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">
                            {{ __('All sections') }}
                        </SelectItem>
                        <SelectItem
                            v-for="section in sections"
                            :key="section.id"
                            :value="String(section.id)"
                        >
                            {{ section.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-1">
                <label
                    class="block text-xs font-medium text-slate-500"
                    for="filter-date-from"
                >
                    {{ __('Date range') }}
                </label>
                <div class="flex items-center gap-2">
                    <Input
                        id="filter-date-from"
                        v-model="dateFrom"
                        type="date"
                        class="h-9 w-36 font-mono text-xs tabular-nums"
                        data-test="filter-date-from"
                        :aria-invalid="dateError !== null"
                        @change="changeDates"
                    />
                    <span class="text-xs text-slate-400">—</span>
                    <Input
                        v-model="dateTo"
                        type="date"
                        class="h-9 w-36 font-mono text-xs tabular-nums"
                        data-test="filter-date-to"
                        :aria-label="__('End date')"
                        :aria-invalid="dateError !== null"
                        @change="changeDates"
                    />
                </div>
            </div>

            <Button
                variant="ghost"
                size="sm"
                class="h-9 text-xs"
                data-test="filter-reset"
                @click="reset"
            >
                <RotateCcw class="mr-1 size-3.5" />{{ __('Reset') }}
            </Button>
        </div>

        <p
            v-if="dateError !== null"
            class="text-xs text-[#cc0000]"
            role="alert"
            data-test="filter-date-error"
        >
            {{ dateError }}
        </p>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
            <div
                class="flex flex-wrap items-center gap-1"
                role="group"
                :aria-label="__('Zone')"
            >
                <button
                    v-for="option in zoneOptions"
                    :key="option.value"
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                    :class="
                        filters.zone === option.value
                            ? 'border-[#cc0000] bg-[#cc0000] text-white'
                            : 'border-slate-200 text-slate-600 hover:border-slate-400 dark:border-slate-700 dark:text-slate-300'
                    "
                    :aria-pressed="filters.zone === option.value"
                    :data-test="`filter-zone-${option.value}`"
                    @click="
                        change({ zone: option.value as InboxFilters['zone'] })
                    "
                >
                    {{ option.label }}
                </button>
            </div>

            <div
                class="flex flex-wrap items-center gap-1"
                role="group"
                :aria-label="__('Follow-up status')"
            >
                <button
                    v-for="option in statusOptions"
                    :key="option.value"
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                    :class="
                        filters.status === option.value
                            ? 'border-[#cc0000] bg-[#cc0000] text-white'
                            : 'border-slate-200 text-slate-600 hover:border-slate-400 dark:border-slate-700 dark:text-slate-300'
                    "
                    :aria-pressed="filters.status === option.value"
                    :data-test="`filter-status-${option.value}`"
                    @click="
                        change({
                            status: option.value as InboxFilters['status'],
                        })
                    "
                >
                    {{ option.label }}
                </button>
            </div>
        </div>
    </div>
</template>
