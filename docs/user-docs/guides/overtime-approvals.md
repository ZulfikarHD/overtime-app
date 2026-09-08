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

### Inspect a Submission Quickly

1. Locate the row by submission code (for example `OT-20260908-STAMP-001`)
2. Review total hours, headcount, estimated cost (`Rp`), SPKL badge, and status
3. If you see **anomali flagged**, treat that row as higher review priority
4. Click the expand chevron to open the inline employee breakdown:
    - NPK and name
    - Production / TPM / CapEx / Other hours
    - CapEx project code tags
    - Cost snapshot per worker
5. Use **Tinjau** / **Buka Review Lengkap →** when you are ready for full item decisions (item-level approve/reject lands in the next delivery wave)

### Understand SPKL Badges

SPKL status is informational only:

| Badge                       | Meaning                       |
| --------------------------- | ----------------------------- |
| Pending / Belum Dilampirkan | Paperwork still expected      |
| Terlampir                   | Document attached or verified |
| Terlambat                   | Pending past due date         |

Missing SPKL **does not** block you from reviewing operational overtime hours.

## Frequently Asked Questions (FAQ)

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
