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

/** Where a vital comes from: entered actuals, or the planned figure of the Forecasting page as a fallback. */
export type VitalSource = 'actual' | 'forecast';

/**
 * Production volume and manpower change around an alert (E10-05).
 * A `null` value means "no data" and is shown as N/A, never as zero; its source is then `null` too.
 */
export interface AlertVitals {
    volume_change_pct: number | null;
    volume_period_label: string | null;
    volume_source: VitalSource | null;
    manpower_change_pct: number | null;
    manpower_period_label: string | null;
    manpower_source: VitalSource | null;
}

/**
 * The 5W2H text fields of a follow-up, keyed like the form. The same shape is the system suggestion
 * (written by the E10-08 generator) and the `generated_payload` snapshot stored with a follow-up.
 */
export interface FollowUpFields {
    what_text: string;
    why_text: string;
    when_date: string;
    where_location: string;
    who_department_id: number | null;
    how_text: string;
    how_much_cost_idr: number;
    how_much_hours: number;
}

/** One cause the generator detected, with the numbers that justify it (E10-08). */
export interface SuggestionEvidence {
    cause: string;
    score: number;
    values: Record<string, number | string | null>;
}

/**
 * The system suggestion: the form fields plus display hints and audit data of the generator (E10-08).
 * The extra keys are never edited by the user; they are stored with the follow-up as `generated_payload`.
 */
export interface FollowUpSuggestion extends FollowUpFields {
    /** The streak as a readable range, e.g. "24–26 Okt 2026 (3 hari kerja berturut-turut)". */
    when_text?: string | null;
    /** Why the cost is 0 when no cost basis exists. */
    cost_note?: string | null;
    primary_cause?: string;
    supporting_causes?: string[];
    evidence?: SuggestionEvidence[];
}

/** Department offered in the "Who" select. */
export interface WhoDepartmentOption {
    id: number;
    name: string;
}

/** A saved follow-up of the alert as the detail page shows it. */
export interface AlertFollowUpItem {
    id: number;
    is_editable: boolean;
    /** What the system generated when the follow-up was created; null for legacy rows. */
    generated_payload: Partial<FollowUpSuggestion> | null;
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

export { daysBetween } from '@/lib/dateRange';
