# Executive Operational Dashboard & KPI Cards - User Guide

## What is the Executive Operational Dashboard?

The **Executive Operational Dashboard** is the command center for supervisors, department managers, and plant administrators. It provides live, real-time insights into manufacturing plant health, production pace, working day schedules, workforce capacity, and overtime budget consumption across all factory lines.

---

## How to Use

### Accessing the Dashboard

1. Sign in to the application using your email or numeric NPK and password.
2. Click **Dashboard** in the main sidebar.
3. The dashboard opens displaying the current operational header, active shift pill (e.g., `Shift 1: 07:00–15:00 WIB`), and live Jakarta time (WIB).

---

### Reading the Header KPI Cards

The top section displays four executive KPI cards with interactive mini charts:

#### 1. Daily Production Volume

- Displays the daily vehicle unit production target (e.g., **1,450 units**).
- When the factory ERP feed is active, a 14-day line chart shows daily production output and percentage variance against the baseline.
- If the ERP feed is currently offline or waiting for integration, a neutral alert badge displays: _"N/A — Integrasi data produksi ERP belum terhubung"_ to inform you without disrupting dashboard operations.

#### 2. Working Days (HKN)

- Shows the total Normal Working Days (**HKN**) versus completed days for the selected month (e.g., **18 / 22 Hari Kerja**).
- Displays remaining working days left in the month to assist in overtime planning.
- The mini bar chart visualizes the distribution of working days across the 5 weeks of the month.

#### 3. Active Manpower

- Shows the total headcount of active employees currently on the factory floor (e.g., **384 Karyawan Aktif**).
- Displays active operational shift count (**3 Shift Operasional Aktif**).
- The mini bar chart shows employee headcount distributed across factory production sections (e.g., Trim Line, Chassis Line, Welding, Paint).

#### 4. Overtime Burn Index (BBI)

- Compares the overtime budget ceiling against actual consumed overtime hours for the month.
- **Budget Baseline (Plan)**: A blue progress bar representing the approved monthly overtime budget (100%).
- **Current Actual Realization**: Color-coded progress bar and status badge indicating spending health:
    - 🟢 **Safe (<85%)**: Budget consumption is well controlled.
    - 🔵 **On Track (85%–100%)**: Overtime pace matches planned production.
    - 🟡 **Warning (101%–115%)**: Spending exceeds budget; review non-essential hours.
    - 🔴 **Critical Deficit (>115%)**: Urgent management review required; hours exceed permissible thresholds.

---

### Filtering by Department and Date

Supervisors and Administrators can filter metrics to inspect specific production areas:

1. Locate the **Filter Bar** in the upper right of the dashboard header.
2. To filter by department:
    - Click the **Department** dropdown.
    - Choose a specific department (e.g., _Assembly Department_, _Welding Department_) or select **All Departments (Plant-wide)**.
    - _(Note: Department Managers and Team Leaders see their assigned department automatically.)_
3. To change the operational date:
    - Click the **Date** selector and pick any calendar date.
4. The dashboard automatically refreshes all four KPI cards with real-time figures.
5. Click the circular **Reset** icon to return to today's date and default plant view.

---

## Frequently Asked Questions (FAQ)

**Q: Why does the Production Volume card show "ERP N/A"?**  
A: This indicates that the automated plant ERP telemetry interface is either undergoing scheduled maintenance or awaiting connection. The card continues to display standard plant capacity targets safely.

**Q: Can line operators view the Executive Operational Dashboard?**  
A: Regular shopfloor operators are automatically routed to the **My Dashboard** (Self-Service) page, which is customized for individual timesheet verification and personal overtime records.

**Q: How often is the Burn Index (BBI) updated?**  
A: The Burn Index reflects newly approved SPKL timesheets as soon as they are approved by Section Heads or Department Managers.

---

## Troubleshooting

| Issue                                      | Solution                                                                                                                             |
| ------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------ |
| The KPI cards are showing placeholder bars | The data is refreshing. Allow 1–2 seconds for the network request to complete.                                                       |
| Department dropdown is disabled            | Your account role (Manager or Team Leader) is restricted to your assigned department to prevent accidental cross-department scoping. |
| Wrong date metrics displayed               | Check the date picker in the filter toolbar and ensure the correct operational date is selected, or click the **Reset** button.      |
