# User Documentation (User Docs)

## Overtime & CapEx Labor Management System (OT-CapEx)

Welcome to the end-user documentation library for the OT-CapEx system. These guides are written in clear, plain language for factory operators, team leaders, section supervisors, department managers, and plant administrators.

---

## 1. User Guides (`guides/`)

Step-by-step instructions for everyday plant operations:

| Guide                                                                                                  | Target Persona          | Description                                                                                                                                                                                                           |
| ------------------------------------------------------------------------------------------------------ | ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **[Authentication & Access Control](./guides/authentication-rbac.md)**                                 | All Plant Personnel     | Logging in using NPK or Email, understanding role badges, monitoring live shift clocks, and safely signing out.                                                                                                       |
| **[Dashboard & Operational Navigation](./guides/dashboard-navigation.md)**                             | All Plant Personnel     | Navigating the main dashboard, checking active shift hours, live WIB clock, and shift handover sign-out.                                                                                                              |
| **[Department & Section Management](./guides/department-section-management.md)**                       | Plant Administrators    | Managing factory department and section hierarchy, default overtime rates, cost centers, and line activation.                                                                                                         |
| **[Employee Roster Management & CSV Import](./guides/employee-roster-management.md)**                  | Plant Administrators    | Managing factory employee roster, NPK protection, custom overtime labor rates, and bulk CSV onboardings.                                                                                                              |
| **[Policy Threshold Configuration](./guides/policy-threshold-configuration.md)**                       | Plant Administrators    | Configuring plant-wide overtime soft limits, SPKL grace periods, and department-specific threshold overrides.                                                                                                         |
| **[User Account Management](./guides/user-account-management.md)**                                     | Plant Administrators    | Provisioning system logins, assigning operational roles, setting department scopes, and resetting passwords.                                                                                                          |
| **[User Preferences & Display Standards](./guides/user-preferences-settings.md)**                      | All Plant Personnel     | Customizing theme modes (Light/Dark/System), configuring alert notification toggles, and viewing plant standards.                                                                                                     |
| **[Overtime Budget Planning](./guides/overtime-budget-planning.md)**                                   | Admins & Managers       | Planning section monthly overtime hours, 5-week breakdown, estimating costs in Rupiah, and two-stage CSV import.                                                                                                      |
| **[Burn Index & Budget Dashboard](./guides/budget-burn-index.md)**                                     | Managers, Admins & TL   | Real-time section overtime burn monitoring, 5-week burndown curves, 4-quadrant Budget Control Matrix, burn velocity, and CapEx/OpEx labor split.                                                                      |
| **[Policy Threshold Alerts & Budget Warnings](./guides/budget-threshold-alerts.md)**                   | Managers & Admins       | In-app alerts for quota warnings (>100%) and critical deficits (>115%), deduplication locks, pulsing card cues, and 1-click drawer deep links.                                                                        |
| **[Daily Overtime Submission](./guides/daily-overtime-submission.md)**                                 | Team Leaders & Admins   | Submitting daily shift overtime batches, roster auto-filling, CapEx tracking, live Rupiah costs, and non-blocking SPKL.                                                                                               |
| **[Overtime Policy Soft Warning Indicators](./guides/policy-soft-warning-indicators.md)**              | Team Leaders & Managers | Understanding real-time advisory fatigue badges (yellow/red), checking weekly limit thresholds, and non-blocking submission rules.                                                                                    |
| **[SPKL Document Attachment](./guides/spkl-document-attachment.md)**                                   | Team Leaders & Managers | Post-shift photo/PDF upload, physical SPKL registration numbers, manager verification, and download access.                                                                                                           |
| **[SPKL Pending Reminders & Notifications](./guides/spkl-pending-reminders.md)**                       | Team Leaders & Admins   | In-app notification bell alerts, 1-click SPKL attachment drawer resolution, and reminder preference management.                                                                                                       |
| **[Overtime Approvals Queue](./guides/overtime-approvals.md)**                                         | Managers & Admins       | Morning standup approval queue, item approvals, bulk processing, CSV/Excel data export, immutable audit trail, and modification lock / force unlock.                                                                  |
| **[Individual Employee Dossier & Welfare](./guides/individual-employee-dossier.md)**                   | All Plant Supervisors   | Fast debounced employee search by NPK or name, recent lookups, roster quick-pick, dossier header, period filtering, peer benchmarking, welfare monitoring, chronological audit timesheet, and zero-memory CSV export. |
| **[Employee Self-Service Dashboard](./guides/employee-self-service.md)**                               | Line Operators (User)   | Mobile-first personal summary (/my/dashboard), monthly hours, estimated gross earnings, welfare safety status, recent 5 submissions, and rejection feedback.                                                          |
| **[CapEx Project Labor & Portfolio Monitoring](./guides/capex-project-labor.md)**                      | Managers & Admins       | Multi-project portfolio monitoring, milestone burn ratio tracking, at-risk project alerts, burndown curves, and in-place physical progress updates.                                                                   |
| **[Executive Operational Dashboard & KPI Cards](./guides/executive-dashboard-kpi.md)**                 | Managers, Admins & TL   | Live plant command center (/dashboard), 4 header KPI cards (Production Volume, Working Days, Manpower, Burn Index), sparklines, and date/department filters.                                                          |
| **[Daily Burn Chart Index & Section Comparison](./guides/daily-burn-and-section-comparison.md)**       | Managers, Admins & TL   | Interactive daily cumulative burn line chart with budget ceiling, month navigation, section ranking horizontal bar chart, and 1-click drill-down.                                                                     |
| **[Executive Dashboard Multi-Chart Analytics Grid](./guides/executive-dashboard-multi-chart-grid.md)** | Managers, Admins & TL   | Morning standup guide for reading the Top 10 Overtime Leaderboard, Category Donut, 12-Month HKN/HLR Trend, Daily Index Pacing, and Weekly Day Type Breakdown.                                                         |
| **[Summary Employee Overtime Table](./guides/executive-dashboard-employee-summary-table.md)**          | Managers, Admins & TL   | Standup guide for using the Band 5 summary table, live search, sortable metrics, category donut cross-filtering, and slide-in Quick Dossier Drawer.                                                                   |

---

## 2. Factory Operational Navigation

All navigation in the OT-CapEx system corresponds directly to your assigned sidebar items:

- **Dashboard**: High-level shift summary, operational clock, and role capability status (Admin, Manager, Team Leader). For Line Operators (`User` role), automatically resolves to the personal self-service dashboard (`/my/dashboard`).
- **Burn Index**: Operational health command center for real-time section burn rates, budget control matrix zones, and velocity (Admin and Manager roles).
- **Master Data**: Consolidated administrative hub for plant departments, sections, employee rosters, and operational calendars (Admin role).
- **Administration**: Consolidated administrative hub for plant-wide policy thresholds and user accounts (Admin role).
- **Budget Planning**: Consolidated hub for section-level monthly overtime hour quotas, 5-week distributions, and labor cost estimates (Admin and Manager roles).
- **Persetujuan Lembur**: Morning approval queue for reviewing Team Leader overtime submissions (Manager and Admin roles).
- **Laporan Karyawan (Employee Reports)**: Consolidated dossier hub for looking up individual employee overtime profiles, welfare indicators, and chronological timesheets (Admin, Manager, and Team Leader roles).
- **Settings → Profile / Security / Appearance / Preferences**: Update personal profile details, change password, configure two-factor authentication, or set UI and alert preferences.

---

## 3. Plant Support & Helpdesk

If you experience account lockout or technical difficulties on the shopfloor:

- **Internal Extension**: 1204 (IT Support / HR Operations)
- **WhatsApp Support**: +62 857-1583-8733
- **Lead Developer**: Zulfikar Hidayatullah
- **Plant Operational Timezone**: Western Indonesian Time (`Asia/Jakarta`, WIB)
