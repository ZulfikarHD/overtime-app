# Burn Index & Budget Dashboard

## What is the Burn Index Dashboard?

The **Burn Index Dashboard** is your operational command center for tracking section-level overtime hours, monitoring monthly budget consumption in real time, and identifying overtime fatigue risks before quota overruns occur.

Every section is tracked using the **Burn Index (%)**, calculated as:

$$\text{Burn Index (\%)} = \left(\frac{\text{Actual Approved Overtime Hours}}{\text{Planned Budget Hours}}\right) \times 100$$

Sections are color-coded and classified into the 4-quadrant **Budget Control Matrix** to give managers instant clarity during daily morning standups.

---

## Who Has Access?

| Role                    | Access Level      | What You Can See                                                                                           |
| ----------------------- | ----------------- | ---------------------------------------------------------------------------------------------------------- |
| **Plant Administrator** | Full Access       | Can view all departments, switch between departments, trigger recalculations, and configure budgets.       |
| **Department Manager**  | Department Access | Automatically scoped to all sections within their assigned department. Can trigger instant recalculations. |
| **Team Leader**         | Section Access    | Can view their assigned section card to monitor team burn rate.                                            |
| **Operator / User**     | Restricted        | No access. Operators should check with their Team Leader.                                                  |

---

## How to Use the Dashboard

### 1. Navigating to the Dashboard

1. Log in to the system with your plant email or NPK.
2. In the left navigation sidebar, click **Burn Index**.
3. The dashboard opens displaying your department's sections for the current fiscal month and year.

### 2. Reading the Department Macro KPI Summary

At the top of the page, four summary cards provide a quick snapshot of department health:

- **Total Jam Departemen (Total Department Hours)**: Shows total cumulative actual overtime hours versus total planned budget hours, along with the remaining quota balance.
- **Indeks Burn Departemen (Department Burn Index %)**: Aggregate percentage consumption for the entire department with an operational status badge (**Aman**, **Terkendali**, **Peringatan**, or **Defisit**).
- **Matriks Kontrol Anggaran (Budget Control Matrix Zone)**: Aggregate quadrant status across all sections.
- **Status Risiko Seksi (Section Risk Status)**: Counter showing how many sections are currently in **Defisit** (danger), **Peringatan** (warning), or **Aman** (safe).

### 3. Filtering by Period and Department

- **Department Selector**: Plant Administrators can switch between departments using the dropdown at the top right. For Managers, this indicator shows their locked assigned department.
- **Month Picker**: Select any fiscal month (January to December) to review historical burn rates or plan ahead.
- **Year Picker**: Switch between fiscal years (e.g., 2025, 2026, 2027).

### 4. Reading Section Burn Cards

Each section within the department is represented by an analytical card featuring:

1. **Section Header**: Section code and name with department badge.
2. **Burn Index %**: Prominently displayed in large font with color coding:
    - **Green (< 85%)**: Safe / Under Budget.
    - **Blue (85–100%)**: On Track / Caution.
    - **Amber (101–115%)**: Warning / Accelerated Burn.
    - **Red (> 115%)**: Critical Deficit / Over Budget.
3. **Quota vs Actuals**: Realized hours versus planned quota (e.g., `142.5 / 200.0 jam`) and remaining quota balance.
4. **Budget Control Matrix Zone**:
    - `Zona 1: Sangat Baik (Aman)`: Healthy consumption well within planned boundaries.
    - `Zona 2: Baik (Terkendali)`: Normal high-volume production on track with quota.
    - `Zona 3: Peringatan (Burn Cepat)`: Unusually rapid burn relative to the calendar date.
    - `Zona 4: Defisit (Melebihi Anggaran)`: Actual hours have exceeded budget limits.
5. **Burn Velocity & Projected Total (Story E05-06)**:
    - **Kecepatan Burn (Burn Velocity)**: Average approved overtime hours consumed per operational week (`jam/mgg`). Evaluated in Western Indonesia Time (`Asia/Jakarta`) with automatic clamping to a minimum of 1.0 week to prevent statistical distortion during the first days of the month.
    - **Proyeksi Akhir (Projected Total)**: Forecasted month-end cumulative hours ($\text{Velocity} \times 4.3\text{ weeks}$).
    - **Trajectory Badge (Indikator Trajektori)**:
        - `→ Aman (On Pace)`: Projected hours are $\le 100\%$ of allocated quota. The section is consuming overtime at a safe, sustainable pace.
        - `↗ Waspada (Trending Over)`: Projected hours are between $100\%$ and $120\%$ of allocated quota. Early intervention (workload smoothing or shift redistribution) is recommended.
        - `↑ Kritis (Will Overrun)`: Projected hours exceed $120\%$ of quota. An active deficit warning requiring immediate managerial corrective action.
    - **Prediksi AI (Supervised Machine Learning Comparison)**:
        - When predictive intelligence models are active, section cards display a purple **AI** badge and a **Prediksi AI** comparison box (e.g., `Prediksi AI: 175.0 jam ±12.0 jam`).
        - Hovering over this box reveals the side-by-side comparison: `Heuristik: 182.0 jam | Prediksi AI: 175.0 jam ±12.0 jam`, allowing managers to contrast historical linear velocity with data-driven AI projections that account for holiday calendars and maintenance schedules.
6. **CapEx vs OpEx Mini Split Bar**: Shows the breakdown between routine production overtime (OpEx) and capitalized project hours (CapEx).

### 5. Handling Unconfigured Budgets

If a section has not yet configured its monthly quota in **Budget Planning**:

- The section card displays an **Anggaran Belum Dikonfigurasi** (Budget Not Configured) message.
- A direct shortcut link **Atur Anggaran di Planning →** allows managers to immediately jump to **Budget Planning** and allocate hours without seeing zero-division or NaN errors.

### 6. Auto-Refresh and Manual Refresh

- **Automatic Refresh**: The dashboard automatically checks for updated numbers every 60 seconds without reloading the page.
- **Manual Refresh**: Click the **Segarkan (Refresh)** button with the circular arrow icon in the top toolbar to fetch the latest approval figures immediately.

### 7. Inspecting Section 5-Week Burndown & Budget Control Matrix (Story E05-02)

To analyze overtime trends across the 5 weeks of the month without losing your dashboard overview, click anywhere on a section card or the **Lihat Burndown & Matriks** button. A slide-in drawer opens on the right side of the screen.

#### What is Inside the Burndown Drawer?

1. **Header & Status Badges**:
    - Shows the section name, code, department, active period, and a prominent Burn Index status badge (**Aman**, **Terkendali**, **Peringatan**, or **Defisit**).
    - If the budget is not configured, an alert banner provides a direct link to **Budget Planning**.

2. **Quick KPI Summary**:
    - **Rencana vs Realisasi**: Total approved hours vs allocated quota.
    - **Sisa Kuota**: Remaining available hours.
    - **Kecepatan Burn**: Current weekly consumption velocity (`jam/mgg`).
    - **Proyeksi Akhir**: Forecasted month-end consumption with trajectory indicator.

3. **5-Week Burndown Curve (`Kurva Burndown 5-Minggu`)**:
    - **X-Axis**: Minggu 1 through Minggu 5 with Indonesian calendar date ranges (e.g., `01 - 07 Sep`).
    - **Dashed Line (Slate)**: Cumulative planned target budget for each week.
    - **Solid Line (Color-coded)**: Cumulative actual approved hours. Future weeks are left empty so the line does not artificially plummet to zero.
    - **Dotted Line (Purple)**: AI/ML projected trajectory branching from the current week to the predicted month-end total.
    - **Shaded Warning Deficit Zone**: Light red background fill above the 100% budget ceiling.
    - **Shaded Safe Corridor**: Light blue background fill between 85% and 100% of the quota.

4. **Weekly Breakdown Table (`Rincian Jam Lembur Mingguan`)**:
    - Displays a week-by-week audit showing:
        - **Minggu #** and **Rentang Tanggal** (e.g., `08 - 14 Sep`). The active week is highlighted with an **Aktif** tag.
        - **Rencana**: Weekly planned quota hours.
        - **Realisasi**: Weekly approved overtime hours.
        - **Jam HKN**: Overtime performed on regular working days (Hari Kerja Normal).
        - **Jam HLR**: Overtime performed on weekends and factory holidays (Hari Libur Resmi).
        - **Akumulasi Burn**: Cumulative burn percentage at the end of each week.
        - **Deviasi**: Variance between actual and planned hours (`+` indicates overrun, `-` indicates savings).

5. **4-Quadrant Budget Control Matrix (`Matriks Kontrol Anggaran`)**:
    - A scatter plot plotting the section's position against the 4 quadrants:
        - **Zona 1 (Sangat Baik / Aman - Hijau)**: Low burn rate ($\le 100\%$) and low cumulative hours ($< 75\%$ of budget).
        - **Zona 2 (Terkendali / Baik - Biru)**: Normal burn rate ($\le 100\%$) with high volume ($\ge 75\%$ of budget).
        - **Zona 3 (Peringatan / Burn Cepat - Oranye)**: Rapid burn rate ($> 100\%$) early in the month before hours are fully exhausted.
        - **Zona 4 (Defisit Kritis - Merah)**: Exceeded budget ($> 100\%$) with hours exhausted ($\ge 75\%$).
    - A glowing marker indicates the section's exact coordinate with status details.

6. **Deep-Linking & Sharing**:
    - You can copy and share direct links to any section's burndown drawer:
      `/dashboard/burn-index?tab=sections&section=14`
    - Opening this URL automatically opens the drawer for section 14 while preserving active period and department filters.

### 6. CapEx vs OpEx Distribution & Capitalization Tab

For financial controllers, accounting teams, and department managers tracking labor capitalization compliance, click the **Distribusi CapEx vs OpEx** tab in the navigation bar.

#### Time Range Granularity (Rentang Waktu)

At the top of the tab, use the filter pills to switch time boundaries:

- **Bulan Ini (Default)**: Summarizes hours approved within the selected calendar month.
- **Tahun Berjalan (YTD)**: Aggregates hours from January 1st through the end of the selected month.
- **Kustom**: Enables start and end date pickers to inspect custom audit intervals (e.g., project installation milestones). Click **Terapkan** to run the query.

#### Capitalization KPI Summary Cards

Four executive summary cards give instant visibility into labor classification:

1. **Total Jam Lembur (Total Hours)**: All approved overtime hours with the total financial labor cost in Rupiah (`Rp`).
2. **Jam CapEx Proyek (CapEx Project Hours)**: Overtime spent on approved capital projects. Hover over the **CapEx Ratio %** badge to view the calculation formula:
   $$\text{Formula} = (\text{Total Jam Proyek} \div \text{Total Seluruh Jam}) \times 100\%$$
   Also shows total capitalized labor value in Rupiah (`Rp`).
3. **Jam OpEx Rutin (OpEx Routine Hours)**: Routine line production overruns, tooling maintenance, and shopfloor support with corresponding operating expense in Rupiah.
4. **Kepatuhan Kapitalisasi (Capitalization Compliance)**: Status indicator verifying whether labor has been separated into capitalized asset additions or routine operating expense.

#### Visual Distribution Charts

- **Distribusi CapEx vs OpEx (Donut Chart)**: Visual breakdown of CapEx (Sky Blue `#0284c7`) versus OpEx (Neutral Slate `#64748b`) with center percentage readout and interactive hover tooltips detailing hours, percentage, and Rupiah amount.
- **Perbandingan Jam per Seksi (Section Comparison Bar Chart)**: Side-by-side grouped bar chart displaying CapEx project hours and OpEx routine hours for each manufacturing section.

#### CapEx Project Performance Table (Kinerja Jam Tenaga Kerja Proyek CapEx)

A high-density table tracking progress and overtime consumption across all active capital projects:

- **Kode & Aset**: Monospace project code (e.g. `CPX-2026-ASSY-001`) and fixed asset tag.
- **Nama Proyek & Dept**: Project description and owning department.
- **Jam Periode & Total Akumulasi**: Hours logged in the selected filter period versus cumulative hours across all time.
- **Alokasi Kuota**: Target labor hours budget authorized for the project.
- **Deviasi (Variance)**:
    - **Emerald Green Badge**: Cumulative hours are under budget (e.g. `-30.0 jam`).
    - **ISUZU Red Badge**: Cumulative hours have overrun allocated budget (e.g. `+15.0 jam`).
- **Progress Fisik**: Visual progress bar indicating percentage completion of project milestones.
- **Status**: Operational status badge (`ACTIVE`, `PLANNING`, `COMPLETED`, `ON_HOLD`).
- **Live Search**: Use the search input at the top right to filter by project code, asset code, or project name.

---

## Frequently Asked Questions (FAQ)

### Why is a section card showing in red if we still have days left in the month?

A card turns red when the section's cumulative approved overtime hours exceed 115% of its monthly planned budget, or when hours have been recorded for a section that has no allocated budget.

### Do pending overtime submissions affect the Burn Index?

No. The Burn Index only tallies **Approved** overtime items. Submissions currently in draft or pending review in **Persetujuan Lembur** do not count toward actuals until a Manager or Admin approves them.

### When does the snapshot recalculate?

Snapshots recalculate automatically in the background whenever an overtime submission batch is approved. Managers can also click **Segarkan** to force an instant synchronization.

---

## Troubleshooting

| Issue                                                               | Cause                                                                | Solution                                                                                                        |
| ------------------------------------------------------------------- | -------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| Section card displays "Anggaran Belum Dikonfigurasi"                | No budget was allocated in Budget Planning for this fiscal month.    | Click **Atur Anggaran di Planning →** or navigate to **Budget Planning** from the sidebar to set planned hours. |
| Newly approved overtime is not showing immediately on the dashboard | Background snapshot rollup job is processing in the queue.           | Click the **Segarkan** button in the top toolbar to refresh data.                                               |
| Manager cannot view other plant departments                         | Role-based data scoping locks Managers to their assigned department. | Only users with the **Plant Administrator** role can switch between multiple departments.                       |
