# ADR-035: Bivariate Correlation Matrix and Optimal Overtime Productivity Frontier Engine

**Date:** 2026-09-10  
**Status:** accepted  
**Supersedes:** Extends ADR-031, ADR-032, and ADR-034

## Context

Story [E09-09] introduces Tab 3 (**Korelasi & Pola** / _Correlation & Patterns_) under Module B: Analytics & Decision Intelligence (`/analytics?tab=correlation`).
Plant managers and manufacturing engineers need empirical evidence to evaluate whether additional overtime hours translate into proportional vehicle output, identify diminishing returns, and protect against fatigue-induced quality defects.

Key operational realities at PT Isuzu Astra Motor Indonesia:

1. **Bivariate Overtime vs. Production Scatter Analysis**: Section managers require monthly historical scatter points plotting vehicle units (X) against approved overtime hours (Y) overlaid with an empirical linear regression trend line ($y = mx + c$) and Pearson correlation coefficient ($r$).
2. **Defensive ERP Degradation Guard (Zero Crash Guarantee)**: Quality metrics (scrap rate / defect rate) and vehicle production feeds may experience downtime or delayed interfaces. The system must never crash, throw 500 errors, or render broken canvases when ERP feeds are disconnected.
3. **Optimal Overtime Level & Sweet Spot Frontier**: BA specifications define an optimal overtime zone of 12.0–18.0 hours/week peaking around 15.2 hours/week, beyond which employee fatigue causes steep productivity losses and safety risks.
4. **Bivariate Correlation Matrix**: Cross-tabulation of Pearson correlation coefficients ($r$) between 5 key manufacturing variables (Overtime, Production Volume, Quality Metric, Output Efficiency, Cost).

## Decision

We designed and implemented a mathematical and domain service architecture for Story E09-09:

1. **Pure Statistical Calculation Engine (`PearsonCorrelationService`)**:
    - Computes Pearson correlation coefficient $r = \frac{\sum (x - \bar{x})(y - \bar{y})}{\sqrt{\sum (x - \bar{x})^2 \sum (y - \bar{y})^2}}$, clamped defensively to $[-1.0, 1.0]$.
    - Computes ordinary least squares linear regression parameters: slope $m$, intercept $c$, and coefficient of determination $R^2$.
    - Generates trend line bounding coordinates $[(x_{min}, y_{min}), (x_{max}, y_{max})]$.
    - Handles edge cases safely without division by zero: arrays with length $< 2$, mismatched element counts, or zero variance.

2. **Domain Service Integration (`CorrelationAnalysisService`)**:
    - Computes dynamic weekly overtime averages per active employee across historical dates and queries `PolicyThreshold` for plant and department soft limits.
    - Evaluates `services.erp.connected`. If disconnected or absent, returns graceful status objects with polite Indonesian fallback banners (`"N/A — Integrasi data produksi ERP belum terhubung"`).
    - Dynamically calculates the 3-zone non-linear efficiency curve (Under-utilized $<12$h, Sweet Spot $12\text{--}18$h, Over-threshold $>20$h) with vertical markers for current section averages.
    - Computes the 5x5 bivariate correlation matrix with color-coded classification: Green ($|r| \ge 0.70$), Blue ($0.40 \le |r| < 0.70$), Gray ($|r| < 0.40$), and Amber (`ERP Pending`).

3. **Frontend Component Architecture**:
    - `OvertimeProductionScatter.vue`: Renders scatter points alongside linear regression trend lines with interactive section filtering and tooltip details.
    - `OptimalLevelZoneChart.vue`: Visualizes the 3-zone efficiency curve with dynamic marker pills.
    - `CorrelationMatrixTable.vue`: Renders the high-density color-coded correlation matrix table with legend and methodology footnotes.
    - `TabCorrelation.vue`: Combines the 3 summary KPI cards with lazy-fetching, filter synchronization, and error-handling states.

4. **Executive Reporting & Export Pipeline**:
    - `AnalyticsExportService`: Adds full CSV streaming and PDF document generation for Tab 3 correlation metrics, optimal zones, and matrix data.

## Consequences

### Positive

- **Empirical Rigor**: Replaces legacy prototype synthetic formulas with authentic Pearson correlation calculations and linear regression derived from historical production data.
- **Factory Floor Ergonomics**: Clean 3-zone visual segmentation gives managers instant situational awareness regarding section workload health.
- **Zero-Crash Resilience**: Full degradation handling ensures the application remains 100% operational regardless of ERP interface availability.
- **Auditing Transparency**: Color-coded matrix and mathematical formulas are clearly annotated with sample sizes and Indonesian manufacturing context.

### Negative / Trade-offs

- Linear regression assumes linear relationship between production units and overtime hours; extreme multi-variable assembly bottlenecks require more complex non-linear models in future releases.
