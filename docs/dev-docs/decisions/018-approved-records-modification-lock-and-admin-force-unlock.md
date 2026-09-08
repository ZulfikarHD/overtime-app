# ADR-018: Approved Records Modification Lock and Admin Force-Unlock

**Date:** 2026-09-08  
**Status:** accepted  
**Supersedes:** None

## Context

In an industrial automotive manufacturing environment, overtime submissions directly drive shift labor costs, line operational budgets, and payroll calculations. Once an overtime submission is approved or partially approved by department management, allowing retroactive modifications or deletions by Team Leaders would invalidate previously certified budgets, corrupt historical monthly burn snapshots, and compromise legal compliance.

Key challenges addressed:

1. **Strict Immutability on Approved Records**: Team Leaders must be strictly blocked from altering or deleting overtime submissions once any item has been approved (`APPROVED` or `PARTIALLY_APPROVED`). This must be enforced both at the UI layer and at the backend API layer with `422 Unprocessable Entity`.
2. **Administrative Override Mechanism (Force Unlock)**: Legitimate data entry errors (e.g., mistyped employee NPK, wrong task categorization) occasionally require corrections post-approval. Only factory Administrators should have authority to unlock records.
3. **Mandatory Audit Justification**: Any administrative unlock must require a documented, non-trivial reason (minimum 5 characters) that is recorded synchronously in the immutable audit ledger (`overtime_item_audits`) for HR and Finance compliance.
4. **State Consistency & Downstream Recalculations**: Unlocking a submission requires reverting all items to `PENDING`, resetting parent submission status to `SUBMITTED`, clearing reviewer references, incrementing concurrency `lock_version`, and triggering section monthly burn snapshot recalculation (`RecalculateMonthlyBurnSnapshotJob`).
5. **Ergonomic, Non-Confusing UX**: The UI must clearly indicate why a record is locked (`🔒 Terkunci (Disetujui)`), provide a clean, non-nested confirmation modal (`ForceUnlockModal.vue`) for Administrators, and ensure seamless transition back to editable status.

## Decision

We implemented the **Approved Records Modification Lock and Admin Force-Unlock Architecture** (E04-06):

1. **Backend Immutability & Gate Guards (`OvertimeSubmissionController`)**:
    - Guarded `edit()`, `update()`, and `destroy()` actions against submissions containing any `APPROVED` item or with `status in ['APPROVED', 'PARTIALLY_APPROVED']`.
    - Violation immediately terminates with `422 Unprocessable Entity` and bilingual message: `"Pengajuan ini memuat item yang sudah disetujui dan tidak dapat diubah."` (`"This submission contains approved items and cannot be modified."`).
    - Added `destroy()` endpoint allowing Team Leaders to delete only clean, unapproved submissions (`SUBMITTED` or `DRAFT`), safely updating monthly burn snapshots upon deletion.

2. **Form Request Authorization & Validation (`ForceUnlockSubmissionRequest`)**:
    - Admin-only route: `PATCH /overtime/submissions/{submission}/unlock` named `overtime.submissions.unlock`.
    - Protected by `role:admin` middleware and Form Request authorization (`$user->isAdmin()`).
    - Validates mandatory trimmed reason: minimum 5 characters, maximum 1000 characters.

3. **Atomic Unlock Transaction with Audit Logging**:
    - Inside `OvertimeSubmissionController@forceUnlock`:
        - Validates submission is actually locked (`status in ['APPROVED', 'PARTIALLY_APPROVED']` or has `APPROVED` items).
        - In DB transaction:
            - Reverts all items to `status: 'PENDING'`, clearing `reviewed_by_user_id`, `reviewed_at`, `rejection_reason`, and incrementing `lock_version`.
            - Logs item-level `OvertimeItemAudit` records with `action: 'ADMIN_UNLOCK'`, actor ID, previous and new state JSON diffs, notes, and IP address.
            - Logs parent submission-level audit record (`overtime_item_id: null`).
            - Reverts `overtime_submissions.status` to `SUBMITTED`.
            - Dispatches `RecalculateMonthlyBurnSnapshotJob` for the section.

4. **Centered Admin Override Modal (`ForceUnlockModal.vue`)**:
    - Built as a centered dialog following ISUZU industrial design standards.
    - Displays submission snapshot (code, status, date, section, total hours).
    - Amber-bordered warning explaining that all items will revert to `MENUNGGU REVIEW (SUBMITTED)`.
    - Mandatory reason textarea with live character counter (`N / 5 karakter minimal`).
    - Integrated seamlessly into `ApprovalQueue.vue`, `SubmissionQueueRow.vue`, `Index.vue`, and `SubmissionDetailModal.vue` without modal nesting.

## Consequences

### Positive

- **Financial & Audit Integrity**: Approved records cannot be modified or deleted without explicit administrative authority and a mandatory audit trail.
- **Traceable Overrides**: Every unlock action is permanently cataloged in the immutable audit trail with actor, timestamp, IP address, and explanation.
- **Zero Inconsistent States**: All item statuses, submission headers, and monthly budget burn indicators are synchronously updated in a single atomic database transaction.
- **Role-Appropriate Ergonomics**: Team Leaders see clear locked pills; Administrators have one-click access to force-unlock dialogs across queues and detail views.

### Negative

- Force-unlocking a submission resets all items to `PENDING`, requiring the Manager to re-review all items after the Team Leader completes corrections.
