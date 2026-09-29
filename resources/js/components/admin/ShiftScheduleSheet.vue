<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Clock, Info } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import shiftSchedulesRoute from '@/routes/admin/shift-schedules';

export type ShiftScheduleRecord = {
    id?: number;
    name: string;
    effective_from: string;
    effective_until?: string | null;
    shift_1_start: string;
    shift_1_end: string;
    shift_2_start: string;
    shift_2_end: string;
    shift_3_start: string;
    shift_3_end: string;
};

const props = defineProps<{
    open: boolean;
    schedule: ShiftScheduleRecord | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const { __ } = useTrans();

const form = useForm({
    name: 'Standard Plant Shift',
    effective_from: new Date().toISOString().slice(0, 10),
    effective_until: '' as string,
    shift_1_start: '07:00',
    shift_1_end: '15:00',
    shift_2_start: '15:00',
    shift_2_end: '23:00',
    shift_3_start: '23:00',
    shift_3_end: '07:00',
});

const isSubmitting = ref(false);
const formError = ref<string | null>(null);

const isEditing = computed(() => Boolean(props.schedule?.id));

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen || !props.schedule) {
            return;
        }

        form.clearErrors();
        formError.value = null;
        form.name = props.schedule.name;
        form.effective_from = props.schedule.effective_from;
        form.effective_until = props.schedule.effective_until ?? '';
        form.shift_1_start = props.schedule.shift_1_start;
        form.shift_1_end = props.schedule.shift_1_end;
        form.shift_2_start = props.schedule.shift_2_start;
        form.shift_2_end = props.schedule.shift_2_end;
        form.shift_3_start = props.schedule.shift_3_start;
        form.shift_3_end = props.schedule.shift_3_end;
    },
);

function closeSheet() {
    emit('update:open', false);
}

function syncChainFromShift1End() {
    form.shift_2_start = form.shift_1_end;
}

function syncChainFromShift2End() {
    form.shift_3_start = form.shift_2_end;
}

function syncChainFromShift3End() {
    form.shift_1_start = form.shift_3_end;
}

function submit() {
    formError.value = null;
    isSubmitting.value = true;

    const payload = {
        ...form.data(),
        effective_until: form.effective_until || null,
    };

    if (isEditing.value && props.schedule?.id) {
        form.transform(() => payload).put(
            shiftSchedulesRoute.update.url(props.schedule.id),
            {
                preserveScroll: true,
                onSuccess: () => {
                    emit('saved');
                    closeSheet();
                },
                onError: () => {
                    formError.value = __(
                        'Please correct the highlighted fields and try again.',
                    );
                },
                onFinish: () => {
                    isSubmitting.value = false;
                    form.transform((data) => data);
                },
            },
        );
        return;
    }

    form.transform(() => payload).post(shiftSchedulesRoute.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            closeSheet();
        },
        onError: () => {
            formError.value = __(
                'Please correct the highlighted fields and try again.',
            );
        },
        onFinish: () => {
            isSubmitting.value = false;
            form.transform((data) => data);
        },
    });
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent
            side="right"
            class="flex w-full flex-col overflow-y-auto sm:max-w-lg"
            data-test="shift-schedule-sheet"
        >
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <Clock class="size-4 text-[#cc0000]" />
                    {{
                        isEditing
                            ? __('Edit Shift Schedule')
                            : __('New Shift Schedule')
                    }}
                </SheetTitle>
                <SheetDescription>
                    {{
                        __(
                            'Adjust Shift 1 / 2 / 3 times for a dated period. Plant shift times may change up to 3 times per year.',
                        )
                    }}
                </SheetDescription>
            </SheetHeader>

            <form
                class="mt-4 flex flex-1 flex-col gap-5"
                @submit.prevent="submit"
            >
                <div
                    v-if="formError"
                    class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                >
                    {{ formError }}
                </div>

                <div class="space-y-1.5">
                    <Label for="shift-schedule-name">{{
                        __('Schedule Name')
                    }}</Label>
                    <Input
                        id="shift-schedule-name"
                        v-model="form.name"
                        data-test="input-shift-schedule-name"
                        :placeholder="__('e.g. Standard 2026 / Ramadan 2026')"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="shift-effective-from">{{
                            __('Effective From')
                        }}</Label>
                        <Input
                            id="shift-effective-from"
                            v-model="form.effective_from"
                            type="date"
                            data-test="input-shift-effective-from"
                        />
                        <InputError :message="form.errors.effective_from" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="shift-effective-until">{{
                            __('Effective Until (optional)')
                        }}</Label>
                        <Input
                            id="shift-effective-until"
                            v-model="form.effective_until"
                            type="date"
                            data-test="input-shift-effective-until"
                        />
                        <InputError :message="form.errors.effective_until" />
                    </div>
                </div>

                <div
                    class="flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50/80 p-3 text-[11px] text-slate-600 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-300"
                >
                    <Info class="mt-0.5 size-3.5 shrink-0 text-slate-400" />
                    <p>
                        {{
                            __(
                                'Leave Effective Until empty for an open-ended (current) period. Creating a new period auto-closes the previous open schedule.',
                            )
                        }}
                    </p>
                </div>

                <div class="space-y-3">
                    <h3
                        class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                    >
                        {{ __('Shift Windows (WIB)') }}
                    </h3>

                    <div
                        class="grid grid-cols-[auto_1fr_auto_1fr] items-center gap-2 rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                    >
                        <span
                            class="text-xs font-bold text-slate-700 dark:text-slate-200"
                            >{{ __('Shift 1') }}</span
                        >
                        <Input
                            id="shift-1-start"
                            v-model="form.shift_1_start"
                            type="time"
                            data-test="input-shift-1-start"
                            @change="syncChainFromShift3End"
                        />
                        <span class="text-center text-[10px] text-slate-400"
                            >→</span
                        >
                        <Input
                            id="shift-1-end"
                            v-model="form.shift_1_end"
                            type="time"
                            data-test="input-shift-1-end"
                            @change="syncChainFromShift1End"
                        />
                        <InputError
                            class="col-span-4"
                            :message="
                                form.errors.shift_1_start ||
                                form.errors.shift_1_end
                            "
                        />
                    </div>

                    <div
                        class="grid grid-cols-[auto_1fr_auto_1fr] items-center gap-2 rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                    >
                        <span
                            class="text-xs font-bold text-slate-700 dark:text-slate-200"
                            >{{ __('Shift 2') }}</span
                        >
                        <Input
                            id="shift-2-start"
                            v-model="form.shift_2_start"
                            type="time"
                            data-test="input-shift-2-start"
                        />
                        <span class="text-center text-[10px] text-slate-400"
                            >→</span
                        >
                        <Input
                            id="shift-2-end"
                            v-model="form.shift_2_end"
                            type="time"
                            data-test="input-shift-2-end"
                            @change="syncChainFromShift2End"
                        />
                        <InputError
                            class="col-span-4"
                            :message="
                                form.errors.shift_2_start ||
                                form.errors.shift_2_end
                            "
                        />
                    </div>

                    <div
                        class="grid grid-cols-[auto_1fr_auto_1fr] items-center gap-2 rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                    >
                        <span
                            class="text-xs font-bold text-slate-700 dark:text-slate-200"
                            >{{ __('Shift 3') }}</span
                        >
                        <Input
                            id="shift-3-start"
                            v-model="form.shift_3_start"
                            type="time"
                            data-test="input-shift-3-start"
                        />
                        <span class="text-center text-[10px] text-slate-400"
                            >→</span
                        >
                        <Input
                            id="shift-3-end"
                            v-model="form.shift_3_end"
                            type="time"
                            data-test="input-shift-3-end"
                            @change="syncChainFromShift3End"
                        />
                        <InputError
                            class="col-span-4"
                            :message="
                                form.errors.shift_3_start ||
                                form.errors.shift_3_end
                            "
                        />
                    </div>
                </div>

                <SheetFooter class="mt-auto gap-2 sm:justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="isSubmitting"
                        @click="closeSheet"
                    >
                        {{ __('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        class="bg-[#cc0000] text-white hover:bg-[#b30000]"
                        :disabled="isSubmitting"
                        data-test="btn-save-shift-schedule"
                    >
                        {{
                            isSubmitting
                                ? __('Saving...')
                                : isEditing
                                  ? __('Update Schedule')
                                  : __('Save Schedule')
                        }}
                    </Button>
                </SheetFooter>
            </form>
        </SheetContent>
    </Sheet>
</template>
