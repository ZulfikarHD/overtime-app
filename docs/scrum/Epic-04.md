# Epic-04: Verification & Approval Lifecycle

**Epic ID:** E-04  
**Priority:** P1 – Must Have  
**Estimated Total:** 38 Story Points  
**Target Sprints:** Sprint 4 (Weeks 7–8)  
**Dependencies:** Epic-01 (Foundation), Epic-02 (Master Data), Epic-03 (Overtime Submission exists)  
**Lead Area:** Backend (`ApproveOvertimeItemsAction`, `OvertimeItemAudit`) + Vue 3 Approval Queue UI

---

## Business Context

After a Team Leader submits overtime, a Manager or Admin must review and approve or reject individual line items. This is not a simple "approve the whole batch" button — approvers have **granular, item-level authority**: they can approve some employees in a submission while rejecting others (e.g., approve Production workers, reject someone miscategorized as CapEx without a project reference).

Key invariants from the architecture:

- **Optimistic locking** (`lock_version`) prevents two managers from simultaneously overriding each other on the same record (concurrency-safe).
- **Approved items are immutable** — they cannot be edited or deleted by the Team Leader after approval.
- **Rejection requires a documented reason** — no silent rejections allowed (audit compliance).
- All state transitions are written to the **immutable audit ledger** (`overtime_item_audits`).
- Approving items triggers an async job to **recalculate the monthly Burn snapshot** for that section.

---

## User Stories

---

### Story E04-01: Pending Approval Queue (Manager/Admin)

**Status:** ✅ Completed  
**As a** Manager,  
**I want** to view a filtered queue of all overtime submissions pending my review,  
**So that** I can efficiently process the morning standup approval workload without missing any submissions.

**Story Points:** 8  
**Priority:** Must Have

#### Acceptance Criteria

- [x] Manager sees all `SUBMITTED` and `PARTIALLY_APPROVED` submissions for their department(s)
- [x] Admin sees submissions across ALL departments
- [x] Queue displays per submission: `submission_code`, `operational_date`, day type badge (HKN/HLR), `section`, submitting Team Leader name, `total_hours_cached`, SPKL status badge, `status` badge, item count
- [x] Queue is sortable by: date (default desc), section, total hours
- [x] Queue is filterable by: department, section, date range, status, SPKL status
- [x] Each submission row has a quick-view expand toggle to show a summary of employees and their total hours inline
- [x] Visual indicator when SPKL is `PENDING` (orange badge) or overdue (red badge) — but this does NOT block approval
- [x] ML Anomaly badge shown on submission row if any items in that submission have anomaly flags: `🤖 1 anomaly flagged`
- [x] Pagination: 20 records per page, server-side

#### Technical Tasks

- [x] `php artisan make:controller OvertimeApprovalController` with `index()` method (E04-01); `approveItems()` deferred to E04-02
- [x] `OvertimeApprovalController@index` query: scoped by Manager's `department_id`, filter params, eager-load items, SPKL doc, anomaly log count
- [x] Route: `GET /overtime/approvals` → `OvertimeApprovalController@index`
- [x] Create `resources/js/Pages/Overtime/ApprovalQueue.vue` — the main manager approval page
- [x] Create `resources/js/Components/Overtime/SubmissionQueueRow.vue` — expandable row component
- [x] Server-side filters: inline query scopes on `OvertimeSubmission` in controller index
- [x] Anomaly count subquery: `withCount(['items as anomaly_count' => fn($q) => $q->whereHas('anomalyLogs', fn($q2) => $q2->where('is_dismissed', false))])`

---

### Story E04-02: Item-Level Approval/Rejection Modal (Manager/Admin)

**Status:** ✅ Completed  
**As a** Manager,  
**I want** to review each employee's line item in a submission and approve or reject them individually,  
**So that** I can approve valid overtime while rejecting specific entries that are miscategorized, over-budget, or otherwise non-compliant.

**Story Points:** 13  
**Priority:** Must Have

#### Acceptance Criteria

- [x] Clicking "Review" on a submission opens a detailed **Approval Modal**
- [x] Modal displays for each employee item:
    - Employee name + NPK (read-only snapshot)
    - Day type (HKN/HLR)
    - Hours breakdown: Production, TPM, Project (CapEx), Others
    - Total hours + estimated cost (formatted as `Rp 1.234.567`)
    - Linked CapEx project name (if applicable)
    - RCA category and notes (if provided)
    - Task description
    - Individual policy warning badges (if applicable)
    - ML anomaly flag and reason (if flagged — see Epic-08)
    - Current `status` badge
- [x] Each item has individual radio or toggle: `✅ Approve` / `❌ Reject` (plus Pending)
- [x] Rejecting an item makes `rejection_reason` text field **mandatory** (BR-10, min 5 chars)
- [x] "Approve All" button sets all items to Approved
- [x] "Reject All" button opens a shared rejection reason field for all items
- [x] Confirming the decision calls `POST /overtime/submissions/{id}/approve-items`
- [x] On success: modal closes, queue row status updates reactively, success toast shown
- [x] On optimistic lock conflict: amber banner + "Muat Ulang Data Terbaru" (HTTP 409, not 500)
- [x] Approved / partially approved submissions remain locked against Team Leader edit (`abort(422)` on update/edit)

#### Technical Tasks

- [x] `ApproveOvertimeItemsAction` — implement exactly as in `data-architect-analyst.md` §3.2 (with `lockForUpdate()` + `lock_version` check)
- [x] Route: `POST /overtime/submissions/{id}/approve-items` → `OvertimeApprovalController@approveItems`
- [x] `ApproveOvertimeItemsRequest` — validates `decisions[]` array: each has `item_id`, `action` (APPROVED/REJECTED), optional `rejection_reason`, optional `lock_version`
- [x] `OptimisticLockException` — maps lock collisions to HTTP 409 with Indonesian message
- [x] `OvertimeItemAudit` records written inside `ApproveOvertimeItemsAction` for each item
- [x] Dispatch `RecalculateMonthlyBurnSnapshotJob` after all items processed
- [x] Enhance `OvertimeSubmissionController@show` — anomaly logs + section `burn_indicator` for modal
- [x] Create `resources/js/components/overtime/ApprovalModal.vue` — itemized review modal
- [x] Create `resources/js/components/overtime/ApprovalItemRow.vue` — individual item row with decision controls
- [x] Reactive decision state: `const decisions = reactive({})` keyed by `item_id`
- [x] Rejection reason validation: computed `canSubmit` — if `REJECTED`, require `rejection_reason` ≥ 5 chars
- [x] Optimistic lock: send current `lock_version` in payload, handle `409 Conflict` response
- [x] Feature tests: `tests/Feature/Overtime/ApproveOvertimeItemsActionTest.php`
- [x] Browser tests: `tests/Browser/Overtime/ApprovalModalBrowserTest.php`
- [x] Docs: `docs/dev-docs/features/verification-approval.md` + `docs/user-docs/guides/overtime-approvals.md`

---

### Story E04-03: Bulk Approval & Rejection (Manager/Admin)

**Status:** ✅ Completed  
**As a** Manager,  
**I want** to bulk-approve or bulk-reject multiple submissions at once,  
**So that** I can efficiently clear a backlog of pending approvals during busy production periods.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria

- [x] Manager can select multiple submissions via checkboxes in the queue list
- [x] "Bulk Approve" button: approves **all items in all selected submissions** in a single transaction
- [x] "Bulk Reject" button: rejects all items in all selected submissions — requires a single shared rejection reason
- [x] Confirmation dialog before bulk action: "You are about to approve 45 items across 3 submissions. Confirm?"
- [x] Partial failure handling: if one item fails (e.g., lock conflict), the transaction rolls back for that submission but continues for others — summary report shown
- [x] Max bulk selection: 50 submissions per bulk action (prevent timeout)
- [x] Each bulk action creates individual `OvertimeItemAudit` records per item (not a single grouped record)
- [x] After bulk action: queue refreshes, toast summary: "38/45 items approved (7 skipped due to conflicts)"

#### Technical Tasks

- [x] `BulkApproveSubmissionsAction` — loops over submission IDs, calls `ApproveOvertimeItemsAction` per submission in separate try-catch
- [x] Route: `POST /overtime/approvals/bulk` → `OvertimeApprovalController@bulkProcess`
- [x] `BulkApprovalRequest` — validates `submission_ids[]` max 50, `action`, `rejection_reason` (required if action=REJECT)
- [x] Checkbox state in `ApprovalQueue.vue`: `const selectedIds = ref<number[]>([])`
- [x] "Select All on Page" checkbox — toggles all 20 visible rows
- [x] Result summary component: `BulkActionResultToast.vue` — shows success/skip counts

---

### Story E04-04: Export to CSV/Excel (Manager/Admin)

**Status:** ✅ Completed  
**As a** Manager,  
**I want** to export overtime records to CSV or Excel,  
**So that** I can share data with HR, finance, or payroll systems for processing and audit.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria

- [x] Export is available on the Approval Queue page with the same filters applied (date range, department, section, status)
- [x] CSV format columns: `submission_code`, `operational_date`, `day_type`, `department`, `section`, `npk`, `employee_name`, `hours_production`, `hours_tpm`, `hours_project`, `hours_others`, `total_hours`, `hourly_rate_snapshot`, `total_cost_idr`, `rca_category`, `status`, `rejection_reason`, `capex_project_code`, `spkl_status`
- [x] Export is streamed (not loaded into memory) using Laravel's `LazyCollection` for large datasets
- [x] Export filename: `overtime-export-{department}-{YYYY-MM}.csv`
- [x] Excel export (`.xlsx`) also available using `maatwebsite/excel` or equivalent
- [x] Export limited to records the Manager has authority over (scoped by their department)
- [x] Export action is logged in the audit trail: `{ action: 'EXPORT', actor_user_id: X, filters: {...} }`

#### Technical Tasks

- [x] Native streaming engine with `LazyCollection` cursor without external bloat
- [x] `OvertimeExportService` implements query builder, UTF-8 BOM CSV, and native OpenXML XLSX
- [x] Route: `GET /overtime/approvals/export` → `OvertimeApprovalController@export` (streams response)
- [x] Apply same query scopes as the approval queue list (department, section, date, status, SPKL)
- [x] Log export: `OvertimeItemAudit::create(['action' => 'EXPORT', ...])` and dedicated `export_logs` table
- [x] Create `resources/js/components/overtime/ExportButton.vue` — dropdown popover, shows spinner, triggers download

---

### Story E04-05: Immutable Audit Trail (Full Lifecycle)

**Status:** ✅ Completed  
**As an** Auditor / Admin,  
**I want** a complete, tamper-proof log of every state change to every overtime item,  
**So that** any dispute over hours, approvals, or cost allocations can be traced to the exact user action and timestamp.

**Story Points:** 5  
**Priority:** Must Have

#### Acceptance Criteria

- [x] Every state change to `overtime_items` (`SUBMITTED → APPROVED`, `SUBMITTED → REJECTED`, re-review) writes a row to `overtime_item_audits`
- [x] Each audit record contains: `overtime_item_id`, `action`, `actor_user_id`, `previous_state` (JSON), `new_state` (JSON), `notes`, `ip_address`, `created_at`
- [x] `overtime_item_audits` records are **insert-only** — no UPDATE or DELETE is permitted on this table (enforced at application layer via `OvertimeItemAuditObserver` + DB level if supported)
- [x] Admin and Managers can view the full audit trail for any overtime item via a detail drawer: chronological list of state changes with timestamps, actor names, and diff between previous and new state
- [x] Audit records are written **synchronously inside the transaction** (not via a queued job) to guarantee consistency
- [x] Export action (E04-04) is also recorded

#### Technical Tasks

- [x] `OvertimeItemAudit::create(...)` called inside `ApproveOvertimeItemsAction` and `SubmitOvertimeAction` synchronously inside transactions
- [x] `OvertimeItemAuditObserver::updating()` and `deleting()` — if someone attempts to update or delete an existing audit record, throw `RuntimeException`
- [x] Create `resources/js/components/overtime/AuditTrailDrawer.vue` — shows timeline of audit events, actor badge, and visual state diffing
- [x] Route: `GET /overtime/items/{item}/audit` → `OvertimeItemAuditController@index` with role & department scoping
- [x] Diff display: show which fields changed between `previous_state` and `new_state` JSON translated to friendly plant terms
- [x] Lock DB-level: `GRANT INSERT ON overtime_item_audits TO app_user; REVOKE UPDATE, DELETE ON overtime_item_audits FROM app_user;` (documented in ADR-017)

---

### Story E04-06: Modification Lock on Approved Records

**As a** system,  
**I want** approved overtime items to be locked against any modification or deletion by the submitting Team Leader,  
**So that** finalized payroll and financial records cannot be tampered with after management approval.

**Story Points:** 2  
**Priority:** Must Have

#### Acceptance Criteria

- [ ] A Team Leader attempting to edit or delete a submission that contains any `APPROVED` item receives a `422` error: "This submission contains approved items and cannot be modified."
- [ ] The edit button is hidden in the UI for submissions with `status = APPROVED` or `PARTIALLY_APPROVED`
- [ ] Even via direct API call, the lock is enforced at the controller layer (not just UI-level)
- [ ] Admin can override and "force-unlock" an approved submission with a mandatory audit reason — this creates an audit record: `{ action: 'ADMIN_UNLOCK', notes: reason }`
- [ ] After force-unlock, the submission returns to `SUBMITTED` status for re-review

#### Technical Tasks

- [ ] Gate check in `OvertimeSubmissionController@update`: `if ($submission->items()->where('status', 'APPROVED')->exists()) abort(422, ...)`
- [ ] UI: `v-if="submission.status === 'SUBMITTED'"` on the edit button in `SubmissionQueueRow.vue`
- [ ] Admin-only route: `PATCH /overtime/submissions/{id}/unlock` → `OvertimeSubmissionController@forceUnlock`
- [ ] `forceUnlock()`: revert all items to `PENDING`, update submission status to `SUBMITTED`, write audit record
- [ ] Admin UI: "Force Unlock" button visible only for `role:admin`

---

## Sprint 4 Breakdown

| Sprint Day | Focus                                                           | Stories |
| ---------- | --------------------------------------------------------------- | ------- |
| Day 1–2    | Approval queue page + server-side filtering + ML anomaly badge  | E04-01  |
| Day 3–5    | `ApproveOvertimeItemsAction` + approval modal + optimistic lock | E04-02  |
| Day 6–7    | Bulk approval/rejection + confirmation dialog                   | E04-03  |
| Day 7–8    | CSV/Excel export with streaming                                 | E04-04  |
| Day 9      | Audit trail page + audit drawer UI                              | E04-05  |
| Day 10     | Modification lock + Admin force-unlock                          | E04-06  |

---

## Key Business Rules Implemented in This Epic

| Rule                                    | Implementation                                                                                |
| --------------------------------------- | --------------------------------------------------------------------------------------------- |
| BR-09: Burn Index governance            | Triggered indirectly — `RecalculateMonthlyBurnSnapshotJob` dispatched after each approval     |
| BR-10: Item-level approval independence | Each `overtime_items` row has independent `status`, reviewed independently                    |
| Rejection reason mandatory              | `ApproveOvertimeItemsRequest` + action validate `rejection_reason` (≥5 chars) when `REJECTED` |
| Immutability after approval             | Gate check in controller + UI hide pattern                                                    |

---

## Risks & Assumptions

| Risk                                                   | Likelihood | Mitigation                                                                                           |
| ------------------------------------------------------ | ---------- | ---------------------------------------------------------------------------------------------------- |
| Two managers reviewing the same section simultaneously | Medium     | `lockForUpdate()` pessimistic lock + `lock_version` optimistic check in `ApproveOvertimeItemsAction` |
| Bulk approval transaction timeout on large batches     | Low        | Max 50 submissions per bulk; per-submission try-catch continues on conflict                          |
| Excel export memory exhaustion on large data           | Medium     | Use `FromQuery` + `LazyCollection` cursor streaming, not `collect()->all()`                          |
| Audit table size growth                                | Low        | Append-only, indexed on `overtime_item_id`; no performance concern for foreseeable volume            |

---

## Definition of Done — Epic-04

- [x] Manager can open approval queue filtered by their department
- [x] Item-level approval and rejection work correctly with audit records written
- [x] Optimistic lock conflict returns user-friendly error (not 500)
- [x] Bulk approve/reject works for up to 50 submissions
- [x] Export downloads a correct CSV with all specified columns
- [x] Approved items are locked — Team Leader edit is blocked both in UI and API
- [x] `pnpm lint` passes
- [x] `pnpm build` succeeds
- [x] `ApproveOvertimeItemsActionTest` suite passes (including lock conflict test)
- [x] `OvertimeItemAuditTest` suite passes (insert-only immutability, role/dept scoping, lifecycle records)
- [x] `ApprovalModalBrowserTest` Pest Playwright suite passes
- [x] `AuditTrailDrawerBrowserTest` Pest Playwright suite passes (full drawer timeline, actor badge, state diff, metadata toggle)
