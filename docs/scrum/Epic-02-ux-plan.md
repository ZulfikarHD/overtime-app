# UX Plan — E02: Master Data & Administration

> Created before implementation. This document is a hard constraint for all work in Epic E02.
> Epic file: `docs/scrum/Epic-02.md`

---

## 1. Navigation Footprint

### 1.1 Executive Philosophy: Zero Administrative Bloat

Epic E02 establishes the operational master data and administrative backbone of the Overtime & CapEx Labor Management System (OT-CapEx System): Department & Section hierarchies, Employee rosters, Operational Calendars, Policy Thresholds, User Accounts, Personal Preferences, and Overtime Budget Plans.

In traditional enterprise ERPs, master data is notorious for labyrinthine multi-level submenus, 20-field modal forms, and fragmented pages that overwhelm frontline manufacturing staff and plant administrators.

Target users are Indonesian plant administrators, factory managers, and production supervisors. They operate in fast-paced industrial environments, have **low patience** for convoluted page jumps, and prioritize speed and clarity. Every extra click, page reload, or nested modal increases abandonment and entry errors.

The primary architectural constraint for Epic E02 is **extreme surface consolidation**:

1. **Combine related resources into unified, tabbed operational hubs** rather than proliferating separate top-level pages.
2. **Confine all creation, editing, and CSV imports to side drawers (`Sheet`) or focused dialogs (`Modal`)** so users never lose their spatial context or table scroll position.
3. **Respect role scoping**: Frontline Team Leaders and general Users see zero administrative clutter; Managers see only their scoped Budget Planning; Admins get consolidated, high-efficiency workspaces.

---

### 1.2 Sub-Epic Functional Separation

| Sub-Epic / Story                                      | Category                      | User-Facing Surface                                                                                                                      | Dedicated UI Needed? |
| ----------------------------------------------------- | ----------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- | -------------------- |
| **E02-01**: Department & Section Hierarchy Management | **User-Facing**               | Master Data Hub (`/admin/master-data?tab=departments`), Hierarchy Tree/Table, Form Dialogs                                               | ✅ Yes               |
| **E02-02**: Employee Roster Management & CSV Import   | **User-Facing**               | Master Data Hub (`/admin/master-data?tab=employees`), Searchable Roster, Roster Drawer, CSV Import Drawer                                | ✅ Yes               |
| **E02-03**: Operational Calendar Management & API     | **User-Facing & Backend API** | Master Data Hub (`/admin/master-data?tab=calendar`), Monthly Grid, Day Edit Sheet, CSV Import Modal; `GET /api/calendar/{date}` endpoint | ✅ Yes (UI + API)    |
| **E02-04**: Policy Threshold Configuration            | **User-Facing**               | Administration Hub (`/admin/administration?tab=policies`), Plant Default Card, Override Table, Form Sheet                                | ✅ Yes               |
| **E02-05**: User Account Management & RBAC            | **User-Facing**               | Administration Hub (`/admin/administration?tab=users`), Account Table, Provisioning Drawer, Reset Trigger                                | ✅ Yes               |
| **E02-06**: Notification & UI Preferences             | **User-Facing**               | Settings Layout (`/settings/preferences`), Theme Toggle, Notification Checkboxes, Format Indicators                                      | ✅ Yes               |
| **E02-07**: Overtime Budget Plan Setup                | **User-Facing**               | Budget Planning Hub (`/budgets/planning`), Monthly Grid, Budget Breakdown Drawer, CSV Import Sheet                                       | ✅ Yes               |

---

### 1.3 Minimal Navigation Footprint Decisions

#### Sidebar Navigation Additions:

- **For `Admin` Role**:
    - Adds **3 sidebar items** organized cleanly under standard system groupings:
        1. **Master Data** (`/admin/master-data`) — Unified 3-tab hub for Employees, Departments/Sections, and Operational Calendar.
        2. **Administration** (`/admin/administration`) — Unified 2-tab hub for User Accounts and Policy Thresholds.
        3. **Budget Planning** (`/budgets/planning`) — Departmental and section overtime budget matrix.
- **For `Manager` Role**:
    - Adds **1 sidebar item**:
        1. **Budget Planning** (`/budgets/planning`) — Auto-scoped to the Manager's assigned department.
- **For `Team Leader` & `User` Roles**:
    - Adds **0 sidebar items**. (All E02 administrative surfaces are hidden via RBAC gates).
- **For All Authenticated Roles (Personal Settings)**:
    - Adds **0 sidebar items**. User Preferences (E02-06) lives directly inside the existing User Avatar dropdown under the Settings shell (`/settings/preferences`).

#### Distinct Routes & Pages:

Across the entire 42-story-point epic, exactly **4 primary routes** are created:

1. `/admin/master-data` — Single tabbed page (`employees`, `departments`, `calendar`).
2. `/admin/administration` — Single tabbed page (`users`, `policies`).
3. `/budgets/planning` — Single consolidated planning matrix with fiscal year/month filtering.
4. `/settings/preferences` — Extension of the existing user settings layout (`resources/js/layouts/settings/Layout.vue`).

#### Surfaces Handled via Drawer/Sheet (Never Dedicated Routes):

- **Employee Create & Edit**: Slide-in Right Drawer (`Sheet`), keeping the 25-row paginated roster visible in the background.
- **Employee CSV Import & Preview**: Slide-in Right Drawer (`Sheet`) with live validation preview table.
- **Department / Section Create & Edit**: Centered Modal Dialog (`Dialog`), maintaining quick 2-to-3 field interactions.
- **Calendar Day Inspector / Holiday Form**: Quick Slide-in Sheet (`Sheet`) triggered by tapping any day in the monthly calendar grid.
- **Calendar Holiday CSV Import**: Modal Dialog (`Dialog`).
- **Policy Threshold Override Form**: Slide-in Right Drawer (`Sheet`).
- **User Account Provisioning / Edit**: Slide-in Right Drawer (`Sheet`).
- **Monthly Budget 5-Week Breakdown**: Slide-in Right Drawer (`Sheet`) with dynamic sum-validation badge.

#### Pure Backend & Headless Logic (No UI):

- `OperationalCalendarService::generateForYear(int $year)` and CLI command `php artisan app:seed-calendar`.
- Day type classification endpoint `GET /api/calendar/{date}` (used downstream by E03 timesheet entry).
- `ImportEmployeesFromCsvJob` background parsing and in-memory pre-commit validation.
- `PolicyThresholdService::getForDepartment(int $deptId)` hierarchical inheritance fallback engine.
- Integrity protection hooks blocking accidental hard-deletion of linked departments, sections, and users.
- Role-change audit logging into `overtime_item_audits` / `user_audits`.

---

## 2. Screen Inventory

| Screen / Panel                                   | Location                                                      | What's visible on first open                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             | Further triggers                                                                                                                                                                                                                                                                                                                | Click depth                                       |
| ------------------------------------------------ | ------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------- |
| **Master Data Hub — Employee Roster Tab**        | `/admin/master-data?tab=employees`                            | • Tab navigation header: _Employees_, _Departments & Sections_, _Operational Calendar_<br>• Search input (NPK, Name) & Dept filter dropdown<br>• Primary button: _+ Tambah Karyawan_ (Add Employee)<br>• Secondary button: _Import CSV_<br>• Paginated table (25 rows): NPK (monospace badge), Name, Department, Section, Position, Hourly Rate (`Rp 1.234,56`), Status badge (`Aktif` / `Nonaktif`), Action buttons                                                                                                                                                                                                     | • Click _+ Tambah Karyawan_ opens **Employee Form Sheet**<br>• Click _Import CSV_ opens **Employee CSV Import Sheet**<br>• Click row edit icon opens **Employee Form Sheet** (NPK locked)<br>• Click status toggle triggers inline confirmation to activate/deactivate<br>• Type in search bar triggers instant debounce filter | **1** (Sidebar > Master Data)                     |
| **Employee Form Sheet**                          | Slide-in Sheet (Right) inside `/admin/master-data`            | • Sheet title: _Tambah Karyawan Baru_ or _Edit Data Karyawan_<br>• NPK field (Text input for new, **locked read-only with lock icon** for edit)<br>• Full Name input<br>• Department selector (dropdown)<br>• Section selector (dynamically filtered by selected Dept)<br>• Job Position input<br>• Hourly Rate (Rp) input with helper text: _"Kosongkan untuk menggunakan rate default departemen"_<br>• Active toggle switch (`Status Aktif`)<br>• Action buttons: _Batal_ (Cancel), _Simpan Data_ (Save)                                                                                                              | • Changing Department instantly resets and repopulates Section dropdown<br>• Click _Simpan Data_ validates, closes drawer, and shows green toast                                                                                                                                                                                | **2** (Master Data > Add/Edit Employee)           |
| **Employee CSV Import Sheet**                    | Slide-in Sheet (Right) inside `/admin/master-data`            | • Drag-and-drop CSV upload zone<br>• Download Template link (_Unduh Format CSV_)<br>• Column requirements guide: `npk`, `full_name`, `department_code`, `section_code`, `job_position`, `hourly_rate`<br>• Pre-commit preview table (renders parsed rows; errors highlighted in red pill badges)<br>• Summary counters: _Total: X \| Valid: Y \| Error: Z_<br>• Primary button: _Konfirmasi & Import_ (disabled if error count > 0)                                                                                                                                                                                      | • File drop triggers immediate client-side parse & server dry-run<br>• Click _Konfirmasi & Import_ dispatches background job and displays success toast                                                                                                                                                                         | **2** (Master Data > Import CSV)                  |
| **Master Data Hub — Departments & Sections Tab** | `/admin/master-data?tab=departments`                          | • Hierarchical accordion/table of Departments<br>• Header action: _+ Tambah Departemen_<br>• Each Dept row shows: Code badge (`PROD`), Name, Cost Center, Default Rate (`Rp 25.000,00`), Employee count, Status badge, Expand/collapse caret<br>• Expanded row reveals nested child Sections table with Code, Name, Status, and action _+ Tambah Section_                                                                                                                                                                                                                                                                | • Click _+ Tambah Departemen_ opens **Department Dialog**<br>• Click _+ Tambah Section_ opens **Section Dialog**<br>• Click Edit on Dept/Section opens corresponding dialog<br>• Click Deactivate triggers confirmation dialog (blocked if active children exist)                                                               | **1** (Sidebar > Master Data > Tab 2)             |
| **Department / Section Form Dialog**             | Centered Modal (`Dialog`)                                     | • Modal title: _Tambah/Edit Departemen_ or _Tambah/Edit Section_<br>• Code input (**immutable after creation**, uppercase enforced)<br>• Name input<br>• Cost Center Code (for Dept)<br>• Default Hourly Rate in Rp (for Dept)<br>• Active toggle (`is_active`)<br>• Action buttons: _Batal_, _Simpan_                                                                                                                                                                                                                                                                                                                   | • Submit triggers instant validation and parent table refresh                                                                                                                                                                                                                                                                   | **2** (From Dept/Section tab)                     |
| **Master Data Hub — Operational Calendar Tab**   | `/admin/master-data?tab=calendar`                             | • Year & Month selector bar with quick navigation arrows (`<`, `>` buttons)<br>• Legend pills: `HKN (Hari Kerja Normal)` in slate/blue, `HLR (Hari Libur / Rest Day)` in red/rose<br>• Monthly 7-column calendar grid (Senin–Minggu)<br>• Each cell shows: Day number, Day Type badge, Holiday Name (if set)<br>• Header action: _Import Hari Libur Nasional (CSV)_                                                                                                                                                                                                                                                      | • Click any calendar day cell opens **Calendar Day Sheet**<br>• Click _Import Hari Libur Nasional_ opens CSV Modal<br>• Month selector smoothly updates grid via Inertia partial reload                                                                                                                                         | **1** (Sidebar > Master Data > Tab 3)             |
| **Calendar Day Inspector Sheet**                 | Slide-in Sheet (Right) inside `/admin/master-data`            | • Selected Date header (e.g., _Senin, 17 Agustus 2026_)<br>• Day Type Toggle: Segmented control between `HKN (Hari Kerja)` and `HLR (Hari Libur / Istirahat)`<br>• Holiday Name input (enabled only if HLR is selected)<br>• Description / Catatan input<br>• Plain-language notice: _"Perubahan kalender hanya berlaku untuk pengajuan lembur baru. Data lembur yang sudah masuk tidak akan terpengaruh."_<br>• Buttons: _Batal_, _Simpan Perubahan_                                                                                                                                                                    | • Toggling to HLR automatically shows the Holiday Name field<br>• Submit saves date override and updates cell color on the grid                                                                                                                                                                                                 | **2** (Master Data > Calendar > Click Day)        |
| **Administration Hub — User Accounts Tab**       | `/admin/administration?tab=users`                             | • Tab navigation header: _User Accounts_, _Policy Thresholds_<br>• Role filter pills (`Semua`, `Admin`, `Manager`, `Team Leader`, `User`)<br>• Search bar (Name, Email)<br>• Header button: _+ Tambah Akun Pengguna_<br>• Paginated table: Name, Email, Role badge (color-coded), Dept assignment, Last Login timestamp (WIB), Status toggle, Actions (Edit, Reset Password)                                                                                                                                                                                                                                             | • Click _+ Tambah Akun Pengguna_ opens **User Account Sheet**<br>• Click _Kirim Reset Password_ triggers instant confirmation dialog<br>• Click Edit opens **User Account Sheet**<br>• Self-account actions are protected (cannot deactivate self)                                                                              | **1** (Sidebar > Administration)                  |
| **User Account Form Sheet**                      | Slide-in Sheet (Right) inside `/admin/administration`         | • Sheet title: _Tambah / Edit Akun Pengguna_<br>• Name input<br>• Email input (must be unique)<br>• Role dropdown: `Admin`, `Manager`, `Team Leader`, `User / Operator`<br>• Department dropdown (optional, enables department-scoping)<br>• Password field (required on create, optional on edit)<br>• Status toggle (`Akun Aktif`)<br>• Action buttons: _Batal_, _Simpan Akun_                                                                                                                                                                                                                                         | • Role selection displays clear explanation of role permissions below dropdown<br>• Changing role logs audit entry automatically                                                                                                                                                                                                | **2** (Administration > Add/Edit User)            |
| **Administration Hub — Policy Thresholds Tab**   | `/admin/administration?tab=policies`                          | • Header banner: _Kebijakan Batas Lembur & Toleransi SPKL_<br>• **Plant-wide Default Card**: Weekly Soft Limit (`20.0 jam`), Consecutive Weeks Alert (`3 minggu`), SPKL Grace Period (`2 hari`), Burn Warning (`100%`), Burn Danger (`115%`), with _Edit Kebijakan Default_ button<br>• **Department Overrides Table**: Lists departments with custom overrides<br>• Action button: _+ Tambah Override Departemen_                                                                                                                                                                                                       | • Click _Edit Kebijakan Default_ opens **Policy Threshold Sheet**<br>• Click _+ Tambah Override Departemen_ opens **Policy Threshold Sheet** with Department selector                                                                                                                                                           | **1** (Sidebar > Administration > Tab 2)          |
| **Policy Threshold Form Sheet**                  | Slide-in Sheet (Right) inside `/admin/administration`         | • Sheet title: _Edit Kebijakan Default_ or _Override Kebijakan Departemen_<br>• Department selector (hidden/disabled for plant-wide default)<br>• Weekly Soft Limit (Hours, step 0.5, default: 20)<br>• Consecutive Weeks Alert (Weeks, default: 3)<br>• SPKL Grace Period (Days, default: 2)<br>• Burn Warning Percentage (%, default: 100)<br>• Burn Danger Percentage (%, default: 115)<br>• Plain-language explanation for each metric<br>• Buttons: _Batal_, _Simpan Kebijakan_                                                                                                                                     | • Live validation prevents negative numbers or illogical percentages (Danger < Warning)<br>• Save updates thresholds immediately                                                                                                                                                                                                | **2** (Administration > Edit Policy)              |
| **Overtime Budget Planning Hub**                 | `/budgets/planning`                                           | • Role-aware page title: _Perencanaan Anggaran Lembur (Overtime Budget Plan)_<br>• Filter bar: Fiscal Year selector (e.g. `2026`), Fiscal Month selector (e.g. `September`), Department dropdown (Admin sees all; Manager locked to own dept)<br>• Summary cards: Total Planned Hours for Month, Configured Sections vs Unconfigured Sections<br>• Table of Sections: Section Code, Section Name, Planned Monthly Hours, Week 1–5 Breakdown badges, Status badge (`Ditetapkan` in green / `Belum Ditetapkan` in amber), Action button (_Atur Anggaran_ / Edit Budget)<br>• Action button: _Import Budget CSV_            | • Click _Atur Anggaran_ on any row opens **Budget Breakdown Sheet**<br>• Click _Import Budget CSV_ opens **Budget CSV Import Sheet**<br>• Changing month/year reloads section table smoothly                                                                                                                                    | **1** (Sidebar > Budget Planning)                 |
| **Budget Breakdown Sheet**                       | Slide-in Sheet (Right) inside `/budgets/planning`             | • Header: Section Name & Fiscal Month (e.g., _Stamping Section — September 2026_)<br>• Input: `Planned Hours (Jam Bulanan)` e.g. `120`<br>• Checkbox toggle: _Atur Rincian 5 Minggu (Opsional)_<br>• Collapsible inputs for Week 1 to Week 5 planned hours (auto-distributed by default: `planned_hours / 4.3`)<br>• Real-time reactive calculator widget: Displays _Total Mingguan vs Target Bulanan_ with amber pill warning if mismatch occurs (_"Rincian mingguan berbeda dengan target bulanan, sistem akan tetap menyimpan sebagai estimasi"_)                                                                     | • Adjusting weekly inputs updates difference badge dynamically without page reload<br>• Click _Simpan Anggaran_ persists record via upsert                                                                                                                                                                                      | **2** (Budget Planning > Atur Anggaran)           |
| **User Preferences & Display Standards**         | `/settings/preferences` (using `layouts/settings/Layout.vue`) | • Settings sidebar navigation: _Profile_, _Security_, _Appearance_, _Preferences_<br>• **Theme Mode Selection**: Light / Dark / Auto (radio cards with preview icons)<br>• **Notification Channels**: Checkboxes for _Pengingat Dokumen SPKL Pending_, _Peringatan Batas Anggaran Lembur (Burn Alert)_, _Notifikasi Status Persetujuan Lembur_<br>• **Plant Standards Banner** (Read-Only Trust Signal): Informs user that Timezone is locked to `WIB (Asia/Jakarta)`, Date format is locked to `DD/MM/YYYY`, and Currency is formatted in `Rupiah (IDR)` per company standards<br>• Primary button: _Simpan Preferensi_ | • Toggling theme immediately applies CSS dark class without reload<br>• Click _Simpan Preferensi_ updates user JSON preferences and triggers toast                                                                                                                                                                              | **2** (User Avatar Menu > Settings > Preferences) |
| **Global Destructive Confirmation Dialog**       | Modal (`AlertDialog`)                                         | • Title: Clear, plain-language action title (e.g., _"Nonaktifkan Karyawan?"_ or _"Kirim Tautan Reset Password?"_)<br>• Description: Plain-language operational consequence (e.g., _"Karyawan ini tidak akan muncul pada daftar input lembur baru, namun seluruh riwayat lembur sebelumnya tetap tersimpan rapi."_)<br>• Actions: _Batal_ (Outline), _Ya, Lanjutkan_ (Destructive/Primary)                                                                                                                                                                                                                                | • Clicking confirmation triggers Wayfinder action and auto-closes                                                                                                                                                                                                                                                               | **1** (Triggered directly from table actions)     |

---

## 3. User Journey Maps

### Journey 1: Add a Single New Employee to Section Roster (Admin Daily/Weekly Task)

```
Goal: Register a newly hired line operator so their Team Leader can immediately submit overtime for them
Starts at: /admin/master-data?tab=employees (Master Data Hub)
Steps:
  1. Admin clicks the primary button "+ Tambah Karyawan" in the top-right toolbar.
  2. The Employee Form Sheet slides in from the right. Admin fills NPK (e.g. "20260901"), Full Name, selects Department ("Produksi"), and selects Section ("Stamping").
  3. Admin enters Job Position ("Operator Press") and leaves Hourly Rate blank (system automatically defaults to Department rate).
  4. Admin clicks "Simpan Data".
Done: Drawer slides closed, green toast notification confirms "Karyawan berhasil didaftarkan", and the new employee appears instantly at the top of the roster table.
Step count: 4
Status: ✅ OK (≤ 4 steps)
```

---

### Journey 2: Bulk Import Employee Roster via CSV with Live Pre-Commit Audit (Admin Onboarding)

```
Goal: Bulk register 150 production workers from an HR spreadsheet without manual data entry errors
Starts at: /admin/master-data?tab=employees
Steps:
  1. Admin clicks "Import CSV" button next to "+ Tambah Karyawan".
  2. The Employee CSV Import Sheet opens. Admin drags the prepared CSV file into the dropzone.
  3. System parses the file and presents a Pre-Commit Audit Table showing 150 parsed rows (148 valid green rows, 2 invalid red rows highlighting duplicate NPKs).
  4. Admin clicks "Perbaiki Baris Bermasalah" or chooses "Import 148 Data Valid", then clicks "Konfirmasi & Jalankan Import".
Done: Background job processes import; UI displays "148 Karyawan berhasil diimport", and the roster table updates immediately.
Step count: 4
Status: ✅ OK (≤ 4 steps)
```

---

### Journey 3: Create Department and Add a Nested Section (Admin Reorganization)

```
Goal: Standardize a legacy naming conflict by creating department "Produksi 1" and child section "TCF FS"
Starts at: /admin/master-data?tab=departments
Steps:
  1. Admin clicks "+ Tambah Departemen"; fills Code ("PROD1"), Name ("Produksi 1"), Cost Center ("CC-101"), Default Rate ("25000"), and clicks "Simpan".
  2. The new Department row appears in the hierarchy table. Admin clicks "+ Tambah Section" directly under the newly created "Produksi 1" row.
  3. The Section Dialog opens with Department pre-selected. Admin fills Section Code ("TCF-FS"), Name ("Trim & Chassis Final FS"), and clicks "Simpan".
Done: Section is nested under "Produksi 1" in the hierarchy tree, immediately available for employee assignments and budget setup.
Step count: 3
Status: ✅ OK (≤ 4 steps)
```

---

### Journey 4: Classify a National Holiday on the Operational Calendar (Admin Monthly Task)

```
Goal: Tag 17 August as a national holiday (HLR) so timesheets automatically calculate as holiday overtime
Starts at: /admin/master-data?tab=calendar
Steps:
  1. Admin navigates to the target month using the Month/Year bar and clicks on date "17".
  2. The Calendar Day Inspector Sheet slides in from the right.
  3. Admin toggles Day Type from "HKN (Hari Kerja)" to "HLR (Hari Libur / Istirahat)" and types "HUT Kemerdekaan RI" in Holiday Name.
  4. Admin clicks "Simpan Perubahan".
Done: Sheet closes, date cell turns rose/red with badge "HLR: HUT Kemerdekaan RI", and future overtime submissions on this date auto-classify as HLR.
Step count: 4
Status: ✅ OK (≤ 4 steps)
```

---

### Journey 5: Configure Monthly Overtime Budget for a Section (Manager / Admin Monthly Task)

```
Goal: Set the 120-hour overtime budget for Stamping Section for September 2026 so the Burn Index can calculate
Starts at: /budgets/planning (Budget Planning Hub)
Steps:
  1. Manager selects Fiscal Month "September" and Fiscal Year "2026" (Department is auto-selected for Manager).
  2. In the Section Budget table, Manager finds "Stamping Section" (status: "Belum Ditetapkan") and clicks "Atur Anggaran".
  3. The Budget Breakdown Sheet slides in. Manager enters Planned Hours ("120") and optionally adjusts the auto-filled 5-week breakdown.
  4. Manager clicks "Simpan Anggaran".
Done: Sheet closes, Stamping Section badge updates to "Ditetapkan (120 Jam)" in green, and Burn Index denominator is activated.
Step count: 4
Status: ✅ OK (≤ 4 steps)
```

---

### Journey 6: Adjust Safety Thresholds & SPKL Grace Period (Admin Compliance Task)

```
Goal: Increase plant-wide SPKL submission grace period from 2 days to 3 days per new factory union agreement
Starts at: /admin/administration?tab=policies
Steps:
  1. Admin views the "Kebijakan Default Pabrik" card and clicks "Edit Kebijakan Default".
  2. The Policy Threshold Sheet opens. Admin modifies "Toleransi Keterlambatan SPKL (Hari)" from 2 to 3 days.
  3. Admin reviews the plain-language warning: *"Perubahan ini berlaku untuk pengajuan lembur yang dibuat setelah tanggal simpan."*
  4. Admin clicks "Simpan Kebijakan".
Done: Toast displays "Kebijakan default berhasil diperbarui", and new overtime submissions enforce the 3-day grace period.
Step count: 4
Status: ✅ OK (≤ 4 steps)
```

---

### Journey 7: Provision a New User Account & Assign Role (Admin User Onboarding)

```
Goal: Create a system login for a new Team Leader in the Stamping Section
Starts at: /admin/administration?tab=users
Steps:
  1. Admin clicks "+ Tambah Akun Pengguna".
  2. The User Account Sheet opens. Admin inputs Name ("Agus Supriyanto"), Email ("agus.stamping@plant.local"), and Password.
  3. Admin selects Role: "Team Leader" and selects Department: "Produksi 1".
  4. Admin clicks "Simpan Akun".
Done: Account is created, role badge is color-coded green, and Agus can immediately log into the system with his credentials.
Step count: 4
Status: ✅ OK (≤ 4 steps)
```

---

### Journey 8: Toggle Dark Mode & Notification Preferences (Any User Personal Setting)

```
Goal: Switch UI to Dark Mode for night shift work and disable non-critical budget alerts
Starts at: Any screen while authenticated (/dashboard)
Steps:
  1. User clicks their profile avatar pill in the top header and selects "Settings".
  2. User selects the "Preferences" tab in the Settings sidebar.
  3. User clicks the "Dark Mode" tile (UI instantly switches theme without reload) and unchecks "Peringatan Batas Anggaran".
  4. User clicks "Simpan Preferensi".
Done: Green toast confirms "Preferensi berhasil disimpan", and preferences persist across future login sessions.
Step count: 4
Status: ✅ OK (≤ 4 steps)
```

---

## 4. Indonesian UX Guardrails

### 4.1 Patience Thresholds

Plant administrators, factory managers, and frontline supervisors work in high-stress, interrupt-driven environments. Administrative screens that require multi-stage wizards or obscure sub-menus cause high bounce rates and inaccurate record-keeping.

- **Daily Tasks (Search employee roster, check calendar day type)**: Maximum **2 steps**. Search must debounce automatically; calendar month switching must load smoothly without whole-page refresh.
- **Occasional Tasks (Add/edit employee, configure monthly budget, provision user)**: Maximum **3–4 steps**. All forms must reside in side drawers (`Sheet`) so users never lose context of the main list.
- **Bulk Onboarding Tasks (CSV import of 200+ employees or holidays)**: Maximum **4 steps** with an explicit two-stage safety net (Stage 1: Drag-and-drop & Instant In-Memory Audit; Stage 2: Confirm Commit).

---

### 4.2 Cognitive Load Budget

- **Visible Actions per Screen**: Maximum **3 primary actions** in any table toolbar:
    - _Primary action_ (e.g. `+ Tambah Karyawan`) in solid primary color.
    - _Secondary action_ (e.g. `Import CSV`) in outline button style.
    - _Filter/Search bar_ in muted input style.
- **Form Length & Progressive Disclosure**:
    - Maximum **4 fields visible without scrolling**.
    - On the Employee Form: Hourly Rate is explicitly marked optional with the helper text _"Kosongkan untuk menggunakan rate standar departemen"_.
    - On the Overtime Budget Form: The 5-week breakdown is collapsed behind a clean toggle _"Atur Rincian 5 Minggu (Opsional)"_. If left uncollapsed, the system automatically distributes `planned_hours / 4.3`.
- **Immutable Identifier Lock Signals**:
    - In accordance with Business Rule BR-03, **NPK is strictly immutable** once created.
    - When editing an employee, the NPK field is rendered disabled with a lock icon, a muted background, and a clear tooltip: _"NPK terkunci permanen sesuai regulasi sistem (BR-03)"_. This eliminates user confusion about why they cannot edit an NPK.
- **Strictly No Nested Modals**:
    - Never open a modal on top of a modal. If an action in a modal requires selecting another entity (e.g., adding a Section from within a Department form), use an inline expandable accordion or dismiss the first modal cleanly.

---

### 4.3 Trust Signals & Plant Communication Standards

- **Plain-Language Destructive Action Confirmations**:
    - Deactivating an employee, department, or user account must never display cold technical dialogs like `"Are you sure you want to set is_active=false?"`.
    - Use respectful, plain Indonesian plant terminology:
        - _Title_: `"Nonaktifkan Karyawan [Nama Karyawan]?"`
        - _Body_: `"Karyawan ini tidak akan dapat dipilih pada pengajuan lembur baru oleh Team Leader. Seluruh data historis lembur dan laporan terdahulu tetap tersimpan aman."`
        - _Buttons_: `"Batal"` (outline) and `"Ya, Nonaktifkan"` (warning/destructive).
- **Proactive Integrity Protection (Zero Raw SQL Exceptions)**:
    - When an admin attempts to deactivate or delete a Department that has active linked Employees or existing Overtime Submissions, the UI must intercept this gracefully.
    - The Delete button is disabled with an explanatory badge: _"Tidak dapat dihapus karena masih memiliki 24 karyawan terdaftar. Silakan pindahkan karyawan terlebih dahulu."_
- **Two-Stage CSV Import Transparency**:
    - Bulk CSV imports must never commit blind writes. The Pre-Commit Audit table must explicitly display valid rows in green pills and invalid rows (e.g. invalid Department code or duplicate NPK) in red pills with exact line numbers:
        - _"Baris 14: NPK '2024019' sudah terdaftar pada sistem."_
        - _"Baris 28: Kode Seksi 'ASSEM-3' tidak ditemukan pada Departemen Produksi."_
- **Localized Plant Data Standards**:
    - **Currency**: Always displayed as Rupiah with proper Indonesian thousand separators: `Rp 25.000,00` or `Rp 1.500.000`.
    - **Date Format**: Standardized as `DD/MM/YYYY` (e.g. `17/08/2026`).
    - **Timezone**: Explicitly labeled as `WIB` (`Asia/Jakarta`) on all audit timestamps and calendar references.

---

### 4.4 Epic E02 Specific Risks & Mitigations

| Sub-Epic / Feature                      | Identified UX Risk                                                                                                                  | Mandatory Design Mitigation                                                                                                                                                                                                                   |
| --------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **E02-01 (Hierarchy CRUD)**             | Accidental deletion of a Department with existing historical timesheets, which would cause foreign key crashes or orphaned records. | Deletion of linked Departments/Sections is strictly blocked. The UI hides the delete button and only exposes a soft "Nonaktifkan" toggle with plain-language confirmation.                                                                    |
| **E02-02 (Employee NPK)**               | User tries to edit an employee's NPK after a typo, causing confusion when the field fails to save.                                  | NPK field is explicitly rendered as read-only with a lock icon and tooltip explanation on all Edit screens.                                                                                                                                   |
| **E02-02 (Cascading Section Select)**   | User selects Section A, then changes Department to Dept B where Section A does not belong, creating orphaned relationships.         | Reactive Vue `watch()` on `department_id` immediately clears the `section_id` field and refreshes the section dropdown with only the child sections of the newly selected department.                                                         |
| **E02-03 (Calendar Day Toggle)**        | Admin toggles a past date from HKN to HLR, fearing that past submitted timesheets will be recalculated and corrupt payroll records. | Display prominent informational banner: _"Perubahan status kalender hanya berlaku untuk pengajuan baru. Data lembur terdahulu tidak akan diubah secara retroaktif."_                                                                          |
| **E02-04 (Policy Fallback Confusion)**  | Admin modifies plant-wide defaults but wonders why Department X does not reflect the change.                                        | In the Policy Thresholds overview, clearly tag each department row as either `Menggunakan Standar Pabrik (Inherited)` or `Kustom / Override Aktif`.                                                                                           |
| **E02-05 (Admin Self-Lockout)**         | An Admin accidentally deactivates or changes the role of their own logged-in account, locking themselves out of the system.         | The User Accounts table disables the status toggle and delete button on the row belonging to `auth()->user()->id`, with a tooltip: _"Anda tidak dapat menonaktifkan akun sendiri."_                                                           |
| **E02-07 (Budget 5-Week Sum Mismatch)** | Sum of weekly planned hours does not match the total monthly planned hours, creating mathematical confusion.                        | Real-time reactive calculator widget dynamically compares `sum(week1..week5)` to `planned_hours`. Displays an amber warning pill if they diverge, but does not block saving (as plant weekly breakdowns are often non-linear approximations). |

---

## 5. Implementation Boundaries (Anti-Splitting Rules)

These rules are strict constraints for the implementing engineer or AI subagents. Any deviation will introduce administrative bloat and break the unified UX model.

### 5.1 Do Not Split — Combine Into One Surface:

- **E02-01 (Departments & Sections), E02-02 (Employees), and E02-03 (Operational Calendar)**:
    - **MUST** live together inside `/admin/master-data` as tabs (`?tab=employees`, `?tab=departments`, `?tab=calendar`).
    - **Do NOT** create separate top-level pages like `/admin/departments`, `/admin/sections`, `/admin/employees`, or `/admin/calendar`.
- **E02-04 (Policy Thresholds) and E02-05 (User Account Management)**:
    - **MUST** live together inside `/admin/administration` as tabs (`?tab=users`, `?tab=policies`).
    - **Do NOT** create isolated sidebar routes for `/admin/users` and `/admin/policy-thresholds`.
- **Department and Section Hierarchy**:
    - Sections **MUST** be displayed nested directly inside their parent Department accordion/table.
    - **Do NOT** create a separate disconnected "Section List" page.

---

### 5.2 Make a Tab, Not a New Route:

- Use URL query parameters for tab navigation to preserve shareability and browser history without multiplying routes:
    - Master Data Hub: `/admin/master-data?tab=employees`, `/admin/master-data?tab=departments`, `/admin/master-data?tab=calendar`.
    - Administration Hub: `/admin/administration?tab=users`, `/admin/administration?tab=policies`.
    - User Settings: `/settings/preferences` inside the existing `resources/js/layouts/settings/Layout.vue` shell alongside `profile`, `security`, and `appearance`.

---

### 5.3 Make a Drawer/Sheet, Not a Full Page:

The following forms must be built as slide-in side panels (`Sheet` from right) or centered dialogs (`Dialog`), never full pages:

- **`EmployeeFormSheet.vue`**: Create and edit employee records.
- **`EmployeeImportSheet.vue`**: CSV drag-and-drop, preview table, and pre-commit audit.
- **`DepartmentFormDialog.vue` & `SectionFormDialog.vue`**: Create and edit departments and sections.
- **`CalendarDaySheet.vue`**: View and edit calendar day type and holiday descriptions.
- **`UserFormSheet.vue`**: Provision and edit user accounts and role assignments.
- **`PolicyThresholdSheet.vue`**: Edit plant defaults and department threshold overrides.
- **`BudgetFormSheet.vue`**: Set monthly budget and 5-week breakdown.
- **`BudgetImportSheet.vue`**: Bulk CSV import for annual/monthly budgets.

---

### 5.4 Backend-Only — Strictly No Dedicated UI:

The following components are pure background, CLI, or API logic. **Do NOT create dedicated navigation items, pages, or menus for them**:

- **Calendar Auto-Seeding**: Handled automatically on initial deployment and via CLI `php artisan app:seed-calendar {year}`.
- **Calendar Classification API**: `GET /api/calendar/{date}` is a headless JSON endpoint for the timesheet submission form in Epic E03.
- **CSV Ingestion Workers**: `ImportEmployeesFromCsvJob` executes in the background via Redis queues.
- **Policy Inheritance Engine**: `PolicyThresholdService::getForDepartment()` fallback logic runs entirely in the service layer.
- **Role Change Audit Logging**: Automatically recorded in the audit trail without requiring a dedicated audit-entry screen.
- **Formatters & Composable**: `formatRupiah()`, `formatDate()`, and `useTheme()` are reusable frontend TypeScript utilities.

---

### 5.5 Strictly Forbidden:

1. **Do NOT use legacy Ziggy `route()` helper**: Always use typed **Laravel Wayfinder** functions imported from `@/routes` or `@/actions`.
2. **Do NOT allow writable NPK inputs on Employee edit screens**: Once created, NPK is strictly locked (BR-03).
3. **Do NOT create more than 3 sidebar items for Epic E02**:
    - Admin gets: `Master Data`, `Administration`, `Budget Planning`.
    - Manager gets: `Budget Planning`.
    - Team Leader and User get: `0`.
4. **Do NOT use nested modals**: Maximum modal or sheet depth is strictly **1 layer**.
5. **Do NOT crash on CSV import errors**: Never perform unvalidated bulk database writes. Always run an in-memory pre-commit dry run and display clean, row-by-row error badges.
6. **Do NOT expose raw database error messages or HTTP status codes** (e.g., `SQLSTATE[23000]: Integrity constraint violation`, `403 Forbidden`) to users. All backend errors must be caught and returned as friendly, actionable Indonesian/English feedback.
