# Master Documentation Index

## Overtime & CapEx Labor Management System (OT-CapEx)

**Lead Developer:** Zulfikar Hidayatullah (+62 857-1583-8733)  
**Architecture Baseline:** Laravel 13 · Inertia.js v3 · Vue 3 · Wayfinder · Tailwind CSS v4 · Chart.js  
**Operational Standards:** Timezone `Asia/Jakarta` (WIB) · Currency `Rp (IDR)` · Package Manager `pnpm`

---

## 1. System Architecture & Foundation

The architectural blueprint unifies business requirements, relational database design, real installed packages, and data visualization architectures:

- **[System Architecture Blueprint](./architecture.md)**
    - Technology verification matrix (`composer.lock` & `package.json`)
    - Component topology & request-response sequence
    - Entity-Relationship Diagram (ERD) & database constraints
    - Chart.js visualization engine architecture & component mapping
    - Role-Based Access Control (RBAC) matrix
    - Core calculation cheatsheet (`CALC-01` to `CALC-08`)
    - Asynchronous Redis job queues & ML cold-start circuit breaker
- **[ISUZU OT-CapEx Design System & UI/UX Style Guide](./style-guide.html)** (Interactive HTML Style Guide: ISUZU Red palette, typography, high-density manufacturing components, and UX guardrails)

---

## 2. Technical Architecture & Design Reference (`docs/dev-docs/`)

Technical specifications and decision records for the engineering implementation:

- **[Developer Documentation Index](./dev-docs/README.md)**
- **Architecture Decision Records (ADRs):**
    - [ADR-001: Wayfinder Routing over Ziggy](./dev-docs/decisions/001-wayfinder-routing-over-ziggy.md)
    - [ADR-002: Immutable Labor Rate Snapshotting](./dev-docs/decisions/002-immutable-labor-rate-snapshotting.md)
    - [ADR-003: Non-Blocking SPKL Document Workflow](./dev-docs/decisions/003-non-blocking-spkl-document-workflow.md)
    - [ADR-004: Chart.js Visualization Engine](./dev-docs/decisions/004-chartjs-visualization-engine.md)
    - [ADR-005: Denormalized Monthly Burn Snapshots](./dev-docs/decisions/005-denormalized-monthly-burn-snapshots.md)
    - [ADR-006: Multi-Database Stored Generated Columns and Partial Indexes](./dev-docs/decisions/006-multi-database-stored-generated-columns-and-partial-indexes.md)
    - [ADR-007: Role-Based Access Control and Scoping](./dev-docs/decisions/007-role-based-access-control-and-scoping.md)
    - [ADR-008: Redis Queue Worker and Background Job Architecture](./dev-docs/decisions/008-redis-queue-worker-and-background-job-architecture.md)
    - [ADR-009: Two-Stage Pre-Commit CSV Roster Import](./dev-docs/decisions/009-two-stage-pre-commit-csv-roster-import.md)
    - [ADR-010: Policy Threshold Hierarchical Inheritance Fallback](./dev-docs/decisions/010-policy-threshold-hierarchical-inheritance-fallback.md)
    - [ADR-011: User Lifecycle and Role-Change Audit Logging](./dev-docs/decisions/011-user-lifecycle-and-role-change-audit-logging.md)
    - [ADR-012: User Preferences and Display Standards](./dev-docs/decisions/012-user-preferences-and-display-standards.md)
    - [ADR-013: Overtime Budget Planning and CSV Import](./dev-docs/decisions/013-overtime-budget-planning-and-csv-import.md)
    - [ADR-014: Automated SPKL Document Reminders and In-App Notifications](./dev-docs/decisions/014-automated-spkl-reminders-and-in-app-notifications.md)
    - [ADR-015: Advisory Overtime Policy Soft Warning Indicators](./dev-docs/decisions/015-advisory-overtime-policy-soft-warning-indicators.md)
    - [ADR-016: Streaming Overtime Export and Audit Logging](./dev-docs/decisions/016-streaming-overtime-export-and-audit-logging.md)
    - [ADR-017: Immutable Overtime Item Audit Ledger and Lifecycle History](./dev-docs/decisions/017-immutable-overtime-item-audit-ledger.md)
    - [ADR-018: Approved Records Modification Lock and Admin Force-Unlock](./dev-docs/decisions/018-approved-records-modification-lock-and-admin-force-unlock.md)
    - [ADR-019: Single-Hub Tab-Based CapEx vs OpEx Distribution Panel](./dev-docs/decisions/019-single-hub-tab-based-capex-opex-distribution.md)
    - [ADR-020: Budget Threshold Alert Timestamp Locking for Anti-Fatigue Deduplication](./dev-docs/decisions/020-budget-threshold-alert-deduplication.md)
    - [ADR-021: Department-Level Consolidated Dashboard and Server-Side PDF Reporting](./dev-docs/decisions/021-department-level-consolidated-dashboard-and-pdf-reporting.md)
    - [ADR-022: Unified Single-Surface Employee Dossier Hub and Client-Side Cached Lookups](./dev-docs/decisions/022-unified-employee-dossier-hub-and-client-cached-lookups.md)
    - [ADR-023: Peer Benchmarking Workload Distribution (CALC-06) and Server-Side Operator Anonymization](./dev-docs/decisions/023-peer-benchmarking-calc-06-and-operator-role-anonymization.md)
    - [ADR-024: Rolling 4-Week Welfare Indicators and Calendar-Month Fatigue Alert Deduplication](./dev-docs/decisions/024-rolling-4-week-welfare-indicators-and-fatigue-alert-deduplication.md)
    - [ADR-025: Chronological Audit Timesheet and Zero-Memory Streamed CSV Export](./dev-docs/decisions/025-chronological-audit-timesheet-and-zero-memory-streamed-csv-export.md)
    - [ADR-026: Financial Labor Attribution Schedule & Native OpenXML Streaming Export](./dev-docs/decisions/026-financial-labor-attribution-report-and-native-xlsx-streaming.md)
    - [ADR-027: In-Place CapEx Physical Progress Update & Audit Trail](./dev-docs/decisions/027-in-place-capex-physical-progress-update-and-audit-trail.md)
    - [ADR-028: Shared Vue Chart.js Infrastructure and Operational KPI Cards](./dev-docs/decisions/028-shared-vue-chartjs-infrastructure-and-operational-kpi-cards.md)
    - [ADR-029: Daily Burn Line Chart and Section Burn Comparison](./dev-docs/decisions/029-daily-burn-chart-and-section-burn-comparison.md)
    - [ADR-030: Executive Dashboard Tabbed Progressive Disclosure Architecture](./dev-docs/decisions/030-executive-dashboard-tabbed-progressive-disclosure.md)
    - [ADR-031: Analytics Page Shell and Client-Side Tab Navigation Architecture](./dev-docs/decisions/031-analytics-shell-and-tab-navigation-architecture.md)
    - [ADR-032: Predictive Analytics ML and Moving Average Fallback Engine](./dev-docs/decisions/032-predictive-analytics-ml-and-moving-average-fallback.md)
    - [ADR-033: Automotive Login Portal and Responsive Collapsible Sidebar](./dev-docs/decisions/033-automotive-login-portal-and-responsive-collapsible-sidebar.md)
    - [ADR-034: Cost Analysis OpEx vs CapEx Segregation and Budget Variance Architecture](./dev-docs/decisions/034-cost-analysis-opex-capex-segregation-and-budget-variance.md)
- **Technical Feature Design Specifications:**
    - [Authentication & Role-Based Access Control](./dev-docs/features/authentication-rbac.md)
    - [Database Schema Migrations (Full DDL)](./dev-docs/features/database-schema-migrations.md)
    - [Eloquent Models & Domain Relationships](./dev-docs/features/eloquent-models-relationships.md)
    - [Foundation Layout & Wayfinder Navigation](./dev-docs/features/foundation-layout-wayfinder.md)
    - [Department & Section Hierarchy Management](./dev-docs/features/department-section-management.md)
    - [Employee Roster Management & CSV Import](./dev-docs/features/employee-roster-management.md)
    - [Operational Calendar Management & API](./dev-docs/features/operational-calendar-management.md)
    - [Policy Threshold Configuration & Hierarchical Fallback](./dev-docs/features/policy-threshold-configuration.md)
    - [User Account Management & Role-Based Access Control (RBAC)](./dev-docs/features/user-account-management.md)
    - [User Preferences & Display Standards](./dev-docs/features/user-preferences-settings.md)
    - [Overtime Budget Planning & Scoping](./dev-docs/features/overtime-budget-planning.md)
    - [Background Jobs & Redis Queues](./dev-docs/features/background-jobs-queues.md)
    - [Database Seeding & Demo Data](./dev-docs/features/database-seeding-demo-data.md)
    - [Daily Overtime Entry & SPKL Workflow](./dev-docs/features/daily-overtime-spkl.md)
    - [Policy Soft Warning Indicators](./dev-docs/features/policy-soft-warning-indicators.md)
    - [SPKL Flexible Post-Shift Attachment & Lifecycle Workflow](./dev-docs/features/spkl-document-workflow.md)
    - [SPKL Pending Reminder & In-App Notifications](./dev-docs/features/spkl-pending-reminder-notifications.md)
    - [Verification, Item Approvals, Bulk Decisions & Export (E04-01 to E04-04)](./dev-docs/features/verification-approval.md)
    - [Budget Management & Burn Index Dashboard](./dev-docs/features/budget-burn-index.md)
    - [Budget Threshold Alert System & In-App Warnings (E05-04)](./dev-docs/features/budget-threshold-alerts.md)
    - [Individual Employee Reporting & Welfare](./dev-docs/features/employee-welfare-report.md)
    - [Employee Self-Service Personal Dashboard (E06-06)](./dev-docs/features/employee-self-service.md)
    - [CapEx Project Labor Management](./dev-docs/features/capex-project-labor.md)
    - [Supervised Machine Learning Analytics](./dev-docs/features/ml-predictive-analytics.md)
    - [Executive Operational Dashboard & KPI Cards (E09-00 - E09-01)](./dev-docs/features/executive-dashboard-kpi.md)
    - [Daily Burn Chart Index & Section Burn Comparison (E09-02 & E09-03)](./dev-docs/features/daily-burn-and-section-comparison.md)
    - [Executive Dashboard Multi-Chart Analytics Grid (E09-04)](./dev-docs/features/executive-dashboard-multi-chart-grid.md)
    - [Summary Employee Overtime Table (E09-05)](./dev-docs/features/executive-dashboard-employee-summary-table.md)
    - [Analytics & Decision Intelligence Hub (E09-06)](./dev-docs/features/analytics-decision-intelligence.md)
- **API Reference Design:**
    - [Calendar Classification & Management API](./dev-docs/api/calendar-endpoints.md)
    - [Overtime Policy Check API](./dev-docs/api/overtime-policy-check.md)
    - [Overtime Submissions & Approvals API](./dev-docs/api/overtime-submissions.md)
    - [In-App Notifications API](./dev-docs/api/notifications.md)
    - [Analytics, Burn Index & Machine Learning API](./dev-docs/api/analytics-reports.md)
    - [Analytics & Decision Intelligence API](./dev-docs/api/analytics-endpoints.md)

---

## 3. Scrum Planning & Agile Roadmaps (`docs/scrum/`)

Agile backlog, user stories, acceptance criteria, and estimation:

- **[Scrum Planning Overview](./scrum/scrum-plan-overview.md)**
- **Sprint Epics:**
    - [Epic-01: Foundation & Infrastructure Setup](./scrum/Epic-01.md) (55 SP, Sprint 1)
    - [Epic-02: Master Data & Administration](./scrum/Epic-02.md) (42 SP, Sprint 2)
    - [Epic-03: Daily Overtime Entry & SPKL Workflow](./scrum/Epic-03.md) (47 SP, Sprint 3)
    - [Epic-04: Verification & Approval Lifecycle](./scrum/Epic-04.md) (38 SP, Sprint 4)
    - [Epic-05: Budget Management & Burn Index Dashboard](./scrum/Epic-05.md) (45 SP, Sprint 5)
    - [Epic-06: Individual Employee Reporting & Welfare](./scrum/Epic-06.md) (30 SP, Sprint 6)
    - [Epic-07: CapEx Project Labor Management](./scrum/Epic-07.md) (35 SP, Sprint 6–7)
    - [Epic-08: Supervised ML Analytics & Predictive Intelligence](./scrum/Epic-08.md) (58 SP, Sprint 7–8)
- **Technical Analysis References:**
    - [Business Analysis Draft Requirements](./scrum/ba-analyst-reqs-draft.md)
    - [Data Architecture & Relational Design Blueprint](./scrum/data-architect-analyst.md)

---

## 4. End-User Documentation (`docs/user-docs/`)

Plain-language operating guides for factory supervisors, team leaders, and operators:

- **[User Documentation Index](./user-docs/README.md)**
    - [Authentication & Access Control Guide](./user-docs/guides/authentication-rbac.md)
    - [Dashboard & Operational Navigation Guide](./user-docs/guides/dashboard-navigation.md)
    - [Department & Section Management Guide](./user-docs/guides/department-section-management.md)
    - [Employee Roster Management & CSV Import Guide](./user-docs/guides/employee-roster-management.md)
    - [Policy Threshold Configuration Guide](./user-docs/guides/policy-threshold-configuration.md)
    - [User Account Management Guide](./user-docs/guides/user-account-management.md)
    - [User Preferences & Display Standards Guide](./user-docs/guides/user-preferences-settings.md)
    - [Overtime Budget Planning Guide](./user-docs/guides/overtime-budget-planning.md)
    - [Burn Index & Budget Dashboard Guide](./user-docs/guides/budget-burn-index.md)
    - [Policy Threshold Alerts & Budget Warnings Guide](./user-docs/guides/budget-threshold-alerts.md)
    - [Daily Overtime Submission Guide](./user-docs/guides/daily-overtime-submission.md)
    - [Policy Soft Warning Indicators Guide](./user-docs/guides/policy-soft-warning-indicators.md)
    - [SPKL Document Attachment Guide](./user-docs/guides/spkl-document-attachment.md)
    - [SPKL Pending Reminders & Notifications Guide](./user-docs/guides/spkl-pending-reminders.md)
    - [Overtime Approvals Queue Guide](./user-docs/guides/overtime-approvals.md)
    - [Individual Employee Dossier & Welfare Tracking Guide](./user-docs/guides/individual-employee-dossier.md)
    - [Employee Self-Service Personal Dashboard Guide](./user-docs/guides/employee-self-service.md)
    - [CapEx Project Labor & Portfolio Monitoring Guide](./user-docs/guides/capex-project-labor.md)
    - [Executive Operational Dashboard & KPI Cards Guide](./user-docs/guides/executive-dashboard-kpi.md)
    - [Daily Burn Chart Index & Section Comparison Guide](./user-docs/guides/daily-burn-and-section-comparison.md)
    - [Executive Dashboard Multi-Chart Analytics Grid Guide](./user-docs/guides/executive-dashboard-multi-chart-grid.md)
    - [Summary Employee Overtime Table Guide](./user-docs/guides/executive-dashboard-employee-summary-table.md)
    - [Analytics & Decision Intelligence Guide](./user-docs/guides/analytics-decision-intelligence.md)

---

## 5. Engineering Standards & Quality Checklist

Before committing or pushing any changes, enforce the following quality gates:

```bash
# 1. Type check and Vue compilation
pnpm check

# 2. Production build verification
pnpm build

# 3. PHP code formatting
vendor/bin/pint --dirty --format agent
```
