# ADR-007: Role-Based Access Control and Scoping

**Date:** 2026-09-06  
**Status:** accepted  
**Supersedes:** None

## Context

The OT-CapEx manufacturing management system serves four primary user personas with distinct operational authority and data privacy boundaries:

1. **Administrator**: Full plant-wide authority, user provisioning, global rate settings, and audit logs.
2. **Manager**: Departmental head responsible for approving or rejecting overtime, tracking departmental budget burn, and monitoring ML predictions across sections within their assigned department.
3. **Team Leader**: Line supervisor responsible for submitting post-shift overtime logs and attaching physical SPKL sign-offs for their specific production section.
4. **User / Operator**: Shopfloor technician entitled to inspect their personal overtime records and shift history.

Key architectural challenges:

- Cross-section data leakage: A Team Leader must be strictly prevented from viewing or submitting timesheets for another production section.
- Cross-department data leakage: A Manager must be isolated to sections belonging to their department (unless user is Administrator).
- Dual identifiers: Shopfloor staff authenticate using numeric NPK badges, while office staff use corporate email addresses.
- Rate limiting: High-frequency brute force attempts must trigger an immediate safety lockout with clear countdown feedback without crashing the SPA.
- User experience: Access denied events must render an intuitive in-layout recovery screen rather than an unstyled technical HTTP 403 error page.

## Decision

1. **Enum-Backed Roles**:
    - Implemented PHP backed Enum `App\Enums\UserRole` (`admin`, `manager`, `team_leader`, `user`) providing strongly typed roles, human-friendly labels, and standard badge colors.
2. **Database Schema Additions**:
    - Augmented `users` table via migration with `role`, unique indexed `npk`, nullable foreign keys `department_id` and `section_id`, and `is_active` boolean status flag.
3. **Dual-Identifier Authentication**:
    - Customized `Fortify::authenticateUsing` to perform case-insensitive queries against both `email` and `npk` (`LOWER(email) = ? OR LOWER(npk) = ?`). Deactivated accounts (`is_active = false`) are rejected at the authentication gateway.
4. **Middleware & Authorization Gates**:
    - Created `EnsureRole` middleware (alias `'role'`) capable of accepting single or comma-delimited role parameters (e.g. `role:admin,manager`).
    - Defined granular Gates in `AppServiceProvider` reflecting the capability matrix and scoping rules (`canAccessSection`, `canAccessDepartment`).
5. **In-Layout 403 Exception Handling**:
    - Configured `bootstrap/app.php` exception pipeline to intercept HTTP 403 responses and render `resources/js/pages/Error.vue` via Inertia, providing a one-click return to `/dashboard`.
6. **Frontend Experience & Operational Clock**:
    - Engineered `useShiftInfo` composable calculating live `Asia/Jakarta` (WIB) time and standard 3-shift boundaries (`07:00–15:00`, `15:00–23:00`, `23:00–07:00`).
    - Enhanced `Login.vue` with 48px touch targets, dual-identifier input with icon, password eye toggle, and a dynamic countdown banner triggered on rate limiting.

## Consequences

### Positive

- **Shopfloor Usability**: Line workers can effortlessly log in using their familiar NPK numbers without requiring memorized corporate email aliases.
- **Strict Data Isolation**: Section and department scoping guarantees zero data leakage between distinct manufacturing lines.
- **Non-Technical Error Recovery**: Accidental unauthorized route navigation yields a graceful, branded, in-layout screen with a clear path back to safe ground.
- **Auditable Role Badges**: Persistent color-coded badges in the header and user menu ensure clear identity verification during shift handovers.
- **Automated Test Coverage**: End-to-end browser tests powered by Playwright and Pest provide verification of user interactions and session management.

### Negative

- **Dual Identifier Lookups**: Querying against both `email` and `npk` requires indexed columns to maintain sub-millisecond database lookups.
- **Additional Layout Props**: Loading department and section relationships on authenticated requests incurs a lightweight eager-load overhead, mitigated by selecting only required columns (`id,code,name`).

### Neutral

- User accounts without assigned departments or sections (e.g. global Admin or unassigned new hires) display graceful "Not Assigned" badges on the operational dashboard.
