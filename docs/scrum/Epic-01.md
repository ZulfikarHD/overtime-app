# Epic-01: Foundation & Infrastructure Setup

**Epic ID:** E-01  
**Priority:** P0 – Critical (Blocker for all other epics)  
**Estimated Total:** 55 Story Points  
**Target Sprints:** Sprint 1 (Weeks 1–2)  
**Dependencies:** None — this is the root epic  
**Lead Area:** Backend (Laravel) + Frontend (Vue 3 + Wayfinder) + Database (PostgreSQL/MySQL)

---

## Business Context

Before any overtime timesheet can be entered, approved, or analyzed, the entire technical foundation must exist. This epic covers the full project scaffolding, database schema creation, authentication system, role-based access control, and Wayfinder routing infrastructure. Without this epic, no other feature can be built or tested.

The factory runs three shifts (07:00, 15:00, 23:00 WIB). Every timestamp, form submission, and calculation in this system must operate in `Asia/Jakarta` timezone and store financial values in `NUMERIC(15,2)` format. These are non-negotiable invariants set up here.

---

## User Stories

---

### Story E01-01: Laravel Project Scaffolding & Stack Configuration
**As a** developer,  
**I want** a clean Laravel 11+ project configured with Vue 3 Inertia.js, Wayfinder routing, and pnpm,  
**So that** the full team has a consistent, runnable local development baseline from day one.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria
- [ ] Laravel 11+ project initialized with PHP 8.3+ requirement enforced in `composer.json`
- [ ] Vue 3 with `<script setup>` SFC pattern installed via `pnpm`
- [ ] Inertia.js (server + client) installed and `HandleInertiaRequests` middleware registered
- [ ] **Wayfinder** installed and configured; `@wayfinder/laravel` + `@wayfinder/vue` packages in place — Ziggy is NOT installed
- [ ] `pnpm lint` configured (ESLint + PHP CS Fixer or Pint)
- [ ] `pnpm build` succeeds (Vite production build)
- [ ] `.env.example` includes all required keys: `APP_TIMEZONE=Asia/Jakarta`, `DB_*`, `REDIS_*`, `QUEUE_CONNECTION=redis`
- [ ] `config/app.php` timezone set to `'Asia/Jakarta'`
- [ ] `AppServiceProvider` registers Carbon locale `id` (Indonesian)

#### Technical Tasks
- [ ] `laravel new capex-ot-system --jet` or minimal `laravel new` + manual Inertia setup
- [ ] `pnpm add @inertiajs/vue3 vue @vitejs/plugin-vue`
- [ ] `pnpm add @wayfinder/vue` — configure Wayfinder generation in `vite.config.ts`
- [ ] Configure `vite.config.ts` with `laravel()` plugin + Vue plugin
- [ ] Set up PHP Pint (`composer require laravel/pint --dev`) and `.pint.json`
- [ ] Set up ESLint + Vue plugin: `pnpm add -D eslint @vue/eslint-config-typescript`
- [ ] Create `.eslintrc.cjs` with `vue/vue3-essential` rules
- [ ] Run `pnpm lint && pnpm build` to validate baseline

---

### Story E01-02: Database Schema Migrations (Full DDL)
**As a** developer,  
**I want** all database tables created via modular Laravel migration files,  
**So that** the schema is version-controlled, repeatable across environments, and enforces all business constraints at the database layer.

**Story Points:** 13  
**Priority:** Must Have

#### Acceptance Criteria
- [ ] All migrations run successfully via `php artisan migrate` with zero errors
- [ ] Foreign key constraints use `ON DELETE RESTRICT` by default (no cascading deletes on financial records)
- [ ] `overtime_items.total_hours` is a **Stored Generated Column** (`GENERATED ALWAYS AS (hours_production + hours_tpm + hours_project + hours_others) STORED`)
- [ ] Enum types defined correctly (PostgreSQL `CREATE TYPE` or MySQL `ENUM`)
- [ ] All CHECK constraints enforced: min 0.5 total hours, non-negative category hours, `capex_project_id NOT NULL when hours_project > 0`
- [ ] All partial indexes created for performance (e.g., `WHERE status = 'PENDING'`, `WHERE is_active = TRUE`)
- [ ] `php artisan migrate:fresh` runs cleanly on a blank database
- [ ] `php artisan migrate:status` shows all migrations as "Ran"

#### Migration Files (Create in this order)
```
database/migrations/
├── 2026_01_01_000001_create_departments_table.php
├── 2026_01_01_000002_create_sections_table.php
├── 2026_01_01_000003_create_employees_table.php
├── 2026_01_01_000004_create_operational_calendars_table.php
├── 2026_01_01_000005_create_policy_thresholds_table.php
├── 2026_01_01_000006_create_capex_projects_table.php
├── 2026_01_01_000007_create_overtime_budgets_table.php
├── 2026_01_01_000008_create_overtime_submissions_table.php
├── 2026_01_01_000009_create_spkl_documents_table.php
├── 2026_01_01_000010_create_overtime_items_table.php
├── 2026_01_01_000011_create_overtime_item_audits_table.php
├── 2026_01_01_000012_create_monthly_burn_snapshots_table.php
├── 2026_01_01_000013_create_ml_models_table.php
├── 2026_01_01_000014_create_ml_predictions_table.php
└── 2026_01_01_000015_create_ml_anomaly_logs_table.php
```

#### Technical Tasks
- [ ] Create each migration file following the DDL in `data-architect-analyst.md` §2.3
- [ ] For MySQL: replace `BIGSERIAL` → `BIGINT UNSIGNED AUTO_INCREMENT`, `TIMESTAMPTZ` → `TIMESTAMP`, `JSONB` → `JSON`
- [ ] For the generated column on `overtime_items`, use `->storedAs('hours_production + hours_tpm + hours_project + hours_others')`
- [ ] Use `$table->enum()` for enum columns or raw `DB::statement()` for PostgreSQL custom types
- [ ] Run `php artisan migrate:fresh --seed` after each new migration during development

---

### Story E01-03: Eloquent Models & Relationships
**As a** developer,  
**I want** all Eloquent models created with proper relationships, casts, and fillable guards,  
**So that** the application layer can interact with the database with full type safety and no mass-assignment vulnerabilities.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria
- [ ] All 15 models created in `app/Models/`
- [ ] Each model defines `$fillable` OR `$guarded = []` (no unguarded wildcard on financial models)
- [ ] All `NUMERIC` fields cast to `'decimal:2'`
- [ ] All `BOOLEAN` fields cast to `'boolean'`
- [ ] All `JSONB`/`JSON` fields cast to `'array'`
- [ ] All `TIMESTAMPTZ`/`TIMESTAMP` fields cast to `'datetime'`
- [ ] Relationships defined: `hasMany`, `belongsTo`, `hasOne` as per ERD in `data-architect-analyst.md` §2.2
- [ ] `Employee` model scopes: `scopeActive()`, `scopeForSection($sectionId)`, `scopeForDepartment($deptId)`
- [ ] `OvertimeItem` model: `total_hours` marked as `$appends` computed property if not using generated column in MySQL

#### Model List
```
app/Models/
├── Department.php
├── Section.php
├── Employee.php
├── OperationalCalendar.php
├── PolicyThreshold.php
├── CapexProject.php
├── OvertimeBudget.php
├── OvertimeSubmission.php
├── SpklDocument.php
├── OvertimeItem.php
├── OvertimeItemAudit.php
├── MonthlyBurnSnapshot.php
├── MlModel.php
├── MlPrediction.php
└── MlAnomalyLog.php
```

#### Technical Tasks
- [ ] Generate stubs: `php artisan make:model [Name]`
- [ ] Add all `$casts` arrays per field type
- [ ] Define `BelongsTo`, `HasMany`, `HasOne` relationships with inverse counterparts
- [ ] Add `Employee::scopeActiveInSection()` composite scope
- [ ] Add `OvertimeSubmission::scopePending()`, `scopeForSection()` scopes
- [ ] Add `OvertimeItem::scopeApproved()`, `scopeByEmployee()` scopes

---

### Story E01-04: Authentication & Role-Based Access Control (RBAC)
**As an** Admin,  
**I want** a secure authentication system with four distinct roles enforced at the route and gate level,  
**So that** only authorized personnel can access the correct features (Admin, Manager, Team Leader, User/Employee).

**Story Points:** 13  
**Priority:** Must Have

#### Acceptance Criteria
- [ ] `users` table has `role` column with ENUM: `admin`, `manager`, `team_leader`, `user`
- [ ] `users` table has `department_id` (nullable FK) to scope Manager/Team Leader access
- [ ] Login page with email + password (no social auth required at MVP)
- [ ] Failed login: lockout after 5 attempts (Laravel's `ThrottlesLogins`)
- [ ] Password: minimum 8 characters, complexity configurable
- [ ] `AuthenticatedLayout.vue` shows different navigation items based on authenticated user role
- [ ] Laravel Gates defined for each role: `Gate::define('is-admin', ...)`, `Gate::define('is-manager', ...)`, etc.
- [ ] Middleware `EnsureRole::class` applied to route groups
- [ ] A Team Leader cannot access another section's overtime data
- [ ] A Manager can only see departments they are assigned to (unless Admin)
- [ ] `pnpm lint && pnpm build` passes

#### Role Permission Matrix
| Capability | Admin | Manager | Team Leader | User |
|-----------|-------|---------|-------------|------|
| User management | ✅ | ❌ | ❌ | ❌ |
| Overtime submission (create) | ✅ | ❌ | ✅ | ❌ |
| Overtime approval | ✅ | ✅ | ❌ | ❌ |
| View all sections | ✅ | ✅ (own dept) | ❌ | ❌ |
| View personal report | ✅ | ✅ | ✅ | ✅ |
| ML dashboard | ✅ | ✅ | ❌ | ❌ |
| Policy configuration | ✅ | ❌ | ❌ | ❌ |

#### Technical Tasks
- [ ] `php artisan make:migration add_role_to_users_table`
- [ ] `php artisan make:middleware EnsureRole`
- [ ] Register middleware in `bootstrap/app.php` middleware aliases
- [ ] Define Gates in `AuthServiceProvider` or `AppServiceProvider`
- [ ] Create `LoginController` and update Inertia auth pages in `resources/js/Pages/Auth/`
- [ ] Update `HandleInertiaRequests` to share `auth.user.role` in shared props
- [ ] Protect route groups: `->middleware(['auth', 'role:admin'])` etc.

---

### Story E01-05: Wayfinder Route Generation & Base Inertia Layout
**As a** developer,  
**I want** Wayfinder route generation configured and a base Inertia layout shell in place,  
**So that** all Vue pages can navigate type-safely using generated route functions from the first commit.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria
- [ ] `wayfinder.json` or Vite Wayfinder plugin configured to auto-generate route files on `pnpm dev`
- [ ] No `route()` Ziggy calls anywhere in the codebase — confirmed by `pnpm lint` rule
- [ ] `AuthenticatedLayout.vue` scaffolded with sidebar, top navigation, and role-based menu rendering
- [ ] `GuestLayout.vue` scaffolded for login page
- [ ] Base Inertia `app.blade.php` root template configured
- [ ] Flash message handling (success/error) wired up via shared Inertia props
- [ ] `pnpm build` passes

#### Technical Tasks
- [ ] Configure Wayfinder in `vite.config.ts`
- [ ] Register all application routes in `routes/web.php` with named routes
- [ ] Run `pnpm dev` and verify `resources/js/wayfinder/` (or equivalent) route files are generated
- [ ] Create `AuthenticatedLayout.vue` and `GuestLayout.vue` in `resources/js/Layouts/`
- [ ] Add ESLint rule to warn on `route()` calls if possible (or document as manual convention)
- [ ] Create base `resources/js/app.ts` Inertia bootstrap

---

### Story E01-06: Redis Queue, Background Jobs & Environment Configuration
**As a** developer,  
**I want** Redis queue workers configured and base job classes scaffolded,  
**So that** asynchronous workloads (SPKL reminders, Burn snapshot rollups, ML anomaly scans) do not block user-facing requests.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria
- [ ] `QUEUE_CONNECTION=redis` set in `.env.example` with Redis `REDIS_*` keys
- [ ] `php artisan queue:work` starts without errors
- [ ] Three base job classes scaffolded (empty implementations, dispatching will be wired in later epics):
  - `RunAnomalyDetectionJob`
  - `RecalculateMonthlyBurnSnapshotJob`
  - `SendSpklReminderJob`
- [ ] Horizon or Supervisor config documented in `README.md` or deployment notes
- [ ] Jobs have `$tries = 3`, `$backoff = [30, 120, 300]` configured

#### Technical Tasks
- [ ] `php artisan make:job RunAnomalyDetectionJob`
- [ ] `php artisan make:job RecalculateMonthlyBurnSnapshotJob`
- [ ] `php artisan make:job SendSpklReminderJob`
- [ ] Configure `config/queue.php` Redis connection
- [ ] Add `config/horizon.php` if using Horizon, or document Supervisor config
- [ ] Test job dispatch with `Artisan::call('queue:work --once')`

---

### Story E01-07: Database Seeding (Dev & Demo Data)
**As a** developer,  
**I want** comprehensive database seeders with realistic factory floor data,  
**So that** I can develop and test all features without manually entering data through the UI.

**Story Points:** 3  
**Priority:** Must Have

#### Acceptance Criteria
- [ ] `DatabaseSeeder` orchestrates all seeders in dependency order
- [ ] At least 1 demo Admin, 2 Managers, 5 Team Leaders, 20 Users seeded
- [ ] At least 6 Departments and 12+ Sections seeded with realistic automotive plant names
- [ ] At least 100 Employees seeded with unique NPK values
- [ ] `operational_calendars` seeded for the current year (HKN/HLR classification)
- [ ] `policy_thresholds` seeded with plant defaults (20 hrs/week soft limit, 2 days SPKL grace)
- [ ] `overtime_budgets` seeded for current fiscal month (at least one section)
- [ ] `php artisan db:seed` runs in < 30 seconds
- [ ] `php artisan migrate:fresh --seed` resets and re-seeds cleanly

#### Technical Tasks
- [ ] `php artisan make:seeder DepartmentSeeder`
- [ ] `php artisan make:seeder SectionSeeder`
- [ ] `php artisan make:seeder EmployeeSeeder` (use Faker, Indonesian locale)
- [ ] `php artisan make:seeder UserSeeder` (one per role)
- [ ] `php artisan make:seeder OperationalCalendarSeeder` (loop full year)
- [ ] `php artisan make:seeder PolicyThresholdSeeder`
- [ ] `php artisan make:seeder OvertimeBudgetSeeder`

---

## Sprint 1 Breakdown

| Sprint Day | Focus | Stories |
|-----------|-------|---------|
| Day 1–2 | Project scaffolding, pnpm/Vite/Inertia/Wayfinder setup | E01-01 |
| Day 3–5 | All 15 database migrations, verify foreign keys & constraints | E01-02 |
| Day 6–7 | Eloquent models, relationships, casts, scopes | E01-03 |
| Day 8–9 | Authentication, RBAC Gates, role middleware | E01-04 |
| Day 9–10 | Wayfinder config, layouts, base routes; Redis jobs; Seeders | E01-05, E01-06, E01-07 |

---

## Risks & Assumptions

| Risk | Likelihood | Mitigation |
|------|-----------|------------|
| MySQL vs PostgreSQL DDL differences (JSONB, GENERATED columns, enum types) | Medium | Architect has noted MySQL equivalents; test both dialects in CI |
| Wayfinder route generation breaking on Windows path separators | Low | Test on Windows dev environment early (developer uses Windows 10) |
| Redis not available on local dev | Low | Provide Docker Compose with Redis service in repo |
| Generated column syntax differences across MySQL versions | Medium | Test on MySQL 8.0.29+ specifically; document minimum version |

---

## Definition of Done — Epic-01

- [ ] `php artisan migrate:fresh --seed` runs cleanly
- [ ] `pnpm lint` passes with zero errors
- [ ] `pnpm build` succeeds (Vite production)
- [ ] Admin/Manager/Team Leader/User login and redirects to correct dashboard
- [ ] Role middleware blocks unauthorized routes (returns 403)
- [ ] All 15 migrations show "Ran" in `migrate:status`
- [ ] Wayfinder routes generated and used in at least one test navigation link
- [ ] Code reviewed and merged to `main`
