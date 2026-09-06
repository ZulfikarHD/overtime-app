# UX Plan — E01: Foundation & Infrastructure Setup

> Created before implementation. This document is a hard constraint for all work in Epic E01.
> Epic file: `docs/scrum/Epic-01.md`

---

## 1. Navigation Footprint

### 1.1 Executive Layout Philosophy

Epic E01 provides the foundational scaffolding, database schema, authentication, role-based access control (RBAC), and layout shell for the entire Overtime & CapEx Labor Management System (OT-CapEx System). Because Epic E01 is foundational (Sprint 1), the primary design goal is **extreme simplicity and clarity**: establishing a rock-solid layout shell without cluttering the screen with placeholder or non-functional links that confuse factory supervisors and line operators.

Target users are Indonesian frontline factory supervisors (_Team Leaders_ / Mandor), department heads (_Managers_), and production operators (_Users_) working in an industrial manufacturing plant with three operational shifts (07:00, 15:00, 23:00 WIB). They have **low patience** during shift handovers, use smartphones or shared line workstations, and are accustomed to intuitive apps like WhatsApp.

### 1.2 Sub-Epic Functional Separation

| Sub-Epic / Story                                                            | Category                | User-Facing Surface                                                                                                | Dedicated UI Needed? |
| --------------------------------------------------------------------------- | ----------------------- | ------------------------------------------------------------------------------------------------------------------ | -------------------- |
| **E01-01**: Scaffolding & Tooling (Laravel, Vue 3, Wayfinder, Pint, ESLint) | Pure Backend / Infra    | None (developer tooling)                                                                                           | ❌ No                |
| **E01-02**: Database Migrations (15 Tables, DDL, Constraints)               | Pure Backend / Database | None (database layer)                                                                                              | ❌ No                |
| **E01-03**: Eloquent Models & Scopes (15 Models, Casts)                     | Pure Backend / ORM      | None (application code)                                                                                            | ❌ No                |
| **E01-04**: Authentication & RBAC (Roles, Gates, Throttling)                | **User-Facing**         | Login Page (`/login`), Lockout Alert, Role Badge                                                                   | ✅ Yes               |
| **E01-05**: Layout Shell & Wayfinder Navigation                             | **User-Facing**         | `AuthenticatedLayout.vue`, `GuestLayout.vue`, Topbar, Dashboard (`/dashboard`), 403 Error Page, Toast Notification | ✅ Yes               |
| **E01-06**: Redis Queue & Background Jobs (3 Jobs)                          | Pure Backend / Worker   | None (asynchronous workers)                                                                                        | ❌ No                |
| **E01-07**: Database Seeding (Demo Users, Depts, Sections)                  | Backend / CLI Seed      | None (`php artisan db:seed`)                                                                                       | ❌ No                |

### 1.3 Minimal Navigation Footprint Decisions

- **Sidebar Items for Epic E01**: Exactly **1 active item** (`Dashboard` / Beranda).
    - Subsequent epic modules (e.g., _Daily OT Entry_, _Approvals_, _Master Data_, _Individual Reports_) must remain completely hidden until their respective epics are built, preventing dead clicks and user confusion during early iterations.
- **Distinct Routes/Pages in Epic E01**: Exactly **2 routes**:
    1. `/login` — Login Screen (Guest view).
    2. `/dashboard` — System Home / Operational Dashboard (Authenticated view, role-aware).
    3. `403 Forbidden` — Access Restricted State (Handled in-layout as an error state, no dedicated route required).
- **Sub-Epics Sharing One Page**:
    - E01-04 (Auth state, Role badge, Section/Dept indicator) and E01-05 (Layout shell, Topbar, WIB Clock, Flash Toast) share the single `/dashboard` screen and `AuthenticatedLayout.vue` wrapper.
- **Surfaces Handled via Drawer/Sheet (Not Separate Routes)**:
    - **User Profile & Session Panel**: Dropdown (Desktop) / Bottom Sheet (Mobile) anchored to the user avatar pill in the topbar (no separate `/profile` page required for basic session review and logout).
    - **Mobile Navigation Drawer**: Off-canvas slide-in sheet (`Sheet` / drawer from the left) triggered by the hamburger icon on mobile viewports (<768px).
- **Pure Backend (No Dedicated UI)**:
    - E01-01, E01-02, E01-03, E01-06, E01-07, and backend components of E01-04 (`EnsureRole` middleware, Gates, rate limiting).

---

## 2. Screen Inventory

| Screen / Panel                                   | Location                                                         | What's visible on first open                                                                                                                                                                                                                                                                                                                                                                                                                           | Further triggers                                                                                                                                                                                             | Click depth                                                      |
| ------------------------------------------------ | ---------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------- |
| **Login Screen**                                 | Route: `/login` (using `GuestLayout.vue`)                        | • Company logo & system title ("OT-CapEx System")<br>• Input field: _Email or NPK_ (with user icon)<br>• Input field: _Password_ (with lock icon & show/hide toggle)<br>• Checkbox: _Remember Me_<br>• Primary action button: _Log In to System_<br>• Plain-language help text: _"Having trouble logging in? Contact HR / IT Admin"_                                                                                                                   | • Toggle _show password_ (eye icon)<br>• Inline error alert if credentials are invalid<br>• Lockout countdown warning banner if 5 failed attempts occur<br>• Loading spinner on button during authentication | **0** (Direct entry / auto-redirect from protected URLs)         |
| **Operational Dashboard Shell**                  | Route: `/dashboard` (using `AuthenticatedLayout.vue`)            | • **Top Header**: Mini logo, Live WIB clock (`Asia/Jakarta`), Active Shift indicator (`Shift 1: 07:00–15:00 WIB`), Account pill (Name, NPK, Role badge)<br>• **Sidebar (Desktop)**: Single active item: _Dashboard_<br>• **Main Content**: Welcoming banner (_"Welcome back, [Name]!"_), Assignment card (_Department_, _Section_, _Role_), Sprint 1 system status summary, and guidance note that overtime entry will activate in subsequent releases | • Click account pill opens Profile/Session popover<br>• Click notification bell icon (placeholder status indicator)<br>• Click hamburger menu (mobile) opens Mobile Navigation Drawer                        | **1** (Immediately post-login) or **0** (active session)         |
| **Mobile Navigation Drawer**                     | Slide-in Sheet (left) on viewports `< 768px`                     | • Drawer header: Plant logo & close button (X)<br>• Compact profile: User name, NPK, colored role badge<br>• Single menu item: _Dashboard_ (active highlight)<br>• System build indicator (`v1.0.0 — Sprint 1`)                                                                                                                                                                                                                                        | • Click close button (X) or backdrop area dismisses drawer<br>• Click navigation link closes drawer and navigates                                                                                            | **1** (Click hamburger icon on mobile topbar)                    |
| **User Session & Sign-Out Panel**                | Dropdown (Desktop) / Bottom Sheet (Mobile) on Topbar             | • Full employee name & NPK<br>• Assigned unit (Department & Section)<br>• Color-coded Role Badge: `Admin` (Purple), `Manager` (Blue), `Team Leader` (Green), `User/Operator` (Neutral Gray)<br>• Destructive action button: _Sign Out_ (Logout)                                                                                                                                                                                                        | • Click _Sign Out_ triggers an inline confirmation prompt (no nested modal)<br>• Confirmation triggers the Wayfinder `logout` action                                                                         | **1** (Click user avatar/pill on topbar)                         |
| **Access Restricted View (403 Forbidden State)** | In-layout Error View (Inertia template)                          | • Friendly, non-technical lock illustration<br>• Clear title: _"Access Restricted"_<br>• Human-friendly message: _"Your account ([Role]) does not have permission to view this section. Please return to Dashboard."_<br>• Single primary action: _Return to Dashboard_                                                                                                                                                                                | • Click _Return to Dashboard_ navigates to `/dashboard` via Wayfinder                                                                                                                                        | **0** (Rendered automatically if route middleware blocks access) |
| **Global Flash Toast Notification**              | Floating Container (Top-right on desktop / Top-center on mobile) | • Hidden by default<br>• Appears automatically when flash message is received from server (success/error/info)                                                                                                                                                                                                                                                                                                                                         | • 'X' button for manual dismiss<br>• Auto-dismiss timer after 4 seconds                                                                                                                                      | **0** (Reacts automatically to system events)                    |

---

## 3. User Journey Maps

### Journey 1: Sign In to the System (Shift Worker / Team Leader Daily Login)

```
Goal: Access the application using work credentials before starting a shift
Starts at: /login (or auto-redirect when opening any protected URL)
Steps:
  1. User opens the application and views a concise 2-field form (Email/NPK and Password).
  2. User enters NPK/Email and Password (may toggle eye icon to verify spelling).
  3. User clicks the prominent primary button "Log In to System".
Done: System verifies credentials, displays a green success toast "Logged in successfully", and redirects to /dashboard according to role.
Step count: 3
Status: ✅ OK (≤ 4 steps)
```

#### Failure & Lockout Handling (Zero Confusion):

- **Invalid Credentials**: The system does NOT wipe the Email/NPK input. An inline red message appears below the password field: _"The provided credentials do not match our records. Please verify and try again."_
- **Account Lockout (Throttled > 5 failed attempts)**: An orange banner appears with an active countdown timer: _"Too many failed login attempts. For security reasons, your account is temporarily locked for :seconds seconds. Contact HR/IT Admin if you forgot your password."_

---

### Journey 2: Verify Identity, Role, and Active Shift on Dashboard

```
Goal: Confirm user identity, assigned section, and active operational shift at start of shift
Starts at: /dashboard (immediately after successful login)
Steps:
  1. User glances at Topbar: checks current live WIB clock and active shift badge (e.g. "Shift 1: 07:00 - 15:00 WIB").
  2. User inspects assignment identity card: verifies Name, NPK, Department, and Section.
Done: User is fully confident that the application session is operating under the correct identity and line section.
Step count: 2
Status: ✅ OK (≤ 4 steps)
```

---

### Journey 3: Access Control Interception (RBAC 403) & Safe Recovery

```
Goal: Understand access denial when following an unauthorized link and safely return to dashboard
Starts at: User (e.g., Team Leader) attempts to access an Admin-only or cross-section URL
Steps:
  1. Backend middleware (EnsureRole) intercepts the request.
  2. Page displays a friendly "Access Restricted" screen (no raw 403 error codes or stack traces).
  3. User reads clear message explaining that their role does not have authorization for this area.
  4. User clicks "Return to Dashboard".
Done: User returns safely to /dashboard without session termination or confusion.
Step count: 4
Status: ✅ OK (≤ 4 steps)
```

---

### Journey 4: Sign Out at Shift Handover (Logout)

```
Goal: Securely terminate session so the next shift cannot use the workstation under the previous user's identity
Starts at: Any screen while authenticated (/dashboard)
Steps:
  1. User taps account avatar/pill in the top-right header.
  2. User clicks "Sign Out".
  3. User confirms prompt "Yes, Sign Out".
Done: Session is securely invalidated, user is redirected to /login, and an informative toast confirms "You have been signed out."
Step count: 3
Status: ✅ OK (≤ 4 steps)
```

---

## 4. Indonesian UX Guardrails

### 4.1 Patience Thresholds

Factory personnel often interact with workstations and mobile devices while physically fatigued, under tight shift handover deadlines, or wearing industrial gloves.

- **Daily Tasks (Login, Check Shift, Logout)**: Maximum **2–3 steps**. No convoluted confirmation chains or intrusive captchas in daily routines.
- **Occasional Tasks (Check profile, update password)**: Maximum **3–4 steps**.
- **Recovery Tasks (Locked account due to forgotten password)**: Immediate contact guidance provided on-screen (phone extension, WhatsApp IT contact link).

### 4.2 Cognitive Load Budget

- **Visible Actions**: Maximum **3 primary actions** on any screen. On the login screen: only _Log In_, _Remember Me_, and _Help_.
- **Form Length**: Maximum **2 fields** on the login screen (`Email/NPK` and `Password`). Inputs must fit on a 360x640px smartphone viewport without vertical scrolling.
- **Comfortable Touch Target Sizes**: All interactive buttons, text inputs, and navigation links must have a minimum touch height of **48px** (Tailwind `h-12`) with ample padding for finger interaction on smartphones and shopfloor industrial tablets.
- **Modal Depth**: Strictly no modals inside modals (_no nested modals_). Maximum drawer/modal depth is **1 layer**.

### 4.3 Trust Signals & Plant Communication Standards

- **Destructive Action Confirmations**: Sign out and session termination must use explicit, respectful human language:
    - _Avoid_: `"Terminate authenticated session tokens?"`
    - _Required_: `"Sign out of your account? Please ensure your current shift tasks are saved before leaving."`
- **Immediate Visual Feedback**: Every successful action (login, logout, theme change) must trigger a clear green toast notification lasting 4 seconds, avoiding silent redirects.
- **Zero Raw Technical Jargon**:
    - Never display raw technical phrases like _"Unauthorized"_, _"Forbidden 403"_, _"CSRF mismatch"_, _"Payload invalid"_, or _"Throttle limit reached"_.
    - Use clear, actionable copy: _"Session expired, please sign in again"_, _"Too many attempts, please wait :seconds seconds"_, _"Access Restricted"_.
- **Factory Localization Standards**:
    - Timezone: Always display timestamps in explicit `WIB` format (`Asia/Jakarta`), e.g., `07:15 WIB` and `Senin, 07 September 2026`.
    - Employee Identity: Prioritize **NPK (Nomor Pokok Karyawan)** alongside the employee's full name, as NPK is the primary identifier in manufacturing plants.

### 4.4 Epic E01 Specific Risks & Mitigations

| Sub-Epic / Feature                              | Identified UX Risk                                                                                                                                    | Mandatory Design Mitigation                                                                                                                                                          |
| ----------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **E01-04 (Login & Authentication)**             | Factory workers memorize their numeric **NPK**, not lengthy corporate email addresses. An email-only login form will trigger immediate user friction. | Design the login identifier field to accept **either Email OR NPK** (_dual-identifier login_).                                                                                       |
| **E01-04 (Account Lockout Throttling)**         | A supervisor mistypes password 5 times during a rushed shift handover; account locks with no indication of wait time, causing panic.                  | Display a dynamic countdown timer (_"Please try again in 54 seconds"_) alongside HR/IT Admin contact extension.                                                                      |
| **E01-04 (RBAC Ambiguity)**                     | Users may wonder why certain menus are absent, suspecting system failure.                                                                             | Prominently display a clear, color-coded **Role Badge** on the header: `[Team Leader - Stamping Section]` or `[Manager - Production Dept]`.                                          |
| **E01-05 (Empty Dashboard in Sprint 1)**        | In Sprint 1, overtime entry (E03) and approvals (E04) are not yet live. Users logging in may perceive the empty dashboard as broken.                  | Provide a warm welcome card explaining system status: _"System Infrastructure Initialized — Sprint 1 Active. Overtime entry and SPKL workflows will activate in upcoming releases."_ |
| **E01-05 (Mobile Responsiveness on Shopfloor)** | Desktop sidebar breaks or overlaps content on mobile devices or tablets used at line stop monitoring stations.                                        | Implement a responsive, collapsible off-canvas drawer (`Sheet`) for mobile viewports (`< 768px`). Sidebar is completely hidden by default on mobile.                                 |

---

## 5. Implementation Boundaries (Anti-Splitting Rules)

These rules are hard constraints for the implementing engineer or AI agent. Do not deviate without architectural approval.

### 5.1 Do Not Split — Combine Into One Surface:

- **Single Login Screen**: Do NOT create role-specific login pages (e.g., do NOT create `/admin/login`, `/manager/login`, or `/worker/login`). All roles must authenticate via `/login`. RBAC logic will route users to their appropriate dashboard view.
- **Account Identity Pill in Header**: Name, NPK, and Role Badge must be combined into a single interactive pill in the top-right header, rather than dispersed across multiple separate navigation blocks.

### 5.2 Make a Tab, Not a New Route:

- For Epic E01, multi-tab routing is unnecessary. The dashboard (`/dashboard`) is a single, clean surface providing system status and user context.

### 5.3 Make a Drawer/Sheet, Not a Full Page:

- **Mobile Navigation Menu**: Must use a slide-in sheet (`Sheet` from left), NOT a separate `/mobile-menu` page.
- **User Profile & Sign-Out Panel**: Must use a Popover/Dropdown on desktop or a Bottom Sheet on mobile, NOT separate routes like `/account-info` or `/logout-confirm`.

### 5.4 Backend-Only — Strictly No Dedicated UI:

The following sub-epics and components are purely system infrastructure and data layer logic. **Do NOT create dedicated navigation items, views, or pages for them**:

- **E01-01**: Tooling scaffolding, Vite config, Wayfinder generator, PHP Pint, and ESLint.
- **E01-02**: Database schema migrations (15 tables, foreign keys, CHECK constraints, stored generated columns).
- **E01-03**: Eloquent models, casts, and database scopes.
- **E01-06**: Redis queue workers and the 3 base job classes (`RunAnomalyDetectionJob`, `RecalculateMonthlyBurnSnapshotJob`, `SendSpklReminderJob`).
- **E01-07**: Database seeders (executed via CLI `php artisan db:seed`).
- **RBAC Middleware**: Role verification logic (`EnsureRole`) runs purely at the HTTP middleware layer.

### 5.5 Strictly Forbidden:

1. **Do NOT** use legacy Ziggy `route()` helper. Always use **Laravel Wayfinder** functions imported from `@/routes` or `@/actions`.
2. **Do NOT** display raw English error codes or HTTP statuses (`403 Forbidden`, `419 CSRF Expired`, `500 Internal Server Error`) to end users. Wrap all errors in friendly, actionable copy.
3. **Do NOT** add sidebar items for future unbuilt modules. The only active navigation item in Epic E01 is `Dashboard`.
4. **Do NOT** introduce multi-step or intrusive login barriers (such as mandatory SMS/email OTP) outside Sprint 1 factory readiness. Core authentication is Email/NPK + Password with rate limiting.
5. **Do NOT** overuse alarming red/orange indicators during normal states to avoid inducing anxiety during fast-paced shift operations.
