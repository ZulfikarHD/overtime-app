# Epic-02: Master Data & Administration

**Epic ID:** E-02  
**Priority:** P0 – Critical  
**Estimated Total:** 42 Story Points  
**Target Sprints:** Sprint 2 (Weeks 3–4)  
**Dependencies:** Epic-01 (Foundation must be complete)  
**Lead Area:** Backend (Laravel CRUD + Services) + Vue 3 Admin UI

---

## Business Context

The system cannot function without clean, authoritative master data. Every overtime timesheet entry references a Department, Section, Employee (NPK), and an Operational Calendar date. Every budget calculation references a Policy Threshold. This epic builds the Administration module — the backbone that all other epics depend on.

Key real-world constraints:

- **NPK is immutable** once assigned (Business Rule BR-03). The system must enforce this.
- **Department/Section hierarchy** must resolve the naming conflict found during legacy analysis (e.g., `Assembly Line 1` vs `TCF FS`). This is the moment to standardize.
- **Operational Calendar** must be pre-seeded for the entire fiscal year so that every timesheet date is classified as `HKN` or `HLR` automatically.
- **Policy Thresholds** are configurable per department, with plant-wide defaults — this is where Admin configures warning levels and SPKL grace periods.

---

## User Stories

---

### Story E02-01: Department & Section Hierarchy Management (Admin)

**As an** Admin,  
**I want** to create, edit, and deactivate Departments and their child Sections,  
**So that** the organizational hierarchy is authoritative and all timesheet references are consistent.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] Admin can create a Department with: `code` (unique, e.g. `PROD`), `name`, `cost_center_code`, `default_hourly_rate` (Rp), `is_active`
- [ ] Admin can create a Section nested under a Department with: `code` (unique), `name`, `is_active`
- [ ] Department `code` and Section `code` are unique across the entire table — enforced at both DB and application layer
- [ ] Editing a Department/Section updates `name` and `default_hourly_rate` only — `code` is immutable after creation
- [ ] Deactivation (`is_active = false`) soft-disables the record; it remains visible in admin but hidden from timesheet entry forms
- [ ] Deleting a Department/Section with linked employees or submissions is **blocked** (returns validation error, not a DB exception)
- [ ] Section list is always displayed nested under its parent Department
- [ ] Department `default_hourly_rate` is displayed formatted as `Rp 1.234,56` (Indonesian number format)
- [ ] `pnpm lint && pnpm build` passes

#### Technical Tasks

- [ ] `php artisan make:controller Admin/DepartmentController --resource`
- [ ] `php artisan make:controller Admin/SectionController --resource`
- [ ] `php artisan make:request StoreDepartmentRequest` (validate unique `code`, numeric `default_hourly_rate`)
- [ ] `php artisan make:request StoreSectionRequest` (validate `department_id` exists, unique `code`)
- [ ] Create `resources/js/Pages/Admin/Departments/Index.vue` — table with nested sections
- [ ] Create `resources/js/Pages/Admin/Departments/Form.vue` — create/edit modal or page
- [ ] Create `resources/js/Pages/Admin/Sections/Form.vue`
- [ ] Register routes in `routes/web.php` with `->middleware(['auth', 'role:admin'])`
- [ ] Protect delete with check: `if ($department->employees()->exists() || $department->overtimeSubmissions()->exists()) abort(422)`
- [ ] Format `default_hourly_rate` as Rupiah in Vue: use `Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' })`

---

### Story E02-02: Employee Roster Management (Admin)

**As an** Admin,  
**I want** to create, edit, deactivate, and search employee records,  
**So that** Team Leaders always see an accurate and up-to-date section roster when entering overtime.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] Admin can create an Employee with: `npk` (unique, max 20 chars), `full_name`, `department_id`, `section_id`, `job_position`, `hourly_rate` (Rp), `is_active`
- [ ] **NPK is read-only after creation** — the edit form does not show a writable NPK field (BR-03)
- [ ] `section_id` dropdown is dynamically filtered based on selected `department_id` (no cross-department section assignment)
- [ ] Employee `hourly_rate` is nullable — if null, the system uses the parent Department's `default_hourly_rate` during cost snapshotting
- [ ] Admin can deactivate an employee (`is_active = false`); deactivated employees do not appear in the Team Leader's roster
- [ ] Admin can search employees by `npk`, `full_name`, or `section`
- [ ] Admin can bulk-import employees via CSV upload (columns: `npk`, `full_name`, `department_code`, `section_code`, `job_position`, `hourly_rate`)
- [ ] CSV import validates: unique NPK, valid department/section codes, numeric hourly rate
- [ ] CSV import shows a preview + error report before committing
- [ ] Pagination: 25 employees per page with server-side search

#### Technical Tasks

- [ ] `php artisan make:controller Admin/EmployeeController --resource`
- [ ] `php artisan make:request StoreEmployeeRequest` + `UpdateEmployeeRequest` (NPK excluded from update fillable)
- [ ] `php artisan make:job ImportEmployeesFromCsvJob`
- [ ] `EmployeeService::importFromCsv(UploadedFile $file): array` — returns `['imported' => N, 'errors' => [...]]`
- [ ] Create `resources/js/Pages/Admin/Employees/Index.vue` (paginated table, search bar)
- [ ] Create `resources/js/Pages/Admin/Employees/Form.vue` (create/edit, dynamic section dropdown)
- [ ] Create `resources/js/Pages/Admin/Employees/CsvImport.vue` (upload + preview table)
- [ ] Reactive section list: `watch(form.department_id, async (id) => { sections.value = await fetchSections(id) })`

---

### Story E02-03: Operational Calendar Management (Admin)

**As an** Admin,  
**I want** to manage the operational calendar to classify each date as HKN (normal workday) or HLR (holiday/rest day),  
**So that** every overtime submission is automatically tagged with the correct day type and analytics correctly separate regular vs. holiday overtime.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] System auto-populates the calendar for the current and next fiscal year using Saturday/Sunday = HLR, weekdays = HKN as the default rule
- [ ] Admin can view the calendar in a monthly grid view (like a standard calendar UI)
- [ ] Admin can click any date to toggle it between HKN and HLR
- [ ] Admin can add/edit a `holiday_name` and `description` for HLR dates
- [ ] Bulk import of national holidays via CSV (columns: `date`, `holiday_name`)
- [ ] Calendar changes take effect **immediately** for new submissions; already-submitted records retain their `day_type` snapshot and are **not retroactively updated**
- [ ] API endpoint: `GET /api/calendar/{date}` — returns `{ date, day_type, is_holiday, holiday_name }` — used by the overtime form to auto-classify the selected date

#### Technical Tasks

- [ ] `php artisan make:controller Admin/OperationalCalendarController`
- [ ] `OperationalCalendarService::generateForYear(int $year)` — bulk create rows for full year
- [ ] Run generation on `php artisan app:seed-calendar {year}` command
- [ ] `php artisan make:command SeedOperationalCalendarCommand`
- [ ] Create `resources/js/Pages/Admin/Calendar/Index.vue` — monthly grid component
- [ ] Create Vue `CalendarDayCell.vue` component — shows day type badge, togglable
- [ ] `GET /api/calendar/{date}` route: `->middleware('auth')->name('api.calendar.show')`
- [ ] Import CSV command or admin upload form

---

### Story E02-04: Policy Threshold Configuration (Admin)

**As an** Admin,  
**I want** to configure company policy thresholds for overtime safety limits and SPKL grace periods,  
**So that** the system enforces internal company policy (kebijakan perusahaan) rather than hardcoded values.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] Admin can view and edit the **plant-wide default** policy threshold (where `department_id IS NULL`)
- [ ] Admin can create **department-specific** overrides (where `department_id` is set)
- [ ] Configurable fields per threshold record:
    - `weekly_soft_limit_hours` (default: 20.0 hrs)
    - `consecutive_weeks_alert` (default: 3 weeks)
    - `spkl_grace_period_days` (default: 2 days)
    - `burn_warning_pct` (default: 100%)
    - `burn_danger_pct` (default: 115%)
- [ ] Changes to `spkl_grace_period_days` affect **new submissions only** — existing SPKL due dates are not retroactively recalculated
- [ ] Changes to `burn_warning_pct` / `burn_danger_pct` are reflected in dashboards **on next page load** (no cache required)
- [ ] If no department-specific threshold exists, the system falls back to the plant-wide default
- [ ] All numeric inputs are validated (non-negative, reasonable range: hours 0–168, pct 0–500)

#### Technical Tasks

- [ ] `php artisan make:controller Admin/PolicyThresholdController`
- [ ] `php artisan make:request StorePolicyThresholdRequest`
- [ ] `PolicyThresholdService::getForDepartment(int $departmentId): PolicyThreshold` — implements fallback logic
- [ ] Create `resources/js/Pages/Admin/PolicyThresholds/Index.vue` — table grouped by plant default + dept overrides
- [ ] Create `resources/js/Pages/Admin/PolicyThresholds/Form.vue` — edit modal
- [ ] Inject `PolicyThresholdService` into `SubmitOvertimeAction` (wired in Epic-03)

---

### Story E02-05: User Account Management (Admin)

**As an** Admin,  
**I want** to create, edit, deactivate, and reset passwords for user accounts,  
**So that** workforce changes (new hires, transfers, departures) are reflected immediately in system access.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] Admin can create a user with: `name`, `email` (unique), `password`, `role` (admin/manager/team_leader/user), `department_id` (optional, for scoping)
- [ ] Admin can deactivate a user (`is_active = false`) — deactivated users cannot log in (middleware check)
- [ ] Admin can trigger a password reset link email for any user
- [ ] Admin can change a user's role (the change takes effect on next login/session)
- [ ] Admin cannot delete their own account (guard against accidental self-lockout)
- [ ] User list shows: name, email, role badge, department, last login, status
- [ ] Role-change audit: when Admin changes a user's role, it is logged to `overtime_item_audits` (or a `user_audits` table if preferred) with `actor_user_id`, `previous_role`, `new_role`

#### Technical Tasks

- [ ] `php artisan make:controller Admin/UserController --resource`
- [ ] Update `users` table: add `is_active` boolean, `department_id` FK, `last_login_at` timestamp
- [ ] Update `AuthenticatedSessionController` to stamp `last_login_at` and check `is_active`
- [ ] `php artisan make:request StoreUserRequest` + `UpdateUserRequest`
- [ ] Create `resources/js/Pages/Admin/Users/Index.vue` — paginated table with role filter
- [ ] Create `resources/js/Pages/Admin/Users/Form.vue` — create/edit with role dropdown
- [ ] Send password reset via `Password::sendResetLink($email)`
- [ ] Guard self-delete: `if ($userToDelete->id === auth()->id()) abort(422, 'Cannot delete your own account.')`

---

### Story E02-06: Notification & UI Preference Settings (Per User)

**As any** logged-in user,  
**I want** to configure my UI theme and notification preferences,  
**So that** I receive the right alerts (SPKL reminders, budget alerts) through my preferred channel and in a comfortable UI mode.

**Story Points:** 3  
**Priority:** Should Have

#### Acceptance Criteria

- [ ] Each user can toggle: `theme` (Light / Dark / Auto)
- [ ] Each user can toggle notification preferences:
    - SPKL pending reminders (on/off)
    - Budget threshold alerts (on/off)
    - Approval status notifications (on/off)
- [ ] Preferences are persisted per user in `user_preferences` table or `users.preferences JSON` column
- [ ] Theme preference is applied immediately via CSS class on `<html>` or `<body>` (no page reload)
- [ ] Date format shown as `DD/MM/YYYY` (Indonesian standard) throughout the app — not configurable per user but confirmed as global standard
- [ ] Number format shown as Indonesian: `1.234,56` — enforced globally

#### Technical Tasks

- [ ] `php artisan make:migration add_preferences_to_users_table` (add `preferences JSON NULL`)
- [ ] `UserPreferencesController::update()` — PATCH `/user/preferences`
- [ ] Create `resources/js/Pages/Settings/Preferences.vue`
- [ ] Vue composable `useTheme()` — reads `preferences.theme`, applies `dark` class to `<html>`
- [ ] Pass `auth.user.preferences` via Inertia shared props in `HandleInertiaRequests`
- [ ] Global date formatter utility: `formatDate(date: string): string` using `dd/MM/yyyy` pattern
- [ ] Global currency formatter utility: `formatRupiah(amount: number): string` using `Intl.NumberFormat('id-ID')`

---

### Story E02-07: Overtime Budget Plan Setup (Admin/Manager)

**As an** Admin or Manager,  
**I want** to set the monthly overtime budget (planned hours) for each Department/Section,  
**So that** the Burn Index calculation has a denominator and budget tracking is possible.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] Admin and Manager can create/update an `overtime_budget` record per Section per fiscal month
- [ ] Required fields: `department_id`, `section_id` (optional for dept-level), `fiscal_year`, `fiscal_month`, `planned_hours`
- [ ] Optional 5-week breakdown: `week1_planned_hours` through `week5_planned_hours` (defaults to `planned_hours / 4.3` if not specified)
- [ ] Sum of weekly planned hours should equal `planned_hours` — system warns (not blocks) if they diverge
- [ ] Budget records are unique per `(department_id, section_id, fiscal_year, fiscal_month)` — upsert behavior
- [ ] Manager can only set budgets for their own department
- [ ] If no budget is set for a section/month, the Burn Index dashboard shows `"Budget Not Configured"` rather than crashing or showing 0%
- [ ] Bulk import: Admin can upload a CSV with columns `section_code`, `fiscal_year`, `fiscal_month`, `planned_hours`

#### Technical Tasks

- [ ] `php artisan make:controller OvertimeBudgetController --resource`
- [ ] `php artisan make:request StoreOvertimeBudgetRequest`
- [ ] Upsert logic: `OvertimeBudget::updateOrCreate(['department_id' => ..., 'section_id' => ..., 'fiscal_year' => ..., 'fiscal_month' => ...], [...])`
- [ ] Create `resources/js/Pages/Admin/OvertimeBudgets/Index.vue` — filterable by dept/section/month
- [ ] Create `resources/js/Pages/Admin/OvertimeBudgets/Form.vue` — month picker, section selector, weekly breakdown inputs
- [ ] Warn on week breakdown sum mismatch: reactive Vue `computed` property comparing sum to `planned_hours`

---

## Sprint 2 Breakdown

| Sprint Day | Focus                                        | Stories        |
| ---------- | -------------------------------------------- | -------------- |
| Day 1–2    | Department & Section CRUD + UI               | E02-01         |
| Day 3–4    | Employee Roster management + CSV import      | E02-02         |
| Day 5–6    | Operational Calendar + auto-generation + API | E02-03         |
| Day 7–8    | Policy Thresholds + User Account Management  | E02-04, E02-05 |
| Day 9–10   | Budget Plan setup + User Preferences         | E02-07, E02-06 |

---

## Risks & Assumptions

| Risk                                                                     | Likelihood | Mitigation                                                                                                          |
| ------------------------------------------------------------------------ | ---------- | ------------------------------------------------------------------------------------------------------------------- |
| Legacy naming conflicts (Assembly Line 1 vs TCF FS) unresolved by client | High       | Architect's solution: use immutable `code` + flexible `name`; get client to sign off on code list early in Sprint 2 |
| CSV import edge cases (duplicate NPK, invalid section codes)             | Medium     | Validate in-memory before DB write; return per-row error report                                                     |
| Employee `hourly_rate` missing for many employees initially              | High       | System gracefully falls back to `department.default_hourly_rate` — enforce this in `SubmitOvertimeAction`           |
| Calendar auto-generation missing edge cases (substitute holidays)        | Low        | Admin can override any date manually; design clearly documents this                                                 |

---

## Definition of Done — Epic-02

- [ ] Admin can create/edit/deactivate Departments, Sections, Employees, and Users
- [ ] Employee NPK cannot be changed after creation (enforced at controller and request level)
- [ ] Operational Calendar seeded for current + next year; Admin can override dates
- [ ] Policy Thresholds configurable per department with plant-wide fallback
- [ ] Overtime Budget plans creatable per section per month
- [ ] User preferences (theme, notifications) persist across sessions
- [ ] `pnpm lint` passes
- [ ] `pnpm build` succeeds
- [ ] All new routes registered with named Wayfinder-compatible names
