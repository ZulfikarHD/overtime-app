import { onMounted, onUnmounted, ref } from 'vue';

export interface ShiftDetails {
    shiftNumber: 1 | 2 | 3;
    name: string;
    hours: string;
    badgeText: string;
}

export function useShiftInfo() {
    const timeString = ref('');
    const dateString = ref('');
    const currentShift = ref<ShiftDetails>({
        shiftNumber: 1,
        name: 'Shift 1',
        hours: '07:00 – 15:00 WIB',
        badgeText: 'Shift 1: 07:00–15:00 WIB',
    });

    let timer: ReturnType<typeof setInterval> | null = null;

    const updateClock = () => {
        const now = new Date();

        // Format live time in Asia/Jakarta timezone
        timeString.value = new Intl.DateTimeFormat('id-ID', {
            timeZone: 'Asia/Jakarta',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        }).format(now);

        dateString.value = new Intl.DateTimeFormat('id-ID', {
            timeZone: 'Asia/Jakarta',
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric',
        }).format(now);

        // Get hour in Asia/Jakarta
        const jakartaHour = parseInt(
            new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Jakarta',
                hour: 'numeric',
                hour12: false,
            }).format(now),
            10,
        );

        if (jakartaHour >= 7 && jakartaHour < 15) {
            currentShift.value = {
                shiftNumber: 1,
                name: 'Shift 1',
                hours: '07:00 – 15:00 WIB',
                badgeText: 'Shift 1: 07:00–15:00 WIB',
            };
        } else if (jakartaHour >= 15 && jakartaHour < 23) {
            currentShift.value = {
                shiftNumber: 2,
                name: 'Shift 2',
                hours: '15:00 – 23:00 WIB',
                badgeText: 'Shift 2: 15:00–23:00 WIB',
            };
        } else {
            currentShift.value = {
                shiftNumber: 3,
                name: 'Shift 3',
                hours: '23:00 – 07:00 WIB',
                badgeText: 'Shift 3: 23:00–07:00 WIB',
            };
        }
    };

    onMounted(() => {
        updateClock();
        timer = setInterval(updateClock, 1000);
    });

    onUnmounted(() => {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    });

    // Run initial update for immediate SSR / mount readiness
    updateClock();

    return {
        timeString,
        dateString,
        currentShift,
    };
}
