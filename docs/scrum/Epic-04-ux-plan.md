# UX Plan — E04: Verification & Approval Lifecycle

> Created before implementation. This document is a hard constraint for all work in Epic E04.
> Epic file: `docs/scrum/Epic-04.md`

---

## 1. Navigation Footprint

### 1.1 Executive Philosophy: Morning Standup Velocity & Granular Integrity

Epic E04 represents the operational decision-making gateway of the Overtime & CapEx Labor Management System (OT-CapEx System). Every morning between 07:15 and 08:30 WIB, Department Managers (_Kepala Departemen_), Section Managers (_Kepala Seksi_), and Plant Administrators review overtime submissions from the previous day's three production shifts (Shift 1: 07:00–15:00, Shift 2: 15:00–23:00, Shift 3: 23:00–07:00). During morning standup meetings, approvers face heavy time pressure to clear up to 40 submissions containing over 800 employee line items before production schedules lock in.

Primary users are Indonesian manufacturing managers and section heads. Their operational realities:

- **High Time Pressure & Low Patience**: Approvals occur in short 10-to-15 minute standup windows before daily line startup. If an approval workflow requires navigating through nested pages or clicking 20 times per submission, managers will either blindly "rubber-stamp" entire batches without review or abandon the digital tool for manual spreadsheets.
- **Granular Authority Requirement (BR-10)**: Approvers cannot be forced into an "all-or-nothing" batch decision. A manager must be able to approve 18 compliant line workers while rejecting 2 workers whose CapEx project allocations or overtime hours are questionable, without stalling the entire submission.
- **Mid-Level Digital Literacy**: Comfortable with intuitive mobile interfaces (WhatsApp, banking apps) but resistant to overly complex enterprise ERP grids. They expect immediate visual cues, clear financial figures in Indonesian Rupiah (`Rp`), and obvious safety guards.
- **Multi-Reviewer Concurrency**: In large departments, multiple managers or assistant managers may open the queue at the same time. The interface must handle concurrent reviews gracefully through optimistic locking (`lock_version`) without data corruption or technical error screens.

The core UX principles for Epic E04 are:

1. **Single-Queue Centralization**: All pending reviews, filtering, quick inspections, and bulk actions live on **exactly one primary page**.
2. **Item-Level Independence in a Focused Dialog**: Granular line-item decisions happen inside a high-density, single-layer modal that keeps approvers focused on worker names, hour breakdowns, CapEx allocations, and costs.
3. **Mandatory Documented Rejection Reason**: No silent rejections. Any rejected item requires a clear, plain-language explanation to maintain audit compliance and provide constructive feedback to frontline Team Leaders.
4. **Immediate Concurrency & State Feedback**: If another manager acts on a record first, the system presents a friendly resolution banner and refreshes smoothly, never throwing raw `409 Conflict` errors.
5. **Non-Blocking SPKL Visibility**: Physical SPKL documents are flagged clearly (`PENDING`, `ATTACHED`, or `OVERDUE`), but missing paperwork never artificially locks a manager out from approving valid operational hours.

---

### 1.2 Sub-Epic Functional Separation

| Sub-Epic / Story                                                | Category                            | User-Facing Surface                                                                                                                                | Dedicated UI Needed?                                                        |
| --------------------------------------------------------------- | ----------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| **E04-01**: Pending Approval Queue (Manager/Admin)              | **User-Facing**                     | Approval Queue Page (`/overtime/approvals`), Filter Bar, Status Badges, Expandable Row Summary (`SubmissionQueueRow.vue`)                          | ✅ Yes                                                                      |
| **E04-02**: Item-Level Approval/Rejection Modal (Manager/Admin) | **User-Facing**                     | Itemized Review Modal (`ApprovalModal.vue`), Employee Row Decision Controls (`ApprovalItemRow.vue`), Rejection Reason Input                        | ✅ Yes                                                                      |
| **E04-03**: Bulk Approval & Rejection (Manager/Admin)           | **User-Facing**                     | Multi-Select Checkboxes, Floating Bulk Action Bar, Confirmation Modal (`BulkApprovalConfirmModal.vue`), Result Toast (`BulkActionResultToast.vue`) | ✅ Yes                                                                      |
| **E04-04**: Export to CSV/Excel (Manager/Admin)                 | **User-Facing & Backend Streaming** | Export Trigger Button (`ExportButton.vue`) on Queue Toolbar, Streamed CSV/XLSX Download                                                            | ✅ Yes (Toolbar action; streaming engine is headless)                       |
| **E04-05**: Immutable Audit Trail (Full Lifecycle)              | **User-Facing & Data Integrity**    | Audit Trail Slide-in Drawer (`AuditTrailDrawer.vue`), State Diff Viewer, Insert-Only Audit Ledger                                                  | ✅ Yes (Slide-in Drawer; ledger engine is headless)                         |
| **E04-06**: Modification Lock on Approved Records               | **System Enforcement & UI State**   | Visual Lock Indicators on Team Leader Views, Disabled Edit Controls, Admin Force-Unlock Modal (`ForceUnlockModal.vue`)                             | ✅ Yes (In-place locked state + Admin dialog; lock enforcement is headless) |

---

### 1.3 Minimal Navigation Footprint Decisions

#### Sidebar Navigation Additions:

- **For `Manager` Role**:
    - Adds **1 primary sidebar item**:
        - **Persetujuan Lembur** (Overtime Approvals) — Route: `/overtime/approvals`.
        - Includes a dynamic numeric badge showing the current count of pending submissions awaiting review for their department (e.g., `🔴 6`).
- **For `Admin` Role**:
    - Shares the **1 sidebar item**:
        - **Persetujuan Lembur** (Overtime Approvals) — Route: `/overtime/approvals`.
        - Displays plant-wide pending counts and unrestricted multi-department filtering.
- **For `Team Leader` Role**:
    - Adds **0 sidebar items**. (Team Leaders submit and monitor status via existing `/overtime/submissions` routes from Epic E03. Approved records automatically display lock icons and disabled action buttons).
- **For `User / Operator` Role**:
    - Adds **0 sidebar items**. (Operators view personal logs via Epic E06).

#### Distinct Routes & Pages:

Across the entire 38-story-point epic, exactly **1 primary route** is created:

1. `/overtime/approvals` — The Consolidated Approval Queue Hub (`resources/js/Pages/Overtime/ApprovalQueue.vue`).

#### Surfaces Handled via Modal, Drawer, or Dialog (Never Dedicated Routes):

- **Item-Level Approval Modal**: High-density centered modal (`ApprovalModal.vue`) opened when clicking "Review" on any submission row.
- **Bulk Action Confirmation Dialog**: Centered modal (`BulkApprovalConfirmModal.vue`) confirming multi-submission batch processing.
- **Item Audit Trail Drawer**: Slide-in Right Drawer (`AuditTrailDrawer.vue`) displaying the chronological history of state changes, actor NPKs, timestamps, and JSON diffs.
- **Admin Force-Unlock Dialog**: Centered modal (`ForceUnlockModal.vue`) requiring a documented reason before unlocking an approved submission.
- **Export Trigger**: Direct button with format selector dropdown (`ExportButton.vue`) initiating a streamed download without leaving the page.

#### Pure Backend & Headless Logic (No UI):

- `ApproveOvertimeItemsAction`: Atomic database transaction enforcing pessimistic row locking (`lockForUpdate()`), optimistic version verification (`lock_version`), state transitions, and audit logging.
- `BulkApproveSubmissionsAction`: Batch orchestrator iterating submissions with per-submission transaction isolation and error collection.
- `RecalculateMonthlyBurnSnapshotJob`: Asynchronous queued job recalculating department/section cumulative overtime hours and budget burn.
- `OvertimeItemAudit` Synchronous Ledger: Tamper-proof, insert-only logging triggered during state changes.
- `OvertimeExport` Engine: Server-side streaming export leveraging Laravel `LazyCollection` and `maatwebsite/excel`.
- Controller Gate & Immutability Enforcement: HTTP 422 block on modifying submissions containing approved items.

---

## 2. Screen Inventory

| Screen / Panel                               | Location                                                     | What's visible on first open                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | Further triggers                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         | Click depth                                                                  |
| -------------------------------------------- | ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| **Approval Queue Workspace**                 | `/overtime/approvals` (`ApprovalQueue.vue`)                  | • **Page Header**: Title ("Persetujuan Lembur"), Live WIB Clock, Department badge, Pending Count pill (`6 Pengajuan Menunggu Review`)<br>• **Filter Toolbar**:<br> - Status filter tabs: `Semua`, `Menunggu Review (Submitted)`, `Disetujui Sebagian (Partially Approved)`<br> - Section selector dropdown (auto-scoped to Manager's dept)<br> - Date range picker (defaults to last 7 days)<br> - SPKL Status filter (`Semua`, `Belum Dilampirkan`, `Terlampir`, `⚠️ Terlambat`)<br> - Sort dropdown: Date (descending default), Section, Total Hours<br>• **Batch Actions Toolbar** (appears when rows are checked):<br> - Selection counter: `X Pengajuan Dipilih`<br> - Button: `Setujui Terpilih (Bulk Approve)`<br> - Button: `Tolak Terpilih (Bulk Reject)`<br>• **Submissions Table** (20 rows/page, server-side):<br> - Checkbox per row + "Select All on Page" in header<br> - Submission Code (`OT-YYYYMMDD-SECT-NNN`)<br> - Operational Date & Day Type pill (`HKN` slate / `HLR` rose)<br> - Section & Submitter Team Leader name<br> - Total Hours (`38.5 jam`) & Headcount (`15 Org`)<br> - Total Estimated Cost snapshot (`Rp 962.500`)<br> - SPKL Status Badge (`Terlampir`, `Pending`, `⚠️ Terlambat`)<br> - ML Anomaly Flag (if detected: `🤖 1 anomali flagged`)<br> - Status Badge (`SUBMITTED`, `PARTIALLY_APPROVED`)<br> - Quick Expand Toggle (`▼`) & Primary Action: `Review (Tinjau)`<br>• **Header Action**: `Export (Unduh CSV/Excel)` | • Clicking row checkbox reveals Floating Bulk Action Bar<br>• Clicking "Select All on Page" toggles all visible rows<br>• Clicking Quick Expand Toggle (`▼`) reveals inline employee breakdown table<br>• Clicking `Review (Tinjau)` opens **Item-Level Approval Modal**<br>• Clicking `Bulk Approve` or `Bulk Reject` opens **Bulk Action Confirmation Modal**<br>• Clicking `Export` opens format selector and starts download<br>• Changing filters reloads queue seamlessly via Inertia partial reload                                                                                               | **1** (Sidebar > Persetujuan Lembur)                                         |
| **Inline Row Summary Panel**                 | Expandable drawer under table row (`SubmissionQueueRow.vue`) | • Compact list of all employees in that submission<br>• For each worker: Monospace NPK, Full Name, Production hrs, TPM hrs, CapEx hrs (with project tag), Others hrs, Total hrs, Cost snapshot (`Rp`)<br>• Compact policy/anomaly warning tags (if applicable)<br>• Direct shortcut link: `Buka Review Lengkap →`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  | • Clicking `Buka Review Lengkap →` opens **Item-Level Approval Modal**<br>• Clicking `▲` collapses the inline summary                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | **1** (Click expand toggle on any queue row)                                 |
| **Item-Level Approval Modal**                | Centered Modal Dialog (`ApprovalModal.vue`)                  | • **Modal Header**:<br> - Submission Code: `OT-20260907-STAMP-001`<br> - Section, Date, Day Type (`HKN`), Submitting Team Leader<br> - Section Monthly Burn Progress Bar: `Anggaran Seksi: 85 / 200 Jam (42.5%)`<br> - SPKL Status Badge with clickable link to view document (if attached)<br>• **Batch Decision Controls**:<br> - `Setujui Semua (Approve All)` button (green outline)<br> - `Tolak Semua (Reject All)` button (red outline)<br>• **Itemized Employee Table** (scrollable):<br> - Worker Card/Row: NPK, Full Name, Current Status badge<br> - Hours breakdown: Prod, TPM, CapEx (with linked Project Name), Others<br> - Total Hours + Cost Snapshot (`Rp 75.000`)<br> - RCA Category & Notes (if entered)<br> - Machine/Task description<br> - Policy Warning pills (e.g., `⚠️ Batas mingguan terlampaui`)<br> - ML Anomaly Alert banner (if flagged: `🤖 Anomali: Jam lembur 2x rata-rata historis`)<br> - Item Decision Segmented Control: `✅ Setuju` / `❌ Tolak` / `⚪ Pending`<br> - Rejection Reason text field (appears only when `❌ Tolak` is selected)<br> - Audit Trail button (`🕒 Riwayat`) per row<br>• **Modal Footer**:<br> - Decision Summary Counter: `14 Disetujui, 1 Ditolak, 0 Pending`<br> - Approved Total Cost preview (`Rp 1.050.000`)<br> - Secondary button: `Batal (Cancel)`<br> - Primary button: `Simpan Keputusan (Save Decisions)`                                                                             | • Clicking `Setujui Semua` sets all items to Approved<br>• Clicking `Tolak Semua` sets all items to Rejected and exposes a shared rejection note input<br>• Selecting `❌ Tolak` on an individual row reveals a mandatory text input for that worker's rejection reason<br>• Clicking `🕒 Riwayat` opens **Item Audit Trail Drawer** for that specific employee item<br>• Clicking `Simpan Keputusan` submits decisions via `POST /overtime/submissions/{id}/approve-items`<br>• If an optimistic lock conflict occurs (`409 Conflict`), displays friendly conflict banner with `Muat Ulang Data` button | **2** (Queue > Click Review)                                                 |
| **Bulk Action Confirmation Modal**           | Centered Modal Dialog (`BulkApprovalConfirmModal.vue`)       | • Modal Title: `Konfirmasi Persetujuan Massal` or `Konfirmasi Penolakan Massal`<br>• Scope Summary: `Anda akan memproses [X] pengajuan ([Y] total jam karyawan) secara serentak.`<br>• List of selected submissions (Submission code, Section, Total Hours, Item Count)<br>• If Bulk Reject: Mandatory text area: `Alasan Penolakan Massal` with placeholder: `Contoh: Target shift terpenuhi, lembur tidak dialokasikan`<br>• Plain-language warning: `Aksi ini akan mencatat riwayat audit resmi untuk setiap item karyawan.`<br>• Action Buttons: `Batal` (Cancel) and `Konfirmasi & Proses` (Confirm & Execute)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | • Entering rejection reason (if rejecting) enables confirm button<br>• Clicking `Konfirmasi & Proses` calls `POST /overtime/approvals/bulk`<br>• On completion, modal closes and triggers **Bulk Action Result Toast**                                                                                                                                                                                                                                                                                                                                                                                   | **2** (Queue > Select Rows > Click Bulk Action)                              |
| **Bulk Action Result Toast**                 | Floating Toast Banner (`BulkActionResultToast.vue`)          | • Status Icon (Green checkmark or Amber warning)<br>• Clear bilingual summary: `Persetujuan Massal Selesai`<br>• Counter breakdown: `✓ 38 item berhasil disetujui` • `⚠️ 7 item dilewati (konflik data / sudah diproses)`<br>• If skips occurred: Link to view detailed conflict log                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               | • Auto-dismisses after 6 seconds<br>• Click 'X' closes toast immediately                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 | **0** (Appears automatically after bulk processing)                          |
| **Export Configuration Dropdown**            | Popover Menu anchored to Export button (`ExportButton.vue`)  | • Option 1: `Unduh Format CSV (.csv)` — Best for raw data and ERP imports<br>• Option 2: `Unduh Format Excel (.xlsx)` — Best for formatted finance and management reports<br>• Filter summary reminder: `Sesuai filter aktif saat ini: [Departemen], [Bulan/Rentang Tanggal]`<br>• Direct streaming download trigger                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               | • Clicking either option initiates server-side streaming download<br>• Export button shows active loading spinner: `Menyiapkan berkas...`<br>• Audit event logged silently in background                                                                                                                                                                                                                                                                                                                                                                                                                 | **2** (Queue > Click Export > Select format)                                 |
| **Item Audit Trail Drawer**                  | Slide-in Sheet (Right) (`AuditTrailDrawer.vue`)              | • **Drawer Header**: Title (`Riwayat Perubahan Item`), Employee Name, NPK, Submission Code<br>• Current Status pill (`APPROVED`, `REJECTED`, or `PENDING`)<br>• **Chronological Audit Timeline** (Newest first):<br> - Timestamp in WIB (`08/09/2026, 08:15:30 WIB`)<br> - Actor Name, NPK, and Role badge (`Budi Santoso (NPK 1042) - Manager`)<br> - Action Badge: `SUBMITTED`, `APPROVED`, `REJECTED`, `ADMIN_UNLOCK`, `EXPORT`<br> - Plain-language summary note (e.g., Rejection reason or approval note)<br> - Visual State Diff: Previous State vs New State (changed fields highlighted in amber/green)<br> - IP Address and system metadata (collapsible)<br>• Plain-language notice: `Catatan audit bersifat permanen dan tidak dapat diubah (Immutable Ledger).`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | • Clicking close button (X) or backdrop dismisses drawer, returning cleanly to Approval Modal or Queue                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | **3** (Queue > Review Modal > Click Riwayat) or **2** (via direct item link) |
| **Admin Force-Unlock Confirmation Modal**    | Centered Modal Dialog (`ForceUnlockModal.vue`)               | • Accessible **only to Admin role**<br>• Modal Title: `⚠️ Buka Kunci Pengajuan (Admin Override)`<br>• Submission Code & Current Status (`APPROVED` / `PARTIALLY_APPROVED`)<br>• Consequence warning in plain Indonesian: `Membuka kunci akan mengembalikan status seluruh item pengajuan ini ke MENUNGGU REVIEW (SUBMITTED) sehingga dapat diperbaiki oleh Team Leader. Seluruh rekaman persetujuan sebelumnya akan dicatat ulang.`<br>• Mandatory text field: `Alasan Pembukaan Kunci (Wajib diisi untuk audit HR & Finance)`<br>• Action Buttons: `Batal` (Cancel), `Buka Kunci Pengajuan` (Unlock Submission)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | • Entering mandatory reason enables submit button<br>• Submitting sends `PATCH /overtime/submissions/{id}/unlock`<br>• Closes modal, refreshes table, and shows audit-logged confirmation toast                                                                                                                                                                                                                                                                                                                                                                                                          | **2** (Queue > Admin click "Buka Kunci")                                     |
| **Locked State on Team Leader History View** | `/overtime/submissions` (`Overtime/Index.vue`)               | • Submission row with `status = APPROVED` or `PARTIALLY_APPROVED`<br>• The standard "Edit" button is replaced by a disabled lock pill: `🔒 Terkunci (Disetujui)`<br>• Hover tooltip: `Pengajuan telah disetujui oleh Manajer dan tidak dapat diubah lagi (BR-10). Hubungi Admin jika memerlukan revisi.`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | • Clicking the row opens read-only Submission Detail Modal<br>• Edit actions are disabled at both frontend and backend API layers                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | **1** (Team Leader views history)                                            |

---

## 3. User Journey Maps

### Journey 1: Morning Standup Routine Approval (Item-Level Review & Decision)

```
Goal: Review a shift overtime submission for 15 operators and approve all items within 2 minutes during morning standup
Starts at: /overtime/approvals (Pending Approval Queue)
Steps:
  1. Manager opens the Approval Queue. Table shows 6 pending submissions auto-scoped to their department.
  2. Manager locates the top submission (e.g., "OT-20260907-STAMP-001", Stamping, 15 workers, 37.5 hrs, SPKL: Terlampir) and clicks "Review".
  3. Item-Level Approval Modal opens. Manager scans employee names, hours (all standard Production/TPM), and glances at section monthly burn bar (42.5% — well within budget).
  4. Manager clicks "Setujui Semua (Approve All)" — all 15 toggles switch to green "Setuju", then clicks "Simpan Keputusan".
Done: Submission status transitions to APPROVED, modal closes, queue row updates reactively, and a green toast confirms: "Persetujuan lembur OT-20260907-STAMP-001 berhasil disimpan (15 item disetujui)".
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

### Journey 2: Fast Bulk Approval of Clean Submissions (High-Volume Routine)

```
Goal: Bulk-approve 4 clean submissions (no anomalies, SPKL attached, within budget) in a single action
Starts at: /overtime/approvals (Pending Approval Queue)
Steps:
  1. Manager reviews queue rows and checks the checkboxes for 4 clean submissions (or clicks "Pilih Semua").
  2. Floating Bulk Action Bar appears at the bottom of the screen displaying: "4 Pengajuan Dipilih (58 Karyawan, 142.0 Jam)".
  3. Manager clicks "Setujui Terpilih (Bulk Approve)".
  4. Confirmation dialog appears: "Anda akan menyetujui 58 item karyawan di 4 pengajuan. Lanjutkan?". Manager clicks "Konfirmasi & Setujui".
Done: Bulk action executes atomically; dialog closes; queue refreshes; floating toast confirms: "Persetujuan massal selesai: 58/58 item berhasil disetujui".
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

### Journey 3: Granular Rejection of an Invalid / Miscategorized Line Item

```
Goal: Approve 14 production workers but reject 1 worker who was miscategorized without an approved CapEx project
Starts at: /overtime/approvals (Pending Approval Queue)
Steps:
  1. Manager clicks "Review" on submission "OT-20260907-ASSY1-002".
  2. Approval Modal opens. Manager clicks "Setujui Semua" to set all 15 items to Approved as a baseline.
  3. Manager scrolls to worker #12 (Budi, Toolmaker), who has 4.0 hrs under Project (CapEx) but no valid project code linked. Manager clicks "❌ Tolak" on Budi's row.
  4. A mandatory text box immediately expands under Budi's row: "Alasan Penolakan". Manager types: "Kategori CapEx tidak valid. Alihkan ke jam lembur maintenance rutin (TPM) dan ajukan ulang.", then clicks "Simpan Keputusan".
Done: Atomic transaction approves 14 items, rejects 1 item with documented reason, transitions submission status to PARTIALLY_APPROVED, closes modal, and notifies Team Leader.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

### Journey 4: Concurrency Conflict Resolution under Optimistic Locking

```
Goal: Handle a situation where Assistant Manager approved a submission while Manager was reviewing the same record
Starts at: /overtime/approvals (inside Approval Modal)
Steps:
  1. Manager reviews line items in Approval Modal and clicks "Simpan Keputusan".
  2. The server detects lock_version mismatch (HTTP 409 Conflict) because the Assistant Manager submitted decisions 10 seconds earlier.
  3. Instead of a raw error screen or losing input, the modal displays a clear amber banner: "⚠️ Pengajuan ini telah diperbarui oleh reviewer lain (Siti Rahma - Asst. Manager) beberapa saat yang lalu. Keputusan Anda belum tersimpan."
  4. Manager clicks "Muat Ulang Data Terbaru (Reload)". Modal refreshes with current database state, showing the updated statuses.
Done: Concurrency conflict resolved gracefully with zero data corruption; manager understands what happened and reviews the updated state.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

### Journey 5: Investigating State Changes via Immutable Item Audit Trail

```
Goal: Admin or Auditor traces who approved an overtime item and when a revision occurred during a payroll dispute
Starts at: /overtime/approvals or from Submission Detail Modal
Steps:
  1. Admin clicks "Review" on the disputed submission and locates the employee line item.
  2. Admin clicks the "🕒 Riwayat (Audit Trail)" icon button on that employee row.
  3. The Item Audit Trail Drawer slides in from the right, displaying a vertical timeline of every state change:
     - 07/09/2026 15:30 WIB: SUBMITTED by Agus Supriatna (Team Leader)
     - 08/09/2026 08:14 WIB: APPROVED by Budi Santoso (Manager)
  4. Admin clicks "Lihat Detail Perubahan" on the approval step to inspect the exact snapshot diff (status: SUBMITTED → APPROVED, lock_version: 1 → 2).
Done: Complete tamper-proof chronological history verified with actor NPK, exact WIB timestamp, and IP address.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

### Journey 6: Filtered Overtime Data Export for Payroll & Finance

```
Goal: Export approved overtime records for Department Stamping for August 2026 to CSV for payroll calculation
Starts at: /overtime/approvals (Pending Approval Queue)
Steps:
  1. Manager/Admin selects filters on the queue toolbar: Department: "Stamping", Date Range: "01/08/2026 - 31/08/2026", Status: "Disetujui (Approved)".
  2. Manager clicks the "Export (Unduh Data)" button on the toolbar.
  3. A concise dropdown appears with two options: "Unduh CSV (.csv)" and "Unduh Excel (.xlsx)". Manager clicks "Unduh CSV (.csv)".
  4. Button shows a loading spinner ("Menyiapkan berkas..."). The server streams the CSV file directly to the browser.
Done: Browser initiates download of `overtime-export-stamping-2026-08.csv` containing all 19 standardized columns; export event is logged in the audit trail.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

### Journey 7: Admin Force-Unlock of an Approved Record for Shift Correction

```
Goal: Plant Admin unlocks an approved submission so Team Leader can correct an erroneous operator assignment
Starts at: /overtime/approvals (Queue filtered by Status: Disetujui)
Steps:
  1. Admin locates the approved submission and clicks the "Buka Kunci (Force Unlock)" button (visible only to Admin role).
  2. The Force-Unlock Confirmation Dialog appears, warning that all items will revert to PENDING and require re-approval.
  3. Admin types mandatory explanation: "Koreksi NPK operator yang salah catat atas memo HR No. 124/HR/IX/2026".
  4. Admin clicks "Konfirmasi Buka Kunci".
Done: Submission status resets to SUBMITTED, all item statuses revert to PENDING, immutable audit record `ADMIN_UNLOCK` is written, and Team Leader can now edit the submission.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

## 4. Indonesian UX Guardrails

### 4.1 Patience Thresholds

Indonesian manufacturing managers have extremely limited time for administrative software during production hours. The user experience must be tuned for rapid, high-confidence decision making:

- **Daily Morning Standup Review (Core Daily Task)**: Maximum **2–3 clicks** to review and approve a standard submission:
    - `Review` → `Setujui Semua` → `Simpan Keputusan`.
    - For clean batches, **Bulk Approval** from the queue table requires only **2 clicks**: Check all → `Setujui Terpilih`.
- **Granular Item Rejection (Occasional Task)**: Maximum **3–4 steps**:
    - `Tolak` toggle → Type brief reason → `Simpan Keputusan`.
    - The rejection input must appear inline directly under the row without triggering secondary popups or separate modal pages.
- **Exporting Data for Payroll (Weekly/Monthly Task)**: Maximum **2 clicks**:
    - Filter is already active on screen → Click `Export` → Select `.csv` or `.xlsx`.
- **Audit Inspection & Admin Force-Unlock (Rare / Exception Task)**: Clear plain-language guidance, mandatory justification inputs, and explicit warnings before executing irreversible overrides.

---

### 4.2 Cognitive Load Budget

- **Maximum 3–4 Primary Actions Visible on Screen**:
    - Queue Screen: Search/Filter bar, Table rows with quick `Review` button, and contextual Floating Bulk Bar (visible only when checkboxes are ticked).
    - Modal Screen: `Setujui Semua`, `Tolak Semua`, and `Simpan Keputusan`.
- **ISUZU Clean Industrial Design Standards**:
    - **Monospace Identifiers**: All NPKs (`10425`), Submission Codes (`OT-20260907-STAMP-001`), and CapEx Project Codes (`CAPEX-2026-004`) rendered in clean tabular monospace font (`font-mono`).
    - **Tabular Figures for Numerical Data**: All hours (`38.5 jam`) and currency amounts formatted with tabular numbers (`tabular-nums`) to align vertically across table rows.
    - **Standard Indonesian Currency Formatting**: Monetary values strictly formatted as `Rp 1.234.567` (using Indonesian thousand periods and no decimal clutter for whole Rupiah).
- **CapEx vs OpEx Visual Segregation**:
    - CapEx line items (investments, project machinery installation) must be visually distinct from routine OpEx (regular production, routine TPM).
    - CapEx hours display a distinctive blue/indigo badge (`CapEx: Line Automation`) to prevent accidental approval under general department operating budgets.
- **SPKL Non-Blocking Indicator**:
    - The SPKL status must be clearly visible via color-coded badges:
        - `🟢 Terlampir` (Attached & verified)
        - `🟠 Pending` (Awaiting upload — 1-2 days remaining)
        - `🔴 Terlambat` (Overdue > 2 days)
    - An informational tooltip reinforces rule BR-05: _"SPKL pending tidak menghambat persetujuan lembur operasional."_
- **ML Anomaly Warning Flags (Epic-08 preview)**:
    - Submissions with AI-flagged anomalies display a distinctive robot pill: `🤖 1 anomali flagged`.
    - Inside the modal, the flagged employee row displays an amber callout with plain-language explanation (e.g., _"Jam lembur 3.5 jam melebihi rata-rata historis shift ini (1.5 jam)"_).
- **Strictly No Nested Modals**:
    - Modals and drawers must never overlap in multiple layers. The Item Audit Trail is a slide-in side drawer (`Sheet`), while Approval and Bulk Confirmations are standalone centered dialogs.

---

### 4.3 Trust Signals & Plant Communication Standards

- **Plain-Language Indonesian Manufacturing Terminology**:
    - Use familiar shopfloor terms alongside standard administrative labels:
        - `Persetujuan Lembur` (Overtime Approvals)
        - `Menunggu Review` (Pending Review / Submitted)
        - `Disetujui Sebagian` (Partially Approved)
        - `Disetujui` (Approved)
        - `Ditolak` (Rejected)
        - `SPKL` (_Surat Perintah Kerja Lembur_)
        - `HKN` (_Hari Kerja Normal_)
        - `HLR` (_Hari Libur / Istirahat_)
        - `NPK` (_Nomor Pokok Karyawan_)
- **Zero Raw Technical Errors or HTTP Exception Codes**:
    - Never display raw errors such as `409 Conflict`, `422 Unprocessable Entity`, `SQLSTATE[40001]`, or `lockForUpdate timeout`.
    - Friendly translations:
        - _HTTP 409 (Lock Conflict)_ → _"Data pengajuan ini baru saja diperbarui oleh reviewer lain. Silakan muat ulang halaman untuk melihat status terbaru."_
        - _HTTP 422 (Mandatory Rejection Reason)_ → _"Alasan penolakan wajib diisi untuk setiap karyawan yang ditolak."_
        - _HTTP 422 (Approved Immutability)_ → _"Pengajuan ini telah disetujui dan tidak dapat diubah oleh Team Leader."_
- **Plain-Language Consequence Warning on Rejections**:
    - When rejecting items, the UI provides reassuring feedback: _"Karyawan yang ditolak akan dikembalikan ke Team Leader beserta alasan penolakan untuk diperbaiki, tanpa membatalkan karyawan lain yang sudah disetujui."_
- **Immediate Reactive Visual Feedback**:
    - Every action (Approve, Reject, Bulk, Unlock) produces an immediate toast notification with explicit counts: `✓ 14 karyawan disetujui, 1 ditolak`.
    - Row status badges in the underlying queue table update instantly via Inertia reactive props without a full-page browser refresh.

---

### 4.4 Epic E04 Specific Risks & Mitigations

| Sub-Epic / Feature                               | Identified UX Risk                                                                                                                                     | Mandatory Design Mitigation                                                                                                                                                                                                                                                    |
| ------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **E04-01 (Queue Overload)**                      | Managers with large departments see 50+ submissions at once, causing decision fatigue and missed items.                                                | Default filter shows only `Menunggu Review` (active workload). Queue provides a 1-click date filter (`Hari Ini`, `7 Hari Terakhir`, `Bulan Ini`) and section dropdown. Default sort places oldest pending submissions first.                                                   |
| **E04-02 (Silent Rejection Frustration)**        | Managers reject a line item without typing an explanation, leaving frontline Team Leaders confused about why a worker was denied overtime pay.         | The system strictly disables the "Simpan Keputusan" button whenever an item is marked `❌ Tolak` until a non-empty `rejection_reason` (min 5 characters) is entered on that row.                                                                                               |
| **E04-02 (Concurrent Review Collision)**         | Two managers or assistant managers review the same section at the same time. The second submission could overwrite the first reviewer's decisions.     | Enforce optimistic locking via `lock_version`. On conflict, the modal traps the 409 response, prevents data loss, and shows an amber banner with a single-click "Muat Ulang Data Terbaru" button.                                                                              |
| **E04-03 (Accidental Bulk Rejection Disaster)**  | A manager accidentally clicks "Bulk Reject" on 20 submissions, rejecting 300 workers without realizing the consequences.                               | Bulk Reject requires a distinct two-step confirmation modal with a red destructive action button and a mandatory shared rejection reason input.                                                                                                                                |
| **E04-03 (Partial Bulk Failure Stalling)**       | During a bulk approval of 40 submissions, 1 submission fails due to a concurrent lock conflict, causing the manager to assume the entire batch failed. | Implement per-submission transaction handling in `BulkApproveSubmissionsAction`. The UI displays a detailed summary toast: _"38/40 pengajuan berhasil disetujui (2 dilewati karena sedang ditinjau reviewer lain)"_, allowing the manager to inspect only the skipped records. |
| **E04-04 (Large Export Browser Freeze)**         | Exporting 5,000 overtime records for a quarterly audit freezes the browser or triggers a memory timeout.                                               | Export uses Laravel `LazyCollection` streaming directly to the HTTP response. The UI button displays an active spinner and disables repeated clicks until the download stream begins.                                                                                          |
| **E04-05 (Audit Trail Confusion)**               | Non-technical managers or auditors find raw JSON diffs incomprehensible.                                                                               | The `AuditTrailDrawer.vue` translates JSON keys into friendly Indonesian plant terms (e.g., `status: SUBMITTED → APPROVED` is rendered as `Status: Menunggu Review → Disetujui`).                                                                                              |
| **E04-06 (Accidental Tampering After Approval)** | Team Leaders edit approved overtime hours after manager signoff, causing payroll discrepancies.                                                        | Controller enforces strict immutability. UI hides the Edit button on Team Leader screens and replaces it with a locked badge (`🔒 Terkunci`). Admin force-unlock requires a mandatory documented reason.                                                                       |

---

## 5. Implementation Boundaries (Anti-Splitting Rules)

These rules are strict architectural constraints for the implementing engineer or AI subagents. Any deviation will violate the morning standup velocity and audit compliance requirements.

### 5.1 Do Not Split — Combine Into One Surface:

- **Pending Approval Queue (`/overtime/approvals`)**:
    - The queue table, search/filter controls, status counters, and bulk action triggers **MUST live on a single page**.
    - **Do NOT** split approvals into separate pages by status (e.g., do NOT create `/overtime/approvals/pending`, `/overtime/approvals/approved`, or `/overtime/approvals/rejected`). Use URL query parameters (`?status=SUBMITTED`) on `/overtime/approvals`.
- **Item-Level Review & Decision Surface**:
    - All employee line items belonging to a submission **MUST be reviewed on a single modal surface** (`ApprovalModal.vue`).
    - **Do NOT** split item reviews into multi-step wizards (e.g., do NOT make Step 1: Review Production → Step 2: Review CapEx → Step 3: Confirm).
- **Four Work Categories on Item Rows**:
    - `hours_production`, `hours_tpm`, `hours_project`, and `hours_others` **MUST be displayed side-by-side on each employee row** within the modal.
    - **Do NOT** create separate dialogs for different overtime categories.

---

### 5.2 Make a Tab or Filter, Not a New Route:

- In the Approval Queue (`/overtime/approvals`), switching between `Menunggu Review`, `Disetujui Sebagian`, and `Semua` must be handled via tab pills bound to query parameters (`?status=...`), **NOT separate routes**.
- Department and Section filtering must update the table reactively using Inertia partial reloads (`preserveState: true`, `preserveScroll: true`), **NOT hard full-page redirects**.

---

### 5.3 Make a Drawer/Sheet or Modal, Not a Full Page:

The following user interactions must be built as centered modal dialogs (`Dialog`) or slide-in side panels (`Sheet` from right), never separate pages:

- **`ApprovalModal.vue`**: Itemized review and approval/rejection of employee hours for a submission.
- **`BulkApprovalConfirmModal.vue`**: Multi-submission bulk confirmation and shared rejection reason entry.
- **`AuditTrailDrawer.vue`**: Slide-in right drawer displaying the chronological state change timeline and JSON diff for an overtime item.
- **`ForceUnlockModal.vue`**: Admin-only confirmation dialog with mandatory reason to unlock an approved submission.

---

### 5.4 Backend-Only — Strictly No Dedicated UI:

The following components and services are pure background, transaction, or API logic. **Do NOT create dedicated navigation items, pages, or menus for them**:

- **`ApproveOvertimeItemsAction`**: Core atomic transaction engine executing pessimistic row locks and optimistic `lock_version` validation.
- **`BulkApproveSubmissionsAction`**: Batch orchestrator looping through submission IDs with isolated try-catches.
- **`RecalculateMonthlyBurnSnapshotJob`**: Asynchronous background queue job recalculating section monthly burn statistics.
- **`OvertimeItemAudit` Synchronous Creation**: Direct database insert ledger recording state transitions inside the database transaction.
- **Database Immutability Constraint Guard**: Controller gate check aborting with `422` if an edit is attempted on an approved submission.

---

### 5.5 Strictly Forbidden:

1. **Do NOT use legacy Ziggy `route()` helper**: Always use typed **Laravel Wayfinder** functions imported from `@/actions` or `@/routes`.
2. **Do NOT allow silent rejections**: Rejecting an item without a documented reason is strictly forbidden (BR-10). The UI must disable submission until a reason is provided, and the backend Form Request must enforce `required_if:action,REJECTED`.
3. **Do NOT block approvals due to missing SPKL documents**: Enforce Business Rule BR-05 strictly. Missing or pending SPKL documents display visual advisory badges, but **must never disable or block the manager from approving valid overtime hours**.
4. **Do NOT show nested modals**: Modal inside modal is strictly forbidden. The Item Audit Trail must be an off-canvas slide-in drawer (`Sheet`), never an overlay on top of an existing modal.
5. **Do NOT use raw technical HTTP error codes in user alerts**: Never display `409 Conflict`, `422 Unprocessable Entity`, or `500 Server Error` directly to managers. Always provide actionable, respectful Indonesian guidance.
6. **Do NOT hard-redirect or wipe modal state on concurrency conflict**: If an optimistic lock conflict (`409`) occurs, preserve the user's view and offer an explicit "Muat Ulang Data Terbaru" refresh action.
7. **Do NOT allow Team Leaders to edit approved submissions**: Once any item in a submission is `APPROVED` or `PARTIALLY_APPROVED`, editing is permanently blocked. The UI must replace the edit button with a locked badge.
8. **Do NOT create a dedicated sidebar item for Team Leaders or Operators for approvals**: Only `Manager` and `Admin` roles have access to the Approval Queue (`/overtime/approvals`).
