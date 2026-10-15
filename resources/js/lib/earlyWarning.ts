import type { BurnZone } from '@/lib/burnZone';

/**
 * Shared types and helpers of the Early Warning module (inbox + alert detail).
 */
export type AlertZone = Exclude<BurnZone, 'green'>;

export type FollowUpStatus = 'none' | 'draft' | 'sent';

export interface EarlyWarningAlert {
    section_id: number;
    section_name: string;
    department_id: number;
    department_name: string | null;
    alert_date: string;
    index_pct: number;
    zone: AlertZone;
    streak_days: number;
    green_max_pct: number;
    yellow_max_pct: number;
    follow_up_status: FollowUpStatus;
    follow_up_id: number | null;
}

export interface AlertSummary {
    total: number;
    red: number;
    yellow: number;
    without_follow_up: number;
}

export interface AlertPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface AlertPage {
    data: EarlyWarningAlert[];
    pagination: AlertPagination;
}

export interface InboxFilters {
    department_id: number | null;
    section_id: number | null;
    date_from: string;
    date_to: string;
    zone: 'all' | AlertZone;
    status: 'all' | FollowUpStatus;
    page: number;
}

export interface DepartmentOption {
    id: number;
    code: string;
    name: string;
}

export interface SectionOption {
    id: number;
    department_id: number;
    name: string;
}

/** One day of the daily index series drawn by the alert detail chart. */
export interface SeriesPoint {
    date: string;
    index_pct: number;
    actual_hours: number;
    zone: BurnZone;
    is_working_day: boolean;
}

/**
 * Production volume and manpower change around an alert (E10-05).
 * A `null` value means "no data" and is shown as N/A, never as zero.
 */
export interface AlertVitals {
    volume_change_pct: number | null;
    volume_period_label: string | null;
    manpower_change_pct: number | null;
    manpower_period_label: string | null;
}

/** A saved follow-up of the alert as the detail page shows it (read-only until E10-06). */
export interface AlertFollowUpItem {
    id: number;
    zone: BurnZone;
    index_pct: number;
    streak_days: number;
    what_text: string | null;
    why_text: string | null;
    when_date: string | null;
    where_location: string | null;
    who_department_id: number | null;
    who_department_name: string | null;
    how_text: string | null;
    how_much_cost_idr: number;
    how_much_hours: number;
    status: Exclude<FollowUpStatus, 'none'>;
    sent_at: string | null;
    sent_by_name: string | null;
    created_at: string | null;
    created_by_name: string;
}

/** Longest date range the inbox accepts (mirrors the server rule). */
export const MAX_RANGE_DAYS = 93;

/**
 * Format a `Y-m-d` date (a calendar day, no timezone) such as "14 Okt 2026".
 * Parsed as UTC and formatted as UTC so the day never shifts with the browser timezone.
 */
export function formatAlertDate(date: string, locale: string = 'id'): string {
    const parsed = new Date(`${date}T00:00:00Z`);

    if (Number.isNaN(parsed.getTime())) {
        return '-';
    }

    return new Intl.DateTimeFormat(locale === 'en' ? 'en-GB' : 'id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(parsed);
}

/**
 * Format a `Y-m-d` date without the year, such as "14 Okt" (chart axis labels).
 */
export function formatShortDate(date: string, locale: string = 'id'): string {
    const parsed = new Date(`${date}T00:00:00Z`);

    if (Number.isNaN(parsed.getTime())) {
        return '-';
    }

    return new Intl.DateTimeFormat(locale === 'en' ? 'en-GB' : 'id-ID', {
        day: '2-digit',
        month: 'short',
        timeZone: 'UTC',
    }).format(parsed);
}

/**
 * Signed percentage such as "+20%" / "-5%" / "0%"; `null` and non-finite values are N/A.
 */
export function formatSignedPct(
    value: number | null | undefined,
): string | null {
    if (value === null || value === undefined || !Number.isFinite(value)) {
        return null;
    }

    const rounded = Math.round(value * 10) / 10;

    return `${rounded > 0 ? '+' : ''}${rounded}%`;
}

/**
 * Whole days between two `Y-m-d` dates (inclusive count is `result + 1`).
 */
export function daysBetween(from: string, to: string): number {
    const start = Date.parse(`${from}T00:00:00Z`);
    const end = Date.parse(`${to}T00:00:00Z`);

    return Math.round((end - start) / 86_400_000);
}
