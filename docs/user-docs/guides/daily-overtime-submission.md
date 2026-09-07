# Daily Overtime Submission Guide

## Audience & Persona

- **Primary Persona**: Team Leader (_Mandor / Pengawas Lapangan_).
- **Secondary Persona**: Section Supervisor, Department Manager, and Plant Administrator.
- **Goal**: Rapidly submit daily overtime hours for active section crew members during shift-end handover (07:00, 15:00, 23:00 WIB) with zero data loss and automated cost calculation.

---

## 1. Accessing the Overtime Entry Form

1. Log in to the OT-CapEx system with your assigned **Team Leader** or **Admin** credentials.
2. In the left navigation sidebar under **Platform**, click **Input Lembur** (or **Overtime Entry**).
3. You will arrive at the single-screen timesheet entry page (`/overtime/submissions/create`).
4. At the top of the workspace, you will find navigation tabs allowing 1-click toggling between:
    - **Form Input Lembur**: The rapid batch entry workspace.
    - **Riwayat Pengajuan**: History of previous submissions for your section.

---

## 2. Reviewing Header Metadata

Before adding hours, verify the operational header parameters:

1. **Tanggal Operasional Lembur**:
    - Defaults automatically to today's date in Western Indonesian Time (`Asia/Jakarta` WIB).
    - Change the date if entering retroactive handover hours.
2. **Klasifikasi Hari (HKN vs. HLR)**:
    - The system queries the factory calendar and automatically displays a badge:
        - `📅 Hari Kerja Normal (HKN)`: Standard weekday shift.
        - `🔴 Hari Libur (HLR)`: Weekend or national holiday shift.
    - **Manual Override**: If your section is working on an exceptional holiday shift or special calendar day, click the **Ubah HKN/HLR** button to toggle the classification manually. To revert, click **Reset Kalender**.
3. **Departemen & Seksi**:
    - For **Team Leaders**, this is auto-locked to your assigned section (e.g. _Press Stamping 1000T_).
    - For **Administrators**, use the dropdowns to switch departments and sections.
4. **Anggaran Seksi (Section Monthly Burn Indicator)**:
    - Displays real-time progress of your section's monthly overtime quota:
        - `Safe (Green)`: Actual hours well within planned budget (< 85%).
        - `Caution (Yellow)`: Actual hours nearing monthly limit (85% - 100%).
        - `Critical (Red)`: Section has exceeded the monthly budget quota (> 100%).

---

## 3. Populating the Overtime Crew Roster

The high-density timesheet allows you to populate your active team members in two convenient ways:

### Option A: 1-Click "Pilih Semua (Add All)"

- Click the **Pilih Semua (Add All)** button in the roster header toolbar or the large red **+ Tambah Semua Anggota Seksi** button in the empty state card.
- All active employees belonging to your section are instantly populated into the timesheet rows with their NPK, name, job title, and hourly overtime rate.

### Option B: Adding Individual Employees

- Type an employee's NPK or name into the **Cari NPK / Nama...** search box.
- Select the worker from the **+ Tambah Karyawan...** dropdown menu to add them as a single line item.

---

## 4. Entering Overtime Hours

For each employee row in the timesheet:

1. **Four Category Hour Buckets**:
    - **Produksi (Jam)**: Regular production overtime (e.g. catch-up quotas).
    - **TPM (Jam)**: Total Productive Maintenance (e.g. die maintenance, machine check).
    - **CapEx (Jam)**: Labor hours dedicated to active capital investment projects.
    - **Lainnya**: Miscellaneous overtime (e.g. 5S, safety training, audits).
    - _Rules_: Enter hours in increments of 0.5 hours (e.g., 1.5, 2.0).

2. **Progressive CapEx Project Allocation (BR-08)**:
    - If you enter any hours under **CapEx (Jam)** (> 0), the row automatically reveals the **Alokasi Proyek CapEx** dropdown.
    - You must select the specific capital project (e.g., _CIP-BRW-01 · Robotic Weld Jig Automation_) to which this labor cost should be capitalized.

3. **Optional Root Cause (RCA) & Task Notes**:
    - Click the chevron button on the right side of the row to expand the RCA card.
    - Select an RCA category (e.g. _Kerusakan Mesin_, _Keterlambatan Komponen_, _Rework Kualitas_).
    - Add a task description (e.g. _Penggantian bearing die stamping_) for shift documentation.

---

## 5. Live Summary & Submission

1. **Summary Footer**:
    - As you type, the bottom sticky footer dynamically calculates:
        - **Total Kru**: Total number of employees in the batch.
        - **Total Jam Lembur**: Sum of all hours entered across all 4 categories.
        - **Estimasi Biaya**: Real-time labor cost calculated in Indonesian Rupiah (`Rp`) based on each employee's snapshot rate.
2. **Catatan Pengajuan Batch**:
    - Optionally enter overall batch notes (e.g., _Lembur shift-2 perakitan chassis target delivery_).
3. **Submit Overtime**:
    - Click **Kirim Pengajuan Lembur (Submit Overtime)**.
    - The entire batch is processed in an **atomic database transaction**. If any row fails validation (e.g. total worker hours < 0.5 hours per BR-01), the system alerts you with inline highlighting while retaining all entered data (Zero Data Loss).

---

## 6. Post-Submission Confirmation (Non-blocking SPKL)

1. Upon successful submission, a confirmation card appears at the top:
    - Displays the official submission tracking code (e.g., `OT-20260907-SEC_BRW_PRESS-0001`).
    - Summary statistics (crew count, total hours, and locked snapshot cost).
    - **SPKL: Belum Dilampirkan (Non-blocking BR-05)**: The submission is already active and approved for operations; physical paper forms can be scanned and attached within the department grace period.
2. You can click **Input Lembur Baru** to begin entering another batch or **Riwayat Pengajuan** to view submission history.

---

## 7. Tracking Historical Submissions & Locked Financial Snapshots

1. In the navigation tabs or left sidebar under **Input Lembur** (Overtime Entry), click **Riwayat Pengajuan** (Submission History).
2. The submission history table displays all batches recorded for your section, including:
    - **Kode Pengajuan**: System generated identifier (e.g. `OT-20260908-SECBRWCYL-001`).
    - **Tanggal & Hari**: Shift operational date and HKN/HLR classification badge.
    - **Seksi**: Operational section.
    - **Diajukan Oleh**: Submitting supervisor's name and NPK.
    - **Total Jam**: Cumulative overtime hours for that shift.
    - **Estimasi Biaya**: Permanently locked financial snapshot calculated at the moment of submission using standard IDR currency formatting (`Rp`). Even if base wage rates or department default rates are adjusted in subsequent months or fiscal years, historical submission records permanently maintain their original cost snapshot.
    - **Status Persetujuan**: Current managerial review state (`Menunggu Review`, `Disetujui Sebagian`, `Disetujui`, `Ditolak`).
    - **Dokumen SPKL**: SPKL attachment status (`Terlampir`, `Terverifikasi`, `Belum Dilampirkan`, or `⚠️ Terlambat`).
3. **Filtering Submissions**:
    - Use the **Quick Status Pills** (`Semua Status`, `Menunggu Review`, `Disetujui Sebagian`, `Disetujui`, `Ditolak`) for rapid 1-click filtering.
    - Select date ranges with **Dari Tanggal** and **Sampai Tanggal**.
    - Filter by specific **Seksi** (for multi-section managers and admins) or **Dokumen SPKL** status.
    - Click **Terapkan** to filter or **Reset Filter** to clear.
4. **Pagination**:
    - The table displays 20 submissions per page. Use the previous, next, and page number buttons at the bottom to browse through records without losing your filter criteria or scroll position.

---

## 8. Inspecting Submission Details (Read-Only Modal)

1. Click the **Detail** button or the **Kode Pengajuan** badge on any row in the history table.
2. A centered detail modal will appear displaying:
    - **Header & Status**: Submission code, department, section, operational date, and approval status badge.
    - **SPKL Document Status Banner**: Shows whether the SPKL document is attached, verified, or pending with its due date deadline.
    - **Batch Notes**: Displays any notes entered during shift handover.
    - **Employee Breakdown Table**: Detailed list of crew members with their individual hours across Production, TPM, CapEx (with sky-blue project pill), and Others, alongside their hourly rate snapshot and total line item cost snapshot.
    - **Summary Strip**: Total crew count, total batch hours, and total estimated cost in ISUZU Red (`#cc0000`).
3. Click **Tutup** to dismiss the modal, or click **Edit Pengajuan** if the submission is eligible for editing.

---

## 9. Re-Editing a Submission (Guarded Workflow)

1. **Eligibility**:
    - You can only edit submissions that are still in **Menunggu Review (SUBMITTED)** or **Draf (DRAFT)** status.
2. **Editing Process**:
    - Click the yellow **Edit** button in the action column or inside the detail modal.
    - You will be returned to the timesheet entry form pre-populated with all previous data, displaying an amber banner: `Mode Edit Pengajuan: OT-...`.
    - Modify the operational date, batch notes, add/remove crew members, or adjust overtime hours.
    - Click **Simpan Perubahan (Save Changes)**. The system will atomically re-save the batch, re-calculate and re-snapshot all labor rates and financial totals, and redirect you back to the history hub.
3. **Managerial Lock Protection**:
    - If a submission has already been **Disetujui (APPROVED)** or **Disetujui Sebagian (PARTIALLY_APPROVED)** by a Department Manager, it is permanently locked.
    - The Edit button is replaced by a disabled lock indicator (`🔒 Terkunci`), and backend security guards strictly reject any modification requests with an HTTP 422 error.
