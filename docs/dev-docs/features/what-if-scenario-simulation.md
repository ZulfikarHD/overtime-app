# What-If Scenario Simulation (Simulasi Skenario)

## Overview

The What-If Scenario Simulation tab (`/analytics?tab=scenario`) is Story [E09-10] under the Analytics & Decision Intelligence Hub. It provides factory floor managers and manufacturing industrial engineers with a dual-cockpit simulation environment:

1. **Production Volume Planning Calculator**: Estimates required overtime hours, labor costs, headcount additions, and CapEx/OpEx category allocations based on expected vehicle production targets.
2. **Scenario Builder & Workload Simulator**: Simulates the effects of scaling overtime hours (from -50% to +50%) against department budget allocations, calculating resulting cost impacts, production output fluctuations, Burn Index percentages, and employee Safety Risk Scores.
3. **Multi-Scenario Comparison & Preset Management**: Visualizes baseline actuals vs. active simulations vs. up to 3 saved scenarios via grouped bar charts and matrix tables, with a slide-in right drawer for managing saved presets.

## Architecture Diagram

```mermaid
flowchart TD
    subgraph Client [Vue 3 Inertia Client (/analytics?tab=scenario)]
        TabNav[6-Tab Switcher: Tab 4 Simulasi Skenario] --> TabScenario[TabScenario.vue]
        TabScenario --> LeftPanel[ProductionCalculatorPanel.vue]
        TabScenario --> RightPanel[ScenarioBuilderPanel.vue]
        TabScenario --> ComparisonChart[ScenarioComparisonChart.vue]
        TabScenario --> SavedDrawer[SavedScenariosDrawer.vue]
    end

    subgraph Backend [Laravel Backend]
        ScenarioCtrl[AnalyticsScenarioController.php]
        ScenarioSvc[ScenarioCalculatorService.php]
        UserPrefs[(users.preferences JSON)]
        Policy[(policy_thresholds)]
        Submissions[(overtime_submissions & items)]
    end

    LeftPanel -->|POST /analytics/scenario/calculate| ScenarioCtrl
    RightPanel -->|POST /analytics/scenario/calculate| ScenarioCtrl
    RightPanel -->|POST /analytics/scenario/save| ScenarioCtrl
    SavedDrawer -->|DELETE /analytics/scenario/{id}| ScenarioCtrl
    ScenarioCtrl --> ScenarioSvc
    ScenarioSvc --> Policy
    ScenarioSvc --> Submissions
    ScenarioSvc --> UserPrefs
```

## Key Files & UI Mapping

| Layer         | File / Route / Component                                          | Purpose                                                         |
| :------------ | :---------------------------------------------------------------- | :-------------------------------------------------------------- |
| Sidebar Menu  | `Analitik & Keputusan` (`testId: 'nav-analytics'`)                | Navigation entry point (Admin & Manager)                        |
| Tab Router    | `/analytics?tab=scenario`                                         | Sub-tab 4 of Analytics Hub                                      |
| Tab Page      | `resources/js/pages/Analytics/TabScenario.vue`                    | State orchestrator, asynchronous data fetch, responsive grid    |
| Left Panel    | `resources/js/components/analytics/ProductionCalculatorPanel.vue` | Target volume inputs, period selector, 4 KPIs, category table   |
| Right Panel   | `resources/js/components/analytics/ScenarioBuilderPanel.vue`      | -50% to +50% slider, budget allocation, 4 outcome cards         |
| Bottom Chart  | `resources/js/components/analytics/ScenarioComparisonChart.vue`   | Grouped bar chart (BaseBarChart) & metric switcher comparison   |
| Slide Drawer  | `resources/js/components/analytics/SavedScenariosDrawer.vue`      | Right slide-in sheet to view, apply, and delete saved scenarios |
| Service Layer | `app/Services/Analytics/ScenarioCalculatorService.php`            | Mathematical calculations, empirical labor factor, preferences  |
| Controller    | `app/Http/Controllers/AnalyticsScenarioController.php`            | JSON endpoints for simulation, planning, saving, and deletion   |
| Feature Tests | `tests/Feature/Analytics/AnalyticsScenarioTest.php`               | 9 automated pest tests for authorization, calculations, and DB  |
| Browser Tests | `tests/Browser/Analytics/ScenarioSimulationBrowserTest.php`       | Playwright end-to-end browser tests verifying UI interactions   |

## Mathematical Formulation

### 1. Empirical Labor Factor ($\text{labor\_factor}$)

$$\text{labor\_factor} = \frac{\sum_{m=1}^{12} \text{approved\_ot\_hours}_m}{\sum_{m=1}^{12} \text{actual\_production\_units}_m}$$
_Fallback default when historical records are absent:_ $0.1800 \text{ hours/unit}$.

### 2. Production Planning

$$\text{estimated\_hours} = \text{target\_volume} \times \text{labor\_factor} \times \text{period\_multiplier}$$
$$\text{estimated\_cost} = \text{estimated\_hours} \times \text{hourly\_rate}$$
$$\text{headcount\_needed} = \left\lceil \frac{\text{estimated\_hours}}{\text{weekly\_soft\_limit} \times \text{weeks\_in\_period}} \right\rceil$$

### 3. Category Breakdown (Historical Ratios)

$$\text{hours}_c = \text{estimated\_hours} \times \text{ratio}_c \quad (c \in \{\text{production}, \text{tpm}, \text{project}, \text{others}\})$$
$$\text{cost}_c = \text{hours}_c \times \text{hourly\_rate}$$

### 4. Overtime Scenario Builder

$$\text{projected\_hours} = \text{baseline\_hours} \times \left(1 + \frac{\Delta\text{OT}\%}{100}\right)$$
$$\text{projected\_cost} = \text{projected\_hours} \times \text{hourly\_rate}$$
$$\Delta\text{Cost} = \text{projected\_cost} - \text{baseline\_cost}$$
$$\Delta\text{Volume}\% = \Delta\text{OT}\% \times r \quad (r = 0.78)$$
$$\text{Burn Index}\% = \frac{\text{projected\_hours}}{\text{budget\_ceiling\_hours}} \times 100$$
$$\text{Safety Risk Score}\% = \min\left(95.0, \max\left(2.0, \text{baseline\_risk} \times \left(1 + 1.25 \times \frac{\Delta\text{OT}\%}{100}\right)\right)\right)$$

## API Endpoints

### 1. `GET /analytics/scenario`

Retrieves baseline metrics, section labor factors, department lists, and user's saved scenarios.

### 2. `POST /analytics/scenario/calculate`

Runs production planning calculation or scenario builder calculations.

- Payload for planning: `{ "target_volume": 1500, "period": "monthly", "section_id": 1 }`
- Payload for builder: `{ "overtime_change_pct": 20, "budget_allocation": 30000000, "department_id": 1 }`

### 3. `POST /analytics/scenario/save`

Saves the active builder scenario into `users.preferences['saved_scenarios']` (max 10 items).

### 4. `DELETE /analytics/scenario/{id}`

Deletes the specified scenario by ID from `users.preferences['saved_scenarios']`.
