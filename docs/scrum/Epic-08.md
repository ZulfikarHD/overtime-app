# Epic-08: Supervised ML Analytics & Predictive Intelligence

**Epic ID:** E-08  
**Priority:** P3 – Nice to Have (High Business Value, Long-Tail ROI)  
**Estimated Total:** 58 Story Points  
**Target Sprints:** Sprint 7–8 (Weeks 13–16)  
**Dependencies:** Epic-01 through Epic-05 (Full data pipeline must be producing approved records), minimum 3 months historical data preferred (12–24 months for production-grade accuracy)  
**Lead Area:** ML Service (Python FastAPI or Laravel Queue Worker) + Backend (`MlPredictionController`, `AnomalyDetectionDispatcher`) + Vue 3 ML Dashboard

---

## Business Context

Static formulas fail on the factory floor. Overtime demand fluctuates with product mix changes, machine breakdown patterns, supplier delays, and seasonal order surges. A plant manager looking at "last month's average times 1.1" will routinely get it wrong. This epic replaces that guesswork with **Supervised Machine Learning** — models trained on the plant's own history.

Four distinct ML capabilities are scoped:

1. **ML-1: Overtime Demand Forecasting** — how many hours does this section need next week/month?
2. **ML-2: Burn Index Trajectory Prediction** — will this section run out of budget before month-end?
3. **ML-3: CapEx Labor Forecasting** — will this capital project overrun its labor budget?
4. **ML-4: Supervised Anomaly Detection** — are these timesheet entries suspicious?

**Critical design constraints:**

- **Human-in-the-loop always wins.** ML outputs are advisory. No autonomous decisions.
- **Cold-start circuit breaker**: if a section has < 30 historical shifts, auto-fallback to moving averages.
- **Explainability required**: every ML prediction must surface its top contributing factors in plain language.
- **MAPE ≤ 12%** for demand forecasting, **Recall ≥ 85%** for budget deficit early warning.
- ML worker can be Python (FastAPI/Scikit-Learn) behind an internal API, or embedded as a Laravel queue worker with PHP-ML — architecture choice left to the developer (see note in §ML Worker Strategy below).

---

## ML Worker Strategy Note (Developer Decision Point)

Before beginning this epic, decide between two architectural approaches:

| Approach A: Python FastAPI Microservice        | Approach B: Laravel Queue Worker (PHP-ML) |
| ---------------------------------------------- | ----------------------------------------- |
| Full Scikit-Learn, XGBoost, LightGBM ecosystem | PHP-ML or Rubix ML library                |
| Best model performance                         | Simpler deployment (no extra service)     |
| Requires Docker + internal HTTP routing        | All-in-one Laravel monolith               |
| Better for >10k rows training data             | Adequate for < 500k rows                  |
| **Recommended for production**                 | **Acceptable for MVP**                    |

For the MVP, **Approach B** (PHP queue worker) is acceptable to unblock the feature. Migrate to **Approach A** when the data volume and model complexity justify it.

---

## User Stories

---

### Story E08-01: ML Feature Store & Training Data Pipeline

**As a** developer,  
**I want** a feature extraction query and data pipeline that assembles historical overtime data into ML-ready feature vectors,  
**So that** ML models have clean, structured inputs for both training and inference.

**Story Points:** 8  
**Priority:** Must Have (for this epic)

#### Acceptance Criteria

- [ ] Feature extraction query runs against `overtime_items`, `overtime_submissions`, `operational_calendars`, and `overtime_budgets` to produce a tabular feature set
- [ ] Feature vector per section per month includes:
    - `total_hours_consumed` (lagged 4 months)
    - `holiday_hours` (HLR-specific hours)
    - `breakdown_tpm_hours` (machine breakdown indicator via RCA_MACHINE_BREAKDOWN)
    - `active_shift_days` (count of distinct operational dates)
    - `lagged_3m_avg_hours` (4-week moving average)
    - `capex_hours_ratio` (Project hours / Total hours)
    - (Optional, if ERP data available) `production_volume`, `oee_score`
- [ ] Feature extraction can be run on-demand: `php artisan ml:extract-features {section_id} {year} {month}`
- [ ] Feature data is stored in a structured format (JSON in `ml_models.hyperparameters` or a dedicated feature store table)
- [ ] Cold-start detection: `IF COUNT(historical_months) < 3, flag as cold_start = true`
- [ ] Feature extraction SQL from `data-architect-analyst.md` §5.1 is implemented exactly as specified

#### Technical Tasks

- [ ] `php artisan make:command Ml/ExtractFeaturesCommand` with `{section_id}` and `{year}` `{month}` args
- [ ] `MlFeatureExtractorService::extractForSection(int $sectionId, int $lookbackMonths = 12): array`
- [ ] Implement the SQL CTE from `data-architect-analyst.md` §5.1 using Eloquent query builder or raw query
- [ ] Window function `AVG(...) OVER (PARTITION BY section_id ORDER BY fiscal_month ROWS BETWEEN 3 PRECEDING AND 1 PRECEDING)` — use raw SQL
- [ ] Store extracted features: write to `ml_predictions` with `target_type = 'FEATURE_SNAPSHOT'` or a dedicated staging table
- [ ] Unit test: `MlFeatureExtractorServiceTest::test_lagged_average_correct_with_3_months_history()`

---

### Story E08-02: ML-1 Overtime Demand Forecasting (Regression)

**As a** Manager,  
**I want** the system to predict how many overtime hours my section will need next week or next month,  
**So that** I can plan staffing levels and avoid both idle labor costs and missed shipment deadlines.

**Story Points:** 13  
**Priority:** Should Have

#### Acceptance Criteria

- [ ] For each section, the system produces a **weekly/monthly overtime demand forecast** with:
    - Predicted hours (point estimate): e.g., `185 hrs`
    - Confidence interval: `±12 hrs at 90% confidence`
    - Risk level: `Low | Moderate | High`
    - Top contributing factors (plain text, e.g., "Holiday production on Saturday (+22 hrs), Machine OEE below 80% last week (+15 hrs)")
- [ ] Prediction is stored in `ml_predictions` with `prediction_horizon = 'MONTH_NEXT'` or `'WEEK_NEXT'`
- [ ] Forecast is displayed on the Burn Index dashboard section card as a secondary label: `"ML Forecast: 185 ±12 hrs"`
- [ ] If `fallback_used = true` (cold-start or insufficient data), badge shows `"Calculation: Moving Average (Insufficient ML Baseline)"`
- [ ] Manager can **override** the recommendation with a justified note — this override is stored but does not change the `ml_predictions` record (human-in-the-loop principle)
- [ ] Forecast is recalculated on a scheduled job: **weekly on Sunday at 23:00 WIB**
- [ ] Model evaluation: MAPE target ≤ 12% on section-level monthly aggregations (to be verified after first 3 months of live operation)

#### Technical Tasks

- [ ] `OvertimeDemandForecastService::forecastForSection(int $sectionId, string $horizon): MlPrediction`
- [ ] MVP implementation: `OvertimeDemandForecastService` uses 4-week weighted moving average as the base model (Approach B)
- [ ] Advanced implementation path: Dispatch to Python FastAPI `/ml/predict/demand` endpoint (Approach A)
- [ ] `php artisan make:job Ml/RunDemandForecastJob` — processes all active sections
- [ ] Schedule: `$schedule->job(new RunDemandForecastJob())->weeklyOn(0, '23:00')->timezone('Asia/Jakarta')`
- [ ] Store result in `ml_predictions`: `{ ml_model_id, target_type: 'SECTION', target_id: $sectionId, prediction_horizon: 'MONTH_NEXT', predicted_value, confidence_interval_lower, confidence_interval_upper, risk_level, feature_impact_json }`
- [ ] Fallback circuit breaker: in `OvertimeDemandForecastService`, check `$historicalMonths < 3`, set `fallback_used = true`, compute `headcount × standard_days × 0.10`
- [ ] Update `BurnIndexCard.vue` to show ML forecast label
- [ ] Manager override: PATCH `/ml/predictions/{id}/override` → stores `manager_override_hours` and `override_reason` (add columns to `ml_predictions`)

---

### Story E08-03: ML-2 Burn Index Trajectory Prediction & Early Warning (Classification + Regression)

**As a** Manager,  
**I want** the system to predict by mid-month whether a section is likely to exceed its budget by month-end,  
**So that** I can redistribute work or request a budget extension while there is still time to act.

**Story Points:** 10  
**Priority:** Should Have

#### Acceptance Criteria

- [ ] Starting from **Day 10 of each month**, the system evaluates each section and produces:
    - `predicted_burn_index_at_month_end`: e.g., `118.4%`
    - `overrun_risk_probability`: `0.0 to 1.0`
    - `risk_level`: `LOW | MODERATE | HIGH` (based on probability thresholds: < 0.3 = Low, 0.3–0.7 = Moderate, > 0.7 = High)
    - Trajectory visualization data: `{ week: 1-5, planned: X, actual_to_date: Y, ml_projected: Z }` (for the burndown chart in Epic-05)
- [ ] **Early Warning Radar**: if `risk_level = HIGH`, the system sends an in-app notification to the Manager: `"🔴 Early Warning: Section X is on track to reach 118% budget utilization by month-end"`
- [ ] Notification sent **once** when risk first reaches HIGH (not on every job run) — deduplicated by section + fiscal month
- [ ] If `risk_level` drops back to MODERATE (due to approved rejections or budget adjustments): re-evaluate and update badge
- [ ] Trajectory prediction curve displayed on the 5-week burndown chart (Epic-05, E05-02) as a 3rd dotted line: "ML Projected"
- [ ] `fallback_used = true` badge shown if < 3 months historical data

#### Technical Tasks

- [ ] `BurnTrajectoryForecastService::predictMonthEnd(int $sectionId, int $year, int $month): MlPrediction`
- [ ] Input features: current day-of-month, cumulative hours to date, current burn velocity, remaining planned days, historical month-end burn for this section (last 12 months)
- [ ] MVP model: linear extrapolation of current burn velocity (`projected = velocity × 4.3`)
- [ ] Advanced path: Quantile Gradient Boosting (10th, 50th, 90th percentile) via Python FastAPI
- [ ] `php artisan make:job Ml/RunBurnTrajectoryForecastJob`
- [ ] Schedule: `$schedule->job(new RunBurnTrajectoryForecastJob())->dailyAt('06:00')->timezone('Asia/Jakarta')` (but only generates actionable output from Day 10+)
- [ ] `php artisan make:notification BurnTrajectoryEarlyWarningNotification`
- [ ] Deduplication: `ml_predictions.risk_level` previous value check before sending notification
- [ ] Update `BurndownLineChart.vue` to accept and render ML trajectory array as 3rd data series

---

### Story E08-04: ML-3 CapEx Project Labor Forecasting

**As a** Project Manager,  
**I want** the system to predict whether my CapEx project will overrun its allocated labor budget before the project is physically complete,  
**So that** I can escalate for additional labor allocation or accelerate physical progress to rebalance the budget.

**Story Points:** 8  
**Priority:** Nice to Have

#### Acceptance Criteria

- [ ] For each `ACTIVE` CapEx project, the system produces:
    - `predicted_total_capex_hours`: projected total capitalized labor to complete the project
    - `overrun_probability`: `P(Overrun) ∈ [0,1]`
    - **Milestone Burn Rate Alert**: if `CapEx Burn Index / Physical Progress > 1.2`, flag as `"Labor burning faster than physical progress"`
- [ ] Prediction displayed on CapEx project detail page (Epic-07, E07-02)
- [ ] Fallback when < 3 historical CapEx projects of same type: display `"Insufficient CapEx baseline — showing current burn extrapolation"`
- [ ] Features used: `project_type`, `allocated_hours`, `physical_progress_pct`, `cumulative_capex_hours`, `elapsed_project_days`
- [ ] Prediction updated daily (recalculate after each approval that contains Project hours for this CapEx project)

#### Technical Tasks

- [ ] `CapexLaborForecastService::predictProjectCompletion(int $capexProjectId): MlPrediction`
- [ ] MVP: linear regression on `cumulative_capex_hours vs physical_progress_pct` trend
- [ ] Advanced: Support Vector Regression via Python FastAPI
- [ ] `php artisan make:job Ml/RunCapexForecastJob` — dispatched from `RecalculateMonthlyBurnSnapshotJob` when CapEx items are approved
- [ ] Store in `ml_predictions` with `target_type = 'CAPEX_PROJECT'`, `target_id = $capexProjectId`
- [ ] Create `resources/js/Components/CapEx/CapexMlForecastPanel.vue` — displays on project detail page
- [ ] Integrate with `CapexBurnIndexPanel.vue` (E07-02) — add forecast row to the summary cards

---

### Story E08-05: ML-4 Supervised Anomaly Detection on Timesheet Entries

**As a** Manager or Auditor,  
**I want** the system to automatically flag unusual overtime entries for review,  
**So that** potential errors, misclassifications, or suspicious claims are surfaced before they are approved and finalized.

**Story Points:** 10  
**Priority:** Should Have

#### Acceptance Criteria

- [ ] Each new `OvertimeItem` is scored by the anomaly detection model **asynchronously** after submission (via `RunAnomalyDetectionJob` from Epic-03)
- [ ] Anomaly scoring produces:
    - `anomaly_score` (0.0 to 1.0)
    - `anomaly_reasons[]` (array of plain-text contributing factors)
    - Classification: `score > 0.7 = FLAGGED`, `score 0.4–0.7 = REVIEW_RECOMMENDED`, `< 0.4 = NORMAL`
- [ ] Flagged items display an advisory badge in the approval queue: `🤖 Anomaly: Unusual Category Allocation` — **does NOT block approval**
- [ ] Manager can dismiss a flag with a dismissal note: `is_dismissed = true`, `dismissed_at`, `dismissed_by_user_id`, `dismissal_note`
- [ ] Anomaly reasons include (at minimum these rule-based checks, before ML model is trained):
    - Hours > 3 standard deviations from employee's 60-day moving average
    - `Others` category > 60% of total hours
    - Weekend (HLR) overtime without linked project or production reference
    - `hours_project > 0` without task description
- [ ] Anomaly detection **starts as rule-based** and transitions to a trained ML classifier when ≥ 500 labeled historical records are available
- [ ] `ml_anomaly_logs` records are written for all items with `score > 0.3`
- [ ] Admin dashboard shows: "Anomaly Summary: 12 flagged this month, 8 dismissed, 4 pending review"

#### Technical Tasks

- [ ] Implement `RunAnomalyDetectionJob` (scaffolded in Epic-01, E01-06)
- [ ] `AnomalyDetectionService::score(OvertimeItem $item): AnomalyResult`
- [ ] `AnomalyResult` DTO: `{ score: float, reasons: string[], is_flagged: bool }`
- [ ] Rule-based checks (phase 1):
    - Employee 60-day moving average: `AVG(total_hours) OVER (PARTITION BY employee_id ORDER BY created_at ROWS 60 PRECEDING)` — compare current item
    - Others ratio: `hours_others / total_hours > 0.60`
    - HLR without project: `day_type = 'HLR' AND capex_project_id IS NULL AND hours_project = 0`
    - Project without description: `hours_project > 0 AND task_description IS NULL`
- [ ] ML phase 2 (future): train binary classifier on `is_dismissed` (0 = legit, 1 = was flagged and NOT dismissed = confirmed anomaly)
- [ ] Write to `ml_anomaly_logs` for items with `score > 0.3`
- [ ] Update `ApprovalModal.vue` (Epic-04): add anomaly badge on item row if `anomaly_logs` present
- [ ] Update approval queue (Epic-04, E04-01): show anomaly count badge on submission row
- [ ] Dismiss endpoint: `PATCH /overtime/items/{id}/anomaly/dismiss` → `MlAnomalyController@dismiss`
- [ ] Admin summary widget: `GET /ml/anomaly-summary` → `MlAnomalyController@summary`
- [ ] Create `resources/js/Components/ML/AnomalyBadge.vue` — reusable badge component
- [ ] Create `resources/js/Components/ML/AnomalyDismissModal.vue` — dismissal dialog with note field

---

### Story E08-06: ML Model Health & Governance Dashboard (Admin)

**As an** Admin,  
**I want** to monitor the health and accuracy of all active ML models,  
**So that** I know when a model's predictions are degrading and when it needs to be retrained.

**Story Points:** 5  
**Priority:** Nice to Have

#### Acceptance Criteria

- [ ] Admin dashboard panel shows all registered `ml_models` records with:
    - Model key, type, version, algorithm name
    - `is_active` status badge
    - Training date (`trained_at`)
    - Accuracy metrics from `metrics` JSON: MAPE (demand), Recall (burn trajectory), AUC (anomaly)
    - Number of predictions made in the last 30 days
    - Fallback rate: `% of predictions where fallback_used = true` (ideally < 20%)
- [ ] Admin can toggle `is_active` flag (deactivate a model without deleting it)
- [ ] Alert shown if model MAPE > 12% or Recall < 85% (thresholds from BA spec §5.3)
- [ ] "Retrain" button: triggers `php artisan ml:retrain {model_key}` command (dispatches retraining job)
- [ ] Last inference timestamp per model shown
- [ ] Human-in-the-loop override log: list of all predictions overridden by managers (from E08-02 manager override)

#### Technical Tasks

- [ ] `MlModelHealthController@index` — `GET /admin/ml/models`
- [ ] Query: join `ml_models` with aggregated `ml_predictions` count (last 30 days), fallback rate
- [ ] Create `resources/js/Pages/Admin/ML/ModelHealth.vue`
- [ ] Create `resources/js/Components/ML/ModelHealthCard.vue` — per-model health card
- [ ] `php artisan make:command Ml/RetrainModelCommand` — scaffolded (implementation depends on ML approach A/B)
- [ ] PATCH `/admin/ml/models/{id}/toggle` → `MlModelHealthController@toggle`

---

### Story E08-07: ML Explainability & Feature Impact Display

**As a** Manager,  
**I want** to understand WHY the ML model predicted a certain overtime demand or flagged an anomaly,  
**So that** I can trust the prediction and use it as a decision support tool rather than a black box.

**Story Points:** 4  
**Priority:** Should Have

#### Acceptance Criteria

- [ ] Every ML prediction that is shown in the UI includes a "Why this forecast?" expandable section
- [ ] Demand forecast: "Top factors driving this prediction:
    1. +22 hrs — Planned holiday production (Saturday shift)
    2. +15 hrs — Machine line OEE below 80% last week
    3. −8 hrs — Production volume 12% below seasonal baseline"
- [ ] Burn trajectory: "Risk factors: Current burn velocity (18.5 hrs/wk) is 23% higher than your historical average at this point in the month"
- [ ] Anomaly: each flagged item shows the specific rules/features that triggered the flag
- [ ] Feature impact is stored in `ml_predictions.feature_impact_json` as an ordered array of `{ factor, impact_hours, direction }`
- [ ] If `feature_impact_json` is null (cold-start/fallback), show: "Prediction based on historical moving average — detailed breakdown not available"

#### Technical Tasks

- [ ] Ensure `feature_impact_json` is populated in `ml_predictions` for all ML-1 and ML-2 predictions
- [ ] For rule-based anomaly (E08-05): `feature_impact_json` = the list of triggered rule descriptions
- [ ] Create `resources/js/Components/ML/PredictionExplainerPanel.vue` — expandable "Why?" section
- [ ] `PredictionExplainerPanel` renders `feature_impact_json` array as a sorted factor list with +/- direction indicators
- [ ] Integrate into `BurnIndexCard.vue` (E05-01) and `ApprovalModal.vue` (E04-02) anomaly section

---

## Sprint 7–8 Breakdown

| Sprint Day         | Focus                                                          | Stories |
| ------------------ | -------------------------------------------------------------- | ------- |
| Sprint 7, Day 1–3  | Feature store pipeline + data extraction command               | E08-01  |
| Sprint 7, Day 4–6  | Rule-based anomaly detection (ML-4 phase 1) + badge wiring     | E08-05  |
| Sprint 7, Day 7–10 | Burn trajectory forecast (ML-2) + early warning notification   | E08-03  |
| Sprint 8, Day 1–3  | Demand forecasting (ML-1) + moving average fallback + override | E08-02  |
| Sprint 8, Day 4–5  | CapEx project labor forecast (ML-3)                            | E08-04  |
| Sprint 8, Day 6–7  | ML explainability panel                                        | E08-07  |
| Sprint 8, Day 8–9  | Model health dashboard + Admin governance                      | E08-06  |
| Sprint 8, Day 10   | UAT hardening: edge cases, cold-start tests, CI pass           | All     |

---

## ML Model Acceptance Criteria (Technical Quality Gates)

These are the business-mandated thresholds from `ba-analyst-reqs-draft.md` §5.3:

| Model                   | Metric                     | Target       | Measurement Point                                          |
| ----------------------- | -------------------------- | ------------ | ---------------------------------------------------------- |
| ML-1: Demand Forecast   | MAPE                       | ≤ 12%        | Section-level monthly aggregation after 6 months live      |
| ML-2: Burn Trajectory   | Recall (overrun detection) | ≥ 85%        | Evaluated at Day 15 of each month on historical holdout    |
| ML-4: Anomaly Detection | Precision / Recall         | Baseline TBD | After 500+ labeled records from manager dismissal feedback |

**MAPE Formula** (implemented in `ml_models.metrics` JSON and tracked in health dashboard):

```
MAE  = (1/n) × SUM(|Actual - Predicted|)
MAPE = (100%/n) × SUM(|(Actual - Predicted) / Actual|)
```

---

## Cold-Start Fallback Circuit Breaker

This is mandatory per the BA spec (§5.3). Implement in EVERY prediction service:

```php
// In every Ml*Service::predict() method
if ($historicalShifts < 30) {
    return MlPrediction::create([
        ...
        'fallback_used' => true,
        'predicted_value' => $this->computeMovingAverageFallback($sectionId),
        'feature_impact_json' => null,
        'risk_level' => 'LOW',
    ]);
}
```

UI badge when `fallback_used = true`:

```
"Calculation: Moving Average (Insufficient ML Baseline)"
```

---

## Risks & Assumptions

| Risk                                                         | Likelihood | Mitigation                                                                                                |
| ------------------------------------------------------------ | ---------- | --------------------------------------------------------------------------------------------------------- |
| Insufficient historical data at project launch (< 12 months) | High       | Start with rule-based anomaly + moving average fallback for all models; ML training deferred to Sprint 9+ |
| ML inference latency blocking timesheet submission           | High       | Anomaly scoring is ALWAYS async (queued job) — never in the submission transaction                        |
| PHP-ML library accuracy vs XGBoost                           | Medium     | Accept lower accuracy for MVP; migrate to Python FastAPI in Phase 2                                       |
| Manager distrust of ML predictions ("black box")             | High       | Explainability panel (E08-07) is non-negotiable; always show contributing factors                         |
| ML model confidently wrong on a new production line          | High       | Cold-start circuit breaker with fallback + clear badge mandatory                                          |

---

## Definition of Done — Epic-08

- [ ] `RunAnomalyDetectionJob` fires asynchronously after every submission
- [ ] Anomaly badges appear in approval queue and approval modal for flagged items
- [ ] Burn trajectory prediction runs daily and produces early warning notifications when risk = HIGH
- [ ] Demand forecast runs weekly and displays on Burn Index section cards
- [ ] Cold-start fallback activates and shows correct badge for sections with < 30 historical shifts
- [ ] ML model health dashboard shows all 4 model types with accuracy metrics
- [ ] Explainability panel shows top contributing factors for each prediction
- [ ] `pnpm lint` passes
- [ ] `pnpm build` succeeds
- [ ] `AnomalyDetectionServiceTest` passes rule-based detection cases
- [ ] `BurnTrajectoryForecastServiceTest` passes linear extrapolation test
