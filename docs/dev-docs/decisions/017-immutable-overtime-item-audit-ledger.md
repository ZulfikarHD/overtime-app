# ADR-017: Immutable Overtime Item Audit Ledger and Lifecycle History

**Date:** 2026-09-08  
**Status:** accepted  
**Supersedes:** None

## Context

In an enterprise automotive plant setting, overtime submissions have immediate legal, labor union, and payroll implications. Once an overtime item transitions through its lifecycle (`SUBMITTED` → `APPROVED` | `REJECTED` | `ADMIN_UNLOCK` | `EXPORT`), any tampering, retroactive modification, or accidental deletion of historical records introduces regulatory non-compliance, financial audit exposure, and labor dispute vulnerabilities.

Key challenges addressed:

1. **Application-Level Immutability**: While database foreign key constraints maintain referential integrity, application code (or unintended ORM cascade executions) could inadvertently invoke `update()` or `delete()` on audit log records.
2. **End-to-End Lifecycle Completeness**: Overtime items previously logged audits upon approval or rejection, but lacked an initial synchronous audit ledger entry upon creation (`SUBMITTED`), creating an incomplete initial state snapshot.
3. **UX & Cognitive Ergonomics**: Managers and auditors inspecting historical decisions inside the Approval modal require immediate visibility of chronological state transitions, actor identification, and visual state diffing without navigating away to separate pages or opening nested modals (violating UX constraint rules).
4. **Scoping & Confidentiality**: Departmental wage rates, overtime hours, and rejection notes are sensitive personnel data. Managers must only access audit records within their authorized department.

## Decision

We implemented an **Immutable Overtime Item Audit Ledger and Slide-in Inspection System** (E04-05):

1. **Insert-Only Immutability Guard via Eloquent Observer**:
    - Implemented `App\Observers\OvertimeItemAuditObserver` attached to `OvertimeItemAudit` via `#[ObservedBy([OvertimeItemAuditObserver::class])]`.
    - Any invocation of `updating()` or `deleting()` on an `OvertimeItemAudit` instance immediately aborts and throws a `RuntimeException('Overtime item audit records are immutable and cannot be modified.')` or `RuntimeException('Overtime item audit records are immutable and cannot be deleted.')`.
    - The model strictly disables timestamps (`$timestamps = false`), storing only a deterministic `created_at` timestamp.

2. **Synchronous Initial Lifecycle Tracking in `SubmitOvertimeAction`**:
    - Inside `SubmitOvertimeAction::execute()` and `SubmitOvertimeAction::update()`, an initial `OvertimeItemAudit` record with `action: 'SUBMITTED'` is created synchronously within the atomic database transaction for every created `OvertimeItem`.
    - Stores `previous_state: null`, complete `new_state` snapshot, submitting actor User ID, IP address, and WIB creation timestamp.

3. **Scoped Audit Trail API (`GET /overtime/items/{item}/audit`)**:
    - Managed by `App\Http\Controllers\Overtime\OvertimeItemAuditController@index`.
    - Protected by `role:admin,manager` middleware.
    - Enforces strict departmental isolation: Department Managers attempting to query audit trails for items outside their department receive `403 Forbidden`. Plant Administrators retain factory-wide visibility.

4. **Slide-in Audit Trail Drawer (`AuditTrailDrawer.vue`)**:
    - Implemented as a slide-in right sheet component (`resources/js/components/overtime/AuditTrailDrawer.vue`) adhering to UX plan Section 4.2 ("Strictly No Nested Modals").
    - Integrated into `ApprovalItemRow.vue` via a `🕒 Riwayat` button and mounted inside `ApprovalModal.vue`.
    - Provides chronological timeline display (newest first) featuring:
        - Actor name, NPK, and role pill.
        - WIB timestamp (`dd/MM/yyyy HH:mm:ss WIB`).
        - Action badge (`SUBMITTED`, `APPROVED`, `REJECTED`, `ADMIN_UNLOCK`, `EXPORT`).
        - Plain-language summary note / rejection reason.
        - Visual State Diffing comparing `previous_state` and `new_state` with plant terminology and formatted Rupiah/hour units.
        - Collapsible technical metadata toggle for IP address and raw state inspection.
        - Immutable ledger guarantee badge.

## Consequences

### Positive

- **Tamper-Proof Auditability**: Full lifecycle history is preserved in an append-only ledger; updates and deletes are blocked at the application core.
- **Complete Traceability**: Every overtime item has a traceable origin from initial `SUBMITTED` through all managerial and administrative decisions.
- **Clean Industrial UX**: Drawer opens cleanly without cluttering the screen or navigating away from the review flow.
- **Zero Heavy Dependencies**: Implemented natively using Eloquent observers, Wayfinder route typing, and shadcn-vue sheets.

### Negative

- Append-only tables grow monotonically over time; monthly database archiving strategies may be required for multi-year historical logs.

### Neutral

- Export operations also record into `overtime_item_audits` with `overtime_item_id: null` to maintain unified ledger tracking across data extraction events.
