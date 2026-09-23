# Planning OT — User Guide

## What is Planning OT?

Planning OT is a monthly overtime planning tool. Supervisors and managers use it to schedule
how many overtime hours each employee is expected to work each day of the month, broken down
into four work categories: Production (A), TPM (B), Project/Kaizen (C), and Others (D).

The grid mirrors the Excel workbook `planning_ot.xlsx`: dense day × category cells, sticky
employee columns, keyboard navigation, and a monitoring strip (Total Jam, Conversi Idx,
weekly hours, Plan vs Actual index).

---

## Who Can Use This?

| Role                | What they can do                            |
| ------------------- | ------------------------------------------- |
| **Team Leader**     | View and create plans for their own section |
| **Manager**         | View and manage plans for their department  |
| **Administrator**   | Full access to all plans                    |
| **Operator (User)** | No access                                   |

---

## How to Use

### Viewing Existing Plans

1. Open the **Planning OT** menu in the left sidebar.
2. The list shows all plans you have access to, with their period, section, status, and item count.
3. Click **Edit** to open a plan's monthly grid.

### Creating a New Plan

1. Click the red **Buat Planning Baru** button at the top-right of the list.
2. Select your **Department** and **Section** from the dropdowns.
3. Select **Year** and **Month** from the dropdowns (and Department / Section).
4. The grid loads your section's active employees as rows and the days of the month as columns.

### Filling in Hours (Excel-style)

1. Each day has four columns: **A** (Prod), **B** (TPM), **C** (Proj), **D** (Lain).
2. Click a cell (or Tab / arrow into it) — the value is selected so you can type immediately.
3. **Tab** / **Shift+Tab** move horizontally; **arrow keys** move in any direction; **Enter** moves down.
4. **Delete** / **Backspace** with the value selected clears the cell.
5. Non-zero cells highlight in light green.

> 💡 **Tip:** Red-shaded day headers are **HLR** (holiday); white/neutral headers are **HKN** (workday).

### Monitoring (below the grid)

Summary lives in a separate **Monitoring Ringkasan** panel under the day grid (not mixed into days 1–N):

| Tab                | Contents                                        |
| ------------------ | ----------------------------------------------- |
| **Jam Mingguan**   | Conversi Idx, W1–W5 category hours + Σ, GT HOUR |
| **Plan vs Actual** | Index W1–W5 (P / A) and GT Idx                  |

The day entry grid only shows employee identity, day × A/B/C/D cells, and **Total Jam**.

Week buckets: days 1–7 → W1, 8–14 → W2, 15–21 → W3, 22–28 → W4, 29+ → W5.

### Saving and Publishing

| Button           | Effect                                                         |
| ---------------- | -------------------------------------------------------------- |
| **Simpan Draft** | Saves the plan as a draft — you can still edit it              |
| **Publikasikan** | Locks the plan. Published plans cannot be deleted or re-edited |

> ⚠️ **Warning:** Once you publish a plan, it cannot be unpublished or deleted. Make sure all
> hours are correct before publishing.

---

## Frequently Asked Questions

**Q: What is the difference between HKN and HLR?**
A: HKN (Hari Kerja Normal) means a regular workday. HLR (Hari Libur Resmi) means an official
holiday or weekly day off. The day type affects Conversi Idx (1.5 vs 2.0).

**Q: Can two plans exist for the same section and month?**
A: No. There is one plan per section per month. Re-saving the same section and period updates the
existing plan rather than creating a duplicate.

**Q: What are the four categories?**
A: They map to the standard OT type codes used in the SPL document:

- **A / Prod** = Production work (codes 61, 62)
- **B / TPM** = Maintenance work (codes 65, 66)
- **C / Proj** = Project / Kaizen (codes 67, 68)
- **D / Lain** = Other types

**Q: Why is Actual (A) empty in Plan vs Actual?**
A: Actual index comes from **approved** overtime submissions for that section and month. Until
hours are approved, Actual stays at zero.

---

## Troubleshooting

| Issue                                      | Solution                                                                                 |
| ------------------------------------------ | ---------------------------------------------------------------------------------------- |
| "Pilih seksi untuk memuat daftar karyawan" | You have not selected a section yet. Choose a department and section from the dropdowns. |
| Employees are missing from the grid        | The employee may be inactive. Ask your administrator to check their status.              |
| Cannot delete a plan                       | Only draft plans can be deleted. Contact your administrator to resolve a published plan. |
| Changes not visible after saving           | Try refreshing the page, or ask your administrator to run `npm run build`.               |
