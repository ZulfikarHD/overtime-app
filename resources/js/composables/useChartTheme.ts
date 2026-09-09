export const chartColors = {
    primary: '#2563eb', // Planned / Budget
    success: '#16a34a', // Under Budget / Approved / Safe
    warning: '#d97706', // Threshold Warning / Amber
    danger: '#dc2626', // Over Budget / Deficit
    isuzuRed: '#cc0000', // ISUZU Brand Red
    capex: '#7c3aed', // CapEx Violet
    capexAlt: '#0284c7', // Sky Blue
    opex: '#0891b2', // OpEx Cyan
    opexAlt: '#64748b', // Slate
    hkn: '#3b82f6', // HKN Blue
    hlr: '#f59e0b', // HLR Amber
    neutral: '#94a3b8', // Slate-400
    gridLight: 'rgba(226, 232, 240, 0.8)',
    gridDark: 'rgba(51, 65, 85, 0.4)',
} as const;

export type BurnZone = 'safe' | 'on_track' | 'warning' | 'danger';

export function getBurnZone(burnIndexPct: number): BurnZone {
    if (burnIndexPct > 115) {
        return 'danger';
    }
    if (burnIndexPct > 100) {
        return 'warning';
    }
    if (burnIndexPct >= 85) {
        return 'on_track';
    }
    return 'safe';
}

export function getBurnZoneColor(burnIndexPct: number): string {
    const zone = getBurnZone(burnIndexPct);
    switch (zone) {
        case 'danger':
            return chartColors.isuzuRed;
        case 'warning':
            return chartColors.warning;
        case 'on_track':
            return chartColors.primary;
        case 'safe':
        default:
            return chartColors.success;
    }
}

export function useChartTheme() {
    return {
        colors: chartColors,
        getBurnZone,
        getBurnZoneColor,
    };
}
