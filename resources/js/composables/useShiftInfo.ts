import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

export interface ShiftWindow {
    number: 1 | 2 | 3;
    start: string;
    end: string;
}

export interface SharedShiftSchedule {
    id: number | null;
    name: string;
    effective_from: string;
    effective_until: string | null;
    shifts: ShiftWindow[];
}

export interface ShiftDetails {
    shiftNumber: 1 | 2 | 3;
    name: string;
    hours: string;
    badgeText: string;
}

const DEFAULT_SHIFTS: ShiftWindow[] = [
    { number: 1, start: '07:00', end: '15:00' },
    { number: 2, start: '15:00', end: '23:00' },
    { number: 3, start: '23:00', end: '07:00' },
];

function parseMinutes(hhmm: string): number {
    const [h, m] = hhmm.split(':').map((part) => parseInt(part, 10));
    return h * 60 + (m || 0);
}

function isWithinWindow(minutes: number, start: string, end: string): boolean {
    const startMin = parseMinutes(start);
    const endMin = parseMinutes(end);

    if (startMin === endMin) {
        return false;
    }

    // Overnight window (e.g. 23:00 – 07:00)
    if (startMin > endMin) {
        return minutes >= startMin || minutes < endMin;
    }

    return minutes >= startMin && minutes < endMin;
}

function resolveShift(
    shifts: ShiftWindow[],
    jakartaMinutes: number,
): ShiftDetails {
    const windows = shifts.length === 3 ? shifts : DEFAULT_SHIFTS;

    for (const window of windows) {
        if (isWithinWindow(jakartaMinutes, window.start, window.end)) {
            const hours = `${window.start} – ${window.end} WIB`;
            return {
                shiftNumber: window.number,
                name: `Shift ${window.number}`,
                hours,
                badgeText: `Shift ${window.number}: ${window.start}–${window.end} WIB`,
            };
        }
    }

    const fallback = windows[0] ?? DEFAULT_SHIFTS[0];
    const hours = `${fallback.start} – ${fallback.end} WIB`;
    return {
        shiftNumber: fallback.number,
        name: `Shift ${fallback.number}`,
        hours,
        badgeText: `Shift ${fallback.number}: ${fallback.start}–${fallback.end} WIB`,
    };
}

export function useShiftInfo() {
    const page = usePage();
    const schedule = computed(() => {
        const shared = page.props.shiftSchedule as
            | SharedShiftSchedule
            | undefined;
        return shared?.shifts?.length === 3 ? shared.shifts : DEFAULT_SHIFTS;
    });

    const timeString = ref('');
    const dateString = ref('');
    const currentShift = ref<ShiftDetails>(
        resolveShift(DEFAULT_SHIFTS, 7 * 60),
    );

    let timer: ReturnType<typeof setInterval> | null = null;

    const updateClock = () => {
        const now = new Date();

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

        const hour = parseInt(
            new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Jakarta',
                hour: 'numeric',
                hour12: false,
            }).format(now),
            10,
        );
        const minute = parseInt(
            new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Jakarta',
                minute: 'numeric',
            }).format(now),
            10,
        );

        currentShift.value = resolveShift(schedule.value, hour * 60 + minute);
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

    updateClock();

    return {
        timeString,
        dateString,
        currentShift,
        schedule,
    };
}
