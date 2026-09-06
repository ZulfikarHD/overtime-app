# ADR-011: User Lifecycle and Role-Change Audit Logging

**Date:** 2026-09-07  
**Status:** accepted

## Context

System security and compliance in industrial labor management require strict governance over user access, role assignments, and authentication states. Specifically:

1. **Accidental Administrator Lockout**: In previous enterprise ERP deployments, administrators occasionally deactivated their own account or demoted their own role, locking themselves out of the system and requiring database administrator intervention.
2. **Accountability for Privilege Escalation**: Promoting a user to `manager` or `admin` confers approval powers and visibility into plant financial budgets. Changes in roles must be auditable, recording who performed the modification and when.
3. **Referential Integrity vs. Account Deletion**: Users may be linked to overtime submissions, timesheet item reviews, and attached SPKL documents. Hard-deleting active users with linked records results in SQL foreign key violations or orphaned audit trails.

## Decision

We implemented a defense-in-depth user lifecycle management architecture:

1. **Two-Tier Self-Lockout Guard**:
    - Both the application layer (`app/Http/Requests/Admin/UpdateUserRequest.php`) and service layer (`app/Services/UserService.php`) enforce invariant checks: if the actor's ID matches the target user's ID, setting `is_active = false` or demoting `role` from `admin` is blocked with clear validation exceptions.
    - The UI (`UserFormSheet.vue` and `Administration.vue`) disables the role dropdown, status checkbox, and status toggle button for self-accounts, displaying an informative lock banner.
2. **Dedicated Role-Change and Lifecycle Audit Table (`user_audits`)**:
    - Instead of mixing user credential audits with timesheet item audits, a dedicated `user_audits` table tracks:
        - `user_id`, `actor_user_id`, `action` (`user_created`, `role_change`, `status_toggled`, `user_updated`, `password_reset_sent`), `previous_role`, `new_role`, `details`, and `ip_address`.
    - Modifying a user's role creates an immutable audit row with previous and new role values.
3. **Soft Deactivation Over Hard Deletion**:
    - Hard deletion is blocked if the user has linked overtime submissions, reviews, or attached SPKL documents.
    - Plant administrators are guided to deactivate accounts instead, which instantly blocks authentication in `FortifyServiceProvider` and `EnsureRole` while preserving complete relational integrity.
4. **Login Timestamp Auditing**:
    - An event listener (`UpdateUserLastLogin`) hooks into `Illuminate\Auth\Events\Login` and stamps `last_login_at` on successful authentication without triggering full model event overhead.

## Consequences

### Positive

- **Zero Accidental Lockouts**: Factory administrators cannot inadvertently remove their own access or deactivate their account.
- **Full Traceability**: Every privilege escalation, role change, and password reset request is recorded with actor metadata and timestamps.
- **Relational Stability**: No foreign key crashes from hard deletions; all historical labor approvals maintain their author identities.
- **Immediate Invalidation**: Deactivating an employee prevents future logins immediately.

### Negative

- **Audit Storage**: Dedicated audit table accumulates records over time, though volume is low given typical administrative change frequency.

### Neutral

- **Password Resets**: Triggering a reset link leverages Laravel's native password broker, dispatching standard notification emails without exposing temporary credentials.
