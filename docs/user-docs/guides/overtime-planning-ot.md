# Planning OT — User Guide

## What is Planning OT?

Planning OT is a monthly overtime planning tool. Supervisors and managers use it to schedule
how many overtime hours each employee is expected to work each day of the month, broken down
into four work categories: Production (A), TPM (B), Project/Kaizen (C), and Others (D).

The screen mirrors the Excel workbook `planning_ot.xlsx`: dense day × category cells, sticky
employee columns, keyboard navigation, and a monitoring panel under the grid (weekly hours,
Conversi Idx, Plan vs Actual index).

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
2. Choose **Year** and **Month** from the dropdowns.
3. Select **Department** and **Section**.
4. The grid loads active employees as rows and each day of the month as four columns (A–D).

### Filling in Hours (Excel-style)

1. Each day has four columns: **A** (Prod), **B** (TPM), **C** (Proj), **D** (Lain).
2. Click a cell (or Tab / arrow into it) — the value is selected so you can type immediately.
3. **Tab** / **Shift+Tab** move horizontally; **arrow keys** move in any direction; **Enter** moves down.
4. **Delete** / **Backspace** with the value selected clears the cell.
5. Non-zero cells highlight in light green. Day groups use a thicker border and alternating backgrounds.
6. Published plans are read-only (cells and Save are disabled).

> 💡 **Tip:** Red-shaded day headers are **HLR** (holiday / weekend from the Operational Calendar);
> other days are **HKN** (workday).

### Monitoring (below the grid)

| Tab                | Contents                                        |
| ------------------ | ----------------------------------------------- |
| **Jam Mingguan**   | Conversi Idx, W1–W5 category hours + Σ, GT HOUR |
| **Plan vs Actual** | Index W1–W5 (P = plan, A = approved actual)     |

Week buckets: days 1–7 → W1, 8–14 → W2, 15–21 → W3, 22–28 → W4, 29+ → W5.

Conversi Idx and Plan index use HKN × 1.5 and HLR × 2.0.

### Saving and Publishing

| Button           | Effect                                         |
| ---------------- | ---------------------------------------------- |
| **Simpan Draft** | Saves as draft — you can still edit            |
| **Publikasikan** | Locks the plan; cannot delete or re-edit hours |

> ⚠️ **Warning:** Publishing cannot be undone from the UI. Confirm hours first.

### Changing holiday / HLR days

If a weekday should be HLR (or the reverse), an administrator updates it under
**Master Data → Operational Calendar** (click the date, set HKN/HLR). Planning OT then shows
the updated day type after reload.

---

## Frequently Asked Questions

**Q: What is the difference between HKN and HLR?**
A: HKN is a normal workday. HLR is a rest day or national holiday. It changes Conversi Idx
(1.5 vs 2.0).

**Q: Why is Actual (A) empty?**
A: Actual index comes from **approved** overtime for that section and month. Until approvals
exist, Actual stays zero.

**Q: Can two plans exist for the same section and month?**
A: No. Saving the same section and period updates the existing plan.

**Q: What are the four categories?**
A: They map to SPL OT codes — A Prod (61, 62), B TPM (65, 66), C Proj (67, 68), D Others.

---

## Troubleshooting

| Issue                                      | Solution                                                                         |
| ------------------------------------------ | -------------------------------------------------------------------------------- |
| "Pilih seksi untuk memuat daftar karyawan" | Choose a department and section from the dropdowns.                              |
| Employees missing from the grid            | Employee may be inactive — ask an administrator.                                 |
| Cannot delete a plan                       | Only draft plans can be deleted.                                                 |
| Holiday shown as workday                   | Ask admin to seed/update **Operational Calendar**, or override that date as HLR. |
| Changes not visible after saving           | Refresh the page, or ask an administrator to run a frontend rebuild.             |
