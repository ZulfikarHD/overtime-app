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
