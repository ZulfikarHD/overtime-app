/**
 * Shared helpers of the DateRangePicker. All dates are calendar days (`Y-m-d`, no timezone).
 */
export type DateRangePresetKey =
    | 'today'
    | 'last7'
    | 'last30'
    | 'thisMonth'
    | 'lastMonth';

export interface DateRangeValue {
    from: string;
    to: string;
}

export interface DateRangePreset extends DateRangeValue {
    key: DateRangePresetKey;
}

const DAY_MS = 86_400_000;

/**
 * Whole days between two `Y-m-d` dates (inclusive count is `result + 1`).
 */
export function daysBetween(from: string, to: string): number {
    const start = Date.parse(`${from}T00:00:00Z`);
    const end = Date.parse(`${to}T00:00:00Z`);

    return Math.round((end - start) / DAY_MS);
}

function toIsoDate(time: number): string {
    return new Date(time).toISOString().slice(0, 10);
}

/**
 * Add (or subtract) whole days to a `Y-m-d` date.
 */
export function addDays(date: string, days: number): string {
    return toIsoDate(Date.parse(`${date}T00:00:00Z`) + days * DAY_MS);
}

/**
 * Today as `Y-m-d` in WIB (Asia/Jakarta), independent of the browser timezone.
 */
export function todayInJakarta(now: Date = new Date()): string {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(now);
}

/**
 * The quick ranges offered next to the date inputs, relative to `today`.
 */
export function dateRangePresets(today: string): DateRangePreset[] {
    const [year, month] = today.split('-').map(Number);
    const monthStart = `${year}-${String(month).padStart(2, '0')}-01`;
    const lastMonthEnd = addDays(monthStart, -1);

    return [
        { key: 'today', from: today, to: today },
        { key: 'last7', from: addDays(today, -6), to: today },
        { key: 'last30', from: addDays(today, -29), to: today },
        { key: 'thisMonth', from: monthStart, to: today },
        {
            key: 'lastMonth',
            from: `${lastMonthEnd.slice(0, 7)}-01`,
            to: lastMonthEnd,
        },
    ];
}

export interface YearMonth {
    year: number;
    /** 1-12 */
    month: number;
}

/**
 * The year-month of a `Y-m-d` date.
 */
export function yearMonthOf(date: string): YearMonth {
    const [year, month] = date.split('-').map(Number);

    return { year, month };
}

/**
 * Move a year-month forward (positive) or backward (negative) by whole months.
 */
export function shiftMonth(value: YearMonth, delta: number): YearMonth {
    const index = value.year * 12 + (value.month - 1) + delta;

    return { year: Math.floor(index / 12), month: (index % 12) + 1 };
}

/**
 * The 6 x 7 grid of `Y-m-d` days that shows a month, weeks start on Monday.
 * Leading and trailing days of the neighbouring months fill the grid.
 */
export function monthGrid({ year, month }: YearMonth): string[] {
    const first = `${year}-${String(month).padStart(2, '0')}-01`;
    const weekday = new Date(`${first}T00:00:00Z`).getUTCDay();
    const offset = (weekday + 6) % 7;
    const start = addDays(first, -offset);

    return Array.from({ length: 42 }, (_, index) => addDays(start, index));
}

function intlLocale(locale: string): string {
    return locale === 'en' ? 'en-GB' : 'id-ID';
}

/**
 * Short date such as "14 Okt 2026".
 */
export function formatDisplayDate(date: string, locale: string = 'id'): string {
    return new Intl.DateTimeFormat(intlLocale(locale), {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
}

/**
 * Month heading such as "Oktober 2026".
 */
export function formatMonthLabel(
    { year, month }: YearMonth,
    locale: string = 'id',
): string {
    return new Intl.DateTimeFormat(intlLocale(locale), {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, month - 1, 1)));
}

/**
 * Monday-first short weekday names, e.g. "Sen" ... "Min".
 */
export function weekdayLabels(locale: string = 'id'): string[] {
    const formatter = new Intl.DateTimeFormat(intlLocale(locale), {
        weekday: 'short',
        timeZone: 'UTC',
    });

    return Array.from({ length: 7 }, (_, index) =>
        formatter.format(new Date(Date.UTC(2024, 0, 1 + index))),
    );
}
