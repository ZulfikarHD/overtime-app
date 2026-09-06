# ADR-004: Standardizing Chart.js for Manufacturing Analytics and Machine Learning Visualizations

**Date:** 2026-09-06  
**Status:** accepted  
**Supersedes:** None

## Context

The OT-CapEx application requires diverse, high-performance data visualizations:

- **Burn Index Gauges**: Semi-circular radial indicators displaying budget depletion percentages with operational zone colors.
- **5-Week Burndown Curves**: Multi-line charts comparing weekly planned milestones against actual cumulative hour burn.
- **CapEx vs. OpEx Splits**: Stacked horizontal distribution bars tracking capitalizable labor ratios.
- **Employee Peer Variance**: Distribution and threshold charts identifying workload imbalances and fatigue alerts.
- **Supervised ML Trajectories**: Line charts with confidence ribbons ($P_{10}$ to $P_{90}$) and anomaly scatter plots.

Multiple charting libraries exist in the Vue ecosystem (ApexCharts, ECharts, Chart.js, D3.js). The team requires a lightweight, canvas-based, responsive library that minimizes bundle size, renders efficiently on plant floor kiosks, and integrates seamlessly with Vue 3 SFCs without licensing restrictions.

## Decision

We standardize on **Chart.js** (v4+) for all charts across the application:

1. Chart.js utilizes HTML5 Canvas, providing high-performance rendering for multi-point time series and ribbons.
2. Standardized color palette mapped to manufacturing operational zones:
    - `ZONE_1_EXCELLENT` (< 85%): Emerald Green (`#10B981`)
    - `ZONE_2_GOOD` (85%–100%): Corporate Blue (`#3B82F6`)
    - `ZONE_3_WARNING` (101%–115%): Amber Orange (`#F59E0B`)
    - `ZONE_4_POOR` (> 115%): Crimson Red (`#EF4444`)
    - CapEx Projects: Indigo (`#6366F1`) | OpEx: Slate (`#64748B`)
3. Components are wrapped in reusable Vue 3 SFC components (e.g., `BurnIndexGauge.vue`, `BurndownTrajectoryChart.vue`, `MlForecastRibbonChart.vue`) with automatic resize observers and canvas disposal on unmount to prevent memory leaks in Inertia SPAs.

## Consequences

### Positive

- **High Performance**: Canvas rendering avoids heavy DOM node bloat on complex multi-section plant dashboards.
- **Consistent Design Language**: Standardized palettes and tooltip formatters provide unified visual feedback across all management screens.
- **Predictable API**: Broad developer familiarity, excellent documentation, and zero commercial licensing fees.

### Negative

- Canvas charts require explicit resize observer hooks when containers expand or tabs toggle.
- Complex dual-axis charts (like RCA Pareto) require careful configuration of dataset scales.

### Neutral

- Server-side rendering (SSR) of canvas charts is not required; charts hydrate on client mount using Inertia page props.
