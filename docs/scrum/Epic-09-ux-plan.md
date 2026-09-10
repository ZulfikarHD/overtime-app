# UX Plan — E09: Executive Dashboard & Analytics Decision Intelligence

> Created before implementation. This document is a hard constraint for all work in Epic E09.  
> Epic file: `docs/scrum/Epic-09.md`

---

## 1. Navigation Footprint

### 1.1 Executive Philosophy: Morning Standup Velocity & Strategic Decision Intelligence

In commercial vehicle assembly and automotive manufacturing plants (such as ISUZU Astra Motor Indonesia), plant leadership operates across two distinct operational cadences:

1. **Daily Operational Monitoring (Module A — Executive Operational Dashboard at `/dashboard`)**:
   Between 07:15 and 08:30 WIB, Department Managers (_Kepala Departemen_), Section Heads (_Kepala Seksi_), and Shift Supervisors (_Team Leaders_) conduct fast-paced morning standups. They require immediate situational awareness within 60 seconds:
    - _"Are we consuming overtime hours within planned budget trajectories today?"_
    - _"Which production sections or lines are experiencing acute overtime spikes?"_
    - _"Who are the top overtime contributors, and are there immediate fatigue or policy compliance risks?"_
      This operational view must load in $<1.5$ seconds, feature self-explanatory visual charts, and offer instant, client-side filtering without heavy page reloads.

    **Anti-Cramping Architecture on `/dashboard`**:
    Rather than stacking 4 KPI cards, 7 heavy Chart.js canvases, and a 50-row data table in an unreadable 4,500px vertical scroll (which causes severe scroll fatigue on mobile/tablet shopfloor devices), `/dashboard` organizes its operational scope into **3 progressive-disclosure tabs** below a persistently visible macro KPI bar:
    - **Persistent Header Band**: 4 Macro KPI Cards with Sparklines (Production Volume, Working Days, Active Headcount, Burn Index Plan vs Actual). Visible across all tabs for instant 60-second pulse.
    - **Tab 1: `Laju Lembur & Seksi` (`?tab=pacing`)** [Default]:
        - Hero: Daily Cumulative Burn Line Chart (Plan vs Actual vs ML Trajectory) (E09-02)
        - Mid: Section Burn Comparison Horizontal Bar Chart (E09-03)
        - _Focus: Operational standup pacing and section-level variance identification._
    - **Tab 2: `Distribusi & Tren` (`?tab=distribution`)**:
        - Top Row: Overtime Leaderboard (Top 10) + Category Donut (CapEx vs OpEx) (E09-04)
        - Bottom Row: 12-Month Trend Line + Daily Index Trend + Day Type Breakdown (HKN vs HLR) (E09-04)
        - _Focus: Resource allocation across people, work categories, and holiday shifts._
    - **Tab 3: `Daftar Karyawan` (`?tab=employees`)**:
        - Searchable Summary Employee Overtime Table (E09-05) with category filter pills, sorting, and pagination
        - Slide-in Quick-Look Dossier Drawer (`EmployeeQuickDossierDrawer.vue`) for immediate individual compliance review
        - _Focus: People, SPKL verification, and individual fatigue inspection._

2. **Strategic Planning & Financial Governance (Module B — Analytics & Decision Intelligence at `/analytics`)**:
   Weekly and monthly, Plant Heads (_Kepala Pabrik_), Finance Controllers, and Production Engineering Planners conduct deep-dive resource evaluations:
    - _"What is our projected overtime demand and cost run-rate for next month?"_
    - _"How does our overtime correlate with vehicle production volume and defect rates?"_
    - _"What happens to labor costs and safety thresholds if production increases by 20% next quarter?"_
    - _"Which operational anomalies or budget risks require immediate executive action?"_
      This strategic view requires deep multi-dimensional analytics organized across **6 dedicated client-side tabs** (`?tab=predictive|cost|correlation|scenario|insights|comparison`), backed by server-side aggregations and machine learning models.

#### Target User Personas:

- **Department Manager & Section Head (`Manager` Role)**: Factory managers managing 4–12 production sections (e.g., Stamping, Welding, Assembly, Painting, Quality). They have low tolerance for slow ERP pages and need 1-click visual diagnostics.
- **Plant Head & General Manager (`Admin` / `Manager` Role)**: Executive decision-makers who evaluate strategic cross-department performance, approve quarterly overtime allocations, and simulate production surge scenarios.
- **Corporate Finance Controller & Plant Auditor (`Admin` Role)**: Financial custodians who must audit actual labor costs against authorized budget ceilings, track Rupiah variances, and ensure strict segregation between Capital Expenditures (CapEx project hours) and Operating Expenditures (OpEx routine hours).
- **Frontline Team Leader (`Team Leader` Role)**: Shift supervisors on the shopfloor who review departmental overtime leaderboards and active headcount to balance shift allocations before dispatching technicians.

#### Core UX Principles for Epic E09:

1. **Two-Surface Architecture with Progressive Tab Segmentation**:
   All daily operational tracking is housed on `/dashboard` partitioned into 3 ergonomic tabs (`Laju & Seksi`, `Distribusi & Tren`, `Daftar Karyawan`). All strategic decision tools are housed on `/analytics` across 6 tabs. No orphan routes are permitted.
2. **Elimination of Scroll Fatigue (Smartphone-Primary UX)**:
   By segmenting `/dashboard` into tabs, each view fits cleanly within 1–2 mobile screen heights. A supervisor never has to swipe through 7 charts to find the employee roster.
3. **Instant Asynchronous Rendering & Visual Hierarchy**:
   Heavy metrics load progressively with animated loading skeletons (`ChartSkeleton.vue`). High-level KPI summary cards render first above the fold, followed by tabbed visual modules.
4. **Ergonomic Industrial Precision (ISUZU Design Standards)**:
   All numerical data (hours, currency, employee NPKs, units) enforces monospace tabular numerals (`font-mono tabular-nums`). The plant palette uses ISUZU Brand Red (`#cc0000`) for critical deficits, Sky Blue for CapEx, Slate for OpEx, and international traffic-light color grading for Burn Index zones ($<85\%$ Emerald, $85\text{--}100\%$ Blue, $101\text{--}115\%$ Amber, $>115\%$ ISUZU Red).
5. **Mandatory Prototype Pruning Compliance**:
   All synthetic, unvalidated formulas from legacy prototypes (`Dashboard/dashboard.html` and `AnalyticDecision/analytic-and-decision.html`) are strictly expunged:
    - ❌ **No synthetic ROI formula** (`ROI = 156% + 1.2 * OT%`) $\rightarrow$ Replaced with real CapEx/OpEx financial variance analysis.
    - ❌ **No synthetic Well-being formula** (`Well-being = 7.5 - 0.05 * OT%`) $\rightarrow$ Replaced with fatigue consecutive-week safety indicators from Epic-06.
    - ❌ **No subjective Difficulty Tiers (Index I–IV)** $\rightarrow$ Replaced with real hourly wage snapshots (`hourly_rate_snapshot`).
    - ❌ **No 1-to-5 Star Consistency Rating** $\rightarrow$ Replaced with objective Burn Index zone classification.
    - ❌ **No hard statutory blocks** $\rightarrow$ Handled via soft policy warning badges respecting Indonesian plant management discretion.

---

### 1.2 Sub-Epic Functional Separation

| Sub-Epic / Story                                                        | Category                                 | User-Facing Surface                                                                                                                                                  | Dedicated UI Needed?                                   | Current Status          |
| :---------------------------------------------------------------------- | :--------------------------------------- | :------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :----------------------------------------------------- | :---------------------- |
| **E09-00**: Vue Chart.js Component Library Setup                        | **Frontend Infrastructure**              | Shared Base Chart Library (`BaseLineChart.vue`, `BaseBarChart.vue`, `BaseDonutChart.vue`, `BaseScatterChart.vue`, `BaseMiniSparkline.vue`, `ChartSkeleton.vue`)      | ❌ No (Developer infrastructure & reusable components) | 🟢 Completed            |
| **E09-01**: Dashboard Header KPI Cards with Mini Sparklines             | **User-Facing Operational Cards**        | Operational Dashboard Header (`/dashboard`), 4 Metric Cards: Production Volume, Working Days, Man Power, Burn Index Plan vs Actual (Persistently visible above tabs) | ✅ Yes (Persistent top band of `/dashboard`)           | 🟢 Completed            |
| **E09-02**: Daily Burn Chart Index (Main Line Chart)                    | **User-Facing Visualization**            | Daily Burn Line Chart (`/dashboard?tab=pacing`), Plan vs Actual vs ML Trajectory, Budget Ceiling Threshold, Month & Dept Selector                                    | ✅ Yes (Hero chart on Tab 1 of `/dashboard`)           | 🟢 Completed            |
| **E09-03**: Section/Department Burn Comparison Chart                    | **User-Facing Comparison Chart**         | Horizontal Section Comparison Bar Chart (`/dashboard?tab=pacing`), Color-coded Burn Index Zones, Direct Section Drill-down Navigation                                | ✅ Yes (Mid section on Tab 1 of `/dashboard`)          | 🟢 Completed            |
| **E09-04**: Overtime Leaderboard, Category Donut & Day Type Charts      | **User-Facing Multi-Chart Grid**         | Distribution Grid (`/dashboard?tab=distribution`): Top 10 Leaderboard, CapEx/OpEx Donut, 12-Month Trend Line, Daily Index Trend, HKN vs HLR Breakdown                | ✅ Yes (Tab 2 of `/dashboard`)                         | 🟢 Completed            |
| **E09-05**: Summary Employee Overtime Table                             | **User-Facing High-Density Table**       | Searchable Employee Overtime Table (`/dashboard?tab=employees`), Mini Progress Bar, Mini Category Bar, SPKL Badges, Employee Dossier Drawer                          | ✅ Yes (Tab 3 of `/dashboard`)                         | 🟢 Completed            |
| **E09-06**: Analytics Page Shell & Tab Navigation                       | **User-Facing Shell & Framework**        | Analytics Hub (`/analytics`), 6-Tab Switcher with URL Query Param Sync (`?tab=...`), Global Filter Bar (Dept & Date Range), Export Trigger                           | ✅ Yes (Master Shell for Module B)                     | 🟢 Completed (7/7 AC)   |
| **E09-07**: Tab 1 — Predictive Analytics (_Prediksi Lembur_)            | **User-Facing Predictive Tab**           | Predictive Tab (`/analytics?tab=predictive`), Next-Month Forecast Cards, Section Forecast Bar Chart, 6-Month Trend, Seasonal 12-Month Curve                          | ✅ Yes (Tab 1 on `/analytics`)                         | 🟢 Completed (10/10 AC) |
| **E09-08**: Tab 2 — Cost Analysis (_Analisis Biaya_)                    | **User-Facing Financial Tab**            | Cost Analysis Tab (`/analytics?tab=cost`), IDR Cost KPI Cards, Dept Cost Horizontal Bar, 6-Month Stacked Area, Budget vs Actual, Cost Table                          | ✅ Yes (Tab 2 on `/analytics`)                         | 🟢 Completed (10/10 AC) |
| **E09-09**: Tab 3 — Correlation & Pattern (_Korelasi & Pola_)           | **User-Facing Bivariate Analytics**      | Correlation Tab (`/analytics?tab=correlation`), OT vs Production Scatter, Quality Metric Scatter (ERP guard), Optimal Sweet Spot Zone, Correlation Matrix            | ✅ Yes (Tab 3 on `/analytics`)                         | 🟢 Completed (9/9 AC)   |
| **E09-10**: Tab 4 — What-If Scenario Simulation (_Simulasi Skenario_)   | **User-Facing Simulation Cockpit**       | Scenario Tab (`/analytics?tab=scenario`), Production Volume Calculator, -50% to +50% Overtime Slider, Scenario Comparison Chart, Saved Scenarios                     | ✅ Yes (Tab 4 on `/analytics`)                         | 🟢 Completed (11/11 AC) |
| **E09-11**: Tab 5 — Key Insights & Management Actions (_Wawasan Kunci_) | **User-Facing Triage & Action Hub**      | Key Insights Tab (`/analytics?tab=insights`), Auto-Generated Risk Alerts, 30-Day Anomaly Detection Line Chart, Interactive Action Item Table                         | ✅ Yes (Tab 5 on `/analytics`)                         | 🟢 Completed (10/10 AC) |
| **E09-12**: Tab 6 — Period Comparison (_Perbandingan Periode_)          | **User-Facing Comparative Benchmarking** | Period Comparison Tab (`/analytics?tab=comparison`), Dual Period Pickers, YoY/MoM/QoQ Mode, Dept Benchmark Stacked Bar, Best Practice Insights                       | ✅ Yes (Tab 6 on `/analytics`)                         | ⏳ Pending (0/9 AC)     |

---

### 1.3 Minimal Navigation Footprint Decisions

#### Sidebar Navigation Additions:

- **For `Manager` and `Admin` Roles**:
    - **Existing Sidebar Item Upgraded (0 new items for Module A)**:
        - **Dashboard** (`/dashboard`, Icon: `LayoutGrid`) is transformed from the sprint 1 placeholder into the full **Executive Operational Dashboard** (Module A), structured into 3 tabs.
    - **New Sidebar Item Added (1 new item for Module B)**:
        - **Analitik & Keputusan** (_Analytics & Decision Intelligence_) — Route: `/analytics`.
        - Icon: `TrendingUp` or `LineChart`.
        - Features a subtle red notification badge if any Critical Risk is active in the Insights engine (`tab=insights`).
- **For `Team Leader` Role**:
    - **0 new sidebar items**.
    - Frontline Team Leaders access the upgraded `/dashboard` scoped strictly to their assigned production section and department. They do not access the strategic `/analytics` suite (preventing cognitive overload on the factory floor).
- **For `User / Operator` Role**:
    - **0 new sidebar items**.
    - Operators continue using their personal employee self-service dossier at `/my/dashboard` (governed by Epic E06).

#### Distinct Routes & Pages:

Across the entire epic, exactly **2 primary routes** are registered:

1. `/dashboard` — **Executive Operational Dashboard** (`resources/js/pages/Dashboard.vue`).
    - Serves as the morning standup operational nerve center.
    - Houses Stories E09-01 through E09-05 using a persistent KPI header bar and 3 tabs:
        - `?tab=pacing` (Laju Lembur & Seksi)
        - `?tab=distribution` (Distribusi & Tren)
        - `?tab=employees` (Daftar Karyawan)
2. `/analytics` — **Analytics & Decision Intelligence Hub** (`resources/js/pages/Analytics/Index.vue`).
    - Serves as the weekly strategic decision cockpit.
    - Houses Stories E09-06 through E09-12 across 6 client-side tabs synchronized via URL query parameters (`?tab=predictive|cost|correlation|scenario|insights|comparison`).

#### Surfaces Handled via Drawers/Sheets, Modals, or Tabs (Never Dedicated Routes):

- **3 Operational Dashboard Tabs**: Handled via client-side tabs inside `/dashboard` (`?tab=pacing|distribution|employees`), eliminating scroll fatigue.
- **6 Analytics Tabs**: All analytical views live as lazy-loaded tab components inside `/analytics`. They are **never** registered as standalone URL paths.
- **Employee Quick-Look Dossier**: Slide-in Right Drawer (`EmployeeQuickDossierDrawer.vue`) triggered when clicking an employee row in Tab 3 of `/dashboard`, allowing immediate compliance review without losing filter context.
- **Saved Scenarios Management Drawer**: Slide-in Right Drawer (`SavedScenariosDrawer.vue`) triggered on Tab 4 (`?tab=scenario`) of `/analytics`.
- **Management Action Status Dialog**: Lightweight single-level confirmation modal (`ActionItemStatusModal.vue`) triggered on Tab 5 (`?tab=insights`) of `/analytics`.
- **Executive Report Export Popover**: Action popover anchored to the "Export Report" button on `/analytics`.

---

## 2. Screen Inventory

| Screen / Panel                               | Location                                                                  | What's visible on first open                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | Further triggers                                                                                                                                                                                                                                        | Click depth                                                |
| :------------------------------------------- | :------------------------------------------------------------------------ | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | :--------------------------------------------------------- |
| **Executive Operational Dashboard (Shell)**  | `/dashboard` (`Pages/Dashboard.vue`)                                      | • **Page Header**: Title (_"Dashboard Operasional Eksekutif"_), Live WIB Clock, Shift indicator, Dept & Date filter bar<br>• **Persistent KPI Row** (4 Cards): Production Volume, Working Days, Man Power, Burn Index Plan vs Actual<br>• **Operational Tab Navigation**: 3 Tabs: `Laju Lembur & Seksi`, `Distribusi & Tren`, `Daftar Karyawan`<br>• Active tab content rendered immediately below                                                                                                                                                                                                                                          | • Clicking any tab switches view in $<50$ms and updates URL (`?tab=...`)<br>• Changing department or date filter updates all cards and charts via Inertia partial reload                                                                                | **0** (Default home for Managers/Admins)                   |
| **Tab 1: Laju Lembur & Seksi**               | `/dashboard?tab=pacing` (`Pages/Dashboard.vue`)                           | • **Hero Chart**: Daily Cumulative Burn Line Chart (Plan vs Actual vs ML Trajectory) with month navigation and threshold annotations (E09-02)<br>• **Mid Chart**: Horizontal Section Burn Comparison Bar Chart ranked by Burn Index % with zone color coding (E09-03)                                                                                                                                                                                                                                                                                                                                                                       | • Month arrows ($\leftarrow$/$\rightarrow$) update daily burn curve<br>• Clicking any section bar filters section or navigates to Section Detail (`/dashboard/burn-index?section=...`)                                                                  | **0** (Default active tab on `/dashboard`)                 |
| **Tab 2: Distribusi & Tren**                 | `/dashboard?tab=distribution` (`Pages/Dashboard.vue`)                     | • **Top Row (3-Col)**: Overtime Leaderboard (Top 10), Category Donut (CapEx vs OpEx), 12-Month Trend Line (E09-04)<br>• **Bottom Row (2-Col)**: Daily Index Trend Line, Day Type Breakdown Grouped Bar (HKN vs HLR) (E09-04)                                                                                                                                                                                                                                                                                                                                                                                                                | • Clicking a slice in the Category Donut sets the active category filter<br>• Hovering over data points reveals tooltips formatted in Indonesian locale                                                                                                 | **1** (Click Tab 2 on `/dashboard`)                        |
| **Tab 3: Daftar Karyawan**                   | `/dashboard?tab=employees` (`Pages/Dashboard.vue`)                        | • **Search & Filter Bar**: Instant client-side search box, Active category filter pill with reset trigger<br>• **High-Density Table**: Columns for Employee, Burn Index (mini progress bar), Total Hours, Category (mini stacked bar), Status SPKL<br>• **Pagination Footer**: 50 rows per batch with "Muat Lebih Banyak" button (E09-05)                                                                                                                                                                                                                                                                                                   | • Typing in search filters rows in real time ($<50$ms)<br>• Clicking any row opens **Employee Quick-Look Drawer**<br>• Column headers toggle sort ascending/descending                                                                                  | **1** (Click Tab 3 on `/dashboard`)                        |
| **Employee Quick-Look Drawer**               | Slide-in Sheet (Right) on `/dashboard` (`EmployeeQuickDossierDrawer.vue`) | • **Drawer Header**: Employee Name, NPK, Assigned Section/Dept, Active Role pill<br>• **Current Month Summary**: Total hours, Burn Index gauge, CapEx vs OpEx split bar<br>• **Policy Compliance Alert**: Consecutive weeks alert status, weekly limit indicator<br>• **Recent Shift History**: Last 5 approved overtime shifts with dates, hours, and SPKL numbers<br>• **Footer Action**: Primary button _"Buka Dossier Lengkap"_                                                                                                                                                                                                         | • Clicking backdrop or (X) button closes drawer without altering scroll position<br>• Clicking _"Buka Dossier Lengkap"_ navigates to `/reports/employees/{npk}`                                                                                         | **2** (Click employee row on Tab 3)                        |
| **Analytics & Decision Intelligence Hub**    | `/analytics` (`Pages/Analytics/Index.vue`)                                | • **Page Header**: Title (_"Analitik & Keputusan Lembur"_), Live WIB Clock, Global Filter Bar (Dept & Date Range), _"Ekspor Laporan"_ button<br>• **6 Navigation Tabs**: `Prediksi Lembur`, `Analisis Biaya`, `Korelasi & Pola`, `Simulasi Skenario`, `Wawasan Kunci`, `Perbandingan Periode`<br>• Active tab rendered dynamically via client-side transition (E09-06)                                                                                                                                                                                                                                                                      | • Clicking any tab switches view instantly and updates URL query param (`?tab=...`)<br>• Clicking _"Ekspor Laporan"_ opens the export dropdown menu                                                                                                     | **1** (Sidebar > Analitik & Keputusan)                     |
| **Tab 1: Predictive Analytics**              | `/analytics?tab=predictive` (`TabPredictive.vue`)                         | • **KPI Row** (4 Cards): Prediksi Bulan Depan (`jam ± batas`), Tingkat Akurasi MAPE, Pola Musiman, Arah Tren<br>• **Forecast Bar Chart**: Next-month predicted hours per section with confidence interval error bars<br>• **Trend Projection Line Chart (6 Bulan)**: Historical vs projected line with 90% confidence band<br>• **Seasonal Pattern Line Chart**: 12-month annual cycle curve highlighting peak quarter (E09-07)                                                                                                                                                                                                             | • Hovering on error bars displays confidence bounds<br>• Hovering on seasonal points displays historical monthly averages                                                                                                                               | **1** (Analytics Hub > Tab 1)                              |
| **Tab 2: Cost Analysis**                     | `/analytics?tab=cost` (`TabCostAnalysis.vue`)                             | • **KPI Row** (4 Cards): Total Biaya Lembur (`Rp 125,5 Jt`), Sisa Anggaran (Rp + progress bar), Rata-rata Biaya per Karyawan, Rasio Biaya CapEx<br>• **Biaya Lembur per Departemen**: Horizontal bar chart sorted desc by spend<br>• **Tren Biaya 6 Bulan**: Stacked area chart showing monthly OpEx vs CapEx Rupiah cost<br>• **Anggaran vs Realisasi**: Grouped bar chart with deficit shading<br>• **Cost Breakdown Table**: Department-level audit table with average rate/hr and budget consumption % (E09-08)                                                                                                                         | • Clicking column headers sorts table by Total Cost or Budget Variance<br>• Hovering on stacked area chart reveals exact OpEx and CapEx Rupiah split                                                                                                    | **1** (Analytics Hub > Tab 2)                              |
| **Tab 3: Correlation & Pattern**             | `/analytics?tab=correlation` (`TabCorrelation.vue`)                       | • **Lembur vs Volume Produksi**: Scatter plot with linear regression line and correlation coefficient ($r$)<br>• **Lembur vs Metrik Kualitas**: Scatter plot or graceful ERP fallback banner: _"Menunggu integrasi data kualitas dari ERP"_<br>• **Level Lembur Optimal**: 3-Zone area chart (Under-utilized, Sweet Spot 12–18 hrs/wk, Over-threshold)<br>• **Correlation Matrix Table**: Color-coded bivariate matrix ($                                                                                                                                                                                                                   | r                                                                                                                                                                                                                                                       | >0.7$ green, $0.4\text{--}0.7$ blue, $<0.4$ gray) (E09-09) | • Hovering on scatter points displays specific month, section, volume, and overtime hours<br>• Selecting different sections updates regression line reactively | **1** (Analytics Hub > Tab 3) |
| **Tab 4: What-If Scenario Simulation**       | `/analytics?tab=scenario` (`TabScenario.vue`)                             | • **Dual-Panel Layout**:<br> - **Left Panel: Production Volume Planning Calculator**: Inputs for Target Volume, Period, Section $\rightarrow$ _"Hitung"_ button $\rightarrow$ Hours, Cost Rp, Headcount, Efficiency %<br> - **Right Panel: Scenario Builder**: Overtime Change Slider ($-50\%$ to $+50\%$), Budget Allocation Input, Target Dept $\rightarrow$ _"Jalankan Skenario"_ $\rightarrow$ Projected Cost, Burn Index, Safety Risk Score<br>• **Scenario Comparison Chart**: Baseline vs Current vs Saved Scenarios<br>• **Saved Scenarios Bar**: List of saved scenarios with _"Simpan"_ and _"Kelola"_ triggers (E09-10)          | • Dragging slider updates percentage and projected cost label live in client state<br>• Clicking _"Hitung"_ computes labor requirements via historical labor factor<br>• Clicking _"Kelola Skenario"_ opens **Saved Scenarios Drawer**                  | **1** (Analytics Hub > Tab 4)                              |
| **Saved Scenarios Drawer**                   | Slide-in Sheet (Right) on Tab 4 (`SavedScenariosDrawer.vue`)              | • **Drawer Header**: Title (_"Daftar Skenario Tersimpan"_), Close button (X)<br>• **Scenario Card List**: Each card displays Scenario Name, Creation Date, Overtime % Change, Target Budget, and Projected Burn Index<br>• **Card Actions**: Primary _"Terapkan Skenario"_ button and Delete icon                                                                                                                                                                                                                                                                                                                                           | • Clicking _"Terapkan Skenario"_ loads parameters into builder and closes drawer<br>• Clicking Delete removes scenario with immediate toast feedback                                                                                                    | **2** (Tab 4 > Click _"Kelola Skenario"_)                  |
| **Tab 5: Key Insights & Management Actions** | `/analytics?tab=insights` (`TabInsights.vue`)                             | • **Risk Indicators Panel**: Dynamic alert cards (Budget Overrun, Employee Burnout, Efficiency Drop) with severity badges (_Kritis_, _Peringatan_, _Info_) and _"Lihat Rincian $\rightarrow$"_ links<br>• **Anomaly Detection Chart**: 30-day line chart with shaded mean $\pm 1$ standard deviation band and red scatter dots on flagged anomaly days<br>• **Management Action Items Table**: Priority, Action Item, Dept, Impact, Deadline, Status (_Tertunda_, _Dalam Pengerjaan_, _Selesai_) (E09-11)                                                                                                                                   | • Clicking _"Lihat Rincian $\rightarrow$"_ navigates to the relevant module/section detail<br>• Clicking a status badge in the Action Items table opens **Action Item Status Modal**<br>• Clicking _"Ekspor Action Plan"_ downloads structured CSV file | **1** (Analytics Hub > Tab 5)                              |
| **Action Item Status Modal**                 | Single-Level Modal on Tab 5 (`ActionItemStatusModal.vue`)                 | • **Modal Header**: Title (_"Perbarui Status Tindakan Manajemen"_), Action Item title<br>• **Status Selector**: Radio options: _Tertunda (Pending)_, _Dalam Pengerjaan (In Progress)_, _Selesai (Resolved)_<br>• **Resolution Note**: Textarea for resolution remarks (optional)<br>• **Action Buttons**: _"Batal"_, _"Simpan Perubahan"_                                                                                                                                                                                                                                                                                                   | • Selecting status and clicking _"Simpan Perubahan"_ updates state via Inertia POST, closes modal, and displays a success toast                                                                                                                         | **2** (Tab 5 > Click Status Badge)                         |
| **Tab 6: Period Comparison**                 | `/analytics?tab=comparison` (`TabComparison.vue`)                         | • **Comparison Configuration Bar**: Comparison Type dropdown (_YoY_, _MoM_, _QoQ_, _Dept Benchmarking_) + Dual Date Pickers<br>• **Comparison KPI Row** (4 Cards): Perubahan Jam Lembur (%), Perubahan Biaya (%), Perubahan Efisiensi (%), Perubahan Jumlah Karyawan<br>• **Period Comparison Grouped Bar Chart**: Month-by-month bars for base vs compare periods with percentage variance line overlay<br>• **Department Benchmarking Chart**: Horizontal stacked bar chart ranking departments<br>• **Best Practice Insight Cards**: Automated natural-language insight cards highlighting top-performing department strategies (E09-12) | • Changing comparison type or dates updates all charts automatically via Inertia partial reload<br>• Hovering over bar groups reveals exact variance and percentage delta                                                                               | **1** (Analytics Hub > Tab 6)                              |
| **Executive Report Export Popover**          | Dropdown / Popover on `/analytics` (`ExportReportPopover.vue`)            | • **Popover Content**: Title (_"Unduh Ringkasan Eksekutif"_), Format selection: _PDF (Executive 1-Page Summary)_ or _CSV (Data Mentah Tab Ini)_<br>• **Scope Indicator**: Displays active department and date filter scope<br>• **Primary Trigger**: _"Mulai Mengunduh"_ button with animated spinner during generation                                                                                                                                                                                                                                                                                                                     | • Clicking _"Mulai Mengunduh"_ initiates streamed file download directly from server without navigating away from page                                                                                                                                  | **2** (Click _"Ekspor Laporan"_ on `/analytics`)           |

---

## 3. User Journey Maps

### Journey 1: Morning Standup 60-Second Situational Assessment (Manager on `/dashboard?tab=pacing`)

```
Goal: Department Manager checks overnight shift burn, identifies section health, and spots top overtime spenders at 07:15 WIB standup
Starts at: /dashboard (Operational Dashboard Home)
Steps:
  1. Manager logs in and lands on the Executive Operational Dashboard (Tab 1: Laju Lembur & Seksi is active by default).
  2. Manager glances at the 4 Persistent Header KPI Cards:
     - Production Volume: 1.450 unit (On track).
     - Working Days: 18 / 22 hari kerja selesai.
     - Man Power: 384 active personnel.
     - Burn Index: 92.4% (Blue zone: On track).
  3. Manager reviews the Daily Burn Line Chart (Plan vs Actual vs ML Trajectory).
  4. Manager checks the Section Burn Comparison bar chart: Section "Welding 2" is flagged in Amber (104%), while all other sections are Green (<85%).
Done: Manager assesses complete departmental shift status in 45 seconds without scrolling past endless secondary charts.
Step count: 3 (Page open → Glance KPIs/Chart → Identify Section)
Status: ✅ OK (≤4 steps)
```

---

### Journey 2: Investigating Critical Section Overrun & Employee Follow-up (Manager on `/dashboard`)

```
Goal: Section Manager drills down into an amber-flagged section and checks specific technician fatigue on the shopfloor
Starts at: /dashboard
Steps:
  1. On Tab 1 (Laju Lembur & Seksi), Manager clicks the amber bar for "Welding 2" on the Section Comparison Chart.
  2. The dashboard automatically switches to Tab 3: "Daftar Karyawan" and pre-filters the table to Section "Welding 2".
  3. Manager spots the top row: Technician "Budi Santoso (NPK: 4102)" with 42.5 overtime hours and an Amber SPKL badge.
  4. Manager clicks Budi Santoso's row: The Employee Quick-Look Drawer slides in from the right, showing 3 consecutive high-overtime weeks and 5 recent shift records.
Done: Manager identifies root cause of section overrun and notes fatigue risk for shift rotation in 4 clean, focused steps.
Step count: 4 (Click bar → Switches to Tab 3 auto-filtered → Click employee row → Drawer inspection)
Status: ✅ OK (≤4 steps)
```

---

### Journey 3: Inspecting Category Distribution & Day Type Trends (Manager on `/dashboard?tab=distribution`)

```
Goal: Department Manager checks whether weekend/holiday overtime (HLR) or CapEx project work is driving the department's burn
Starts at: /dashboard
Steps:
  1. Manager clicks Tab 2: "Distribusi & Tren".
  2. Manager reviews the Overtime Leaderboard (Top 10 employees) and Category Donut (CapEx vs OpEx hours).
  3. Manager clicks the "CapEx Project" slice on the Category Donut: the view highlights that CapEx accounts for 28% of all overtime.
  4. Manager reviews the Day Type Breakdown chart: verifies that holiday overtime (HLR) dropped 15% this week.
Done: Manager understands overtime composition without visual noise from daily burn curves or employee tables.
Step count: 3 (Click Tab 2 → Review charts → Click category slice)
Status: ✅ OK (≤4 steps)
```

---

### Journey 4: Sizing Next Month's Overtime Quotas & Manpower Planning (Production Manager on `/analytics?tab=predictive`)

```
Goal: Production Manager evaluates next month's predicted overtime demand and checks seasonal historical patterns to prepare shift rosters
Starts at: Sidebar > Analitik & Keputusan (/analytics)
Steps:
  1. Manager clicks "Analitik & Keputusan" on the sidebar; the Analytics Hub opens on Tab 1: "Prediksi Lembur" (?tab=predictive).
  2. Manager reviews the KPI header: "Prediksi Bulan Depan: 1.250 jam ± 45 jam (MAPE: 8.2%) | Pola Musiman: Puncak (Q4)".
  3. Manager inspects the Section Forecast Bar Chart: Section Assembly 1 and Welding 1 require an additional 120 hours due to seasonal surge.
  4. Manager clicks the "Ekspor Laporan" button and selects "PDF" to print the executive forecast sheet for the monthly plant planning meeting.
Done: Manager prepares data-backed shift allocations in under 2 minutes.
Step count: 4 (Sidebar click → Review forecast cards → Inspect section chart → Export PDF)
Status: ✅ OK (≤4 steps)
```

---

### Journey 5: Monthly Financial Labor Audit & CapEx Segregation Review (Finance Controller on `/analytics?tab=cost`)

```
Goal: Corporate Finance Controller verifies monthly overtime spend against budget ceiling and audits CapEx fixed-asset labor attribution
Starts at: /analytics
Steps:
  1. Finance Controller navigates to `/analytics` and clicks Tab 2: "Analisis Biaya".
  2. Controller inspects the KPI summary:
     - Total Biaya Lembur: Rp 142.850.000.
     - Sisa Anggaran: Rp 22.150.000 (86.5% consumed).
     - Rasio Biaya CapEx: 24.5% (Rp 35.000.000 capitalized to Robot Cell 2).
  3. Controller reviews the 6-Month Stacked Area Chart, verifying that OpEx overtime spend remained flat while CapEx project work drove the month's variance.
  4. Controller sorts the Cost Breakdown Table by "% of Budget" descending and verifies that no individual cost center breached 100%.
Done: Financial audit completed with 100% CapEx/OpEx segregation verified for general ledger booking.
Step count: 4 (Open Analytics → Click Tab 2 → Inspect stacked area chart → Sort table)
Status: ✅ OK (≤4 steps)
```

---

### Journey 6: Running What-If Simulation for High-Volume Production Surge (Plant Head on `/analytics?tab=scenario`)

```
Goal: Plant Head simulates labor demand and cost impact if vehicle production increases by 25% due to a new fleet order
Starts at: /analytics
Steps:
  1. Plant Head opens `/analytics` and clicks Tab 4: "Simulasi Skenario".
  2. In the Production Volume Planning Calculator, Plant Head inputs Target Volume: "1.800 unit", Period: "Bulanan", Section: "Assembly Lini 1", and clicks "Hitung".
  3. The calculator instantly displays required hours (1.620 jam), estimated cost (Rp 40,5 Jt), and additional headcount needed (8 orang).
  4. Plant Head drags the Overtime Change slider to "+25%" in the Scenario Builder to inspect the Safety Risk Score (14% risk) and clicks "Simpan Skenario" as "Surge 1800 Unit Q4".
Done: Plant Head validates the production surge feasibility, saves the scenario for plant review, and checks safety compliance in 4 steps.
Step count: 4 (Click Tab 4 → Enter volume & click Hitung → Adjust slider → Save scenario)
Status: ✅ OK (≤4 steps)
```

---

### Journey 7: Triage Operational Risk Alerts & Resolving Action Items (Operations Manager on `/analytics?tab=insights`)

```
Goal: Operations Manager reviews auto-generated plant risk alerts and marks an overdue SPKL action item as "In Progress"
Starts at: /analytics
Steps:
  1. Manager clicks Tab 5: "Wawasan Kunci".
  2. Manager reviews the Risk Indicators Panel: "1 Kritis (Overrun Stamping Section), 2 Peringatan (3 Teknisi > 40 Jam/Minggu)".
  3. Manager scrolls down to the Management Action Items Table and locates item: "[WELD-02] Rotasi Teknisi Shift Lembur Berlebih".
  4. Manager clicks the "Tertunda" status badge, selects "Dalam Pengerjaan" in the lightweight modal, and clicks "Simpan Perubahan".
Done: Action item updated with immediate visual toggle and persistent audit logging in 4 steps.
Step count: 4 (Click Tab 5 → Review risk alert → Locate action item → Update status)
Status: ✅ OK (≤4 steps)
```

---

## 4. Indonesian UX Guardrails

### 4.1 Patience Thresholds

Shopfloor supervisors, section managers, and factory administrators operate in a high-stress manufacturing environment with rigid shift changeover windows (07:00, 15:00, 23:00 WIB). If software is sluggish or confusing, supervisors will abandon it for unverified paper logs:

- **Daily Morning Standup Review (Daily Task on `/dashboard`)**: Max **1–2 clicks**:
    - Open `/dashboard` $\rightarrow$ Instantly view 4 KPI cards and Tab 1 (Daily Burn curve + Section Comparison).
- **Section & Employee Drilldown (Daily Task on `/dashboard`)**: Max **2–3 clicks**:
    - Click section bar on Tab 1 $\rightarrow$ Dashboard auto-switches to Tab 3 (filtered to section) $\rightarrow$ Click employee row to open drawer.
- **Tab Switching on `/dashboard` & `/analytics`**: Max **1 click**:
    - Instant client-side tab render with zero full-page reload ($<50$ms).
- **Scenario Simulation Calculation (Weekly Task on Tab 4)**: Max **2 clicks**:
    - Input target volume $\rightarrow$ Click _"Hitung"_ $\rightarrow$ Immediate calculation display.
- **Report & Plan Export (Monthly Task on `/analytics`)**: Max **2 clicks**:
    - Click _"Ekspor Laporan"_ $\rightarrow$ Select PDF or CSV $\rightarrow$ Streamed download begins immediately.

---

### 4.2 Cognitive Load Budget & Anti-Cramping Standards

- **Strict Progressive Disclosure (Max 2–3 Primary Visual Elements per Viewport)**:
    - By organizing `/dashboard` into 3 dedicated tabs, each view presents **at most 2–3 primary visualization elements**:
        1. _Top Persistent Band_: 4 KPI summary cards (Production, Days, Headcount, Burn Index).
        2. _Tab 1 (`Laju Lembur & Seksi`)_: Exactly 2 charts: Daily Burn Line Chart + Section Comparison Bar Chart.
        3. _Tab 2 (`Distribusi & Tren`)_: Clean 3-col + 2-col multi-chart distribution grid.
        4. _Tab 3 (`Daftar Karyawan`)_: Dedicated, searchable employee table with quick-look drawer.
- **No Infinite Scrolling on Mobile**:
    - Eliminates the unreadable 4,500px mobile scroll. Each tab fits within 1–2 phone viewport scrolls.
- **Form & Input Budget**:
    - On Tab 4 (_Simulasi Skenario_), the Production Calculator requires only **3 input fields** (Target Volume, Period, Section) before triggering calculation.
    - The Scenario Builder utilizes a single tactile slider ($-50\%$ to $+50\%$) with a live reactive percentage label, eliminating manual algebraic entry.
- **Strictly Single-Level Layout (No Nested Modals)**:
    - Modals inside modals or drawers inside drawers are **strictly prohibited**.
    - Employee detail inspection uses a slide-in drawer (`EmployeeQuickDossierDrawer.vue`).
    - Action item status changes use a single-level modal (`ActionItemStatusModal.vue`).
    - Saved scenario management uses a slide-in drawer (`SavedScenariosDrawer.vue`).
- **ISUZU Brand Palette & Factory Floor Color Tokens**:
    - **ISUZU Brand Primary Red**: `#cc0000` (`var(--primary)`). Used for primary call-to-action buttons, active tab underlines, and critical deficit indicators ($>115\%$ Burn Index or $>100\%$ Budget breach).
    - **CapEx Labor (Capitalized Fixed Asset Labor)**: Sky Blue token (`bg-sky-100 text-sky-800 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800`).
    - **OpEx Labor (Routine Operational Overtime)**: Neutral Slate token (`bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300`).
    - **HKN (Hari Kerja Normal - Regular Workdays)**: Blue series (`#3b82f6`).
    - **HLR (Hari Libur Resmi - Official Weekend/Holiday Overtime)**: Amber series (`#f59e0b`).
    - **Burn Index Zone Semantics**:
        - _Aman / Safe_ ($<85\%$): Emerald Green (`#16a34a`).
        - _Sesuai Rencana / On Track_ ($85\text{--}100\%$): Sky / Royal Blue (`#2563eb`).
        - _Peringatan / Warning_ ($101\text{--}115\%$): Amber / Orange (`#d97706`).
        - _Defisit Kritis / Critical Overrun_ ($>115\%$): ISUZU Red (`#dc2626`).
- **Mandatory Monospace Tabular Figures**:
    - All overtime hours (`42.5 jam`), monetary amounts (`Rp 125.500.000`), Burn Index percentages (`104.2%`), employee NPKs (`ISZ-4091`), and production unit counts **must** use `font-mono tabular-nums` to eliminate layout jitter during live updates.
    - Currency strictly formatted as `Rp 125,5 Jt` for abbreviated KPI cards and `Rp 125.500.000` for detailed financial ledger tables (Indonesian thousand periods, comma decimals).

---

### 4.3 Trust Signals & Plant Compliance

- **Authentic Indonesian Manufacturing Terminology**:
  Avoid developer jargon or raw database column references. Enforce established Indonesian industrial plant terminology:
    - `Jam Lembur Disetujui` (Approved Overtime Hours)
    - `Hari Kerja Normal (HKN)` vs `Hari Libur Resmi (HLR)` (Standard Workday vs Holiday Overtime)
    - `Indeks Burn Bulanan (%)` (Monthly Burn Index %)
    - `Plafon Anggaran / Batas Anggaran` (Budget Ceiling)
    - `Zona Wajar (Sweet Spot)` (Optimal 12–18 hrs/week workload zone)
    - `Biaya Terkapitalisasi (CapEx)` (Capitalized Asset Labor Cost)
    - `Biaya Operasional (OpEx)` (Operational Expense Overtime)
    - `Surat Perintah Kerja Lembur (SPKL)` (Statutory Overtime Order)
    - `Peramalan Kebutuhan Lembur` (Predictive Overtime Demand Sizing)
- **Graceful ERP Integration Degradation (Zero Crash Guarantee)**:
  In automotive plants, ERP interfaces for vehicle production counts and automated quality defect rates can occasionally lag or disconnect. The UI must **never** crash, throw `500 Server Error`, or render blank cards.
    - If production volume is unavailable: Display a friendly slate banner: _"N/A — Integrasi data produksi ERP belum terhubung"_.
    - If quality defect metrics are unavailable: Display a neutral info badge on Tab 3: _"Menunggu integrasi data kualitas dari ERP — Analisis korelasi produksi tetap aktif"_.
    - All financial and overtime burn charts must remain 100% operational regardless of external ERP feed status.
- **Explicit Prototype Pruning Enforcement**:
  The implementing engineer must strictly adhere to the BA pruning directives (§4):
    - **No Synthetic ROI**: The prototype's arbitrary formula (`ROI = 156% + 1.2 * OT%`) is replaced with real CapEx/OpEx financial variance and budget surplus/deficit figures.
    - **No Synthetic Well-being Score**: The arbitrary formula (`Well-being = 7.5 - 0.05 * OT%`) is replaced with real employee fatigue indicators (consecutive weeks working $>40$ hours, derived from Epic-06).
    - **No Subjective Difficulty Index**: The arbitrary 4-tier pricing (Index I–IV) is replaced with real `hourly_rate_snapshot` from approved submissions.
    - **No Star Ratings**: Employee overtime is not a sport or competition; 1-to-5 star ratings for high hours are replaced with objective Burn Index zone classifications.
    - **Soft Policy Warnings over Hard Blocks**: Day type calculations maintain strict statutory distinctions (HKN vs HLR multipliers), but statutory limits are presented as soft warning indicators to preserve management operational discretion.
- **Visual Feedback & Optimistic State Updates**:
    - Toggling action item status on Tab 5 triggers an immediate optimistic UI update with a success toast notification (_"Status tindakan manajemen berhasil diperbarui"_).
    - Tab switching executes client-side in $<100$ms with subtle fade transitions, preserving background filter selections.
    - Independent loading skeletons (`ChartSkeleton.vue`) prevent whole-page layout shifts while chart canvases initialize.

---

### 4.4 Risks Specific to This Epic & Mitigations

| Sub-Epic / Feature                                        | Identified UX / Technical Risk                                                                                                  | Mandatory Design Mitigation                                                                                                                                                                                                                                                             |
| :-------------------------------------------------------- | :------------------------------------------------------------------------------------------------------------------------------ | :-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **E09-01 to E09-05 (Cramped Dashboard & Slow Page Load)** | Cramming 7 charts and a table into one flat page creates massive cognitive overload, visual clutter, and slow mobile rendering. | **Segment `/dashboard` into 3 dedicated tabs**: (1) `Laju Lembur & Seksi`, (2) `Distribusi & Tren`, (3) `Daftar Karyawan`. Keep the 4 KPI cards persistently visible above the tabs.                                                                                                    |
| **E09-01 & E09-09 (Missing External ERP Feeds)**          | ERP feed for production units or defect rates is disconnected, causing division-by-zero errors or broken chart canvases.        | Implement defensive fallback guards in `DashboardController` and `AnalyticsController`. If ERP data is null, return clean status objects rendering friendly placeholder banners (_"ERP feed not connected"_). Never throw uncaught exceptions.                                          |
| **E09-02 (Visual Clutter in Daily Burn Chart)**           | Plotting 30 days of actuals, plan curve, and ML trajectory creates an unreadable spaghetti chart on mobile/tablet screens.      | Enforce distinct stroke styles: Plan = dashed blue line, Actual = solid line with zone color, ML Trajectory = dotted violet line. Provide toggleable legend chips to hide/show individual series.                                                                                       |
| **E09-04 (Category Donut Misleading Proportions)**        | A tiny 1% CapEx slice is unclickable on touchscreen tablets, frustrating shift managers trying to filter the table.             | Set `minAngle` on Chart.js donut slices. Provide an external clickable legend next to the donut showing absolute hours, percentages, and color pills that trigger the table filter.                                                                                                     |
| **E09-05 (Table Freezing Browser on Large Departments)**  | A department with 500+ employees renders 500 DOM rows simultaneously, causing search input lag and scroll jitter.               | Enforce virtualized pagination: render the first 50 rows by default with a _"Muat Lebih Banyak"_ button. Implement debounced client-side filtering (`watch(searchQuery)`) with a 150ms debounce window.                                                                                 |
| **E09-06 (Disorienting Full-Page Reloads on Tab Switch)** | Switching between the 6 analytics tabs causes full browser reloads, resetting the department dropdown and date range pickers.   | Build `/analytics` as a single-page shell (`Index.vue`) where tabs switch via client-side Vue components (`<component :is="activeTabComponent" />`). Synchronize tab state with the URL query param (`?tab=...`) using `router.replace({ preserveState: true, preserveScroll: true })`. |
| **E09-10 (Unrealistic Simulation Slider Inputs)**         | A manager drags the simulation slider or enters 1,000,000 production units, producing absurd numbers or graphical distortion.   | Enforce strict boundary validation in `ScenarioCalculatorService` and frontend inputs: Overtime change slider is strictly clamped between $-50\%$ and $+50\%$; production volume is clamped to $10\times$ historical maximum.                                                           |
| **E09-11 (Action Item Status Ambiguity)**                 | Managers mark action items resolved without documentation, breaking corporate audit accountability.                             | Opening the status modal requires selecting the target state and provides an optional resolution note textarea. Changes are saved with the authenticated manager's User ID and timestamp.                                                                                               |
| **E09-12 (Division-by-Zero in YoY Comparison)**           | A newly established department has 0 overtime hours in the previous year, causing `+Infinity%` or `NaN%` display errors.        | Handle zero-denominator cases gracefully: if `compare_hours == 0`, render `"+100% (Proyek Baru)"` or `"Data Tahun Lalu Belum Ada"` with an informative tooltip instead of `NaN%`.                                                                                                       |

---

## 5. Implementation Boundaries (Anti-Splitting Rules)

These boundaries are rigid architectural constraints for the implementing engineer and AI coding subagents. Any deviation will violate standup velocity, degrade chart rendering performance, or break navigational consistency.

### 5.1 Do Not Split — Combine Into One Surface:

- **Executive Operational Dashboard (`/dashboard`)**:
    - All operational monitoring components (Header KPI Cards E09-01, Daily Burn Line Chart E09-02, Section Comparison Bar E09-03, Lower Chart Grid E09-04, and Summary Employee Table E09-05) **MUST live under the single route `/dashboard`**, organized into 3 tabs:
        - Tab 1: `Laju Lembur & Seksi` (`?tab=pacing`)
        - Tab 2: `Distribusi & Tren` (`?tab=distribution`)
        - Tab 3: `Daftar Karyawan` (`?tab=employees`)
    - **Do NOT** split these into separate disjointed route URLs (e.g., do NOT create `/dashboard/charts`, `/dashboard/leaderboard`, or `/dashboard/employee-summary`).
    - **Do NOT** cram all 5 sub-epics into one vertical scroll without tabs.
- **Analytics & Decision Intelligence Hub (`/analytics`)**:
    - All strategic decision modules (Predictive E09-07, Cost E09-08, Correlation E09-09, Scenario E09-10, Insights E09-11, and Comparison E09-12) **MUST live together inside the master shell**: `resources/js/pages/Analytics/Index.vue`.
    - **Do NOT** split these into isolated route controllers (e.g., do NOT create `/analytics/predictive`, `/analytics/cost`, `/analytics/simulation`).

---

### 5.2 Make a Tab, Not a New Route:

- The 3 operational dashboard views **MUST be client-side tabs** inside `/dashboard`:
    1. `?tab=pacing` $\rightarrow$ Daily Burn Line Chart + Section Comparison Bar Chart
    2. `?tab=distribution` $\rightarrow$ Multi-Chart Grid (Leaderboard, Category Donut, 12-Month Trend, Daily Index, Day Type)
    3. `?tab=employees` $\rightarrow$ Summary Employee Overtime Table
- The 6 strategic intelligence modules **MUST be client-side tabs** inside `/analytics`:
    1. `?tab=predictive` $\rightarrow$ `TabPredictive.vue`
    2. `?tab=cost` $\rightarrow$ `TabCostAnalysis.vue`
    3. `?tab=correlation` $\rightarrow$ `TabCorrelation.vue`
    4. `?tab=scenario` $\rightarrow$ `TabScenario.vue`
    5. `?tab=insights` $\rightarrow$ `TabInsights.vue`
    6. `?tab=comparison` $\rightarrow$ `TabComparison.vue`
- Tab switching must execute seamlessly in client state, updating the browser URL query parameter with `router.replace({ preserveState: true, preserveScroll: true })`.

---

### 5.3 Make a Drawer/Sheet or Modal, Not a Full Page:

- **Employee Quick-Look Dossier**:
    - Previewing an employee's Burn Index, recent shift history, and fatigue indicators from the dashboard table MUST be handled via a slide-in right drawer (`EmployeeQuickDossierDrawer.vue` using Shadcn/Radix `Sheet`), **NOT a standalone route**.
- **Saved Scenarios Management**:
    - Managing saved scenario presets (E09-10) MUST be handled via a slide-in right drawer (`SavedScenariosDrawer.vue`), **NOT a separate settings page**.
- **Action Item Status Transition**:
    - Changing action item lifecycle states on Tab 5 MUST occur inside a lightweight single-level modal (`ActionItemStatusModal.vue`), **NOT an isolated workflow screen**.
- **Executive Report Export**:
    - Exporting PDF or CSV reports MUST be triggered via a toolbar popover/dropdown (`ExportReportPopover.vue`) that initiates a direct file download stream.

---

### 5.4 Backend-Only — Strictly No Dedicated UI:

The following components and services are pure algorithmic, background, or data-pipeline logic. **Do NOT create dedicated navigation menus, route endpoints, or administrative configuration pages for them**:

- **Shared Chart Infrastructure (`E09-00`)**: Tree-shaking Chart.js registrations, global canvas defaults, font configurations, and color token providers.
- **`PearsonCorrelationService`**: Statistical calculation engine computing correlation coefficients ($r$) and linear regression slope/intercept values.
- **`ScenarioCalculatorService`**: Headless math engine calculating required labor hours, headcount, and safety risk scores based on historical labor factors.
- **`InsightAggregatorService`**: Asynchronous risk detection engine evaluating `monthly_burn_snapshots`, `ml_anomaly_logs`, and policy thresholds to generate management alerts.
- **`ERP Degradation Handler`**: Defensive service formatting graceful fallback payloads for disconnected external production/quality feeds.
- **Streaming Report Exporters**: Headless PDF and CSV generation workers streaming formatted spreadsheets directly to the client browser.

---

### 5.5 Strictly Forbidden:

1. **Do NOT use legacy Ziggy `route()` helper**: Always use typed **Laravel Wayfinder** functions imported from `@/actions` or `@/routes`.
2. **Do NOT split `/analytics` or `/dashboard` into multiple standalone routes**: Keep `/dashboard` to 3 tabs and `/analytics` to 6 tabs.
3. **Do NOT restore or port the 5 pruned prototype elements**:
    - ❌ NO synthetic ROI calculation (`ROI = 156% + 1.2 * OT%`).
    - ❌ NO synthetic Well-being Score (`Well-being = 7.5 - 0.05 * OT%`).
    - ❌ NO subjective 4-tier Difficulty Index pricing (Index I–IV).
    - ❌ NO 1-to-5 star consistency ratings for overtime volume.
    - ❌ NO hard statutory blocks that crash or lock daily operational entry.
4. **Do NOT cram all 5 operational sub-epics into one vertical scroll without tabs**: Respect mobile viewports and cognitive load budgets.
5. **Do NOT run live un-indexed raw item queries during dashboard load**: Dashboard queries must aggregate from `monthly_burn_snapshots` or use indexed queries on `operational_date + section_id + status` to ensure load times $<1.5$ seconds.
6. **Do NOT throw 500 errors or render blank cards when ERP feeds are disconnected**: Always display polite, informative fallback banners (_"ERP feed not connected"_).
7. **Do NOT trigger full-page browser reloads when switching tabs or applying filters**: Use Inertia partial reloads (`preserveScroll: true`, `preserveState: true`) or client-side reactive state.
8. **Do NOT create nested modals**: Modals or sheets stacked inside other modals or sheets are strictly forbidden.
9. **Do NOT use variable-width fonts for numbers, hours, currencies, or NPKs**: Always enforce `font-mono tabular-nums`.
10. **Do NOT omit loading skeletons or empty states**: Every chart and KPI card must provide a `ChartSkeleton.vue` loading state and a _"Belum ada data"_ empty state.
