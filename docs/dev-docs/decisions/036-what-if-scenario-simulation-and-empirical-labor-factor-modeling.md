# ADR-036: What-If Scenario Simulation, Empirical Labor Factor Modeling, and Ergonomic Parameter Management

**Date:** 2026-09-10  
**Status:** accepted  
**Supersedes:** Extends ADR-031, ADR-032, ADR-034, and ADR-035

## Context

Story [E09-10] introduces Tab 4 (**Simulasi Skenario** / _What-If Scenario Simulation_) under Module B: Analytics & Decision Intelligence (`/analytics?tab=scenario`).
Manufacturing leadership and plant section managers at PT Isuzu Astra Motor Indonesia require an interactive simulation cockpit to project overtime hours, labor costs, and workforce requirements when vehicle production targets fluctuate, or when evaluating proposed overtime adjustments (-50% to +50%).

Key constraints and business realities:

1. **Strict UX Plan Adherence**:
    - Exactly 2 surfaces exist in Epic E09 (`/dashboard` and `/analytics`). Tab 4 must be a client-side sub-tab component (`TabScenario.vue`) inside `Analytics/Index.vue`, without standalone routes or separate pages.
    - Scenario presets must be managed via a slide-in right drawer (`SavedScenariosDrawer.vue` using Shadcn/Reka UI `Sheet`). No multi-level nested dialogs or intrusive alerts.
2. **Prototype Pruning Directives**:
    - Zero synthetic ROI formulas (`ROI = 156% + 1.2 * OT%`). Replaced by empirical IDR cost impact versus approved budget allocations.
    - Zero synthetic Well-being scores (`7.5 - 0.05 * OT%`). Replaced by an objective **Safety Risk Score** representing the projected percentage of shop-floor employees exceeding statutory/plant weekly soft limits ($>14$–$20$ hrs/week).
    - Zero subjective difficulty index tiers or arbitrary multipliers. Real wage snapshots (`hourly_rate_snapshot`) and section historical baselines are used.
3. **ISUZU Brand Industrial Design**:
    - High-density layouts, ISUZU Brand Red (`#cc0000`) accents, sky-blue CapEx and slate OpEx distinctions, and monospace tabular numerals (`font-mono tabular-nums`) across all currency, hours, units, and percentage figures.
4. **Resilient Data Persistence**:
    - Saved simulation presets are persisted per user inside `users.preferences['saved_scenarios']` (capped at 10 items) without requiring DDL alterations or destructive migrations.

## Decision

We designed and implemented a dedicated service and Vue 3 frontend architecture:

1. **Mathematical & Domain Calculation Engine (`ScenarioCalculatorService`)**:
    - Computes empirical section `labor_factor` ($\text{hours/unit} = \frac{\text{historical\_ot\_hours}}{\text{historical\_production\_units}}$) across a 12-month trailing window, with division-by-zero guards and a standard factory baseline fallback ($0.1800$ hrs/unit).
    - Historical category proportion modeling: extracts empirical distributions for `hours_production`, `hours_tpm`, `hours_project` (CapEx), and `hours_others` (OpEx).
    - `calculateProductionPlanning()`: accepts `target_volume` (clamped 1–50,000 units), `period` (`weekly`, `monthly`, `quarterly`), and `section_id`; returns estimated hours, estimated IDR cost, headcount needed (respecting `PolicyThreshold` weekly limits), relative efficiency %, and category breakdown table.
    - `calculateScenarioBuilder()`: accepts `overtime_change_pct` (clamped -50% to +50%), `budget_allocation` (IDR), and `department_id`; calculates projected hours, projected cost, IDR cost impact, production volume impact % ($r = 0.78$), projected Burn Index %, and Safety Risk Score %.
    - User preference persistence: `saveScenario()` and `deleteScenario()` manage up to 10 stored presets in `users.preferences['saved_scenarios']`.

2. **Controller & Wayfinder Route Architecture**:
    - `AnalyticsScenarioController`: implements `index` (`GET /analytics/scenario`), `calculate` (`POST /analytics/scenario/calculate`), `save` (`POST /analytics/scenario/save`), and `destroy` (`DELETE /analytics/scenario/{id}`).
    - Protected with `role:admin,manager` authorization and department scoping for managers.
    - Typed client-side functions generated using Laravel Wayfinder (`@/routes/analytics/scenario`).

3. **Frontend Component Architecture**:
    - `ProductionCalculatorPanel.vue`: Left-side dual panel with target unit inputs, quick volume chips (500, 1000, 1500, 2000), period dropdown, dynamic labor factor indicator, 4 summary KPI cards, and category breakdown table with CapEx/OpEx badges.
    - `ScenarioBuilderPanel.vue`: Right-side dual panel with interactive -50% to +50% range slider, quick presets (-50%, -25%, 0%, +25%, +50%), budget allocation input, 4 outcome projection cards, and inline save scenario modal.
    - `ScenarioComparisonChart.vue`: Bottom panel rendering grouped bar charts via `BaseBarChart.vue` comparing Baseline (Actuals) vs Active Scenario vs up to 3 Saved Presets, with a 4-metric switcher (Jam Lembur, Biaya, Burn Index, Risiko K3) and comprehensive matrix table.
    - `SavedScenariosDrawer.vue`: Slide-in right drawer displaying saved presets with parameters, metrics, timestamp, "Terapkan Skenario" loader, and confirmation delete action.
    - `TabScenario.vue`: Orchestrates state, asynchronous data loading, filter syncing, and fallback handling.

4. **Localization**:
    - Complete Indonesian (default) and English translation dictionary coverage in `lang/id.json` and `lang/en.json`.

## Consequences

### Positive

- **Realistic Decision Intelligence**: Empirically grounded in actual section labor ratios and statutory policy limits instead of fictitious toy formulas.
- **Factory Floor Usability**: Dual panel layout lets industrial engineers plan vehicle target surges on the left while stress-testing overtime budget limits on the right.
- **Single-Surface Integrity**: Preserves clean navigation within `/analytics` without route sprawl or modal stacking.
- **Resilient Zero-Crash Operation**: Works gracefully even if a section has zero prior submissions or if ERP production tables are unpopulated.

### Negative / Trade-offs

- Production unit correlation assumes constant returns to scale within the $\pm50\%$ window; non-linear bottlenecks at $>120\%$ capacity require advanced assembly line simulations in subsequent epics.
