/**
 * 3-zone tolerance rule shared by every index / burn indicator.
 *
 * Hijau  : index <= green_max_pct
 * Kuning : green_max_pct < index <= yellow_max_pct
 * Merah  : index > yellow_max_pct
 */
export type BurnZone = 'green' | 'yellow' | 'red';

export interface ZoneBoundaries {
    green_max_pct: number;
    yellow_max_pct: number;
}

export const DEFAULT_ZONE_BOUNDARIES: ZoneBoundaries = {
    green_max_pct: 70,
    yellow_max_pct: 90,
};

export interface BurnZoneStyle {
    /** Short Indonesian label */
    label: string;
    /** Hex colour for charts */
    hex: string;
    /** Text colour utility classes */
    text: string;
    /** Solid background utility classes (bars, dots) */
    solid: string;
    /** Soft badge utility classes (background + text + border) */
    badge: string;
    /** Soft row / card highlight */
    soft: string;
}

export const BURN_ZONE_STYLES: Record<BurnZone, BurnZoneStyle> = {
    green: {
        label: 'Hijau',
        hex: '#16a34a',
        text: 'text-emerald-700 dark:text-emerald-400',
        solid: 'bg-emerald-500',
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
        soft: 'bg-emerald-50/60 dark:bg-emerald-950/20',
    },
    yellow: {
        label: 'Kuning',
        hex: '#f59e0b',
        text: 'text-amber-700 dark:text-amber-400',
        solid: 'bg-amber-500',
        badge: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300',
        soft: 'bg-amber-50/60 dark:bg-amber-950/20',
    },
    red: {
        label: 'Merah',
        hex: '#dc2626',
        text: 'text-[#cc0000] dark:text-red-400',
        solid: 'bg-[#cc0000]',
        badge: 'border-red-200 bg-red-50 text-[#cc0000] dark:border-red-900 dark:bg-red-950/40 dark:text-red-300',
        soft: 'bg-red-50/60 dark:bg-red-950/20',
    },
};

/**
 * Classify an index percentage against the zone boundaries.
 */
export function classifyBurnPct(
    pct: number,
    boundaries: ZoneBoundaries = DEFAULT_ZONE_BOUNDARIES,
): BurnZone {
    if (pct <= boundaries.green_max_pct) {
        return 'green';
    }

    if (pct <= boundaries.yellow_max_pct) {
        return 'yellow';
    }

    return 'red';
}

/**
 * Normalise a zone token coming from the server. Legacy 4-zone tokens
 * (safe / on_track / caution / warning / danger) are mapped onto the 3 zones
 * so previously stored payloads (e.g. saved scenarios) keep rendering.
 */
export function normalizeBurnZone(
    zone: string | null | undefined,
    fallback: BurnZone = 'green',
): BurnZone {
    switch (zone) {
        case 'green':
        case 'safe':
            return 'green';
        case 'yellow':
        case 'on_track':
        case 'caution':
            return 'yellow';
        case 'red':
        case 'warning':
        case 'danger':
            return 'red';
        default:
            return fallback;
    }
}

export function describeZoneRange(
    zone: BurnZone,
    boundaries: ZoneBoundaries = DEFAULT_ZONE_BOUNDARIES,
): string {
    const fmt = (value: number): string =>
        Number.isInteger(value) ? String(value) : value.toFixed(1);

    switch (zone) {
        case 'green':
            return `≤${fmt(boundaries.green_max_pct)}%`;
        case 'yellow':
            return `${fmt(boundaries.green_max_pct)}–${fmt(boundaries.yellow_max_pct)}%`;
        case 'red':
        default:
            return `>${fmt(boundaries.yellow_max_pct)}%`;
    }
}
