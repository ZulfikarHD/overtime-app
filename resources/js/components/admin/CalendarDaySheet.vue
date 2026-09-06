<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Calendar as CalendarIcon,
    CheckCircle2,
    Info,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import { update as updateCalendarRoute } from '@/routes/admin/calendar';

export type CalendarDayRecord = {
    calendar_date: string;
    day_type: 'HKN' | 'HLR';
    is_holiday: boolean;
    holiday_name: string | null;
    description: string | null;
    day_of_week?: number;
    day_number?: number;
};

const props = defineProps<{
    open: boolean;
    day: CalendarDayRecord | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'updated'): void;
}>();

const { __ } = useTrans();

const isSubmitting = ref(false);
const formError = ref<string | null>(null);

const dayType = ref<'HKN' | 'HLR'>('HKN');
const holidayName = ref('');
const description = ref('');

watch(
    () => props.day,
    (val) => {
        if (val) {
            dayType.value = val.day_type;
            holidayName.value = val.holiday_name || '';
            description.value = val.description || '';
            formError.value = null;
        }
    },
    { immediate: true },
);

function handleToggleDayType(type: 'HKN' | 'HLR') {
    dayType.value = type;
    if (type === 'HKN') {
        holidayName.value = '';
    } else if (type === 'HLR' && !holidayName.value) {
        if (props.day) {
            const dateObj = new Date(props.day.calendar_date);
            const isSat = dateObj.getDay() === 6;
            const isSun = dateObj.getDay() === 0;
            if (isSat) {
                holidayName.value = 'Sabtu Libur';
            } else if (isSun) {
                holidayName.value = 'Minggu Libur';
            } else {
                holidayName.value = 'Hari Libur / Istirahat';
            }
        }
    }
}

const formattedHeaderDate = computed(() => {
    if (!props.day) return '';
    try {
        const parts = props.day.calendar_date.split('-');
        if (parts.length === 3) {
            const dateObj = new Date(
                parseInt(parts[0], 10),
                parseInt(parts[1], 10) - 1,
                parseInt(parts[2], 10),
            );
            return new Intl.DateTimeFormat('id-ID', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            }).format(dateObj);
        }
    } catch {
        // Fallback
    }
    return props.day.calendar_date;
});

function handleSubmit() {
    if (!props.day) return;

    if (dayType.value === 'HLR' && !holidayName.value.trim()) {
        formError.value = __('Holiday name is required for rest days (HLR).');
        return;
    }

    isSubmitting.value = true;
    formError.value = null;

    router.put(
        updateCalendarRoute.url(props.day.calendar_date),
        {
            day_type: dayType.value,
            is_holiday: dayType.value === 'HLR',
            holiday_name:
                dayType.value === 'HLR' ? holidayName.value.trim() : null,
            description: description.value.trim() || null,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isSubmitting.value = false;
                emit('update:open', false);
                emit('updated');
            },
            onError: (errors) => {
                isSubmitting.value = false;
                formError.value =
                    Object.values(errors)[0] ||
                    __('Failed to update calendar day.');
            },
        },
    );
}

function handleClose() {
    emit('update:open', false);
}
</script>

<template>
    <Sheet
        :open="open"
        @update:open="(val: boolean) => emit('update:open', val)"
    >
        <SheetContent
            side="right"
            class="flex w-full flex-col sm:max-w-md"
            data-test="calendar-day-sheet"
        >
            <SheetHeader class="border-border border-b pb-4 text-left">
                <div class="flex items-center gap-2">
                    <CalendarIcon class="text-primary size-5" />
                    <SheetTitle class="text-foreground text-lg font-bold">
                        {{ __('Edit Day Classification') }}
                    </SheetTitle>
                </div>
                <SheetDescription
                    v-if="day"
                    class="text-foreground/90 text-sm font-medium"
                >
                    <span class="capitalize">{{ formattedHeaderDate }}</span>
                    <span
                        class="text-muted-foreground ml-1.5 font-mono text-xs"
                    >
                        ({{ day.calendar_date }})
                    </span>
                </SheetDescription>
            </SheetHeader>

            <div class="flex-1 space-y-5 overflow-y-auto py-5 text-xs">
                <!-- Informational Hard-UX Warning Banner -->
                <div
                    class="flex items-start gap-3 rounded-lg border border-blue-500/20 bg-blue-500/10 p-3.5 text-blue-900 dark:text-blue-200"
                >
                    <Info
                        class="mt-0.5 size-4 shrink-0 text-blue-600 dark:text-blue-400"
                    />
                    <div class="space-y-1">
                        <p class="leading-tight font-semibold">
                            {{ __('Operational Regulatory Notice') }}
                        </p>
                        <p class="text-[11px] leading-relaxed opacity-90">
                            {{ __('Calendar Non-Retroactive Notice') }}
                        </p>
                    </div>
                </div>

                <!-- Form Error Banner -->
                <div
                    v-if="formError"
                    class="border-destructive/30 bg-destructive/10 text-destructive flex items-center gap-2 rounded-md border p-3"
                >
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{{ formError }}</span>
                </div>

                <!-- Day Type Selector (Segmented Control) -->
                <div class="space-y-2">
                    <Label class="text-foreground text-xs font-semibold">
                        {{ __('Operational Day Type') }}
                    </Label>
                    <div class="grid grid-cols-2 gap-2">
                        <!-- HKN Button -->
                        <button
                            type="button"
                            :class="[
                                dayType === 'HKN'
                                    ? 'border-primary bg-primary/10 text-primary font-bold shadow-xs'
                                    : 'border-border/70 hover:bg-muted/50 text-muted-foreground border',
                                'flex cursor-pointer items-center justify-center gap-2 rounded-lg p-3 text-xs transition-colors',
                            ]"
                            data-test="btn-day-type-hkn"
                            @click="handleToggleDayType('HKN')"
                        >
                            <CheckCircle2
                                v-if="dayType === 'HKN'"
                                class="text-primary size-4"
                            />
                            <div class="text-left">
                                <div class="font-bold">HKN</div>
                                <div class="text-[10px] font-normal opacity-80">
                                    {{ __('Normal Workday') }}
                                </div>
                            </div>
                        </button>

                        <!-- HLR Button -->
                        <button
                            type="button"
                            :class="[
                                dayType === 'HLR'
                                    ? 'border-rose-500 bg-rose-500/10 font-bold text-rose-700 shadow-xs dark:text-rose-300'
                                    : 'border-border/70 hover:bg-muted/50 text-muted-foreground border',
                                'flex cursor-pointer items-center justify-center gap-2 rounded-lg p-3 text-xs transition-colors',
                            ]"
                            data-test="btn-day-type-hlr"
                            @click="handleToggleDayType('HLR')"
                        >
                            <XCircle
                                v-if="dayType === 'HLR'"
                                class="size-4 text-rose-600 dark:text-rose-400"
                            />
                            <div class="text-left">
                                <div class="font-bold">HLR</div>
                                <div class="text-[10px] font-normal opacity-80">
                                    {{ __('Holiday / Rest Day') }}
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Holiday Name (Visible and Enabled if HLR) -->
                <div v-if="dayType === 'HLR'" class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <Label
                            for="holiday_name"
                            class="text-foreground text-xs font-semibold"
                        >
                            {{ __('Holiday / Rest Day Name') }}
                            <span class="text-rose-500">*</span>
                        </Label>
                        <Badge variant="outline" class="text-[10px]">
                            HLR
                        </Badge>
                    </div>
                    <Input
                        id="holiday_name"
                        v-model="holidayName"
                        type="text"
                        :placeholder="__('Holiday Name (Required for HLR)')"
                        class="text-xs"
                        data-test="input-holiday-name"
                    />
                    <p class="text-muted-foreground text-[10px]">
                        {{
                            __(
                                'This name will be attached to SPKL forms and overtime slips.',
                            )
                        }}
                    </p>
                </div>

                <!-- Description / Catatan Input -->
                <div class="space-y-1.5">
                    <Label
                        for="calendar_description"
                        class="text-foreground text-xs font-semibold"
                    >
                        {{ __('Additional Description / Notes (Optional)') }}
                    </Label>
                    <textarea
                        id="calendar_description"
                        v-model="description"
                        rows="3"
                        :placeholder="__('Description / Notes')"
                        class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-16 w-full rounded-md border px-3 py-2 text-xs shadow-xs focus-visible:ring-2 focus-visible:outline-hidden"
                        data-test="input-calendar-description"
                    />
                </div>
            </div>

            <SheetFooter class="border-border border-t pt-4">
                <div class="flex w-full items-center justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-xs"
                        :disabled="isSubmitting"
                        @click="handleClose"
                    >
                        {{ __('Cancel') }}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="text-xs"
                        :disabled="isSubmitting"
                        data-test="btn-save-calendar-day"
                        @click="handleSubmit"
                    >
                        <span v-if="isSubmitting">{{ __('Saving...') }}</span>
                        <span v-else>{{ __('Save Changes') }}</span>
                    </Button>
                </div>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
