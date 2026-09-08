# UX Plan — E05: Budget Management & Burn Index Dashboard

> Created before implementation. This document is a hard constraint for all work in Epic E05.
> Epic file: `docs/scrum/Epic-05.md`

---

## 1. Navigation Footprint

### 1.1 Executive Philosophy: Morning Standup Velocity & Proactive Overrun Prevention

Epic E05 delivers the analytical command center of the Overtime & CapEx Labor Management System (OT-CapEx System). Every morning between 07:15 and 08:30 WIB, Department Managers (_Kepala Departemen_), Section Managers (_Kepala Seksi_), Finance Controllers, and Plant Administrators conduct morning standup meetings. They need an instantaneous, authoritative answer to one vital question: **"Are our production sections consuming overtime at a safe, sustainable pace, or are we heading toward a budget overrun?"**

Primary users are Indonesian manufacturing managers, section heads, and corporate controllers. Their operational realities:

- **Extreme Time Pressure & Low Patience**: Morning standup reviews last only 10 to 15 minutes before the daily production shift accelerates. Managers cannot navigate fragmented menus, wait for heavy database queries to crunch raw timesheets, or parse dense multi-page accounting ledgers.
- **Mid-Level Digital Literacy**: Highly proficient with clean mobile apps (WhatsApp, digital banking, Tokopedia) but frustrated by labyrinthine enterprise ERP grids. They expect immediate visual cues (color-coded statuses, intuitive trend arrows, clean gauges) rather than abstract statistical matrices.
- **Financial Compliance & CapEx Accountability**: Finance controllers must enforce strict segregation between operational expenditures (routine production and TPM overtime) and capital expenditures (capitalized labor for machine installation and automation projects). Mixing these budgets risks audit penalties and distorted cost accounting.
- **Asynchronous Snapshot Source of Truth**: To preserve zero-latency performance during peak hours, dashboard queries must **never** aggregate live `overtime_items` on every page load. Data is sourced from the denormalized `monthly_burn_snapshots` table, kept fresh asynchronously via `RecalculateMonthlyBurnSnapshotJob`.

The core UX principles for Epic E05 are:

1. **Single Analytical Hub**: Section burn cards, consolidated department tables, and CapEx vs OpEx distribution live on **exactly one primary dashboard page** organized by intuitive tabs.
2. **Glanceable RAG Status & 4-Quadrant Control Matrix**: Immediate color-coded status (<85% Emerald: Safe, 85–100% Blue: On Track, 101–115% Amber: Warning, >115% ISUZU Red: Critical Deficit) paired with plain-language Budget Control Matrix zones.
3. **Slide-in Burndown Deep-Dive (Zero Context Loss)**: When a manager investigates an over-burning section, the 5-week burndown line chart and scatter plot slide in from the right as a drawer (`Sheet`), preserving active filters, tab selection, and background scroll position.
4. **Predictive Velocity & Period-End Trajectory**: Clear heuristic trajectory indicators (`→ Aman / On Pace`, `↗ Waspada / Trending Over`, `↑ Kritis / Will Overrun`) warn managers weeks before budget exhaustion happens.
5. **Standup-Ready One-Click PDF Export**: A single toolbar trigger streams an executive-ready 1-page summary PDF for physical distribution at management briefings.

---

### 1.2 Sub-Epic Functional Separation

| Sub-Epic / Story                                                         | Category                            | User-Facing Surface                                                                                                                                                                                                      | Dedicated UI Needed?                                                            |
| :----------------------------------------------------------------------- | :---------------------------------- | :----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :------------------------------------------------------------------------------ |
| **E05-01**: Section-Level Burn Index Dashboard (Manager/Team Leader)     | **User-Facing Analytics**           | Burn Index Hub (`/dashboard/burn-index?tab=sections`), Section Cards Grid (`BurnIndexCard.vue`), Month & Year Picker, CapEx/OpEx Mini Bar (`CapexOpexSplitBar.vue`), 60s Polling                                         | ✅ Yes                                                                          |
| **E05-02**: 5-Week Monthly Burndown Chart (Manager/Admin)                | **User-Facing Visualization**       | Slide-in Deep-Dive Drawer (`SectionBurndownSheet.vue`), 5-Week Burndown Line Chart (`BurndownLineChart.vue`), 4-Quadrant Scatter Plot (`BudgetMatrixScatter.vue`), Tabular Weekly Breakdown (`WeeklyBreakdownTable.vue`) | ✅ Yes (Slide-in Drawer; not a full page)                                       |
| **E05-03**: CapEx vs OpEx Distribution Panel (Manager/Admin)             | **User-Facing Financial Tab**       | CapEx vs OpEx Tab (`/dashboard/burn-index?tab=capex-opex`), Donut Chart (`CapexOpexDonutChart.vue`), Section Comparison Bar Chart, CapEx Project Variance Table (`CapexProjectTable.vue`)                                | ✅ Yes (Tab on Hub; not a separate route)                                       |
| **E05-04**: Policy Threshold Alert System (Budget Warnings)              | **Background Alert & UI Cue**       | Topbar Notification Bell (`NotificationBell.vue`), Pulsing Warning/Danger Card Borders (`burn-card--warning`, `burn-card--danger`), Automated `BudgetAlertService`                                                       | ✅ Yes (Topbar bell item + animated card border; evaluation engine is headless) |
| **E05-05**: Department-Level Consolidated Burn Dashboard (Manager/Admin) | **User-Facing Matrix Tab & Export** | Consolidated Table Tab (`/dashboard/burn-index?tab=department`), Ranked Section Table (`SectionBurnTable.vue`), Department KPI Summary Header, Standup PDF Export Trigger                                                | ✅ Yes (Tab on Hub + streamed PDF; not a separate route)                        |
| **E05-06**: Burn Velocity & Projected Period-End Calculation             | **Algorithmic Engine & Visual Cue** | Visual Trajectory Badges (`→ Aman`, `↗ Waspada`, `↑ Kritis`), Heuristic Period-End Projection Display, `BurnIndexCalculatorService` (CALC-04 & CALC-05)                                                                  | ✅ Yes (Inline visual badges; math engine is headless)                          |

---

### 1.3 Minimal Navigation Footprint Decisions

#### Sidebar Navigation Additions:

- **For `Manager` Role**:
    - Adds **1 primary sidebar item**:
        - **Burn Index** — Route: `/dashboard/burn-index`.
        - Features a dynamic status badge if any section under their department breaches warning (🟠) or critical deficit (🔴) thresholds.
- **For `Admin` Role**:
    - Shares the **1 sidebar item**:
        - **Burn Index** — Route: `/dashboard/burn-index`.
        - Displays plant-wide cross-department aggregation with a multi-department selector.
- **For `Team Leader` Role**:
    - Adds **0 sidebar items**.
        - Frontline Team Leaders do not manage multi-department analytical portfolios. Their section's monthly burn progress bar is already natively integrated into the Daily Timesheet Form header (`/overtime/submissions/create` from Epic E03). Tapping that bar opens the single-section view scoped to their assigned section.
- **For `User / Operator` Role**:
    - Adds **0 sidebar items**. (Operators review personal hours and shifts in Epic E06).

#### Distinct Routes & Pages:

Across the entire 45-story-point epic, exactly **1 primary route** is created:

1. `/dashboard/burn-index` — The Unified Burn Index & Budget Analytics Command Center (`resources/js/pages/dashboard/BurnIndex.vue`).

#### Surfaces Handled via Tabs, Drawer/Sheet, or Modal (Never Dedicated Routes):

- **Section Burn Cards View**: Tab 1 (`?tab=sections`) on `/dashboard/burn-index`. High-density responsive grid of section cards.
- **Department Consolidated Table View**: Tab 2 (`?tab=department`) on `/dashboard/burn-index`. Sortable, ranked table of sections with department-level KPI headers.
- **CapEx vs OpEx Distribution & Projects**: Tab 3 (`?tab=capex-opex`) on `/dashboard/burn-index`. Financial capitalization breakdown, donut charts, and project variance tracking.
- **Section Burndown & Control Matrix Drawer**: Slide-in Right Drawer (`SectionBurndownSheet.vue`) containing the 5-week burndown line chart, 4-quadrant scatter plot, and weekly breakdown table.
- **Standup PDF Report Generator**: Direct toolbar action button triggering a streamed server-side PDF download (`barryvdh/laravel-dompdf`).
- **Policy Threshold Alert**: Topbar Notification Bell (`NotificationBell.vue`) item linking directly to the flagged section's burndown drawer.

#### Pure Backend & Headless Logic (No UI):

- `BurnIndexCalculatorService`: Domain calculation engine executing formulas CALC-02 (Burn Index %), CALC-03 (Remaining Hours), CALC-04 (Burn Velocity), CALC-05 (Projected Period-End), and CALC-07 (CapEx Ratio) using `Asia/Jakarta` elapsed weeks.
- `MonthlySnapshotService`: Read-optimized OLAP query layer retrieving pre-computed snapshots from `monthly_burn_snapshots` with on-demand fallback calculation if snapshots are missing.
- `RecalculateMonthlyBurnSnapshotJob`: Asynchronous Redis queue job triggered automatically post-approval to upsert snapshots.
- `BudgetAlertService`: Automated policy evaluator checking `burn_warning_pct` and `burn_danger_pct` thresholds and creating database notifications with `warned_at` and `danger_at` deduplication.
- Server-Side PDF Streaming: Headless DomPDF generation pipeline compiling print-optimized HTML templates into single-page executive PDFs.

---

## 2. Screen Inventory

| Screen / Panel                                                 | Location                                                              | What's visible on first open                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            | Further triggers                                                                                                                                                                                                                                                                                                                                                               | Click depth                                    |
| :------------------------------------------------------------- | :-------------------------------------------------------------------- | :-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :--------------------------------------------- |
| **Burn Index Hub — Section Cards Tab (Tab 1)**                 | `/dashboard/burn-index?tab=sections` (`BurnIndex.vue`)                | • **Page Header**: Title ("Dashboard Burn Index & Anggaran"), WIB Live Clock, Department Selector dropdown (Manager auto-locked; Admin selectable), Fiscal Month & Year picker (defaults to current month `now('Asia/Jakarta')`)<br>• **Auto-Refresh Indicator**: "Pembaruan otomatis tiap 60 detik" with relative timestamp ("Terakhir diperbarui: 2 menit yang lalu") and manual refresh button<br>• **Department KPI Summary Bar**:<br> - Total Department Hours: Planned vs Actual (`1.245 / 2.000 jam`)<br> - Department Burn Index: `62.3%` (color-coded badge)<br> - Overall Matrix Zone: `ZONE_1_EXCELLENT`<br> - High-Risk Section Counter: `0 Seksi Defisit`, `1 Seksi Peringatan`<br>• **Navigation Tabs**: `Ringkasan Seksi (Cards)`, `Konsolidasi Departemen (Tabel)`, `Distribusi CapEx vs OpEx`<br>• **Grid of Section Burn Cards** (3-4 cards per row):<br> - Section Name & Department badge<br> - Large Burn Index % with color-coded ring/dial: `<85%` Emerald, `85–100%` Blue, `101–115%` Amber, `>115%` ISUZU Red<br> - Planned vs Actual hours (`142.5 / 200.0 jam`) & Remaining hours (`57.5 jam`)<br> - Budget Control Matrix Zone badge: `ZONE_1_EXCELLENT`, `ZONE_2_GOOD`, `ZONE_3_WARNING`, `ZONE_4_POOR`<br> - Weekly Burn Velocity (`35.6 jam/minggu`) & Period-End Projection (`182.0 jam`)<br> - Visual Trajectory indicator: `→ Aman (On Pace)`, `↗ Waspada (Trending Over)`, `↑ Kritis (Will Overrun)`<br> - CapEx vs OpEx mini split bar (Sky blue vs Slate)<br> - "Anggaran Belum Dikonfigurasi" empty state card (if no budget set)<br>• **Header Action**: `Unduh Laporan PDF (Export PDF)` button | • Clicking any Section Card opens **Section Burndown Drawer** (`SectionBurndownSheet.vue`)<br>• Changing Month/Year updates all data seamlessly via Inertia partial reload (`router.reload({ only: ['snapshots'] })`)<br>• Switching tabs updates URL query parameter (`?tab=...`) with zero full-page reload<br>• Clicking `Unduh Laporan PDF` initiates standup PDF download | **1** (Sidebar > Burn Index)                   |
| **Burn Index Hub — Department Consolidated Table Tab (Tab 2)** | `/dashboard/burn-index?tab=department` (`DepartmentBurnTableTab.vue`) | • Same Header, Date Pickers, and Department KPI Summary Bar<br>• **Consolidated Ranked Table** (Sections sorted by Burn Index descending — highest burn first):<br> - Rank & Section Name (with code pill)<br> - Planned Hours (`200.0 jam`)<br> - Actual Hours (`142.5 jam`)<br> - Remaining Hours (`57.5 jam`)<br> - Burn Index % with color-coded progress pill<br> - Budget Control Matrix Zone badge<br> - Weekly Burn Velocity (`35.6 jam/mgg`)<br> - Projected Period-End Total (`182.0 jam`)<br> - Trajectory Pill (`→ Aman`, `↗ Waspada`, `↑ Kritis`)<br> - Quick Action button: `Detail →`<br>• Row background highlights: Subtle amber tint for Warning (>100%), subtle red tint for Danger (>115%)<br>• Table Search input to filter sections by name                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | • Clicking any table row or `Detail →` opens **Section Burndown Drawer**<br>• Clicking table column headers sorts by Planned, Actual, Burn %, or Velocity<br>• Typing in search bar filters table rows with zero-latency client-side filter<br>• Clicking `Unduh Laporan PDF` streams PDF table                                                                                | **1** (Sidebar > Burn Index > Tab 2)           |
| **Burn Index Hub — CapEx vs OpEx Distribution Tab (Tab 3)**    | `/dashboard/burn-index?tab=capex-opex` (`CapexOpexTab.vue`)           | • Same Header & Date Pickers<br>• **Date Range Selector**: `Bulan Ini (Default)`, `Tahun Berjalan (YTD)`, `Kustom`<br>• **Top-Level Capitalization Summary Cards**:<br> - Total Overtime Hours (`1.245 jam`)<br> - CapEx Project Hours & Ratio: `425 jam (34.1%)` with hover formula tooltip: `(Total Project Hours / Total Hours) × 100%`<br> - OpEx Routine Hours & Ratio: `820 jam (65.9%)`<br>• **Visual Distribution Charts**:<br> - Donut Chart: CapEx (Sky Blue) vs OpEx (Slate)<br> - Section Breakdown Bar Chart: Side-by-side CapEx vs OpEx hours for each section<br>• **CapEx Projects Performance Table**:<br> - Project Code (monospace pill: `CPX-2026-ASSY-001`)<br> - Project Name & Asset Code reference<br> - Assigned Department<br> - Total Logged Hours (`120.0 jam`)<br> - Allocated Budget Hours (`150.0 jam`)<br> - Variance (`-30.0 jam` in green or `+15.0 jam` in red)<br> - Physical Progress % bar<br>• Empty State: "Belum ada jam lembur CapEx pada periode ini" (if no project hours logged)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | • Hovering on Donut slices or ratio badges reveals exact formulas and Rupiah value equivalents<br>• Clicking project row links to CapEx Project detail (Epic E07)<br>• Changing date filter triggers Inertia partial reload for chart datasets                                                                                                                                 | **1** (Sidebar > Burn Index > Tab 3)           |
| **Section Burndown & Control Matrix Drawer**                   | Slide-in Sheet (Right, `SectionBurndownSheet.vue`)                    | • **Drawer Header**: Section Name, Department, Fiscal Period, Current Burn Index badge, Close button (X)<br>• **Top Section: 5-Week Burndown Line Chart** (`BurndownLineChart.vue`):<br> - X-Axis: Minggu 1 s/d Minggu 5 (with date ranges in WIB)<br> - Y-Axis: Cumulative overtime hours<br> - Line 1 (dashed slate): Planned cumulative quota (`week1_planned` to `week5_planned`)<br> - Line 2 (solid ISUZU red / blue): Actual cumulative hours approved<br> - Line 3 (dotted purple): ML Forecast trajectory (from Epic E08, displays when available)<br> - Shaded Warning Zone: Light red background fill above 100% budget ceiling<br> - Shaded Safe Zone: Light blue/emerald background fill for 85–100% on-track corridor<br>• **Middle Section: Weekly Tabular Breakdown** (`WeeklyBreakdownTable.vue`):<br> - Table columns: Minggu #, Rentang Tanggal, Rencana (jam), Realisasi (jam), Jam HKN, Jam HLR, Akumulasi Burn %, Deviasi Mingguan<br>• **Bottom Section: 4-Quadrant Budget Control Matrix** (`BudgetMatrixScatter.vue`):<br> - X-Axis: Burn Index % (0% to 150%)<br> - Y-Axis: Cumulative Hours (0 to Budget Quota)<br> - 4 Shaded Quadrants: Zone 1 (Excellent - Green), Zone 2 (Good - Blue), Zone 3 (Warning - Orange), Zone 4 (Poor - Red)<br> - Animated point plotting current section position with label ("Posisi Saat Ini: Zona 2 - Terkendali")                                                                                                                                                                                                                                                        | • Hovering chart data points shows popover tooltip with detailed breakdown<br>• Toggling chart legend items hides/shows specific lines<br>• Clicking Close button (X) or drawer backdrop closes drawer smoothly, returning directly to active tab and scroll position                                                                                                          | **2** (Hub > Click any Section Card or Row)    |
| **Standup PDF Report Generator**                               | Popover Menu on Hub Toolbar (`PdfExportButton.vue`)                   | • Button Label: `Unduh Laporan PDF`<br>• Dropdown Options:<br> - `Ringkasan Standup Mingguan (1 Halaman PDF)` — Best for morning briefings<br> - `Laporan Analisis Bulanan Lengkap (PDF)` — Best for finance monthly closing<br>• Filter summary reminder: `Sesuai filter aktif: [Departemen], [Bulan/Tahun]`<br>• Direct streaming download trigger                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | • Clicking either option triggers backend PDF compilation via `barryvdh/laravel-dompdf`<br>• Button displays loading spinner: `Menyiapkan PDF...` and disables repeated clicks<br>• Browser automatically saves formatted PDF                                                                                                                                                  | **2** (Hub > Click Export PDF > Select option) |
| **Policy Threshold Alert & Card Pulsing**                      | Topbar Notification Bell + In-Place Card Indicator                    | • **Notification Bell Item** (`NotificationBell.vue`):<br> - Amber Warning: `⚠️ Peringatan: Seksi Stamping mencapai 103% (Mendekati batas anggaran)`<br> - Red Danger: `🚨 Kritis: Seksi Body Assembly mencapai 118% (Melebihi anggaran)!`<br>• **In-Place Card Animation**:<br> - Section card on `/dashboard/burn-index` displays an active pulsing red border (`burn-card--danger animate-pulse border-red-500 shadow-red-500/20`)<br> - Notification is dispatched exactly once per section per month per threshold crossing                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | • Clicking notification item navigates to `/dashboard/burn-index?section={id}` and automatically slides open the **Section Burndown Drawer** for that section                                                                                                                                                                                                                  | **1** (Click notification bell from any page)  |
| **Budget Unconfigured Empty Card State**                       | Inside Section Grid (`BurnIndexCard.vue`)                             | • Visible when a section has no `overtime_budgets` record for the active month<br>• Dashed border card with gray muted palette<br>• Icon: Calculator / AlertCircle (`size-8 text-muted-foreground`)<br>• Title: `Anggaran Belum Dikonfigurasi`<br>• Plain-language description: `Target kuota jam lembur belum ditentukan untuk periode [Bulan/Tahun]. Indeks burn tidak dapat dihitung.`<br>• Direct action button: `Atur Anggaran di Planning →` (links to `/budgets/planning` for Admin/Manager)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | • Clicking `Atur Anggaran` navigates to the Overtime Budget Planning Hub (`/budgets/planning`) with the section and month pre-selected                                                                                                                                                                                                                                         | **1** (Visible directly on card grid)          |

---

## 3. User Journey Maps

### Journey 1: Morning Standup 60-Second Health Check (Manager/Supervisor Core Daily Task)

```
Goal: Evaluate department overtime burn status and verify that all sections are operating within budget before morning production standup
Starts at: /dashboard/burn-index (Burn Index Hub)
Steps:
  1. Manager clicks "Burn Index" on the sidebar (or arrives at /dashboard/burn-index).
  2. The page loads instantaneously from pre-calculated snapshots. The top KPI summary bar shows: "Total Departemen: 1.245 / 2.000 Jam (62.3%) — Status: ZONE_1_EXCELLENT (Aman)".
  3. Manager scans the Section Cards Grid. All 5 sections show green (<85%) or blue (85–100%) dials, except Stamping which displays 94% with a blue border and "→ Aman (On Pace)" trajectory.
  4. Manager confirms zero sections are in Warning (Orange) or Deficit (Red).
Done: Manager completes the morning overtime risk assessment in 30 seconds with 100% confidence, ready for the production standup meeting.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

### Journey 2: Investigating an Over-Burning Section via 5-Week Burndown & Scatter Plot (Manager)

```
Goal: Investigate why a section triggered an orange Warning status and identify which operational week caused the overtime spike
Starts at: /dashboard/burn-index (Burn Index Hub)
Steps:
  1. Manager spots Section "Welding Line 2" card displaying 108.5% (Orange Warning, "ZONE_3_WARNING", Trajectory: "↗ Waspada").
  2. Manager clicks the "Welding Line 2" section card.
  3. The Section Burndown Drawer smoothly slides in from the right without reloading the page. Manager inspects the 5-Week Burndown Line Chart:
     - Week 1 & 2 actual hours tracked closely along the dashed planned line.
     - Week 3 actual hours curve spikes sharply upward, penetrating the light-red shaded warning zone.
  4. Manager glances at the Weekly Breakdown Table below the chart: Week 3 logged 64.0 hours of holiday overtime (HLR) due to emergency robot fixture replacement. Manager checks the 4-Quadrant Scatter Plot, confirming the section is in Zone 3 (Fast Early Burn).
Done: Manager identifies the root cause (emergency Week 3 holiday repair) in under 45 seconds without losing their dashboard context, and prepares to redistribute Week 4 maintenance quotas.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

### Journey 3: CapEx vs OpEx Audit & Project Allocation Verification (Finance Controller)

```
Goal: Audit department capitalized labor hours against CapEx project budgets to ensure financial capitalization compliance
Starts at: /dashboard/burn-index (Burn Index Hub)
Steps:
  1. Finance Controller opens /dashboard/burn-index and clicks the "Distribusi CapEx vs OpEx" tab.
  2. The controller reviews the Department Capitalization Summary: CapEx Labor Ratio is 34.2% (425.0 hrs) vs OpEx Ratio 65.8% (820.0 hrs). Controller hovers over the ratio badge to verify the CALC-07 formula tooltip.
  3. Controller reviews the Section Comparison Bar Chart to see which sections generated project labor (Stamping and Assembly 1).
  4. Controller inspects the CapEx Projects Performance Table: Project "CPX-2026-ASSY-001 (Automation Jumper)" shows 120.0 hours logged against 150.0 hours allocated (Variance: -30.0 hrs, 80% physical progress).
Done: Capitalized labor hours verified for accounting journal entries; project variance is confirmed within the allocated threshold.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

### Journey 4: Acting on a High-Burn Policy Alert from Topbar Notification Bell (Manager/Admin)

```
Goal: Respond to an automated threshold crossing alert notifying that a section has exceeded its danger ceiling
Starts at: Any page in the application (Topbar Notification Bell)
Steps:
  1. Manager notices a red numeric badge `1` on the topbar notification bell and clicks it.
  2. Popover displays: "🚨 Kritis: Seksi Body Assembly mencapai 118.4% (Melebihi anggaran bulanan)!".
  3. Manager clicks the notification item.
  4. The application navigates directly to /dashboard/burn-index?tab=sections&section=14, automatically opening the Section Burndown Drawer for Body Assembly with the danger-state line chart pre-focused.
Done: 1-click transition from in-app alert to deep-dive analytics; manager immediately reviews weekly overrun figures.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

### Journey 5: Generating and Exporting Weekly Department Standup PDF Report (Manager/Admin)

```
Goal: Generate a concise, formatted 1-page PDF summary of the department's weekly burn metrics for the plant director's morning briefing
Starts at: /dashboard/burn-index (Burn Index Hub)
Steps:
  1. Manager opens /dashboard/burn-index with their department and active month displayed.
  2. Manager clicks the "Unduh Laporan PDF" button on the top toolbar.
  3. A concise popover menu appears. Manager selects "Ringkasan Standup Mingguan (1 Halaman PDF)".
  4. The button displays an active spinner ("Menyiapkan PDF..."). The server streams the PDF file directly, and the browser saves `Laporan-Burn-Index-PROD1-2026-09.pdf`.
Done: Professional, print-ready executive standup report generated with zero spreadsheet collation.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

### Journey 6: Frontline Team Leader Checking Section Quota Before Scheduling Overtime (Team Leader)

```
Goal: Team Leader verifies remaining monthly overtime hours before scheduling an upcoming weekend shift crew
Starts at: /overtime/submissions/create (Daily Overtime Timesheet Form)
Steps:
  1. Team Leader opens the Daily Timesheet Form (/overtime/submissions/create).
  2. Team Leader looks at the integrated Section Monthly Burn Bar in the form header: "Seksi Stamping: 142.5 / 200.0 Jam (71.3%) — Sisa Kuota: 57.5 Jam (Aman)".
  3. Team Leader clicks the "Lihat Detail Kuota →" link next to the progress bar.
  4. The Section Burndown Drawer slides in, displaying their section's current velocity (35.6 hrs/week) and trajectory: "→ Aman (On Pace) — Proyeksi Akhir: 182.0 Jam".
Done: Team Leader confirms that scheduling a 20-hour weekend maintenance shift will not breach the monthly budget ceiling.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

## 4. Indonesian UX Guardrails

### 4.1 Patience Thresholds

Indonesian manufacturing managers have extremely limited time during daily production operations. The analytical user experience must be engineered for near-zero latency and instant cognitive comprehension:

- **Daily Morning Standup Review (Core Daily Task)**: Maximum **1–2 clicks** to understand department-wide health:
    - Arrive at `/dashboard/burn-index` → Absorb top KPI bar and scan color-coded section cards.
- **Section Burndown Deep-Dive (Occasional Investigation)**: Maximum **1 click**:
    - Click any section card or table row → Drawer slides in with 5-week burndown curves and 4-quadrant scatter plot.
- **CapEx vs OpEx Financial Audit (Weekly/Monthly Task)**: Maximum **1 click**:
    - Click tab `Distribusi CapEx vs OpEx` → Immediate donut chart and project variance table.
- **Standup PDF Report Generation (Weekly Routine)**: Maximum **2 clicks**:
    - Click `Unduh Laporan PDF` on toolbar → Select format → File downloads immediately.
- **Historical Month Switching**: Maximum **2 clicks**:
    - Open Month Picker → Select previous month → All cards, tables, and charts refresh simultaneously via Inertia partial reload.

---

### 4.2 Cognitive Load Budget

- **Maximum 3–4 Primary Actions Visible on Screen**:
    - Main Toolbar: Department selector (if authorized), Month/Year picker, Tab switcher, and `Unduh Laporan PDF`.
    - Cards Grid: Clean section cards with primary click trigger on the card itself.
    - Section Drawer: Tabular toggle, Legend toggles, and Close button.
- **Progressive Disclosure Strategy**:
    - The high-level view (Section Cards & KPI Bar) displays only essential numbers: Planned vs Actual, Burn Index %, Zone, Velocity, and Trajectory.
    - Dense analytical charts (5-week burndown lines, 4-quadrant scatter coordinates, HKN/HLR breakdown) are intentionally sequestered inside the slide-in drawer to prevent visual clutter on the main dashboard.
- **ISUZU Clean Industrial Design Standards**:
    - **ISUZU Brand Color Tokens**:
        - **Primary Red**: `#cc0000` (`var(--primary)`) used for brand badges and critical deficit warnings (`> 115%`).
        - **Emerald / Safe**: `#059669` (`bg-emerald-50 text-emerald-700 border-emerald-300`) for `< 85%` burn index and Zone 1.
        - **Blue / On Track**: `#0284c7` (`bg-sky-50 text-sky-700 border-sky-300`) for `85–100%` burn index and Zone 2.
        - **Amber / Warning**: `#d97706` (`bg-amber-50 text-amber-700 border-amber-300`) for `101–115%` burn index and Zone 3.
    - **CapEx vs OpEx Visual Segregation**:
        - **CapEx Labor (Capitalized Fixed Asset Labor)**: Sky Blue token (`bg-sky-100 text-sky-800 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800`).
        - **OpEx Labor (Routine Operating Overtime)**: Neutral Slate token (`bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300`).
    - **Mandatory Tabular Numbers & Monospace Alignment**:
        - All hours (`142.5 jam`), burn percentages (`71.3%`), velocities (`35.6 jam/mgg`), and financial Rupiah figures **must** use `font-mono tabular-nums` to eliminate horizontal text jitter during 60-second polling reloads.
    - **Standard Indonesian Currency Formatting**:
        - Monetary amounts strictly formatted as `Rp 1.234.567` (Indonesian thousand periods, zero decimal clutter).
    - **Strictly Single Drawer Depth**:
        - Modals inside modals or drawers inside drawers are strictly prohibited. The Section Burndown Drawer is a single-level slide-in sheet (`Sheet` from right).

---

### 4.3 Trust Signals & Plant Communication Standards

- **Plain-Language Indonesian Shopfloor Terminology**:
    - Avoid raw English database column names. Use familiar plant management vocabulary:
        - `Indeks Burn` (Burn Index)
        - `Anggaran Lembur` (Planned Overtime Budget)
        - `Realisasi Jam Lembur` (Realized Overtime Hours)
        - `Sisa Kuota Jam` (Remaining Budget Hours)
        - `Kecepatan Burn` (Burn Velocity — jam/minggu)
        - `Proyeksi Akhir Bulan` (Projected Period-End Total)
        - `Matriks Kontrol Anggaran` (Budget Control Matrix)
        - `Zona 1: Sangat Baik (Aman)` (ZONE_1_EXCELLENT)
        - `Zona 2: Baik (Terkendali)` (ZONE_2_GOOD)
        - `Zona 3: Peringatan (Burn Cepat)` (ZONE_3_WARNING)
        - `Zona 4: Defisit (Melebihi Anggaran)` (ZONE_4_POOR)
        - `HKN` (_Hari Kerja Normal_) vs `HLR` (_Hari Libur / Istirahat_)
        - `CapEx (Tenaga Kerja Proyek/Aset)` vs `OpEx (Lembur Operasional Rutin)`
- **Zero Raw Technical Errors or Math Exceptions**:
    - Never display raw database errors, division-by-zero exceptions (`DivisionByZeroError`), or NaN values.
    - If a section has no budget configured for the selected month, render the friendly "Anggaran Belum Dikonfigurasi" card with a direct link to `/budgets/planning`.
- **Transparent Asynchronous Freshness**:
    - The dashboard prominently displays: `Pembaruan otomatis tiap 60 detik • Terakhir diperbarui: 08:30 WIB`. Users know the data is active and authoritative without needing manual browser F5 refreshes.
- **Plain-Language Explainability for AI & Formulas**:
    - Hovering on CapEx Ratio displays: `(Total Jam Proyek ÷ Total Seluruh Jam) × 100%`.
    - When ML trajectories are active (Epic E08), hover tooltips explain the difference: `Heuristik: 182 jam (kecepatan rata-rata) • Prediksi AI: 175 jam ±12 jam (mempertimbangkan kalender libur & jadwal preventive maintenance)`.

---

### 4.4 Epic E05 Specific Risks & Mitigations

| Sub-Epic / Feature                             | Identified UX Risk                                                                                                                                                            | Mandatory Design Mitigation                                                                                                                                                                                                                                                                    |
| :--------------------------------------------- | :---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **E05-01 (Zero Budget Division by Zero)**      | A section has no `overtime_budgets` record for the month. Formula `(actual / planned) * 100` throws a division by zero error, crashing the entire dashboard.                  | `BurnIndexCalculatorService` intercepts `plannedHours <= 0` and returns `burn_index_pct = 0.0`. The UI detects this state and renders a clean "Anggaran Belum Dikonfigurasi" empty card state with a 1-click shortcut to `/budgets/planning`.                                                  |
| **E05-01 (Stale Data Confusion)**              | A manager approves overtime in the queue, immediately switches to the Burn Index, and sees old numbers because the asynchronous recalculation job took 2 seconds to complete. | Dashboard features 60-second polling (`setInterval(() => router.reload({ only: ['snapshots'] }), 60000)`). If a user arrives via an approval redirect, the controller triggers `MonthlySnapshotService::getOrRecalculate()` to force an immediate synchronous refresh.                         |
| **E05-02 (Burndown Chart Visual Clutter)**     | A 5-week burndown chart with planned lines, actual curves, ML forecasts, and zone shading looks overwhelming on tablet or mobile screens.                                     | The chart features an interactive legend toggle to hide/show individual lines, uses high-contrast line styles (dashed slate for planned, solid red/blue for actual, dotted purple for ML), and provides an accompanying tabular breakdown below the chart for exact numerical verification.    |
| **E05-02 (4-Quadrant Scatter Plot Confusion)** | Frontline manufacturing supervisors find 2D scatter coordinates abstract and difficult to interpret during quick morning standups.                                            | The scatter plot visually shades the 4 quadrants with high-contrast pastel colors and labels them in plain Indonesian: "Zona 1: Sangat Baik", "Zona 2: Terkendali", "Zona 3: Peringatan", "Zona 4: Kritis". An active glowing badge explicitly states: "Posisi Saat Ini: Zona 2 (Terkendali)". |
| **E05-03 (CapEx Overrun Blindspot)**           | Finance controllers fail to notice that a CapEx automation project is consuming excessive overtime labor while total department hours appear normal.                          | The CapEx tab highlights project variance in high-contrast colored badges (`+25 jam (Over Budget)` in red, `-15 jam (Under Budget)` in green). A section-level stacked bar chart immediately surfaces which production section is logging project hours.                                       |
| **E05-04 (Alert Notification Fatigue)**        | Recalculation jobs run frequently, spamming the manager's notification bell with 20 identical warning alerts for the same section.                                            | Database schema enforces `warned_at` and `danger_at` timestamp locks on `monthly_burn_snapshots`. The `BudgetAlertService` dispatches a notification **exactly once per threshold crossing per section per fiscal month**.                                                                     |
| **E05-05 (PDF Export Browser Freeze)**         | Compiling complex SVG charts into server-side DomPDF times out or crashes memory on large departments.                                                                        | The PDF export utilizes optimized pre-calculated snapshot tables and clean HTML/CSS tabular layouts designed specifically for DomPDF, avoiding client-side canvas rasterization timeouts. The export button displays a spinner and disables repeat clicks.                                     |
| **E05-06 (Timezone Day-Count Skew)**           | Calculating elapsed weeks across month boundaries or UTC server clocks skews the velocity calculation (e.g. dividing by 0.1 weeks).                                           | `BurnIndexCalculatorService` strictly enforces `now('Asia/Jakarta')`. Elapsed weeks is clamped at a minimum of `1.0` week to prevent division distortions on the 1st or 2nd day of the month. Closed historical months are locked at `4.3` weeks.                                              |

---

## 5. Implementation Boundaries (Anti-Splitting Rules)

These rules are strict architectural constraints for the implementing engineer or AI subagents. Any deviation will violate standup velocity, create fragmented navigation, or degrade analytical performance.

### 5.1 Do Not Split — Combine Into One Surface:

- **Unified Analytical Command Center (`/dashboard/burn-index`)**:
    - Section Burn Cards (E05-01), Consolidated Department Table (E05-05), and CapEx vs OpEx Distribution (E05-03) **MUST live on a single primary page**: `resources/js/pages/dashboard/BurnIndex.vue`.
    - **Do NOT** split these views into separate controller routes (e.g. do NOT create `/reports/capex-opex`, `/dashboard/burn-index/department/{id}`, or `/analytics/sections`). Use the `tab` query parameter (`?tab=sections`, `?tab=department`, `?tab=capex-opex`) on `/dashboard/burn-index`.
- **Unified Period & Department Filtering**:
    - The Month/Year picker and Department dropdown **MUST control all tabs simultaneously**. Switching tabs must retain the currently selected period and department.

---

### 5.2 Make a Tab, Not a New Route:

- Switching between **Ringkasan Seksi (Cards)**, **Konsolidasi Departemen (Table)**, and **Distribusi CapEx vs OpEx** must be handled via tab components bound to query parameters (`?tab=...`), **NOT separate routes**.
- Tab switching must execute seamlessly using Inertia partial reloads (`preserveState: true`, `preserveScroll: true`), avoiding hard full-page reloads.

---

### 5.3 Make a Drawer/Sheet, Not a Full Page:

The following user interactions must be built as slide-in side drawers (`Sheet` from right) or anchored popovers, never separate pages:

- **`SectionBurndownSheet.vue`**: The 5-week burndown line chart, 4-quadrant scatter plot, and weekly breakdown table **MUST open in a slide-in sheet** when a section card or table row is clicked. The user must never lose their active tab, department selection, or scroll position.
- **`PdfExportButton.vue`**: Format selector and standup report generator must be an anchored dropdown menu on the toolbar, not a separate export configuration page.

---

### 5.4 Backend-Only — Strictly No Dedicated UI:

The following components and services are pure background, algorithmic, or scheduled logic. **Do NOT create dedicated navigation items, pages, or menus for them**:

- **`BurnIndexCalculatorService`**: Core mathematical service computing formulas CALC-02 through CALC-07.
- **`MonthlySnapshotService`**: Read-layer service fetching or lazily calculating `monthly_burn_snapshots`.
- **`RecalculateMonthlyBurnSnapshotJob`**: Asynchronous Redis background queue job recalculating section snapshots post-approval.
- **`BudgetAlertService`**: Background policy threshold evaluator checking `policy_thresholds` and dispatching database notifications.
- **PDF Generation Engine**: Headless server-side DomPDF streaming service.

---

### 5.5 Strictly Forbidden:

1. **Do NOT use legacy Ziggy `route()` helper**: Always use typed **Laravel Wayfinder** functions imported from `@/actions` or `@/routes`.
2. **Do NOT query or aggregate raw `overtime_items` on dashboard load**: All dashboard data must be sourced from the pre-calculated `monthly_burn_snapshots` table. Live aggregation of thousands of item rows during standup peak hours is strictly forbidden.
3. **Do NOT open Section Burndown Detail as a separate full page**: Opening `/dashboard/section-detail` or tearing the user away from their overview is strictly prohibited. It must slide in as a `Sheet` (drawer).
4. **Do NOT allow division by zero or NaN errors**: If a section's planned budget is 0 or unconfigured, the UI must render the friendly "Anggaran Belum Dikonfigurasi" card state with a link to `/budgets/planning`.
5. **Do NOT create nested modals**: Modals or sheets stacked inside other modals or sheets are strictly forbidden.
6. **Do NOT spam threshold alert notifications**: The backend must check `warned_at` and `danger_at` timestamps to ensure notifications are dispatched **only once per threshold crossing per section per fiscal month**.
7. **Do NOT hardcode currency or date formatting**: Monetary amounts must strictly use `Rp` with Indonesian thousand separators; dates, times, and week calculations must strictly use `Asia/Jakarta (WIB)`.
8. **Do NOT create dedicated sidebar items for Team Leaders or Operators for Burn Index**: Team Leaders view their burn quota in the timesheet header; Operators do not manage departmental budgets. Only `Manager` and `Admin` roles receive the **Burn Index** sidebar item.
