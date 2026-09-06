# CapEx Project Labor Management & Capitalization

## Overview

The **CapEx Project Labor Management & Capitalization** module governs overtime labor dedicated to fixed asset creation, machine fabrication, tooling construction, and major facility overhauls. It ensures that capitalized project labor is strictly isolated from standard operational expenses (OpEx), directly traceable to authoritative project codes (`CPX-YYYY-DEPT-NNN`), and compliant with corporate tax depreciation and statutory audit requirements.

## Architecture Diagram

```mermaid
flowchart TD
    PM[CapEx Manager / Admin] -->|Create / Update Project| CPC[CapexProjectController]
    CPC -->|Validate Code & Budget| CPR[StoreCapexProjectRequest]
    CPR --> CPMOD[CapexProject Model]
    CPMOD --> DB[(Database: capex_projects)]

    TL[Team Leader - Timesheet] -->|hours_project > 0| SOA[SubmitOvertimeAction]
    SOA -->|Verify Project is ACTIVE| VAL[Validate BR-08 Constraint]
    VAL --> OTI[(overtime_items: capex_project_id)]

    FIN[Finance / Cost Auditor] -->|Audit Project Burn| CAS[CapExAccountingService]
    CAS -->|Aggregate Hours & Snapshots| CHRT[CapexProjectBurnChart.vue - Chart.js]
```

## Data Model

```mermaid
erDiagram
    DEPARTMENTS ||--o{ CAPEX_PROJECTS : owns
    CAPEX_PROJECTS ||--o{ OVERTIME_ITEMS : capitalizes_labor
    USERS ||--o{ CAPEX_PROJECTS : manages
```

## Key Files & UI Mapping

| Layer           | File / Route / Menu                                        | Purpose                                                            |
| --------------- | ---------------------------------------------------------- | ------------------------------------------------------------------ |
| Sidebar Menu    | `Proyek CapEx` (`/admin/capex-projects`)                   | Master list and capital tracking for managers                      |
| Page Component  | `resources/js/pages/Admin/CapexProjects/Index.vue`         | Project portfolio list with status filters and progress bars       |
| Detail Page     | `resources/js/pages/Admin/CapexProjects/Show.vue`          | Capital labor burn cockpit, worker roster, and financial ledger    |
| Chart Component | `resources/js/components/Charts/CapexProjectBurnChart.vue` | Chart.js multi-line chart of labor hours vs physical progress %    |
| Controller      | `app/Http/Controllers/Admin/CapexProjectController.php`    | CRUD management, status transitions, and audit export              |
| Service         | `app/Services/Analytics/CapExAccountingService.php`        | Calculates CapEx ratio (`CALC-07`) and cumulative capitalized cost |
| Model           | `App\Models\CapexProject`                                  | Master project record with allocated hours and Rupiah budget       |

## Flow Explanation

1. **Project creation**: An Admin or CapEx Manager creates a project under **Proyek CapEx** specifying:
    - Unique, immutable `project_code` (e.g., `CPX-2026-ASSY-001`).
    - Fixed asset reference code (`asset_code`).
    - Allocated capitalized labor hours and budget in Rupiah (`allocated_labor_budget_idr`).
    - Target milestones and active date range.
2. **Shop-floor timesheet binding**:
    - When a Team Leader enters hours in the **Project** category, `hours_project > 0` triggers validation rule `BR-08`.
    - The user must select an `ACTIVE` project from the dropdown. Inactive or closed projects are rejected.
3. **Financial cost accumulation**:
    - As overtime items are approved, their `total_cost_snapshot` is linked to the CapEx project.
    - The system accumulates both total capitalized hours and total capitalized financial cost.
4. **Labor burn vs. physical progress tracking**:
    - `CapexProjectBurnChart.vue` (Chart.js) renders a comparative curve: cumulative labor burn percentage against reported physical project progress (`physical_progress_pct`).
    - If labor burn reaches 80% while physical progress is only 40%, the system flags a **Capital Overrun Risk**.
5. **Closure & audit locking**:
    - When the project reaches completion, its status transitions to `COMPLETED` and subsequently `CLOSED`.
    - Closed projects are permanently locked against any new overtime attributions.

## API Endpoints & Routes

| Method | URI                                 | Controller Action                     | Purpose                                         | Auth / Middleware            |
| ------ | ----------------------------------- | ------------------------------------- | ----------------------------------------------- | ---------------------------- |
| GET    | `/admin/capex-projects`             | `CapexProjectController@index`        | Project portfolio list and burn status          | `auth`, `role:manager,admin` |
| POST   | `/admin/capex-projects`             | `CapexProjectController@store`        | Create new CapEx project                        | `auth`, `role:manager,admin` |
| GET    | `/admin/capex-projects/{id}`        | `CapexProjectController@show`         | Project labor audit cockpit                     | `auth`, `role:manager,admin` |
| PATCH  | `/admin/capex-projects/{id}/status` | `CapexProjectController@updateStatus` | Transition project lifecycle status             | `auth`, `role:manager,admin` |
| GET    | `/admin/capex-projects/{id}/export` | `CapexProjectController@exportLedger` | Export labor capitalization ledger for auditors | `auth`, `role:manager,admin` |

## Decisions & Trade-offs

- **Strict Database Integrity**: We enforce `chk_capex_attribution` at the database level (`hours_project = 0 OR capex_project_id IS NOT NULL`). This ensures no orphaned project labor exists, satisfying strict external financial audits.
- **Immutable Project Codes**: Similar to employee NPKs, project codes cannot be edited after creation to preserve financial ledger traceability.

## Related

- [Epic-07: CapEx Project Labor Management](../../scrum/Epic-07.md)
- [ADR-002: Immutable Rate Snapshotting](../decisions/002-immutable-labor-rate-snapshotting.md)
