# Summary Employee Overtime Table - User Guide

## What is the Summary Employee Overtime Table?

The **Summary Employee Overtime Table** is an interactive monitoring tool located at the bottom of the **Dashboard**. It gives department managers, team leaders, and operational supervisors an instant overview of all employees under their supervision for the active month.

Instead of jumping between different menus, supervisors can monitor each employee's approved overtime hours, individual budget consumption (Burn Index), category allocation (Production, TPM, CapEx, or Others), and physical SPKL document status in a single consolidated table.

---

## Key Features

1. **Live Search**: Quickly find any worker by typing their name, NPK number, or section code with instant results.
2. **Burn Index Progress Bar**: Visual color-coded gauge indicating whether an employee is within safe limits (Green), on track (Blue), nearing limit (Amber), or in critical budget deficit (Red).
3. **Category Proportional Bar**: Mini color-coded bar displaying the proportion of hours spent on Production, TPM, CapEx projects, and general tasks.
4. **SPKL Compliance Badge**: Immediately highlights whether physical SPKL documents have been attached or if deadlines are pending.
5. **Quick-Look Dossier Drawer**: Slide-in inspection panel displaying CapEx vs OpEx segregation, fatigue compliance warnings, and the last 5 overtime shifts without leaving the Dashboard.
6. **One-Click Full Dossier Link**: Direct button to navigate to the worker's complete monthly historical dossier under **Employee Reports**.

---

## How to Use

### 1. Locating the Table on the Dashboard

1. Click **Dashboard** in the main sidebar.
2. Scroll down past the KPI cards and visual trend charts to the section titled **Employee Overtime Summary Data**.
3. By default, the table displays all active employees in your assigned department or section, sorted from highest to lowest total overtime hours.

### 2. Searching and Filtering Employees

- **Instant Name or NPK Search**: Type into the **Search employee name or NPK...** box. The table filters immediately without refreshing the page. Click the **X** button inside the box to clear your search.
- **Sorting by Columns**: Click any column header with an arrow icon to change the sort order:
    - **Employee (NPK)**: Sort alphabetically A–Z or Z–A.
    - **Burn Index**: Sort from lowest to highest percentage, or vice versa.
    - **Total Hours**: Toggle between highest hours and lowest hours.
- **Cross-Filtering by Overtime Category**: Click any category pill in the **Overtime Category Distribution** donut chart above the table (e.g., _Production_ or _CapEx Project_). The table will immediately show only employees who worked overtime in that category. Click the **X** on the blue category badge above the table to clear the filter.

### 3. Inspecting an Employee via Quick Dossier Drawer

1. Click anywhere on an employee's row, or click the **Eye** icon on the right side of the row.
2. A side panel will slide in from the right containing:
    - **Employee Details**: Full name, NPK, assigned department, section, and position.
    - **Current Month Hours**: Total approved overtime hours vs. individual planned hours.
    - **Individual Burn Index Gauge**: Color-coded progress bar and risk zone.
    - **CapEx vs OpEx Segregation**: Blue (CapEx) vs Slate (OpEx) bar showing investment labor vs operational expense.
    - **Policy Compliance Status**: Safe status indicator or **Fatigue Alert** warning if the employee has exceeded weekly limits for consecutive weeks.
    - **Recent 5 Shifts History**: Table showing recent dates, day type (HKN regular workday or HLR weekend/holiday), hours, and SPKL numbers.
3. Click **Open Full Dossier** to open the worker's comprehensive multi-month profile in **Employee Reports**, or click **Close** to return to the dashboard.

---

## Understanding the Color Indicators

### Burn Index Progress Bar

| Color             | Zone     | Percentage    | Meaning                                                   |
| ----------------- | -------- | ------------- | --------------------------------------------------------- |
| **Emerald Green** | Safe     | `< 85%`       | Overtime usage is well within planned monthly allocation. |
| **Royal Blue**    | On Track | `85% – 100%`  | Overtime pace matches planned section budget.             |
| **Amber**         | Warning  | `101% – 115%` | Worker has exceeded monthly plan; monitor closely.        |
| **ISUZU Red**     | Critical | `> 115%`      | Severe budget deficit or high overtime load.              |

### SPKL Document Status Badges

| Badge                           | Meaning                                                                                        | Action Needed                                              |
| ------------------------------- | ---------------------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| **Approved / Attached (Green)** | All physical SPKL documents have been uploaded and verified.                                   | None. Document is compliant.                               |
| **Grace Period (Amber)**        | Submission is approved, but physical SPKL upload is pending within the allowable grace period. | Remind team leader to scan and attach the physical form.   |
| **SPKL Overdue (Red)**          | Grace period has expired and the document is missing.                                          | Immediate escalation required to attach missing paperwork. |
| **None (Gray)**                 | No overtime recorded for the worker this month.                                                | None.                                                      |

---

## Frequently Asked Questions (FAQ)

**Q: Why do I only see employees from my own department?**  
A: The system strictly respects role-based access. Department Managers only see employees in their assigned department, and Team Leaders only see their assigned section. System Administrators can view all plant departments or select specific ones using the department dropdown.

**Q: Can I view overtime data for previous months?**  
A: Yes. Use the date picker at the top of the **Dashboard** to select any prior date. The summary table will automatically update to reflect the employees' overtime totals and SPKL statuses for that selected month.

**Q: What should I do if an employee has a Fatigue Alert?**  
A: Open the Quick Dossier Drawer to inspect the worker's recent shifts. Review whether upcoming weekend or overtime shifts can be rotated to other qualified operators to prevent burnout and ensure workplace safety.

---

## Troubleshooting

| Issue                                                 | Solution                                                                                                                                          |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| Table shows "No matching employee data"               | Clear any search keywords or click the **Reset Search & Filter** button to restore the full roster.                                               |
| Expected an employee who does not appear in the table | Verify that the employee is marked as active in **Employee Roster** and is assigned to your section/department.                                   |
| Drawer does not show recent shifts                    | Shifts only appear once the overtime submission has reached **APPROVED** status. Pending submissions are not counted in official monthly metrics. |
