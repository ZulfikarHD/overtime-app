import { ref, watch, type Ref } from 'vue';
import { show as calendarShow } from '@/routes/api/calendar';

export type DayType = 'HKN' | 'HLR';

export interface CalendarDayInfo {
    date: string;
    day_type: DayType;
    is_holiday: boolean;
    holiday_name: string | null;
    description: string | null;
}

export function useCalendarDayType(
    dateRef: Ref<string>,
    initialDayType: DayType = 'HKN',
) {
    const dayType = ref<DayType>(initialDayType);
    const isHoliday = ref(false);
    const holidayName = ref<string | null>(null);
    const isOverridden = ref(false);
    const isLoading = ref(false);

    async function fetchClassification(targetDate: string) {
        if (!targetDate) {
            return;
        }
        isLoading.value = true;
        try {
            const url = calendarShow.url({ date: targetDate });
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (response.ok) {
                const data: CalendarDayInfo = await response.json();
                if (!isOverridden.value) {
                    dayType.value = data.day_type;
                }
                isHoliday.value = data.is_holiday;
                holidayName.value = data.holiday_name;
            }
        } catch (error) {
            console.error('Failed to fetch calendar day type:', error);
        } finally {
            isLoading.value = false;
        }
    }

    function toggleOverride() {
        isOverridden.value = true;
        dayType.value = dayType.value === 'HKN' ? 'HLR' : 'HKN';
    }

    function setDayType(newType: DayType) {
        isOverridden.value = true;
        dayType.value = newType;
    }

    function resetOverride() {
        isOverridden.value = false;
        void fetchClassification(dateRef.value);
    }

    watch(dateRef, (newDate, oldDate) => {
        if (newDate !== oldDate) {
            isOverridden.value = false;
            void fetchClassification(newDate);
        }
    });

    return {
        dayType,
        isHoliday,
        holidayName,
        isOverridden,
        isLoading,
        toggleOverride,
        setDayType,
        resetOverride,
        fetchClassification,
    };
}
