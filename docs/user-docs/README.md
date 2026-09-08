# User Documentation (User Docs)

## Overtime & CapEx Labor Management System (OT-CapEx)

Welcome to the end-user documentation library for the OT-CapEx system. These guides are written in clear, plain language for factory operators, team leaders, section supervisors, department managers, and plant administrators.

---

## 1. User Guides (`guides/`)

Step-by-step instructions for everyday plant operations:

| Guide                                                                                     | Target Persona          | Description                                                                                                                        |
| ----------------------------------------------------------------------------------------- | ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| **[Authentication & Access Control](./guides/authentication-rbac.md)**                    | All Plant Personnel     | Logging in using NPK or Email, understanding role badges, monitoring live shift clocks, and safely signing out.                    |
| **[Dashboard & Operational Navigation](./guides/dashboard-navigation.md)**                | All Plant Personnel     | Navigating the main dashboard, checking active shift hours, live WIB clock, and shift handover sign-out.                           |
| **[Department & Section Management](./guides/department-section-management.md)**          | Plant Administrators    | Managing factory department and section hierarchy, default overtime rates, cost centers, and line activation.                      |
| **[Employee Roster Management & CSV Import](./guides/employee-roster-management.md)**     | Plant Administrators    | Managing factory employee roster, NPK protection, custom overtime labor rates, and bulk CSV onboardings.                           |
| **[Policy Threshold Configuration](./guides/policy-threshold-configuration.md)**          | Plant Administrators    | Configuring plant-wide overtime soft limits, SPKL grace periods, and department-specific threshold overrides.                      |
| **[User Account Management](./guides/user-account-management.md)**                        | Plant Administrators    | Provisioning system logins, assigning operational roles, setting department scopes, and resetting passwords.                       |
| **[User Preferences & Display Standards](./guides/user-preferences-settings.md)**         | All Plant Personnel     | Customizing theme modes (Light/Dark/System), configuring alert notification toggles, and viewing plant standards.                  |
| **[Overtime Budget Planning](./guides/overtime-budget-planning.md)**                      | Admins & Managers       | Planning section monthly overtime hours, 5-week breakdown, estimating costs in Rupiah, and two-stage CSV import.                   |
| **[Daily Overtime Submission](./guides/daily-overtime-submission.md)**                    | Team Leaders & Admins   | Submitting daily shift overtime batches, roster auto-filling, CapEx tracking, live Rupiah costs, and non-blocking SPKL.            |
| **[Overtime Policy Soft Warning Indicators](./guides/policy-soft-warning-indicators.md)** | Team Leaders & Managers | Understanding real-time advisory fatigue badges (yellow/red), checking weekly limit thresholds, and non-blocking submission rules. |
| **[SPKL Document Attachment](./guides/spkl-document-attachment.md)**                      | Team Leaders & Managers | Post-shift photo/PDF upload, physical SPKL registration numbers, manager verification, and download access.                        |
| **[SPKL Pending Reminders & Notifications](./guides/spkl-pending-reminders.md)**          | Team Leaders & Admins   | In-app notification bell alerts, 1-click SPKL attachment drawer resolution, and reminder preference management.                    |
| **[Overtime Approvals Queue](./guides/overtime-approvals.md)**                            | Managers & Admins       | Morning standup approval queue, item approvals, bulk processing, CSV/Excel data export, and immutable audit trail inspection.      |

---

## 2. Factory Operational Navigation

All navigation in the OT-CapEx system corresponds directly to your assigned sidebar items:

- **Dashboard**: High-level shift summary, operational clock, and role capability status.
- **Master Data**: Consolidated administrative hub for plant departments, sections, employee rosters, and operational calendars (Admin role).
- **Administration**: Consolidated administrative hub for plant-wide policy thresholds and user accounts (Admin role).
- **Budget Planning**: Consolidated hub for section-level monthly overtime hour quotas, 5-week distributions, and labor cost estimates (Admin and Manager roles).
- **Persetujuan Lembur**: Morning approval queue for reviewing Team Leader overtime submissions (Manager and Admin roles).
- **Settings → Profile / Security / Appearance / Preferences**: Update personal profile details, change password, configure two-factor authentication, or set UI and alert preferences.

---

## 3. Plant Support & Helpdesk

If you experience account lockout or technical difficulties on the shopfloor:

- **Internal Extension**: 1204 (IT Support / HR Operations)
- **WhatsApp Support**: +62 857-1583-8733
- **Lead Developer**: Zulfikar Hidayatullah
- **Plant Operational Timezone**: Western Indonesian Time (`Asia/Jakarta`, WIB)
