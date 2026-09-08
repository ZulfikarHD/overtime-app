# Overtime Approvals Queue - User Guide

## What is Overtime Approvals?

**Overtime Approvals** (sidebar: **Persetujuan Lembur**) is the morning review queue for Department Managers and Plant Administrators. It lists overtime batches submitted by Team Leaders so you can inspect hours, SPKL paperwork status, and anomaly flags before deciding on approvals in later steps of the workflow.

## Who Can Use This?

- **Manager** — sees submissions for their own department only
- **Administrator** — sees plant-wide submissions and can filter by department
- **Team Leaders / Operators** — do **not** have this menu (they use **Input Lembur** / history instead)

## How to Use

### Open the Approval Queue

1. Sign in as a Manager or Administrator
2. Open **Persetujuan Lembur** in the sidebar
3. Check the red pending badge on the menu and the **Pengajuan Menunggu Review** pill on the page header for your current backlog

> **Tip:** The top bar already shows the live WIB clock; the queue page repeats it so standup reviewers keep time pressure visible.

### Filter the Morning Workload

1. Use status tabs:
    - **Menunggu Review** — default active submissions waiting for first review
    - **Disetujui Sebagian** — batches already partially processed
    - **Semua** — all statuses in the selected date range
2. Narrow further with:
    - **Seksi** (and **Departemen** for Admins)
    - **Dokumen SPKL** (`Belum Dilampirkan`, `Terlampir`, `Terverifikasi`, `Terlambat`)
    - Date presets (**Hari Ini**, **7 Hari Terakhir**, **Bulan Ini**) or custom dates
    - Sort by **Tanggal**, **Seksi**, or **Total Jam**
3. Click **Reset Filter** when you need to return to the default standup view

### Inspect and Review a Submission

1. Locate the row by submission code (for example `OT-20260908-STAMP-001`).
2. Review total hours, headcount, estimated cost (`Rp`), SPKL badge, and status.
3. If you see **anomali flagged**, treat that row as higher review priority.
4. Click the expand chevron to inspect the inline employee breakdown.
5. Click **Tinjau** or **Buka Review Lengkap →** to open the **Item-Level Approval Modal**.

### Making Item Decisions in the Modal

Inside the approval dialog:

1. **Header Information** — verify the operational date, section, submitter name, SPKL document status, and the **Section Monthly Burn Indicator** bar to check how this approval impacts your department's monthly overtime quota.
2. **Reviewing Individual Employees** — each worker row displays:
    - NPK, Name, and Position.
    - Work breakdown across 4 categories: **Produksi**, **TPM**, **CapEx Project** (with project code pill), and **Lainnya**.
    - Total overtime hours and estimated cost (`Rp`).
    - Machine Learning anomaly warnings or 14-hour/weekly policy limits (if applicable).
3. **Deciding Items**:
    - Click **Setuju** (green checkmark) to approve the worker's hours.
    - Click **Tolak** (red X) to reject.
    - Click **Pending** to defer decision.
4. **Entering Rejection Reasons (Mandatory per BR-10)**:
    - When **Tolak** is clicked, a rejection reason text area appears.
    - You must type a reason of at least **5 characters** (e.g., _"Target shift tercapai tanpa lembur"_).
    - If any rejected item is missing a reason, the **Simpan Keputusan** button is disabled to prevent accidental rejections without explanation.
5. **Standup Fast-Actions (Batch Approval/Rejection)**:
    - Click **Setujui Semua (Approve All)** to mark all lines as approved in 1 click.
    - Click **Tolak Semua (Reject All)** to mark all lines as rejected and enter a shared reason that applies to all workers.
6. **Saving Decisions**:
    - Check the footer summary showing total approved workers, rejected workers, and total approved cost.
    - Click **Simpan Keputusan**.
    - A success notification confirms the saved status, and the queue updates automatically.

### Handling Concurrency Conflicts (409 Conflict)

If another manager or admin saves decisions for the same submission while your modal is open:

- An amber **Konflik Pembaruan Data (409 Conflict)** banner appears at the top of the modal.
- Click **Muat Ulang Data Terbaru** to fetch the latest state without losing your place.

### Bulk Approval & Rejection (Persetujuan & Penolakan Massal)

During morning standups when reviewing dozens of standard shifts, you can process submissions in bulk:

1. **Selecting Submissions**:
    - Use the checkboxes on the left of each row to select specific submissions.
    - Or click the **Pilih Semua (Select All on Page)** checkbox in the table header to select all submissions visible on the current page (up to 50 submissions per bulk action).
2. **Floating Bulk Action Bar**:
    - As soon as at least one submission is checked, a dark industrial toolbar appears at the bottom center of the screen.
    - It displays the total selected count, cumulative headcount (`X Karyawan`), and total hours (`Y Jam`).
    - Use **Batal Pilihan** (`X`) to clear all selections at any time.
3. **Bulk Approving**:
    - Click **Setujui Terpilih (Bulk Approve)** on the floating bar.
    - The **Konfirmasi Persetujuan Massal** dialog opens, displaying a breakdown of all selected submissions and impact.
    - Click **Konfirmasi & Setujui** to process.
4. **Bulk Rejecting with Shared Reason**:
    - Click **Tolak Terpilih (Bulk Reject)** on the floating bar.
    - The **Konfirmasi Penolakan Massal** dialog opens.
    - Type a shared rejection reason of at least **5 characters** (e.g., _"Target shift terpenuhi, lembur tidak dialokasikan"_). The confirm button remains disabled until this reason is entered.
    - Click **Konfirmasi & Tolak** to process.
5. **Partial Failure & Conflict Feedback (Result Toast)**:
    - If all submissions succeed, a green success banner appears confirming the counts.
    - If any submission was modified concurrently by another reviewer, the system safely commits clean submissions and skips the conflicting ones.
    - An amber notification banner informs you: `X item berhasil diproses (Y item dilewati)`.
    - Click **Lihat rincian pengajuan dilewati** to expand the list of skipped submission codes and specific conflict reasons.

### Understand SPKL Badges

SPKL status is informational only:

| Badge                       | Meaning                       |
| --------------------------- | ----------------------------- |
| Pending / Belum Dilampirkan | Paperwork still expected      |
| Terlampir                   | Document attached or verified |
| Terlambat                   | Pending past due date         |

Missing SPKL **does not** block you from reviewing operational overtime hours.

## Frequently Asked Questions (FAQ)

**Q: Can I approve some workers and reject others in the same submission?**
A: Yes! When you approve some and reject others, the submission status automatically updates to **Disetujui Sebagian** (Partially Approved).

**Q: Why is the "Simpan Keputusan" button disabled?**
A: Ensure you have selected either **Setuju** or **Tolak** for at least one item, and verify that any rejected worker has an explanation of at least 5 characters.

**Q: Can a Team Leader edit a submission once I have approved it?**
A: No. Any submission with status **Disetujui** or **Disetujui Sebagian** is permanently locked against team leader edits to preserve financial integrity.

**Q: Why don’t I see another department’s overtime?**  
A: Managers are limited to their assigned department. Ask an Administrator for plant-wide visibility.

**Q: Why is the list empty?**  
A: The default view shows **Menunggu Review** within the last 7 days. Switch tabs, widen the date range, or clear filters.

**Q: What does the sidebar number badge mean?**  
A: It counts submissions still in `SUBMITTED` or `PARTIALLY_APPROVED` status for your authority scope.

## Troubleshooting

| Issue                                      | Solution                                                                 |
| ------------------------------------------ | ------------------------------------------------------------------------ |
| Menu **Persetujuan Lembur** is missing     | Confirm your role is Manager or Admin; Team Leaders use Input Lembur     |
| Cannot open `/overtime/approvals`          | You are unauthorized for approvals — contact IT Admin                    |
| SPKL shows overdue but Review is available | Expected — SPKL is non-blocking; continue review and follow up paperwork |
| Anomaly badge appears                      | Expand the row, inspect flagged workers, prioritize those items          |
