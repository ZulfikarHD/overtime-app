# ADR-032: Predictive Analytics ML and Moving Average Fallback Engine

**Date:** 2026-09-10  
**Status:** accepted

## Context

Story [E09-07] introduces the **Predictive Analytics Tab** (_Prediksi Lembur_) inside the Analytics & Decision Intelligence Hub (`/analytics?tab=predictive`).
The system must provide forward-looking forecasts for next month's overtime demand, seasonal decomposition, and a 6-month trajectory projection.

However, factory plants face cold-start conditions:

1. Newly provisioned sections or environments where ML models (`MlModel`, `MlPrediction`) have not yet completed scheduled training or inference cycles.
2. The strict prototype pruning rule established in Epic-09 UX plan forbids synthetic formulas or hardcoded placeholders.
3. Managers must be strictly scoped to their assigned department, while Administrators require plant-wide visibility.

## Decision

We implemented a dual-engine forecasting strategy inside `PredictiveAnalyticsService`:

1. **Primary ML Inference Lookup**:
    - Checks active models in `ml_models` with `model_type = 'DEMAND_FORECAST'`.
    - Looks up `ml_predictions` matching `prediction_horizon = 'MONTH_NEXT'` for target sections and departments.
    - Extracts model accuracy from `ml_models.metrics->mape` ($100 - \text{MAPE}\%$) and calculates confidence margins from `confidence_interval_lower` and `confidence_interval_upper`.

2. **Automated Cold-Start Moving Average Fallback ($MA_3$)**:
    - If active ML predictions are absent, the service falls back to real historical approved overtime from `overtime_items` over the prior 3 months.
    - Computes a 3-month Simple Moving Average with standard deviation margins ($\pm 10\%$).
    - Flags `fallback_used = true` across the response, driving visual "Moving Average" badges on both KPI cards and section breakdowns.

3. **Database-Agnostic Seasonal Decomposition**:
    - Aggregates approved hours across all historical records and decomposes into 12 calendar months (Jan–Dec) via PHP Carbon. This avoids SQLite vs. MySQL SQL function divergence (such as `MONTH()`).
    - Auto-detects peak quarters (e.g. Q4), lowest months, and variance percentage against grand annual means.

4. **Visual Statistical Representation in Chart.js**:
    - Custom `afterDatasetDraw` Chart.js plugin renders vertical whiskers with horizontal caps for bar chart confidence intervals.
    - Translucent fill dataset (`rgba(139, 92, 246, 0.15)`) renders a 90% confidence ribbon between upper and lower projection bounds on the 6-month trajectory line.
    - Custom `beforeDraw` plugin shades the peak seasonal quarter on the annual cycle line chart.

## Consequences

### Positive

- Zero disruption during cold starts: the UI renders valid data immediately even before ML training pipelines execute.
- Strict adherence to Epic-09 prototype pruning: no fake numbers or synthetic formulas; all data is derived from actual overtime items or trained models.
- Transparent trust signals: users immediately see whether a prediction stems from an active Machine Learning model or a Moving Average baseline.
- Complete compatibility across MySQL/MariaDB production and SQLite test suites.

### Negative

- Simple moving average fallback does not account for complex non-linear plant demand spikes without sufficient historical records.

### Neutral

- Export service (PDF and CSV) uses the same service methods, guaranteeing consistent figures across UI and downloaded reports.
