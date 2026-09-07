# Scrum Planning Overview

## Overtime & CapEx Labor Management System (OT-CapEx System)

**Prepared by:** Senior Business Analyst & Data Architect  
**Lead Developer:** Zulfikar Hidayatullah  
**Tech Stack:** Laravel 11/12 (PHP 8.3+) · Vue 3 Inertia.js (Wayfinder) · PostgreSQL 16 / MySQL 8.0  
**Standards:** Timezone `Asia/Jakarta` · Currency `Rp (IDR)` · Package Manager `pnpm`  
**Reference Docs:**

- BA Requirements: `migration-plan/ba-analyst-reqs-draft.md`
- Data Architecture: `migration-plan/data-architect-analyst.md`

---

## Epic Index

| Epic                    | Title                                             | Priority          | Est. Points | Sprints    | Status         |
| ----------------------- | ------------------------------------------------- | ----------------- | ----------- | ---------- | -------------- |
| [Epic-01](./Epic-01.md) | Foundation & Infrastructure Setup                 | P0 – Critical     | 55 SP       | Sprint 1   | 🟢 Completed   |
| [Epic-02](./Epic-02.md) | Master Data & Administration                      | P0 – Critical     | 42 SP       | Sprint 2   | 🟢 Completed   |
| [Epic-03](./Epic-03.md) | Daily Overtime Entry & SPKL Workflow              | P1 – Must Have    | 47 SP       | Sprint 3   | 🟢 Completed   |
| [Epic-04](./Epic-04.md) | Verification & Approval Lifecycle                 | P1 – Must Have    | 38 SP       | Sprint 4   | 🔴 Not Started |
| [Epic-05](./Epic-05.md) | Budget Management & Burn Index Dashboard          | P1 – Must Have    | 45 SP       | Sprint 5   | 🔴 Not Started |
| [Epic-06](./Epic-06.md) | Individual Employee Reporting & Welfare           | P2 – Should Have  | 30 SP       | Sprint 6   | 🔴 Not Started |
| [Epic-07](./Epic-07.md) | CapEx Project Labor Management                    | P2 – Should Have  | 35 SP       | Sprint 6–7 | 🔴 Not Started |
| [Epic-08](./Epic-08.md) | Supervised ML Analytics & Predictive Intelligence | P3 – Nice to Have | 58 SP       | Sprint 7–8 | 🔴 Not Started |

**Total Estimated Effort:** ~350 Story Points  
**Estimated Duration:** 8 Sprints × 2-Week Cycles = ~16 Weeks

---

## Sprint Roadmap

```
Sprint 1  │  Epic-01: Foundation & Infrastructure
Sprint 2  │  Epic-02: Master Data & Administration
Sprint 3  │  Epic-03: Daily Overtime Entry & SPKL
Sprint 4  │  Epic-04: Verification & Approval Lifecycle
Sprint 5  │  Epic-05: Budget & Burn Index Dashboard
Sprint 6  │  Epic-06: Employee Reporting  +  Epic-07: CapEx Projects
Sprint 7  │  Epic-07 cont.  +  Epic-08: ML (Demand Forecasting + Burn Trajectory)
Sprint 8  │  Epic-08 cont.: ML (CapEx Forecast + Anomaly Detection) + UAT Hardening
```

---

## Architecture Quick Reference

### User Roles

| Role          | Description                                                     |
| ------------- | --------------------------------------------------------------- |
| `Admin`       | Plant-wide access, master data, user management, policy config  |
| `Manager`     | Dept-level oversight, bulk approval, ML dashboard, CapEx splits |
| `Team Leader` | Daily OT entry, SPKL attachment, section dashboard              |
| `User`        | Read-only: personal timesheet, individual Burn Index            |

### Core Calculation Cheatsheet

| Formula                    | Code Reference                          |
| -------------------------- | --------------------------------------- |
| `CALC-01` Total OT Hours   | `Production + TPM + Project + Others`   |
| `CALC-02` Burn Index       | `(Actual Hours / Budget Hours) × 100%`  |
| `CALC-03` Remaining Budget | `Budget Hours − Actual Hours`           |
| `CALC-04` Burn Velocity    | `Cumulative Hours / Elapsed Weeks`      |
| `CALC-05` Projected Total  | `Velocity × Total Weeks in Period`      |
| `CALC-06` Peer Variance    | `Individual Hours − Dept Average Hours` |
| `CALC-07` CapEx Ratio      | `(Project Hours / Total Hours) × 100%`  |

### Burn Index Status Thresholds

| Range         | Status                         | Zone             |
| ------------- | ------------------------------ | ---------------- |
| `< 85%`       | Under Budget (High Efficiency) | ZONE_1_EXCELLENT |
| `85% – 100%`  | On Track                       | ZONE_2_GOOD      |
| `101% – 115%` | Warning (Slight Overrun)       | ZONE_3_WARNING   |
| `> 115%`      | Over Budget (Deficit)          | ZONE_4_POOR      |

### Day Types

| Code  | Meaning                                                             |
| ----- | ------------------------------------------------------------------- |
| `HKN` | Hari Kerja Normal – standard operational weekday                    |
| `HLR` | Hari Libur / Istirahat Mingguan – weekend, rest day, public holiday |

---

## Developer Ground Rules

1. **Routing:** Use **Wayfinder** exclusively. Never use legacy Ziggy `route()` helpers.
2. **Service Pattern:** Controllers ≤ 30 lines. All business logic lives in `Actions/` or `Services/`.
3. **Currency:** Always store as `NUMERIC(15,2)`. Never use `FLOAT` or `DOUBLE` for IDR values.
4. **Timezone:** All `Carbon` calls must specify `'Asia/Jakarta'` explicitly: `now('Asia/Jakarta')`.
5. **Financial Snapshots:** Immutable rate snapshots (`hourly_rate_snapshot`, `total_cost_snapshot`) must be written on item approval, never recalculated retroactively.
6. **Concurrency:** `lockForUpdate()` + optimistic `lock_version` on all approval item writes.
7. **ML Fallback:** If `n_historical_shifts < 30`, auto-fallback to moving average with badge `Calculation: Moving Average (Insufficient ML Baseline)`.
8. **Quality Gate:** Always run `pnpm lint` and `pnpm build` before pushing.
9. **SPKL:** Non-blocking — submission must succeed without an attached SPKL document.
10. **Case Sensitivity:** Table names, enum values, and status strings are case-sensitive. Follow schema exactly.

---

## Definition of Done (DoD) — Global

The following must be true before any story is considered **Done**:

- [ ] All acceptance criteria in the story checklist are met
- [ ] Wayfinder routes generated and typed correctly
- [ ] `pnpm lint` passes with zero errors
- [ ] `pnpm build` succeeds with no warnings that affect production
- [ ] All database interactions use Laravel migrations (no raw schema changes)
- [ ] Unit/feature tests written for domain actions and services
- [ ] Vue components use `<script setup>` SFC pattern
- [ ] No hardcoded timezone, currency symbol, or date format strings
- [ ] Code reviewed by at least one peer before merge
