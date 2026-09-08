# Developer Documentation (Dev Docs)

## Overtime & CapEx Labor Management System (OT-CapEx)

Welcome to the technical engineering documentation for the OT-CapEx system. This documentation is written for backend, frontend, and data engineers maintaining and developing the application.

---

## 1. Core Architecture

- **[System Architecture Blueprint](../architecture.md)** — Comprehensive architecture, technology verification matrix, entity relationship diagrams, database constraints, Chart.js visualization engine, and background queues.

---

## 2. Technical Feature Documentation (`features/`)

Deep-dive technical documentation detailing architecture flows, data models, key file mappings to UI, and controller/service layers:

| Document                                                                                                   | Epic Reference                    | Description                                                                                                                                        |
| ---------------------------------------------------------------------------------------------------------- | --------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| **[Authentication & Role-Based Access Control](./features/authentication-rbac.md)**                        | [Epic-01](../../scrum/Epic-01.md) | Dual-identifier (Email/NPK) login, 4-tier RBAC matrix, department/section scoping, live WIB shift clock, rate limiting, and friendly 403 recovery. |
| **[Database Schema Migrations (Full DDL)](./features/database-schema-migrations.md)**                      | [Epic-01](../../scrum/Epic-01.md) | Relational database schema across 15 tables, foreign keys, stored generated columns, and partial indexes.                                          |
| **[Eloquent Models & Domain Relationships](./features/eloquent-models-relationships.md)**                  | [Epic-01](../../scrum/Epic-01.md) | Type-safe ORM entities across 15 models, explicit fillable guards, decimal casts, and domain query scopes.                                         |
| **[Foundation Layout & Wayfinder Navigation](./features/foundation-layout-wayfinder.md)**                  | [Epic-01](../../scrum/Epic-01.md) | Responsive base shell, live WIB clock, shift indicator, role badges, Wayfinder routes, and flash toast pipeline.                                   |
| **[Department & Section Hierarchy Management](./features/department-section-management.md)**               | [Epic-02](../../scrum/Epic-02.md) | Consolidated Master Data Hub, hierarchical department/section CRUD, immutable codes, and referential integrity protection.                         |
| **[Employee Roster Management & CSV Import](./features/employee-roster-management.md)**                    | [Epic-02](../../scrum/Epic-02.md) | Single-hub employee roster, cascading section select, immutable NPK (BR-03), and two-stage pre-commit in-memory CSV import audit.                  |
| **[Operational Calendar Management & API](./features/operational-calendar-management.md)**                 | [Epic-02](../../scrum/Epic-02.md) | Monthly 7-column grid, slide-in day editor, two-stage national holiday CSV import, auto-seeding, and authenticated classification API.             |
| **[Policy Threshold Configuration & Hierarchical Fallback](./features/policy-threshold-configuration.md)** | [Epic-02](../../scrum/Epic-02.md) | Consolidated Administration Hub, plant baseline defaults, departmental overrides, slide-in sheet, and hierarchical inheritance fallback engine.    |
| **[User Account Management & Role-Based Access Control (RBAC)](./features/user-account-management.md)**    | [Epic-02](../../scrum/Epic-02.md) | Consolidated Administration Hub users tab, self-lockout guards, role-change audit logging, dynamic cascading scopes, and credential management.    |
| **[User Preferences & Display Standards](./features/user-preferences-settings.md)**                        | [Epic-02](../../scrum/Epic-02.md) | User visual theme mode (Light/Dark/System), operational notification alerts (SPKL, Burn alert, approvals), and plant display standards.            |
| **[Overtime Budget Planning & Scoping](./features/overtime-budget-planning.md)**                           | [Epic-02](../../scrum/Epic-02.md) | Monthly section overtime hours, 5-week breakdown, live labor expenditure estimation in Rupiah, RBAC scoping, and two-stage CSV audit.              |
| **[Background Jobs & Redis Queues](./features/background-jobs-queues.md)**                                 | [Epic-01](../../scrum/Epic-01.md) | Asynchronous Redis queue workers, retry backoff configuration, and scaffolded background jobs.                                                     |
| **[Database Seeding & Demo Data](./features/database-seeding-demo-data.md)**                               | [Epic-01](../../scrum/Epic-01.md) | Modular database seeders for automotive plant master data, shifts, employees, thresholds, and budgets.                                             |
| **[Daily Overtime Entry & SPKL Workflow](./features/daily-overtime-spkl.md)**                              | [Epic-03](../../scrum/Epic-03.md) | Shift-end overtime capture, atomic roster validation, and non-blocking SPKL document state machine.                                                |
| **[Overtime Policy Soft Warning Indicators](./features/policy-soft-warning-indicators.md)**                | [Epic-03](../../scrum/Epic-03.md) | Real-time advisory weekly overtime fatigue warning badges, consecutive high workload alert streaks, and manager review indicators.                 |
| **[SPKL Flexible Post-Shift Attachment & Lifecycle Workflow](./features/spkl-document-workflow.md)**       | [Epic-03](../../scrum/Epic-03.md) | Post-shift photo/PDF upload, private disk isolation, file replacement, manager verification, and temporary signed URLs.                            |
| **[SPKL Pending Reminder & In-App Notifications](./features/spkl-pending-reminder-notifications.md)**      | [Epic-03](../../scrum/Epic-03.md) | Automated daily 08:00 WIB reminder job, in-app notifications, topbar notification bell, and 1-click SPKL attachment drawer flow.                   |
| **[Verification, Item Approvals, Bulk Decisions & Export](./features/verification-approval.md)**           | [Epic-04](../../scrum/Epic-04.md) | Manager/Admin standup approval queue, item-level approvals, bulk processing, and CSV/Excel streaming export.                                       |
| **[Budget Management & Burn Index Dashboard](./features/budget-burn-index.md)**                            | [Epic-05](../../scrum/Epic-05.md) | Analytical dashboard, Chart.js Burn Index gauges/burndown lines, and asynchronous monthly rollups.                                                 |
| **[Individual Employee Reporting & Welfare](./features/employee-welfare-report.md)**                       | [Epic-06](../../scrum/Epic-06.md) | Personal employee dossiers, welfare fatigue soft limits, and peer variance benchmarking.                                                           |
| **[CapEx Project Labor Management](./features/capex-project-labor.md)**                                    | [Epic-07](../../scrum/Epic-07.md) | Fixed asset labor capitalization, project codes, progress vs. burn curves, and statutory audit integrity.                                          |
| **[Supervised Machine Learning Analytics](./features/ml-predictive-analytics.md)**                         | [Epic-08](../../scrum/Epic-08.md) | Overtime demand forecasting, Burn Index trajectory ribbon charts, anomaly detection, and cold-start fallback.                                      |

---

## 3. API & Endpoint Documentation (`api/`)

HTTP request, query parameter, and payload specifications:

- **[Calendar API Endpoints](./api/calendar-endpoints.md)** — Day classification endpoint (`GET /api/calendar/{date}`) for timesheet auto-calculation and admin batch management.
- **[Overtime Policy Check API](./api/overtime-policy-check.md)** — Real-time policy threshold check endpoint (`GET /overtime/policy-check`) returning advisory fatigue warnings.
- **[Overtime Submissions & Approvals API](./api/overtime-submissions.md)** — Timesheet batch submissions, SPKL document uploads, and item-level approval/rejection payloads.
- **[In-App Notifications API](./api/notifications.md)** — Fetch unread notifications, mark individual notifications as read, and bulk mark all as read.
- **[Analytics, Burn Index & Machine Learning API](./api/analytics-reports.md)** — Monthly burn snapshot queries, employee dossier endpoints, and ML predictive horizons.

---

## 4. Architecture Decision Records (`decisions/`)

Durable technical decisions and trade-offs:

- **[ADR-001: Use Laravel Wayfinder Instead of Ziggy for TypeScript Routing](./decisions/001-wayfinder-routing-over-ziggy.md)**
- **[ADR-002: Immutable Financial Rate Snapshotting on Overtime Approval](./decisions/002-immutable-labor-rate-snapshotting.md)**
- **[ADR-003: Non-Blocking SPKL Document Attachment with Policy Grace Periods](./decisions/003-non-blocking-spkl-document-workflow.md)**
- **[ADR-004: Standardizing Chart.js for Manufacturing Analytics and Machine Learning Visualizations](./decisions/004-chartjs-visualization-engine.md)**
- **[ADR-005: Asynchronous Denormalized Monthly Burn Snapshots for Dashboard Performance](./decisions/005-denormalized-monthly-burn-snapshots.md)**
- **[ADR-006: Multi-Database Stored Generated Columns and Partial Indexes](./decisions/006-multi-database-stored-generated-columns-and-partial-indexes.md)**
- **[ADR-007: Role-Based Access Control and Scoping](./decisions/007-role-based-access-control-and-scoping.md)**
- **[ADR-008: Redis Queue Worker and Background Job Architecture](./decisions/008-redis-queue-worker-and-background-job-architecture.md)**
- **[ADR-009: Two-Stage Pre-Commit In-Memory CSV Roster Import](./decisions/009-two-stage-pre-commit-csv-roster-import.md)**
- **[ADR-010: Policy Threshold Hierarchical Inheritance Fallback](./decisions/010-policy-threshold-hierarchical-inheritance-fallback.md)**
- **[ADR-011: User Lifecycle and Role-Change Audit Logging](./decisions/011-user-lifecycle-and-role-change-audit-logging.md)**
- **[ADR-012: User Preferences and Display Standards Architecture](./decisions/012-user-preferences-and-display-standards.md)**
- **[ADR-013: Overtime Budget Planning and Two-Stage CSV Import Architecture](./decisions/013-overtime-budget-planning-and-csv-import.md)**
- **[ADR-014: Automated SPKL Document Reminders and In-App Notifications Architecture](./decisions/014-automated-spkl-reminders-and-in-app-notifications.md)**
- **[ADR-015: Advisory Overtime Policy Soft Warning Indicators](./decisions/015-advisory-overtime-policy-soft-warning-indicators.md)**
- **[ADR-016: Streaming Overtime Export and Audit Logging](./decisions/016-streaming-overtime-export-and-audit-logging.md)**
- **[ADR-017: Immutable Overtime Item Audit Ledger and Lifecycle History](./decisions/017-immutable-overtime-item-audit-ledger.md)**

---

## 5. Engineering Standards & Quality Gates

1. **Routing Rule**: Import exclusively from `@/actions/...` and `@/routes/...` via Wayfinder. Never use legacy Ziggy.
2. **Controller Rule**: Keep controllers thin (≤ 30 lines). Encapsulate domain transactions in `app/Actions/` or `app/Services/`.
3. **Database Precision**: Currency stored as `NUMERIC(15,2)` in Rupiah (`Rp`). Timezone fixed to `Asia/Jakarta` (WIB).
4. **Code Quality**: Run `vendor/bin/pint --dirty --format agent` on modified PHP files; run `pnpm check` and `pnpm build` before pushing.
