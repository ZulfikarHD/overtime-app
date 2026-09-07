# Epic-03: Daily Overtime Entry & SPKL Workflow

**Epic ID:** E-03  
**Priority:** P1 – Must Have (Core business operation)  
**Estimated Total:** 47 Story Points  
**Target Sprints:** Sprint 3 (Weeks 5–6)  
**Dependencies:** Epic-01 (Foundation), Epic-02 (Master Data: Departments, Sections, Employees, Calendar)  
**Lead Area:** Backend (`SubmitOvertimeAction`, `SpklDocumentController`) + Vue 3 Timesheet Form

---

## Business Context

This is the primary daily workflow of the application. Every workday, Team Leaders across 35 sections submit overtime records for their crew at shift handover (07:00, 15:00, 23:00 WIB). On busy days, 10–50 Team Leaders submit simultaneously with 10–30 workers each — that's up to 1,500 line items in a 30-minute burst.

**Critical design invariants for this epic:**

- Submission must be **atomic** (all-or-nothing): if any item fails validation, the entire batch rolls back.
- **SPKL is non-blocking** (BR-05): a shift cannot be delayed because a paper document hasn't been processed. The system records the work now; the document follows.
- **Financial cost snapshots are immutable**: the `hourly_rate_snapshot` is locked at submission time based on the employee's current rate. Future rate changes never alter historical records.
- The `SubmitOvertimeAction` from the architecture doc is the canonical backend implementation.

---

## User Stories

---

### Story E03-01: Daily Overtime Submission Form (Team Leader)

**As a** Team Leader,  
**I want** to submit a daily overtime batch for my section with hours distributed across work categories,  
**So that** overtime for my shift is recorded immediately without delays from paperwork.

**Story Points:** 13  
**Priority:** Must Have

#### Acceptance Criteria

- [x] Team Leader can select: `date` (date picker, defaults to today), `department` (filtered to their own), `section` (filtered to their own)
- [x] System automatically classifies the selected date as **HKN** or **HLR** by calling `GET /api/calendar/{date}` and displays a badge: `📅 Hari Kerja Normal` or `🔴 Hari Libur`
- [x] Team Leader can override the day classification (HKN ↔ HLR) — override is saved on the submission record's `day_type` field
- [x] Employee roster loads automatically based on selected `department` + `section` — only `is_active = true` employees are shown
- [x] Team Leader can add employees from the roster to the timesheet (checkboxes or "Add All" button)
- [x] For each added employee, input fields for:
    - `hours_production` (decimal, ≥ 0, step 0.5)
    - `hours_tpm` (decimal, ≥ 0, step 0.5)
    - `hours_project` (decimal, ≥ 0, step 0.5) — when > 0, `capex_project_id` becomes **required**
    - `hours_others` (decimal, ≥ 0, step 0.5)
    - Total hours auto-calculated and displayed live: `Prod + TPM + Project + Others`
- [x] `Total Hours` for each employee must be **≥ 0.5** before submission (BR-01)
- [x] `hours_project > 0` requires a CapEx Project selection — dropdown shows `ACTIVE` projects only (BR-08)
- [x] Optional `rca_category` dropdown with standardized reasons (BR-07)
- [x] Optional `task_description` free text
- [x] Optional `submission_notes` (header-level note for the entire batch)
- [x] Submit button triggers `POST /overtime/submissions` and returns success state
- [x] On success: show a success toast, clear the form, display the generated `submission_code` (e.g., `OT-20260906-ASSY1-001`)
- [x] On validation error: highlight the specific employee row and field that failed, do NOT clear the form
- [x] Section budget burn indicator shown in header: "Section Budget: 85/200 hrs (42.5%)" — soft warning if > 85% (configurable threshold)

#### Technical Tasks

- [x] `php artisan make:controller OvertimeSubmissionController` with `store()` method
- [x] `php artisan make:request StoreOvertimeSubmissionRequest` — validates nested `items[]` array
- [x] Implement `SubmitOvertimeAction` exactly as specified in `data-architect-analyst.md` §3.1
- [x] Route: `POST /overtime/submissions` → `OvertimeSubmissionController@store` (middleware: `auth`, `role:team_leader,admin`)
- [x] Create `resources/js/Pages/Overtime/Create.vue` — the main timesheet form
- [x] Vue component: `OvertimeItemRow.vue` — represents one employee's row with all 4 hour inputs
- [x] Vue composable: `useCalendarDayType(date: Ref<string>)` — fetches day classification reactively
- [x] Vue composable: `useSectionRoster(sectionId: Ref<number>)` — fetches active employees for section
- [x] Real-time total calculation: `computed(() => prod + tpm + proj + others)` per row
- [x] CapEx project dropdown visibility: `v-if="row.hours_project > 0"`
- [x] Hour input validation: HTML5 `min="0" step="0.5"` + Vue validator on form submit
- [x] `dispatch(RunAnomalyDetectionJob::class, $createdItem->id)` at the end of each item in `SubmitOvertimeAction`
- [x] Section burn indicator: inject current `MonthlyBurnSnapshot` data into the page props

---

### Story E03-02: Immutable Financial Cost Snapshotting

**As a** finance controller,  
**I want** overtime cost to be calculated and locked at the time of submission using the employee's current labor rate,  
**So that** historical overtime cost records never change when future salary or standard costing updates occur.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria

- [x] At submission time, `SubmitOvertimeAction` reads `employee.hourly_rate` — if null, reads `department.default_hourly_rate`
- [x] `hourly_rate_snapshot` is written to `overtime_items` at creation and **never updated again**
- [x] `total_cost_snapshot` = `total_hours × hourly_rate_snapshot`, calculated using `bcmul()` for precision (no floating-point math)
- [x] If an employee's rate is later updated in the system, **existing** `overtime_items` records retain their original `hourly_rate_snapshot`
- [x] `total_cost_snapshot` is shown in the approval queue as "Est. Cost: Rp X" (formatRupiah utility)
- [x] Database-level: `overtime_items.hourly_rate_snapshot` and `total_cost_snapshot` have `NUMERIC(15,2)` type — enforced in migration

#### Technical Tasks

- [x] Implement snapshot logic inside `SubmitOvertimeAction` (already in architecture doc §3.1)
- [x] Use `bcmul((string) $lineTotal, (string) $rateSnapshot, 2)` for cost calculation
- [x] Add `hourly_rate_snapshot` and `total_cost_snapshot` to `OvertimeItem::$fillable`
- [x] Guard against update: In `OvertimeItemObserver` or `updating` Eloquent event, throw exception if `hourly_rate_snapshot` changes on an existing record
- [x] Unit test: `SubmitOvertimeActionTest::test_snapshot_does_not_change_after_rate_update()`

---

### Story E03-03: Submission Status & History View (Team Leader)

**As a** Team Leader,  
**I want** to view my submitted overtime records with their current approval status,  
**So that** I can track which submissions are pending review, approved, or rejected and follow up accordingly.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria

- [x] Team Leader can view a list of their own submissions filtered by date range and status
- [x] List shows: `submission_code`, `operational_date`, day type badge (HKN/HLR), `section`, `total_hours_cached`, `status` badge, SPKL status badge
- [x] Status badges:
    - `SUBMITTED` → 🟡 Submitted / Pending Review
    - `PARTIALLY_APPROVED` → 🟠 Partially Approved
    - `APPROVED` → 🟢 Approved
    - `REJECTED` → 🔴 Rejected
- [x] SPKL badges:
    - `PENDING` → 📎 SPKL: Belum Dilampirkan (shows due date)
    - `ATTACHED` → 📎 SPKL: Terlampir
    - `VERIFIED` → ✅ SPKL: Terverifikasi
- [x] Clicking a submission opens a **read-only detail modal** showing each employee's hours, categories, costs
- [x] Team Leader can edit a submission that is still in `SUBMITTED` or `DRAFT` status (NOT if `APPROVED` or `PARTIALLY_APPROVED`) — full re-edit with re-snapshot
- [x] Filter by: date range, status (multi-select), section
- [x] Server-side pagination: 20 records per page

#### Technical Tasks

- [x] `OvertimeSubmissionController@index` — returns paginated, filtered list for current user's sections
- [x] `OvertimeSubmissionController@show` — returns submission detail with eager-loaded items, employees, SPKL doc
- [x] `OvertimeSubmissionController@update` — re-runs `SubmitOvertimeAction` with updated data on a draft/submitted record
- [x] Create `resources/js/Pages/Overtime/Index.vue` — submission list with filters and pagination
- [x] Create `resources/js/Components/Overtime/SubmissionDetailModal.vue` — read-only detail modal
- [x] Guard edit: `abort(422)` if submission is `APPROVED` or `PARTIALLY_APPROVED`
- [x] Route: `GET /overtime/submissions` → `index()`, `GET /overtime/submissions/{id}` → `show()`

---

### Story E03-04: SPKL Flexible Post-Shift Attachment

**As a** Team Leader,  
**I want** to attach an SPKL document to a submission after the shift ends,  
**So that** the physical paperwork can follow the digital record without blocking shift operations.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria

- [x] Any submission automatically has a `spkl_documents` record created in `PENDING` status with `due_date = operational_date + grace_period_days`
- [x] Team Leader can upload an SPKL file (PDF, JPEG, PNG, max 3 MB) or enter an SPKL reference number string
- [x] On upload: file is stored in a **private** disk (not publicly accessible) — not web-server public folder
- [x] Serving SPKL files uses **temporary signed URLs** (Laravel `Storage::temporaryUrl()`)
- [x] Uploading transitions `spkl_documents.status` from `PENDING` → `ATTACHED`
- [x] After uploading, the SPKL badge on the submission list updates immediately to `ATTACHED`
- [x] Only one SPKL record per submission (1:1 relationship) — re-upload replaces the previous file
- [x] File validation: `mimes:pdf,jpeg,png`, `max:3072` (3 MB in KB)
- [x] If `due_date` has passed and status is still `PENDING`, the badge shows `⚠️ SPKL: Terlambat` (overdue warning)
- [x] Manager can mark an SPKL as `VERIFIED` after reviewing the document

#### Technical Tasks

- [x] `php artisan make:controller SpklDocumentController` with `attach()`, `verify()`, and `download()` methods
- [x] `AttachSpklDocumentAction` — handles file upload, storage, status transition, and file replacement
- [x] Configure Laravel `Storage::disk('spkl-private')` with `local` or `s3` driver (env-configurable)
- [x] Route: `POST /overtime/submissions/{submission}/spkl` → `SpklDocumentController@attach`
- [x] Route: `PATCH /overtime/submissions/{submission}/spkl/verify` → `SpklDocumentController@verify`
- [x] Route: `GET /overtime/submissions/{submission}/spkl/download` → `SpklDocumentController@download` (returns signed URL)
- [x] Create `resources/js/components/overtime/SpklUploadSheet.vue` — upload drawer with drag-drop and countdown banner
- [x] `SpklDocument::isDueOverdue(): bool` → computed property
- [x] Update submission list, detail modal, and post-submission success card with SPKL triggers and document links

---

### Story E03-05: SPKL Pending Reminder Job (Automated)

**As a** Team Leader,  
**I want** to receive automated reminders when my SPKL is still pending past the grace period,  
**So that** I don't miss the payroll/audit deadline for formal document submission.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria

- [x] A scheduled job runs **daily at 08:00 WIB** to check for `spkl_documents` where `status = 'PENDING'` and `due_date <= today`
- [x] For each overdue SPKL: the submitting Team Leader receives a **system notification** (in-app) listing the overdue submissions
- [x] If `due_date` is 1 day away (tomorrow): a **pre-due warning** notification is sent (configurable: on/off in user preferences)
- [x] Notification content: submission code, section, date, overdue days
- [x] Notifications respect user preference `notifications.spkl_reminders` (on/off from Epic-02 story E02-06)
- [x] Reminders do **not** block the Team Leader from entering new submissions

#### Technical Tasks

- [x] Implement `SendSpklReminderJob` (scaffolded in Epic-01)
- [x] `php artisan make:command DispatchSpklRemindersCommand` — dispatches the job
- [x] Register in `app/Console/Kernel.php` or `bootstrap/app.php` schedule: `$schedule->command('overtime:spkl-reminders')->dailyAt('08:00')->timezone('Asia/Jakarta')`
- [x] `php artisan make:notification SpklOverdueNotification` (database channel for in-app)
- [x] `php artisan make:migration create_notifications_table` — or use Laravel's built-in `php artisan notifications:table`
- [x] Create `resources/js/Components/NotificationBell.vue` — shows unread count in nav
- [x] `GET /notifications` endpoint returning unread notifications for the auth user
- [x] `PATCH /notifications/{id}/read` endpoint to mark as read
- [x] Query: `SpklDocument::where('status', 'PENDING')->where('due_date', '<=', today)->with('overtimeSubmission.submittedBy')->get()`

---

### Story E03-06: Overtime Entry Form — Policy Soft Warning Indicators

**As a** Team Leader,  
**I want** to see a non-blocking visual warning when I'm adding employees who are approaching the company overtime policy limits,  
**So that** I'm aware of workload concerns without being blocked from entering legitimate shifts.

**Story Points:** 5  
**Priority:** Should Have

#### Acceptance Criteria

- [ ] When adding an employee to a timesheet, the system checks their cumulative hours for the current week from approved/submitted records
- [ ] If current week hours + submitted hours exceed `policy_thresholds.weekly_soft_limit_hours` (default 20 hrs):
    - Display a yellow badge on that employee's row: `⚠️ Weekly limit may be exceeded (22/20 hrs)`
    - Team Leader can still proceed and submit — this is **advisory only** (BR-06)
- [ ] If an employee has had high workload (> weekly limit) for `consecutive_weeks_alert` consecutive weeks (default 3): display a red badge: `🔴 High Workload: 3 consecutive weeks over limit`
- [ ] Thresholds are loaded from `PolicyThresholdService::getForDepartment()` — department-specific if exists, else plant-wide default
- [ ] Warning badges do NOT prevent form submission
- [ ] Warnings are surfaced to the Manager in the approval queue as well (passed in item data)

#### Technical Tasks

- [ ] `OvertimePolicyEvaluator::evaluateEmployee(int $employeeId, float $additionalHours): PolicyWarning`
- [ ] `PolicyWarning` DTO: `{ level: 'none'|'warning'|'danger', message: string, weeklyTotal: float, consecutiveWeeks: int }`
- [ ] Call `OvertimePolicyEvaluator` in `SubmitOvertimeAction` after creating items — store warnings in a cache or embed in response
- [ ] `GET /overtime/policy-check?employee_id=X&date=Y` — API endpoint returning current policy status for an employee
- [ ] In `OvertimeItemRow.vue`: call policy check API on blur of any hours field, display badge
- [ ] Debounce the API call: `useDebounce(totalHours, 500ms)`

---

## Sprint 3 Breakdown

| Sprint Day | Focus                                                                                   | Stories        |
| ---------- | --------------------------------------------------------------------------------------- | -------------- |
| Day 1–3    | `SubmitOvertimeAction` implementation + form backend + `StoreOvertimeSubmissionRequest` | E03-01, E03-02 |
| Day 4–5    | Timesheet form Vue page (`Create.vue`) + employee rows + day-type indicator             | E03-01         |
| Day 6–7    | Submission history list + detail modal                                                  | E03-03         |
| Day 7–8    | SPKL attachment: `AttachSpklDocumentAction` + `SpklUploadPanel.vue`                     | E03-04         |
| Day 9      | SPKL reminder job + notification bell                                                   | E03-05         |
| Day 10     | Policy soft warning indicators on form                                                  | E03-06         |

---

## Key Business Rules Implemented in This Epic

| Rule                                 | Implementation                                                             |
| ------------------------------------ | -------------------------------------------------------------------------- |
| BR-01: Min 0.5 hours                 | `CHECK` constraint in DB + `StoreOvertimeSubmissionRequest` validation     |
| BR-02: Employee assignment integrity | Roster query scoped to `department_id` + `section_id` + `is_active = true` |
| BR-03: NPK immutability              | `npk_snapshot` stored on item; NPK field read-only in Employee update form |
| BR-04: Category summation            | Stored generated column `GENERATED ALWAYS AS (...)` in DB                  |
| BR-05: Non-blocking SPKL             | `SpklDocument` auto-created as `PENDING`; submission succeeds without file |
| BR-06: Soft policy limits            | `OvertimePolicyEvaluator` returns advisory warnings, never blocks          |
| BR-07: Optional RCA                  | `rca_category` nullable in request, stored as NULL when not provided       |
| BR-08: CapEx attribution             | `CONSTRAINT chk_capex_attribution` in DB + frontend conditional required   |

---

## Risks & Assumptions

| Risk                                                   | Likelihood | Mitigation                                                                                                 |
| ------------------------------------------------------ | ---------- | ---------------------------------------------------------------------------------------------------------- |
| Shift-end burst: 50 Team Leaders submit simultaneously | High       | `SubmitOvertimeAction` is append-only insert — no lock contention on budget tables; budget rollup is async |
| SPKL file uploads filling disk                         | Medium     | Max 3 MB validation + private disk (not web server) — use S3/MinIO in production                           |
| Team Leader selecting wrong section accidentally       | Low        | Section dropdown pre-filtered to their assigned section by default; requires additional action to change   |
| `bcmul()` unfamiliar to team                           | Low        | Add code comment explaining why — `bcmul` for precise IDR financial arithmetic                             |

---

## Definition of Done — Epic-03

- [x] Team Leader can submit a full overtime batch and see `submission_code` confirmation
- [x] Submission fails atomically if any employee row fails validation
- [x] `hourly_rate_snapshot` and `total_cost_snapshot` are written on creation and verified as immutable
- [x] SPKL document can be attached post-shift without modifying submission status
- [x] SPKL reminder job runs on schedule at 08:00 WIB and creates in-app notifications
- [ ] Policy soft warnings shown on form — do not block submission
- [x] `pnpm lint` passes
- [x] `pnpm build` succeeds
- [x] `SubmitOvertimeActionTest` unit test suite passes (atomic rollback on validation failure)
