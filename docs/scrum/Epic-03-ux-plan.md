# UX Plan — E03: Daily Overtime Entry & SPKL Workflow

> Created before implementation. This document is a hard constraint for all work in Epic E03.
> Epic file: `docs/scrum/Epic-03.md`

---

## 1. Navigation Footprint

### 1.1 Executive Philosophy: High-Speed, Zero-Friction Shift Handovers

Epic E03 represents the operational heartbeat of the Overtime & CapEx Labor Management System (OT-CapEx System). Every single workday at shift handovers (07:00, 15:00, and 23:00 WIB), between 10 and 50 frontline Team Leaders (_Mandor_ / _Pengawas Lapangan_) across 35 factory sections simultaneously log into the application to submit overtime hours for their crews (10 to 30 workers per section). During this 30-minute burst, up to 1,500 line items are submitted.

The primary users are Indonesian factory Team Leaders. Their characteristics are:

- **Low patience & time pressure**: They submit timesheets while physically exhausted at the end of an 8-hour shift, wearing industrial PPE, or rushing to catch company transport.
- **Mid-level digital literacy**: Intimately familiar with WhatsApp and simple mobile forms, but frustrated by multi-step enterprise wizards, slow loading times, or lost form entries.
- **Shopfloor devices**: Accessing the system via smartphones, line-side rugged tablets, or shared terminal PCs.

The core UX principles for Epic E03 are:

1. **Single-Screen Batch Rapid Entry**: The entire timesheet submission must be completed on **one unified screen** with zero wizard steps.
2. **Form State Preservation (Zero Data Loss)**: If an atomic validation error occurs, the form must NEVER be cleared. The specific row and field are highlighted with plain-language guidance.
3. **Non-Blocking SPKL (BR-05)**: Shift operations cannot be delayed by physical paperwork. Overtime hours are submitted immediately; paper or photo SPKL documents follow post-shift without blocking workers or payroll deadlines.
4. **Instant Visual Feedback**: Immediate confirmation with generated submission codes (`OT-YYYYMMDD-SECT-NNN`), real-time section budget burn indicators, and advisory policy soft limit badges.

---

### 1.2 Sub-Epic Functional Separation

| Sub-Epic / Story                                                 | Category                                  | User-Facing Surface                                                                                                     | Dedicated UI Needed?                                       |
| ---------------------------------------------------------------- | ----------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| **E03-01**: Daily Overtime Submission Form (Team Leader)         | **User-Facing**                           | Daily Timesheet Form (`/overtime/submissions/create`), Section Burn Indicator, Active Roster Grid, Row Calculators      | ✅ Yes                                                     |
| **E03-02**: Immutable Financial Cost Snapshotting                | **Backend Logic & Data Integrity**        | Estimated Cost Summary Pill (`Rp 1.234.567`) in timesheet header, row previews, and detail modals                       | ✅ Yes (Inline cost display; snapshot engine is headless)  |
| **E03-03**: Submission Status & History View (Team Leader)       | **User-Facing**                           | Submission History Hub (`/overtime/submissions`), Status Filters, Submission Detail Modal (`SubmissionDetailModal.vue`) | ✅ Yes                                                     |
| **E03-04**: SPKL Flexible Post-Shift Attachment                  | **User-Facing**                           | SPKL Upload Drawer (`SpklUploadSheet.vue`), Camera/File Dropzone, Overdue Warning Badge                                 | ✅ Yes (Slide-in Sheet)                                    |
| **E03-05**: SPKL Pending Reminder Job (Automated)                | **Background Worker & User-Facing Alert** | Topbar Notification Bell (`NotificationBell.vue`), Popover Notification List, `SendSpklReminderJob` worker (08:00 WIB)  | ✅ Yes (Topbar bell + popover; cron is headless)           |
| **E03-06**: Overtime Entry Form — Policy Soft Warning Indicators | **User-Facing & Advisory Engine**         | Real-time advisory badges on employee rows: Yellow (`⚠️ Weekly limit may be exceeded`) & Red (`🔴 High Workload`)       | ✅ Yes (Inline row badges; evaluation service is headless) |

---

### 1.3 Minimal Navigation Footprint Decisions

#### Sidebar Navigation Impact:

- **For `Team Leader` Role**:
    - Adds **1 primary sidebar item**:
        - **Overtime** (`/overtime/submissions/create` or `/overtime/submissions`) — Labeled as **Input Lembur** (Overtime).
        - Landing page defaults to the **Daily Timesheet Form** (`/overtime/submissions/create`) because daily submission is the Team Leader's primary responsibility.
        - Quick-switch tab bar at the top of the screen provides instant 1-click toggling between **Form Input Lembur** (`/overtime/submissions/create`) and **Riwayat Pengajuan** (`/overtime/submissions`).
- **For `Manager` Role**:
    - Adds **0 additional sidebar items** in Epic E03. (Managers monitor submissions and verify SPKL documents through the approval workflow in Epic E04 and the Budget Planning Hub from Epic E02).
- **For `Admin` Role**:
    - Adds **1 sidebar item**:
        - **Overtime Submissions** (`/overtime/submissions`) — Full plant-wide historical visibility.
- **For `User / Operator` Role**:
    - Adds **0 sidebar items** in Epic E03. (Personal individual timesheets are introduced in Epic E06).

#### Distinct Routes & Pages:

Across the entire 47-story-point epic, exactly **2 primary routes** are created:

1. `/overtime/submissions/create` — The High-Speed Timesheet Entry Page (`Overtime/Create.vue`).
2. `/overtime/submissions` — The Submission History & Status Tracking Page (`Overtime/Index.vue`).

#### Surfaces Handled via Drawer/Sheet or Modal (Never Dedicated Routes):

- **Submission Detail Inspector**: Centered Modal Dialog (`SubmissionDetailModal.vue`) opened from the history table, preserving scroll position.
- **SPKL Document Upload & Attachment**: Slide-in Right Drawer (`SpklUploadSheet.vue`), accessible directly from the post-submission success card, history table rows, or notification popover.
- **Notification Bell & Popover**: Topbar Dropdown Popover (`NotificationBell.vue` & `NotificationPopover.vue`) anchored next to the live WIB clock.
- **Policy Warning Explanations**: Inline Popover / Tooltip anchored to the yellow/red warning pill on the employee row.

#### Pure Backend & Headless Logic (No UI):

- `SubmitOvertimeAction`: Atomic database transaction wrapping header creation, roster integrity checks, row insertion, and rollback on error.
- `bcmul()` Precision Engine: Precise IDR financial snapshotting (`hourly_rate_snapshot`, `total_cost_snapshot`) and immutability guard.
- `SendSpklReminderJob`: Automated daily scheduled job running at 08:00 WIB via `DispatchSpklRemindersCommand`.
- `RunAnomalyDetectionJob`: Asynchronous Redis queue job dispatched per item for downstream ML evaluation.
- `Storage::disk('spkl-private')`: Private disk storage driver and temporary signed URL generator (`Storage::temporaryUrl()`).
- `OvertimePolicyEvaluator`: Service computing 7-day rolling hours and consecutive weekly workload breaches.

---

## 2. Screen Inventory

| Screen / Panel                         | Location                                               | What's visible on first open                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 | Further triggers                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | Click depth                                                               |
| -------------------------------------- | ------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| **Daily Overtime Timesheet Form**      | `/overtime/submissions/create` (`Overtime/Create.vue`) | • **Top Navigation Switcher**: Tabs for _Form Input Lembur_ (active) and _Riwayat Pengajuan_<br>• **Header Metadata Bar**:<br> - Date Picker (defaults to today `now('Asia/Jakarta')`)<br> - Day Type Badge (auto-detected via `GET /api/calendar/{date}`: `📅 HKN (Hari Kerja Normal)` in slate or `🔴 HLR (Hari Libur)` in rose)<br> - Day Type Override Switch (_Ubah ke HKN/HLR_)<br> - Department & Section (auto-locked to user's assigned section)<br> - Section Monthly Burn Indicator bar: `Section Budget: 85/200 jam (42.5%)`<br>• **Roster Toolbar**: Search worker, `+ Tambah Karyawan`, and `Pilih Semua (Add All)` button<br>• **Timesheet Table**:<br> - Worker card/row: Monospace NPK, Full Name, Production hours, TPM hours, Project hours (CapEx), Other hours, Total hours (auto-sum), Row remove icon (trash)<br>• **Footer Summary Bar**:<br> - Total Crew Count, Total Batch Hours, Estimated Total Cost (`Rp`), Batch Notes field, Primary action button: `Kirim Pengajuan Lembur` | • Changing date fetches calendar classification and updates day type badge<br>• Toggling day type override flags `day_type` manually<br>• Clicking `Pilih Semua` adds all active section workers into the table<br>• Typing in `hours_project > 0` immediately reveals CapEx Project dropdown on that row<br>• Typing hours triggers live auto-sum and debounced policy check (surfaces yellow/red badge if limit exceeded)<br>• Submitting with validation error highlights invalid row/field with red outline and error message (no data wipe)<br>• Submitting with success displays **Post-Submission Success Card** | **0** (Default landing for Team Leader) or **1** (Sidebar > Input Lembur) |
| **Post-Submission Success Card**       | Inline Banner (top of `/overtime/submissions/create`)  | • Celebratory green card: _Pengajuan Lembur Berhasil Dikirim!_<br>• Monospace Submission Code: `OT-20260907-STAMP-001`<br>• Summary counters: Total Crew (e.g. `14 Orang`), Total Hours (e.g. `42.0 Jam`), Total Estimated Cost (`Rp 1.050.000`)<br>• SPKL Status alert: `📎 SPKL: Belum Dilampirkan (Jatuh tempo: 09/09/2026 - 2 hari tersisa)`<br>• Two clear call-to-action buttons:<br> 1. Primary: _Lampirkan SPKL Sekarang_ (opens SPKL Upload Sheet)<br> 2. Secondary: _Input Lembur Baru_ (resets form for next shift)<br>• Link: _Lihat di Riwayat Pengajuan_                                                                                                                                                                                                                                                                                                                                                                                                                                       | • Clicking _Lampirkan SPKL Sekarang_ opens **SPKL Upload Sheet** immediately<br>• Clicking _Input Lembur Baru_ dismisses banner and resets form<br>• Clicking _Lihat di Riwayat Pengajuan_ navigates to `/overtime/submissions`                                                                                                                                                                                                                                                                                                                                                                                         | **0** (Appears automatically post-submission)                             |
| **Submission History & Tracking Hub**  | `/overtime/submissions` (`Overtime/Index.vue`)         | • **Top Navigation Switcher**: Tabs for _Form Input Lembur_ and _Riwayat Pengajuan_ (active)<br>• **Filter Toolbar**:<br> - Date range picker (defaults to current month)<br> - Status filter pills: `Semua`, `🟡 Menunggu Review`, `🟠 Disetujui Sebagian`, `🟢 Disetujui`, `🔴 Ditolak`<br> - SPKL filter: `Semua`, `Belum Dilampirkan`, `Terlampir`, `Terverifikasi`, `⚠️ Terlambat`<br>• **Submissions Table** (20 rows per page):<br> - Submission Code (monospace pill)<br> - Operational Date & Day Type badge (`HKN` / `HLR`)<br> - Section Name<br> - Submitter Name (NPK)<br> - Total Hours (e.g. `38.5 jam`)<br> - Est. Cost snapshot (`Rp 962.500`)<br> - Submission Status Badge<br> - SPKL Status Badge with countdown/due date<br> - Action buttons: _Detail_, _Lampirkan SPKL_, _Edit_ (visible only if status is `SUBMITTED`)                                                                                                                                                               | • Click row or _Detail_ button opens **Submission Detail Modal**<br>• Click _Lampirkan SPKL_ opens **SPKL Upload Sheet**<br>• Click _Edit_ re-opens Timesheet Form in edit mode with existing data pre-populated<br>• Changing filters reloads table smoothly via Inertia partial reload                                                                                                                                                                                                                                                                                                                                | **1** (Sidebar > Riwayat Pengajuan or Top Switcher)                       |
| **Submission Detail Modal**            | Centered Modal Dialog (`SubmissionDetailModal.vue`)    | • Modal Header: Submission Code (`OT-20260907-STAMP-001`), Section, Operational Date, Day Type pill, Submission Status pill<br>• Submitter & timestamp: Submitted by Agus S. on 07/09/2026 at 15:15 WIB<br>• Batch Notes (if provided)<br>• Detailed Line Items Table: Employee NPK, Full Name, Production hrs, TPM hrs, CapEx Project & hrs, Others hrs, Total hrs, Rate Snapshot (`Rp/jam`), Cost Snapshot (`Rp`), Policy advisory badge<br>• Summary Footer: Total Line Items, Total Hours (`42.0 jam`), Total Cost (`Rp 1.050.000`), SPKL Status<br>• Modal Actions: _Unduh SPKL_ (if attached), _Lampirkan SPKL_ (if pending), _Tutup_                                                                                                                                                                                                                                                                                                                                                                  | • Clicking _Lampirkan SPKL_ opens **SPKL Upload Sheet**<br>• Clicking _Unduh SPKL_ triggers secure download via signed URL<br>• Clicking _Tutup_ or 'X' closes modal without changing page state                                                                                                                                                                                                                                                                                                                                                                                                                        | **2** (History List > Click Detail)                                       |
| **SPKL Upload & Attachment Sheet**     | Slide-in Sheet (Right) (`SpklUploadSheet.vue`)         | • Sheet Title: _Lampirkan Dokumen SPKL_<br>• Linked Submission pill: `OT-20260907-STAMP-001` (Section, Date, Total Hours)<br>• SPKL Due Date countdown banner: _Batas Pengunggahan: 09/09/2026 (2 hari tersisa)_ (or red banner: _⚠️ SPKL Terlambat: Melewati batas 2 hari_)<br>• File Upload Dropzone (Supports PDF, JPEG, PNG, max 3 MB; Camera capture on mobile)<br>• Text Input: _Nomor Dokumen Fisik SPKL_ (e.g., `SPKL/PROD/2026/IX/089`)<br>• Informational note: _"Pengunggahan dokumen SPKL bersifat fleksibel dan tidak menghambat persetujuan jam lembur awal. Pastikan tanda tangan fisik supervisor terlihat jelas pada foto/scan."_<br>• Action Buttons: _Batal_, _Unggah & Simpan SPKL_                                                                                                                                                                                                                                                                                                      | • Dragging file or selecting phone camera photo shows file preview, name, and size<br>• Submitting validates file format/size and updates status from `PENDING` to `ATTACHED`<br>• Closes sheet and triggers green toast: _"Dokumen SPKL berhasil dilampirkan"_                                                                                                                                                                                                                                                                                                                                                         | **1** (From Success Card or History Row)                                  |
| **Topbar Notification Bell & Popover** | Topbar Dropdown Popover (`NotificationBell.vue`)       | • Bell Icon in top navigation with red badge counter (e.g., `2`)<br>• Dropdown List of unread notifications:<br> - Overdue SPKL: `⚠️ SPKL Terlambat (OT-20260905-STAMP-002: Lewat 1 hari)` with button _Lampirkan_<br> - Pre-due SPKL: `⏰ Pengingat: SPKL Jatuh Tempo Besok`<br> - Approval Updates: `🟢 Pengajuan OT-20260906-ASSY1-001 Disetujui Manajer`<br>• Header link: _Tandai Semua Sudah Dibaca_                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | • Clicking _Lampirkan_ on an SPKL item directly opens **SPKL Upload Sheet** for that record<br>• Clicking _Tandai Dibaca_ calls `PATCH /notifications/{id}/read` and updates badge count                                                                                                                                                                                                                                                                                                                                                                                                                                | **1** (Click bell icon on topbar from any screen)                         |

---

## 3. User Journey Maps

### Journey 1: Shift-End Daily Overtime Submission (Team Leader Core Daily Task)

```
Goal: Submit 8-hour shift overtime hours for 15 production workers within 3 minutes before leaving the plant
Starts at: /overtime/submissions/create (Daily Overtime Form)
Steps:
  1. Team Leader opens the application. Form defaults to today's date, assigned section ("Stamping"), and displays day classification: "📅 HKN (Hari Kerja Normal)".
  2. Team Leader clicks "Pilih Semua (Add All)" — 15 active operators are instantly populated into the timesheet table.
  3. Team Leader enters hours for each operator using the keyboard Tab key (e.g., 2.5 hrs Production; for 2 mechanics: 2.0 hrs TPM).
  4. Team Leader glances at footer summary ("Total: 15 Orang | 37.5 Jam | Est. Cost: Rp 937.500") and clicks "Kirim Pengajuan Lembur".
Done: System executes atomic transaction, displays green success toast, and shows the Post-Submission Success Card with submission code "OT-20260907-STAMP-001" and non-blocking SPKL reminder.
Step count: 4
Status: ✅ OK (≤ 4 steps)
```

---

### Journey 2: Overtime Entry with CapEx Project Attribution & Policy Soft Limit Alert

```
Goal: Submit overtime for 3 toolmakers performing CapEx line installation while acknowledging a policy limit warning
Starts at: /overtime/submissions/create
Steps:
  1. Team Leader enters 3.0 hours under "Project (CapEx)" for toolmaker Budi.
  2. The "CapEx Project" dropdown immediately expands on Budi's row. Team Leader selects "Line Automation Stamping Phase 2".
  3. A yellow badge appears on the row: "⚠️ Weekly limit may be exceeded (22.5/20 jam)". Team Leader hovers/taps to verify the advisory notice (system confirms submission is NOT blocked per BR-06).
  4. Team Leader clicks "Kirim Pengajuan Lembur".
Done: Submission succeeds atomically; CapEx project attribution is locked; financial snapshots are generated; advisory limit flag is logged for managerial review.
Step count: 4
Status: ✅ OK (≤ 4 steps)
```

---

### Journey 3: Zero Data Loss Recovery upon Atomic Validation Failure

```
Goal: Correct a validation error (one worker has 0.0 total hours) without losing the other 19 workers' inputs
Starts at: /overtime/submissions/create (after clicking Submit)
Steps:
  1. Backend transaction validates items and rejects submission (Worker #8 has 0.0 hours, violating BR-01: minimum 0.5 hours).
  2. The page retains all 20 entered rows without refreshing or wiping any fields.
  3. Worker #8's row is highlighted with a red border and an error badge: "Total jam lembur minimal 0.5 jam sesuai aturan (BR-01)".
  4. Team Leader either inputs "1.0" or clicks the trash icon on Worker #8 to remove them from the batch, then clicks "Kirim Pengajuan Lembur".
Done: Form resubmits successfully; green toast confirms creation; zero time lost retyping data.
Step count: 4
Status: ✅ OK (≤ 4 steps)
```

---

### Journey 4: Post-Shift SPKL Photo Attachment via Mobile Smartphone

```
Goal: Upload a smartphone photo of the signed physical SPKL document the morning after shift completion
Starts at: /overtime/submissions (Submission History) or via Topbar Notification
Steps:
  1. Team Leader taps "Lampirkan SPKL" on the pending submission row (badge shows: "📎 SPKL: Belum Dilampirkan - 1 hari tersisa").
  2. The SPKL Upload Sheet slides in from the right.
  3. Team Leader taps the upload dropzone, selects "Take Photo" on smartphone camera, captures the signed paper document, and types physical reference: "SPKL/STAMP/09/014".
  4. Team Leader taps "Unggah & Simpan SPKL".
Done: File is saved to private disk, status transitions immediately to "ATTACHED", sheet closes, and history badge updates to green "📎 SPKL: Terlampir".
Step count: 4
Status: ✅ OK (≤ 4 steps)
```

---

### Journey 5: Resolve Overdue SPKL Reminder from Topbar Notification

```
Goal: Clear an overdue SPKL reminder received upon logging in
Starts at: Any screen while authenticated (/dashboard or /overtime/submissions/create)
Steps:
  1. Team Leader notices red badge "1" on the topbar bell icon and clicks it.
  2. Notification popover reveals: "⚠️ SPKL Terlambat: OT-20260905-STAMP-002 telah melewati batas waktu 2 hari".
  3. Team Leader clicks "Lampirkan Sekarang" directly on the notification card.
  4. SPKL Upload Sheet opens pre-targeted to that submission. Team Leader uploads the PDF scan and clicks "Unggah".
Done: SPKL status updates to "ATTACHED", notification is automatically marked as resolved, and badge count decrements to 0.
Step count: 4
Status: ✅ OK (≤ 4 steps)
```

---

### Journey 6: Edit a Pending Submission Prior to Manager Review

```
Goal: Revise entered hours for a worker before the Department Manager opens morning approvals
Starts at: /overtime/submissions (Submission History)
Steps:
  1. Team Leader locates the submission (status: "🟡 Menunggu Review") and clicks "Edit Pengajuan".
  2. The timesheet form re-opens with all original rows, categories, and CapEx projects pre-populated.
  3. Team Leader modifies hours for the target worker (total batch hours recalculate live).
  4. Team Leader clicks "Simpan Perubahan".
Done: Submission record is updated atomically; financial snapshots are recalculated; green toast confirms "Perubahan pengajuan lembur berhasil disimpan".
Step count: 4
Status: ✅ OK (≤ 4 steps)
```

_Note on Edit Guarding:_ If the submission has already been touched by a manager (`APPROVED` or `PARTIALLY_APPROVED`), the "Edit" button is replaced by a disabled lock pill with tooltip: _"Pengajuan sudah diproses oleh Manajer dan terkunci permanen."_

---

## 4. Indonesian UX Guardrails

### 4.1 Patience Thresholds

Frontline manufacturing staff operate under high stress, tight shift handover schedules, and physical fatigue. A slow, tedious form will lead to abandoned submissions, unrecorded shifts, or off-system paper workarounds.

- **Shift Handover Daily Entry (Daily Burst Task)**: Maximum **3–4 steps** total. Must include:
    - Rapid keyboard navigation: Tabbing between numeric inputs (`Prod` → `TPM` → `Proj` → `Others` → next employee).
    - Bulk inclusion: `Pilih Semua (Add All)` button to populate the entire section crew with a single tap.
    - Zero popups or confirmation dialogues during normal valid entry. A single tap on `Kirim Pengajuan Lembur` submits the form.
- **Post-Shift Document Attachment (Daily / Occasional Task)**: Maximum **3–4 steps**. Smartphone camera integration must work natively via HTML5 file input (`accept="image/*,application/pdf"`).
- **Advisory Policy Limit Notices (BR-06)**: Advisory limits MUST NEVER show blocking alert modals. They must appear as non-intrusive inline badges that allow the supervisor to proceed immediately without extra clicks.

---

### 4.2 Cognitive Load Budget

- **Visible Actions per Row**: Maximum **1 primary delete icon** per row. Hour fields must be clean numeric steppers (`step="0.5"`, `min="0"`).
- **Progressive Disclosure for CapEx & Notes**:
    - The CapEx Project selector is **100% hidden** until the supervisor enters a value `> 0` in `hours_project`. Only then does the dropdown animate into view with active projects.
    - RCA Category and detailed task descriptions are collapsed behind a small `+ Catatan / RCA` link per row, preventing vertical clutter for standard production shifts.
- **Form Summary & Budget Context**:
    - Header displays a compact, live progress bar: `Anggaran Seksi: 85 / 200 Jam (42.5%)`. If cumulative hours exceed 85%, the bar shifts from green to amber; if exceeding 100%, to red. This provides immediate plant context without requiring navigation to a separate budget page.
- **Strictly No Nested Modals**:
    - Modals and sheets are strictly limited to **1 layer**. The SPKL upload panel is a slide-in side sheet (`Sheet`), never an overlay on top of another dialog.

---

### 4.3 Trust Signals & Plant Communication Standards

- **Plain-Language Indonesian Plant Terminology**:
    - `HKN` → `Hari Kerja Normal` (Regular Workday)
    - `HLR` → `Hari Libur / Istirahat` (Holiday / Rest Day)
    - `SPKL` → `Surat Perintah Kerja Lembur` (Formal Overtime Order Document)
    - `CapEx` → `Proyek Modal / Investasi`
    - `NPK` → `Nomor Pokok Karyawan` (Primary employee badge number)
- **Zero Raw Error Codes or Technical Jargon**:
    - Never display raw backend exceptions like `SQLSTATE[23000]`, `422 Unprocessable Entity`, or `Integrity constraint violation`.
    - All errors must be translated into clear, respectful plant instructions:
        - _Technical_: `chk_overtime_min_hours violated` → _User-facing_: `Total jam lembur untuk [Nama Karyawan] minimal 0.5 jam (BR-01).`
        - _Technical_: `chk_capex_attribution failed` → _User-facing_: `Jam lembur proyek CapEx memerlukan pemilihan Proyek Investasi yang aktif (BR-08).`
        - _Technical_: `Roster integrity mismatch` → _User-facing_: `Satu atau lebih karyawan tidak terdaftar aktif pada Seksi ini.`
- **Permanent Submission Code Generation**:
    - Upon submission, prominently display the system-generated code: `OT-20260907-STAMP-001`. Team Leaders write this exact code on physical whiteboards and physical paper SPKL forms during shift handovers.
- **Financial Cost Snapshot Transparency**:
    - Estimated costs must be displayed in Indonesian Rupiah with standard thousand separators: `Rp 1.050.000` (via `formatRupiah()`).
    - Read-only helper tooltip: _"Estimasi biaya dihitung otomatis menggunakan tarif standar karyawan saat pengajuan (Snapshot Biaya Terkunci)."_
- **Timezone Standardization**:
    - All shift timestamps and submission deadlines are explicitly labeled in Western Indonesia Time (`WIB` / `Asia/Jakarta`).

---

### 4.4 Epic E03 Specific Risks & Mitigations

| Sub-Epic / Feature                           | Identified UX Risk                                                                                                                                              | Mandatory Design Mitigation                                                                                                                                                                                     |
| -------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **E03-01 (Atomic Submission Failure)**       | If 1 out of 25 workers has a data typo, the entire batch rolls back. If the page refreshes, the supervisor loses 24 valid entries, causing extreme frustration. | Frontend retains all 25 rows in Vue reactive state. The erroneous row is highlighted with a red pulse outline and an inline error badge. Valid rows remain untouched and ready for resubmission.                |
| **E03-01 (Shift Handover Traffic Burst)**    | Up to 50 Team Leaders submit at 07:00, 15:00, 23:00 WIB simultaneously, potentially causing UI freezing or multiple accidental submissions.                     | The submit button is immediately disabled upon click, displaying a spinner: _"Menyimpan Pengajuan Lembur..."_. The backend uses append-only row inserts with no database lock contention.                       |
| **E03-01 (Wrong Calendar Day Type)**         | Team Leader submits on a Saturday that was designated as an exchange working day (HKN), but system defaults to weekend (HLR), skewing payroll rates.            | System auto-resolves day type via `GET /api/calendar/{date}`, but displays an explicit override switch (_"Ubah ke HKN/HLR"_) directly next to the date badge, saving the manual choice to `day_type`.           |
| **E03-01 (CapEx Project Attribution Error)** | Supervisor enters project hours but forgets to assign the project, causing a backend SQL constraint crash (BR-08).                                              | The CapEx Project selector is made conditionally required on the frontend the moment `hours_project > 0`. The submit button is prevented from sending until a project is chosen, with an inline amber reminder. |
| **E03-04 (Blocking SPKL Fallacy)**           | Team Leaders assume they cannot submit overtime until the physical SPKL document is signed and scanned, delaying digital shift handover.                        | A clear banner on the form reminds users: _"SPKL bersifat fleksibel. Kirim jam lembur sekarang, dokumen fisik dapat difoto dan dilampirkan dalam 2 hari kerja (BR-05)."_                                        |
| **E03-04 (Large Camera Photo Uploads)**      | High-resolution phone camera photos (8–15 MB) fail server upload or exhaust mobile data.                                                                        | Client-side file validation strictly enforces `max: 3 MB` with helpful feedback: _"Ukuran file maksimal 3 MB. Silakan gunakan format PDF atau kompresi foto."_                                                  |
| **E03-05 (Intrusive Overdue Spam)**          | Automated daily SPKL reminders flood the user with modal dialogs, blocking daily shift entry.                                                                   | Reminders are delivered strictly via in-app topbar notification bell badges and banner pills. They NEVER block or lock the supervisor out of entering new daily shifts.                                         |
| **E03-06 (Policy Limit Anxiety)**            | Supervisors mistake the soft weekly warning (20 hrs) for a hard error and believe the system rejected their worker.                                             | Warning badges use an advisory yellow color with explicit copy: _"Batas mingguan terlampaui (22/20 jam) — Bersifat informasi, pengajuan tetap dapat diproses (BR-06)."_                                         |

---

## 5. Implementation Boundaries (Anti-Splitting Rules)

These rules are strict architectural constraints for the implementing engineer or AI subagents. Any deviation will violate the zero-friction shift handover requirements.

### 5.1 Do Not Split — Combine Into One Surface:

- **Daily Timesheet Form (`/overtime/submissions/create`)**:
    - Header metadata (Date, Section, Day Type, Section Burn Indicator), Roster selection toolbar, and all employee hour rows **MUST live on a single page**.
    - **Do NOT** split overtime submission into a multi-step wizard (e.g., Step 1: Select Date → Step 2: Pick Employees → Step 3: Input Hours → Step 4: Review).
- **Four Work Categories on a Single Row**:
    - `hours_production`, `hours_tpm`, `hours_project`, and `hours_others` **MUST be entered on the same employee table row**.
    - **Do NOT** create separate tabs or sub-pages for different overtime types (e.g., do NOT create `/overtime/production` and `/overtime/capex`).
- **Unified Overtime Navigation Switcher**:
    - The Timesheet Form (`/overtime/submissions/create`) and Submission History (`/overtime/submissions`) **MUST share a common top-level tab switcher** (`[Form Input Lembur] [Riwayat Pengajuan]`) for instant 1-click toggling.

---

### 5.2 Make a Tab, Not a New Route:

- In the Submission History Hub (`/overtime/submissions`), filter views (e.g., `Semua`, `Menunggu Review`, `Disetujui`, `Ditolak`) must be handled via URL query parameters (`?status=SUBMITTED`) and tab pills, **NOT separate routes** like `/overtime/submissions/pending` or `/overtime/submissions/approved`.

---

### 5.3 Make a Drawer/Sheet, Not a Full Page:

The following user interactions must be built as slide-in side panels (`Sheet` from right) or centered modal dialogs (`Dialog`), never full pages:

- **`SpklUploadSheet.vue`**: Uploading SPKL document scans, entering physical SPKL document reference numbers, and viewing upload countdown deadlines.
- **`SubmissionDetailModal.vue`**: Inspecting line-item hours, CapEx projects, hourly rates, and snapshot costs of an existing submission.
- **`NotificationPopover.vue`**: Viewing unread SPKL reminders and approval alerts from the topbar bell icon.

---

### 5.4 Backend-Only — Strictly No Dedicated UI:

The following components and services are pure background, CLI, or API logic. **Do NOT create dedicated navigation items, pages, or menus for them**:

- **`SubmitOvertimeAction`**: Atomic database transaction engine.
- **`hourly_rate_snapshot` & `total_cost_snapshot` Generation**: Automatically executed inside `SubmitOvertimeAction` using `bcmul()` IDR arithmetic.
- **`SendSpklReminderJob` & `DispatchSpklRemindersCommand`**: Scheduled CLI cron job running daily at 08:00 WIB.
- **`RunAnomalyDetectionJob`**: Asynchronous Redis queue worker dispatched after item creation.
- **`Storage::disk('spkl-private')`**: Storage configuration and temporary signed URL generation (`Storage::temporaryUrl()`).
- **`OvertimePolicyEvaluator`**: Service class computing rolling weekly hour totals and consecutive alert rules.

---

### 5.5 Strictly Forbidden:

1. **Do NOT use legacy Ziggy `route()` helper**: Always use typed **Laravel Wayfinder** functions imported from `@/routes` or `@/actions`.
2. **Do NOT block overtime submissions due to missing SPKL documents**: Enforce Business Rule BR-05 strictly. SPKL documents are non-blocking; the submission succeeds immediately with SPKL status set to `PENDING`.
3. **Do NOT wipe or reset the timesheet form on validation error**: If an atomic validation error occurs, preserve all rows in reactive state and visually highlight the exact invalid row and field.
4. **Do NOT block form submission on soft policy limit warnings**: Weekly soft limits (20 hours) and consecutive high-workload alerts are advisory only (BR-06). They must never disable the submit button.
5. **Do NOT permit editing of approved submissions**: Once a submission status reaches `APPROVED` or `PARTIALLY_APPROVED`, editing is strictly forbidden. The UI must replace the edit button with a locked status indicator.
6. **Do NOT use nested modals**: Maximum modal or sheet depth is strictly **1 layer**.
7. **Do NOT expose raw database error messages or technical HTTP exceptions**: Wrap all backend errors in friendly, actionable Indonesian plant terminology.
8. **Do NOT create separate sidebar items for different overtime categories or SPKL uploads**: The only sidebar item added for Team Leaders in Epic E03 is **Input Lembur** (`Overtime`).
