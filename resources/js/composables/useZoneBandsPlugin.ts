import type { Plugin } from 'chart.js';
import { computed, toValue } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import type { ZoneBoundaries } from '@/lib/burnZone';

/**
 * Chart.js plugin that paints the three tolerance bands (Hijau / Kuning / Merah)
 * behind a line chart, so the daily index reads against the department's limits.
 *
 * Shared by the Dashboard Early Warning chart and the alert detail chart.
 */
export function useZoneBandsPlugin(
    boundaries: MaybeRefOrGetter<ZoneBoundaries>,
) {
    return computed<Plugin<'line'>>(() => ({
        id: 'earlyWarningZoneBands',
        beforeDatasetsDraw(chart) {
            const {
                ctx,
                chartArea,
                scales: { y },
            } = chart;

            if (!chartArea || !y) {
                return;
            }

            const { green_max_pct, yellow_max_pct } = toValue(boundaries);
            const { top, bottom, left, width } = chartArea;
            const clamp = (value: number): number =>
                Math.min(bottom, Math.max(top, y.getPixelForValue(value)));
            const yGreen = clamp(green_max_pct);
            const yYellow = clamp(yellow_max_pct);

            ctx.save();
            ctx.fillStyle = 'rgba(22, 163, 74, 0.08)';
            ctx.fillRect(left, yGreen, width, bottom - yGreen);
            ctx.fillStyle = 'rgba(245, 158, 11, 0.12)';
            ctx.fillRect(left, yYellow, width, yGreen - yYellow);
            ctx.fillStyle = 'rgba(220, 38, 38, 0.09)';
            ctx.fillRect(left, top, width, yYellow - top);
            ctx.restore();
        },
    }));
}
