import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    BURN_ZONE_STYLES,
    DEFAULT_ZONE_BOUNDARIES,
    classifyBurnPct,
    describeZoneRange,
    normalizeBurnZone,
} from '@/lib/burnZone';
import type { BurnZone, ZoneBoundaries } from '@/lib/burnZone';

/**
 * Access the 3-zone tolerance rule (boundaries shared by the server for the
 * current user's department) and helpers to classify / style a zone.
 */
export function useBurnZone() {
    const page = usePage();

    const boundaries = computed<ZoneBoundaries>(() => {
        const shared = page.props.zoneBoundaries as
            | Partial<ZoneBoundaries>
            | null
            | undefined;

        return {
            green_max_pct:
                shared?.green_max_pct ?? DEFAULT_ZONE_BOUNDARIES.green_max_pct,
            yellow_max_pct:
                shared?.yellow_max_pct ??
                DEFAULT_ZONE_BOUNDARIES.yellow_max_pct,
        };
    });

    function classify(pct: number, override?: ZoneBoundaries): BurnZone {
        return classifyBurnPct(pct, override ?? boundaries.value);
    }

    function styleOf(zone: string | null | undefined) {
        return BURN_ZONE_STYLES[normalizeBurnZone(zone)];
    }

    function rangeOf(zone: BurnZone, override?: ZoneBoundaries): string {
        return describeZoneRange(zone, override ?? boundaries.value);
    }

    return {
        boundaries,
        classify,
        styleOf,
        rangeOf,
        normalizeBurnZone,
        styles: BURN_ZONE_STYLES,
    };
}
