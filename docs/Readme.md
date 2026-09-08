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
    - [Individual Employee Reporting & Welfare](./dev-docs/features/employee-welfare-report.md)
    - [CapEx Project Labor Management](./dev-docs/features/capex-project-labor.md)
    - [Supervised Machine Learning Analytics](./dev-docs/features/ml-predictive-analytics.md)
- **API Reference Design:**
    - [Calendar Classification & Management API](./dev-docs/api/calendar-endpoints.md)
    - [Overtime Policy Check API](./dev-docs/api/overtime-policy-check.md)
    - [Overtime Submissions & Approvals API](./dev-docs/api/overtime-submissions.md)
    - [In-App Notifications API](./dev-docs/api/notifications.md)
    - [Analytics, Burn Index & Machine Learning API](./dev-docs/api/analytics-reports.md)

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
    - [Daily Overtime Submission Guide](./user-docs/guides/daily-overtime-submission.md)
    - [Policy Soft Warning Indicators Guide](./user-docs/guides/policy-soft-warning-indicators.md)
    - [SPKL Document Attachment Guide](./user-docs/guides/spkl-document-attachment.md)
    - [SPKL Pending Reminders & Notifications Guide](./user-docs/guides/spkl-pending-reminders.md)
    - [Overtime Approvals Queue Guide](./user-docs/guides/overtime-approvals.md)

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
