import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';

let isRegistered = false;

export function registerChartDefaults(): void {
    if (isRegistered) {
        return;
    }

    ChartJS.register(
        CategoryScale,
        LinearScale,
        PointElement,
        LineElement,
        BarElement,
        ArcElement,
        Title,
        Tooltip,
        Legend,
        Filler,
    );

    // Global default configuration for ISUZU Plant Operations
    ChartJS.defaults.font.family =
        '"Instrument Sans", "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
    ChartJS.defaults.responsive = true;
    ChartJS.defaults.maintainAspectRatio = false;
    ChartJS.defaults.color = '#64748b'; // slate-500
    ChartJS.defaults.borderColor = 'rgba(226, 232, 240, 0.8)'; // slate-200

    ChartJS.defaults.plugins.tooltip.backgroundColor = '#0f172a'; // slate-900
    ChartJS.defaults.plugins.tooltip.titleColor = '#ffffff';
    ChartJS.defaults.plugins.tooltip.bodyColor = '#f8fafc';
    ChartJS.defaults.plugins.tooltip.borderColor = '#334155';
    ChartJS.defaults.plugins.tooltip.borderWidth = 1;
    ChartJS.defaults.plugins.tooltip.padding = 10;
    ChartJS.defaults.plugins.tooltip.cornerRadius = 8;
    ChartJS.defaults.plugins.tooltip.boxPadding = 4;

    isRegistered = true;
}
