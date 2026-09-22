# Planning OT — User Guide

## What is Planning OT?

Planning OT is a monthly overtime planning tool. Supervisors and managers use it to schedule
how many overtime hours each employee is expected to work each day of the month, broken down
into four work categories: Production, TPM (maintenance), Project/Kaizen, and Others.

Think of it as a digital version of the `planning_ot.xlsx` spreadsheet — but stored in the
system so it can be reviewed, published, and used as a reference against actual realized hours.

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
3. Use the **← →** arrows at the top to navigate to the correct month.
4. The grid loads your section's active employees as rows and the days of the month as columns.

### Filling in Hours

1. **Click any cell** in the grid to open the input panel for that employee + day.
2. The panel shows four category fields: **Prod** (Production), **TPM**, **Proj** (Project), and **Lain** (Others).
3. Type a value directly — the field selects automatically when you click or Tab into it, so you can type the replacement immediately.
4. Use the **−** and **+** buttons to adjust in 0.5-hour steps.
5. Click **OK** to apply. The cell in the grid shows the total hours.

> 💡 **Tip:** Red-shaded columns are **HLR** (holiday) days; white columns are **HKN** (workday) days.

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
holiday or weekly day off. The day type affects the overtime calculation rules.

**Q: Can two plans exist for the same section and month?**
A: No. There is one plan per section per month. Re-saving the same section and period updates the
existing plan rather than creating a duplicate.

**Q: What are the four categories?**
A: They map to the standard OT type codes used in the SPL document:

- **Prod** = Production work (codes 61, 62)
- **TPM** = Maintenance work (codes 65, 66)
- **Proj** = Project / Kaizen (codes 67, 68)
- **Lain** = Other types

---

## Troubleshooting

| Issue                                      | Solution                                                                                 |
| ------------------------------------------ | ---------------------------------------------------------------------------------------- |
| "Pilih seksi untuk memuat daftar karyawan" | You have not selected a section yet. Choose a department and section from the dropdowns. |
| Employees are missing from the grid        | The employee may be inactive. Ask your administrator to check their status.              |
| Cannot delete a plan                       | Only draft plans can be deleted. Contact your administrator to resolve a published plan. |
| Changes not visible after saving           | Try refreshing the page, or ask your administrator to run `npm run build`.               |
