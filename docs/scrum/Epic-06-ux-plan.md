# UX Plan — E06: Individual Employee Reporting & Welfare Tracking

> Created before implementation. This document is a hard constraint for all work in Epic E06.
> Epic file: `docs/scrum/Epic-06.md`

---

## 1. Navigation Footprint

### 1.1 Executive Philosophy: Worker Welfare, Fairness & Ergonomic Simplicity

In an industrial manufacturing plant operating three shifts (Shift 1: 07:00–15:00, Shift 2: 15:00–23:00, Shift 3: 23:00–07:00 WIB), overtime can easily become unevenly distributed. Without transparent individual tracking, critical line operators and technician specialists are frequently called in for consecutive weekend and holiday shifts. This leads to two critical vulnerabilities:

1. **Industrial Safety & Worker Fatigue**: Severe operator exhaustion increases machinery accident rates, quality defects, and unplanned production downtime.
2. **Shopfloor Dissatisfaction & Inequity**: Unbalanced overtime allocations create perceptions of favoritism between shift crews and team members.

Epic E06 delivers the **Individual Employee Dossier & Welfare Tracking Module** (modernizing the legacy `ReportIndividu` screen). It provides four key personas with transparent, instantaneous access to workload patterns:

- **The Shopfloor Operator (`User` Role)**: Factory line operators and assemblers who primarily access the application via smartphones. They have mid-level digital literacy (fluent in WhatsApp, Tokopedia, and mobile banking; frustrated by complex desktop ERP grids) and very low patience. They want an instant answer to: _"How many overtime hours did I work this month, were my submissions approved, and how much extra earnings should I expect?"_
- **The Team Leader / Foreman (`Team Leader` Role)**: Line supervisors who need to schedule overtime shifts during 10-to-15 minute shift handovers. They need quick search-as-you-type employee lookup by NPK/Name and clear visual fatigue warnings before scheduling an overworked worker for another weekend shift.
- **The Department Manager (`Manager` Role)**: Department heads who need to audit section-wide workload distribution, verify that overtime is distributed fairly across operators, and address structural staffing bottlenecks.
- **HR & Plant Safety Officers (`Admin` Role)**: Plant compliance administrators auditing labor law adherence and consecutive-week fatigue alerts across the entire facility.

The core UX principles for Epic E06 are:

1. **Unified Dossier Hub (One Primary Surface)**: All employee analytics—KPI summaries, category breakdowns, peer benchmarking, safety/fatigue indicators, and chronological timesheets—live on **one responsive page** organized by intuitive tabs (`Ringkasan & Kesejahteraan` and `Buku Jam Lembur`).
2. **Instant Search-as-You-Type with Local Memory**: Team Leaders and Managers can find any subordinate in under 2 seconds using debounced NPK/name search with a 5-item "Recent Lookups" list stored in `localStorage` for zero-click return access.
3. **Role-Tailored Information Architecture**:
    - `User` (Operator) lands directly on a streamlined, mobile-first personal self-service dashboard (`/my/dashboard` or `/dashboard`) scoped strictly to their own data.
    - All peer benchmarking data is **strictly anonymized** for the `User` role (showing only their own metric against the section average, hiding co-workers' names and individual numbers).
    - `Manager` and `Team Leader` roles see full peer names and section-wide distribution histograms scoped to their respective department or section.
4. **Advisory Fatigue Soft Indicators (Zero Operational Roadblocks)**: Fatigue alerts (weekly soft thresholds, 3-consecutive-week alarms, safety score gauges) are **purely visual and advisory**. They provide proactive guidance without artificially locking operational shift dispatch.
5. **Shopfloor Ergonomics & Tabular Precision**: Monospace tabular numerals (`font-mono tabular-nums`), Indonesian Rupiah currency formatting (`Rp 1.234.567`), and clear day-type indicators (`HKN` for normal working days vs `HLR` for rest days/holidays) prevent visual clutter and misinterpretation on factory floor screens.

---

### 1.2 Sub-Epic Functional Separation

| Sub-Epic / Story                                                                     | Category                               | User-Facing Surface                                                                                                                                                                                          | Dedicated UI Needed?                                                                        |
| :----------------------------------------------------------------------------------- | :------------------------------------- | :----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :------------------------------------------------------------------------------------------ |
| **E06-01**: Individual Employee Dossier Lookup (Manager/Team Leader)                 | **User-Facing Navigation & Search**    | Employee Search Input (`EmployeeSearch.vue`), Debounced Results Dropdown, Recent Lookups Pills (`RecentLookups.vue`), Dossier Header Card                                                                    | ✅ Yes                                                                                      |
| **E06-02**: Employee Personal Overtime Dashboard (Employee/Team Leader)              | **User-Facing Analytics**              | Dossier Overview Tab (`EmployeeOverviewTab.vue`), 4 KPI Cards (`KpiSummaryCards.vue`), Category Donut Chart (`CategoryDonutChart.vue`), Day-Type Bar (`DayTypeBreakdownBar.vue`), Total Rupiah Cost Snapshot | ✅ Yes                                                                                      |
| **E06-03**: Peer Benchmarking & Workload Distribution (Manager/Team Leader/Employee) | **User-Facing Visualization**          | Peer Comparison Panel (`PeerComparisonPanel.vue`), Section Distribution Histogram (`SectionDistributionChart.vue`), Top 5 / Bottom 5 Lists, Role-Based Anonymization Toggle                                  | ✅ Yes (Part of Overview Tab; not a separate route)                                         |
| **E06-04**: Safety & Fatigue Soft Indicators (Team Leader/HR/Manager)                | **User-Facing Welfare & In-App Alert** | 4-Week Rolling Trend Chart (`FatigueRollingChart.vue`), Safety Score Gauge (`SafetyScoreGauge.vue`), Soft Warning/Danger Badges, Topbar Notification Bell Item (`FatigueAlertNotification`)                  | ✅ Yes (Welfare panel on Overview Tab + in-app notification; evaluation engine is headless) |
| **E06-05**: Chronological Audit Timesheet (Employee/Team Leader)                     | **User-Facing Table & CSV Export**     | Timesheet Tab (`PersonalTimesheetTab.vue`), Paginated Table (`PersonalTimesheetTable.vue`), Status/Category/Date Filters, Expandable Rejection Reason Row, Streamed CSV Export Trigger                       | ✅ Yes (Tab on Dossier; not a separate route)                                               |
| **E06-06**: Employee Self-Service Personal Dashboard (User Role)                     | **User-Facing Portal**                 | Employee Self-Service Home (`EmployeeSelfService.vue` or auto-scoped Dossier at `/my/dashboard`), Simplified 3-Card Summary, Recent 5 Entries Mini-Table                                                     | ✅ Yes (Role-based dashboard landing; shares dossier components)                            |

---

### 1.3 Minimal Navigation Footprint Decisions

#### Sidebar Navigation Additions:

- **For `Manager` Role**:
    - Adds **1 primary sidebar item**:
        - **Laporan Karyawan** (Employee Reports) — Route: `/reports/employees`.
        - Navigates to the Employee Dossier Hub with the search bar pre-focused and department scope pre-filtered.
- **For `Team Leader` Role**:
    - Adds **1 primary sidebar item**:
        - **Laporan Karyawan** (Employee Reports) — Route: `/reports/employees`.
        - Navigates to the Employee Dossier Hub scoped strictly to employees in their assigned section.
- **For `Admin` Role**:
    - Shares the **1 sidebar item**:
        - **Laporan Karyawan** (Employee Reports) — Route: `/reports/employees`.
        - Plant-wide access across all departments and sections with department filtering.
- **For `User / Operator` Role**:
    - Adds **0 new sidebar items**.
    - The existing **Dashboard** (`/dashboard`) sidebar item automatically resolves to their personal self-service overview (`/my/dashboard`), showing their individual hours, welfare status, and personal timesheet tab without exposing admin menus or other employees' data.

#### Distinct Routes & Pages:

Across the entire 30-story-point epic, exactly **2 distinct route endpoints** are registered:

1. `/reports/employees` & `/reports/employees/{npk}` — The Unified Employee Dossier & Welfare Hub (`resources/js/pages/reports/EmployeeDossier.vue`).
    - Accessing `/reports/employees` without an `{npk}` parameter displays the search bar, recent lookup cards, and the user's section roster list.
    - Accessing `/reports/employees/{npk}` renders the complete dossier for the selected employee.
2. `/my/dashboard` — The Employee Self-Service Personal Dashboard (`resources/js/pages/dashboard/EmployeeSelfService.vue`).
    - Dedicated landing page for `User` role logins, optimized for smartphone screens and zero-confusion personal tracking.

#### Surfaces Handled via Tabs or Drawers (Never Dedicated Routes):

- **Ringkasan & Kesejahteraan (Overview & Welfare Tab)**: Tab 1 (`?tab=overview`) on `/reports/employees/{npk}`. Contains KPI cards, category donut chart, HKN/HLR breakdown, peer comparison, and fatigue safety indicators.
- **Buku Jam Lembur (Personal Timesheet Tab)**: Tab 2 (`?tab=timesheet`) on `/reports/employees/{npk}`. Contains the paginated, filterable chronological overtime item ledger and CSV export trigger.
- **Rejection Reason Detail**: Handled via an expandable row accordion or anchored popover inside the timesheet table, **never a separate modal or page**.
- **Personal Timesheet CSV Export**: Action button on the timesheet tab triggering a direct streamed download (`/reports/employees/{npk}/timesheet/export`), avoiding extra configuration pages.

#### Pure Backend & Headless Logic (No Dedicated UI):

- `EmployeeReportService`: Core query and aggregation service computing monthly hours, YTD hours, section rankings, category distributions, and CALC-06 peer variances from `APPROVED` overtime items.
- `OvertimePolicyEvaluator::getEmployeeWelfareStatus`: Welfare calculation engine assessing rolling 4-week workloads, consecutive threshold breaches, and the Safety Score %.
- `FatigueAlertNotification`: Database channel notification dispatched asynchronously when an employee breaches the consecutive-week threshold for the first time in a calendar month.
- Anonymization Policy Gate: Server-side data transformation stripping employee names and NPKs from peer benchmarking datasets when `auth()->user()->role === 'user'`.
- Streamed CSV Export Engine: Headless `EmployeeTimesheetExport` utilizing cursor-based batching for zero-memory CSV generation.

---

## 2. Screen Inventory

| Screen / Panel                                                    | Location                                                              | What's visible on first open                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  | Further triggers                                                                                                                                                                                                                                                                                                                       | Click depth                                            |
| :---------------------------------------------------------------- | :-------------------------------------------------------------------- | :---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :----------------------------------------------------- |
| **Employee Dossier Hub — Search & Roster View (No NPK Selected)** | `/reports/employees` (`EmployeeDossier.vue`)                          | • **Page Header**: Title ("Laporan Karyawan & Kesejahteraan"), WIB Clock, Active Role pill<br>• **Employee Search Bar** (`EmployeeSearch.vue`): Large search input with placeholder ("Cari nama atau NPK karyawan..."), search icon, clear button<br>• **Recent Lookups Bar** (`RecentLookups.vue`): Horizontal scrollable pills of the last 5 viewed employees (Avatar, Name, NPK) stored in `localStorage`<br>• **Section Roster Quick-Pick Grid**: Cards or table listing employees in the user's section/department with: NPK, Name, Job Position, Current Month Hours badge, and Fatigue Status indicator pill                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | • Typing 3+ characters triggers debounced dropdown results (NPK, Name, Section)<br>• Clicking any search result or roster card navigates to `/reports/employees/{npk}`<br>• Clicking a "Recent Lookup" pill navigates directly to that employee's dossier                                                                              | **1** (Sidebar > Laporan Karyawan)                     |
| **Employee Dossier — Overview & Welfare Tab (Tab 1)**             | `/reports/employees/{npk}?tab=overview` (`EmployeeDossier.vue`)       | • **Persistent Dossier Header**:<br> - Full Name, NPK pill (`font-mono tabular-nums`), Department, Section, Job Title<br> - Active status badge (`Aktif` / `Nonaktif`)<br> - Search Bar (compact top right) to quickly switch employees<br> - Month & Year Period Picker (defaults to current month in WIB)<br>• **Tab Navigation Bar**: `Ringkasan & Kesejahteraan (Overview)`, `Buku Jam Lembur (Timesheet)`<br>• **4 KPI Summary Cards** (`KpiSummaryCards.vue`):<br> 1. `Jam Lembur Bulan Ini`: Approved hours (`34.5 jam`) + status comparison<br> 2. `Jam Lembur Tahun Berjalan (YTD)`: Total approved hours (`186.0 jam`)<br> 3. `Indeks Burn Individu`: `86.3%` (or `N/A` with friendly tooltip if section has no individual quota)<br> 4. `Peringkat Seksi`: e.g., "Peringkat ke-4 dari 24 karyawan di seksi"<br>• **Financial Cost Snapshot Card**: `Total Estimasi Biaya Lembur: Rp 2.450.000` (computed from approved item cost snapshots; visible to Manager/Admin/Team Leader)<br>• **Charts Row** (2-column layout on desktop, stacked on mobile):<br> - **Category Donut Chart** (`CategoryDonutChart.vue`): Production (Emerald), TPM (Amber), CapEx Project (Sky Blue), Others (Slate)<br> - **Day-Type Breakdown Bar** (`DayTypeBreakdownBar.vue`): HKN Hours vs HLR Hours (horizontal split progress bar with exact figures)<br>• **Peer Benchmarking Panel** (`PeerComparisonPanel.vue`):<br> - Section Average Gauge vs Individual Hours<br> - Variance Pill (CALC-06): `+6.5 jam di atas rata-rata seksi` (amber) or `-3.0 jam di bawah rata-rata` (emerald)<br> - Section Distribution Histogram (`SectionDistributionChart.vue`): Bar chart showing section peers with the active employee highlighted in ISUZU Red (anonymized for User role)<br> - Quick Lists: Top 5 Highest Hours & Bottom 5 Lowest Hours in section<br>• **Fatigue & Welfare Indicator Panel** (`FatigueRollingChart.vue` & `SafetyScoreGauge.vue`):<br> - Rolling 4-Week Workload Bar Chart (Minggu -3 s/d Minggu Ini)<br> - Weekly Limit Soft Badge: `⚠️ Mendekati Batas Mingguan (21 / 20 jam)` or `✅ Dalam Batas Aman`<br> - Consecutive Weeks Alert Badge: `🔴 Risiko Kelelahan: 3 minggu berturut-turut melebihi batas!` (if triggered)<br> - Safety Score Gauge: Circular arc score (`85% - Tingkat Istirahat Terjaga`) | • Clicking tab switch to `Buku Jam Lembur` updates URL to `?tab=timesheet` without full reload<br>• Changing Month/Year updates all dossier data via Inertia partial reload (`router.reload({ only: ['summary', 'breakdown', 'welfare'] })`)<br>• Hovering on chart segments shows breakdown tooltips with exact hours and percentages | **1** (Sidebar > Laporan Karyawan > Select Employee)   |
| **Employee Dossier — Timesheet Tab (Tab 2)**                      | `/reports/employees/{npk}?tab=timesheet` (`PersonalTimesheetTab.vue`) | • Same Persistent Dossier Header and Month/Year Picker<br>• **Timesheet Filter Toolbar**:<br> - Status filter: `Semua`, `Disetujui (Approved)`, `Menunggu (Pending)`, `Ditolak (Rejected)`<br> - Category filter: `Semua`, `Produksi`, `TPM`, `CapEx Proyek`, `Lainnya`<br> - Quick search input for notes/tasks<br> - **Header Action**: `Unduh CSV (Export CSV)` button<br>• **Chronological Timesheet Table** (`PersonalTimesheetTable.vue`):<br> - Columns: `Tanggal (WIB)`, `Hari`, `Jenis Hari (HKN/HLR)`, `Produksi`, `TPM`, `CapEx`, `Lainnya`, `Total Jam`, `Status`, `Catatan Tugas / Aksi`<br> - Status Badges: Emerald `Disetujui`, Amber `Menunggu Persetujuan`, Red `Ditolak`<br> - Rejection Reason indicator: Red info icon with inline hint (`Alasan Ditolak`)<br> - CapEx project pill: Monospace code (`CPX-2026-ASSY-001`) with project title tooltip<br>• **Pagination Footer**: Server-side pagination controls (25 rows per page, "Menampilkan 1–25 dari 68 catatan")<br>• **Empty State**: "Belum ada catatan lembur pada filter yang dipilih"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | • Clicking an expandable row or rejection icon reveals rejection reason in place without modal popups<br>• Changing filters or page number triggers Inertia partial reload (`preserveScroll: true`)<br>• Clicking `Unduh CSV` initiates immediate server-side CSV stream download                                                      | **2** (Dossier > Click Tab 2)                          |
| **Employee Self-Service Dashboard (User Role Home)**              | `/my/dashboard` or `/dashboard` (`EmployeeSelfService.vue`)           | • **Mobile-Optimized Personal Header**: Greeting ("Halo, [Nama]!"), NPK, Shift assignment, Section name<br>• **3 Compact KPI Cards** (Mobile Swipeable / Grid):<br> 1. `Total Lembur Bulan Ini`: `24.5 jam`<br> 2. `Total Tahun Ini (YTD)`: `112.0 jam`<br> 3. `Status Kesejahteraan`: `Aman (Skor Keselamatan 100%)`<br>• **Category & Day-Type Visual Pill Bar**: Mini visual bar showing Production vs CapEx vs TPM split<br>• **Recent Overtime Submissions Card** (Last 5 items):<br> - Date & Day type (`Sabtu, 05 Sep • HLR`)<br> - Hours count (`04.0 jam`)<br> - Status badge: `Disetujui`, `Menunggu`, `Ditolak`<br> - If rejected: Red alert banner with rejection reason<br>• **Action Shortcuts**:<br> - Button: `Lihat Buku Lembur Lengkap (Buka Timesheet) →`<br> - Button: `Lihat Grafik Kesejahteraan & Beban Kerja →`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | • Clicking `Lihat Buku Lembur Lengkap` switches to the embedded Timesheet view<br>• Clicking `Lihat Grafik Kesejahteraan` expands the peer comparison and rolling 4-week fatigue chart (strictly anonymized)                                                                                                                           | **0** (Default home screen for `User` role post-login) |
| **Fatigue Alert Notification Item**                               | Topbar Notification Bell (`NotificationBell.vue`)                     | • **Warning Item**: Amber bell badge `⚠️ Peringatan Beban Kerja: [Nama Karyawan] (NPK: 12345) mendekati batas mingguan (21/20 jam)`<br>• **Danger Item**: Red bell badge `🚨 Peringatan Kelelahan: [Nama Karyawan] melebihi batas lembur 3 minggu berturut-turut!`<br>• Timestamp: Relative Indonesian time (`15 menit yang lalu`)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            | • Clicking notification navigates directly to `/reports/employees/{npk}?tab=overview` with the Welfare panel scrolled into view                                                                                                                                                                                                        | **1** (Click notification bell from any page)          |

---

## 3. User Journey Maps

### Journey 1: Team Leader Reviewing Employee Workload Before Scheduling Overtime (Core Daily Flow)

```
Goal: Team Leader checks an operator's current monthly hours and fatigue indicators before assigning them to a critical weekend overtime shift
Starts at: /reports/employees (or from the Daily Timesheet Form header in Epic E03)
Steps:
  1. Team Leader clicks "Laporan Karyawan" in the sidebar (or types employee NPK into the top search bar).
  2. Team Leader types "4091" or "Budi" into the search bar. The debounced dropdown appears in 300ms showing "Budi Santoso (NPK: 4091) — Sub-Assy Line 1".
  3. Team Leader clicks the result. The dossier loads instantly with the "Ringkasan & Kesejahteraan" tab active.
  4. Team Leader glances at the 4 KPI cards and the Fatigue Panel:
     - Current Month: 38.0 hours.
     - Fatigue Alert: ⚠️ "Mendekati Batas Mingguan (19/20 jam)".
     - Rolling 4-week chart shows hours increasing over the last 3 weeks.
Done: Team Leader sees that Budi is approaching fatigue limits and decides to assign the weekend shift to another operator in the section with lower hours.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

### Journey 2: Line Operator Checking Personal Overtime Hours and Status on Smartphone (Daily Worker Flow)

```
Goal: Production operator checks whether their weekend overtime was approved and verifies their monthly accumulated hours
Starts at: /login on a mobile smartphone
Steps:
  1. Operator logs in with their NPK and password.
  2. The system detects the 'User' role and directs immediately to the simplified Employee Self-Service Dashboard (/my/dashboard).
  3. Operator views the top KPI card: "Jam Lembur Bulan Ini: 18.5 Jam" and reviews the "Catatan Lembur Terakhir" list showing their Saturday shift marked with a green "Disetujui" (Approved) badge.
Done: Operator verifies approved hours in under 15 seconds without navigating through any complex manager menus.
Step count: 3
Status: ✅ OK (≤4 steps)
```

---

### Journey 3: Operator Investigating a Rejected Overtime Item and Viewing Detailed Timesheet

```
Goal: Operator sees that a recent overtime submission was rejected and wants to find the supervisor's rejection reason
Starts at: /my/dashboard (Employee Self-Service Home)
Steps:
  1. Operator notices a red status badge "Ditolak" on their recent shift entry for "Rabu, 02 Sep".
  2. Operator taps the rejection item (or clicks "Lihat Buku Lembur Lengkap").
  3. The Timesheet view opens. The rejected row displays an inline red callout: "Alasan Penolakan: Salah alokasi CapEx, pekerjaan preventive maintenance rutin harus dimasukkan ke kategori TPM bukan CapEx."
Done: Operator reads the exact feedback in 3 taps and contacts their Team Leader to correct the categorization.
Step count: 3
Status: ✅ OK (≤4 steps)
```

---

### Journey 4: Department Manager Auditing Section Workload Fairness (Weekly Management Flow)

```
Goal: Department Manager checks if overtime hours in Section "Welding 2" are evenly distributed or monopolized by a few senior workers
Starts at: /reports/employees
Steps:
  1. Manager opens "Laporan Karyawan" and selects Section "Welding 2" from the section filter.
  2. Manager selects the top-ranked employee (highest hours) from the quick roster list.
  3. On the dossier overview tab, Manager inspects the "Peer Benchmarking" panel:
     - Section Distribution Histogram shows 3 operators clustered at >40 hours, while 15 operators have <10 hours.
     - Top 5 list confirms the top worker has +18.5 hours above the section average (CALC-06 variance).
  4. Manager notes the imbalance to discuss workload rotation during the weekly section supervisor meeting.
Done: Imbalance identified with actionable comparative data in under 45 seconds.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

### Journey 5: Exporting Personal Timesheet to CSV for Payroll Verification (Monthly Administrative Task)

```
Goal: Team Leader or Employee exports an employee's monthly overtime audit timesheet to CSV for offline payroll cross-checking
Starts at: /reports/employees/4091?tab=timesheet
Steps:
  1. User navigates to the employee's dossier and clicks the "Buku Jam Lembur" (Timesheet) tab.
  2. User selects the desired month (e.g., "Agustus 2026") from the period selector.
  3. User clicks the "Unduh CSV" button on the timesheet toolbar.
  4. The browser immediately downloads `Timesheet-ISZ-4091-2026-08.csv` with formatted columns (Date, Day Type, Production, TPM, CapEx, Total, Status, Notes).
Done: Formatted CSV file saved locally with zero intermediate modal dialogues.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

## 4. Indonesian UX Guardrails

### 4.1 Patience Thresholds

Shopfloor line operators and shift supervisors work in demanding industrial environments where prolonged screen time is unacceptable:

- **Daily Operator Self-Check (Daily Mobile Flow)**: Max **1–2 clicks / taps**:
    - Open app / login → Land directly on `/my/dashboard` with current month hours and latest approval statuses visible above the fold.
- **Supervisor Employee Dossier Lookup (Daily Shift Flow)**: Max **2 clicks**:
    - Click `Laporan Karyawan` → Type 3 letters of name or NPK → Click result → Dossier is open.
- **Recent Employee Return Lookup (Frequent Routine)**: Max **1 click**:
    - Click any pill in the `Pencarian Terakhir` (Recent Lookups) bar on the dossier hub to immediately load that worker's profile.
- **Checking Rejection Feedback (Troubleshooting Flow)**: Max **2 taps**:
    - Open timesheet → Tap rejected row to view reason inline.
- **Timesheet CSV Export (Monthly Routine)**: Max **2 clicks**:
    - Open `Buku Jam Lembur` tab → Click `Unduh CSV`.

---

### 4.2 Cognitive Load Budget

- **Maximum 3–4 Primary Interactive Controls on Screen**:
    - Dossier View: Period selector (Month/Year), Tab switcher (`Ringkasan` vs `Timesheet`), Search bar, and `Unduh CSV` (on Timesheet tab).
    - Clean card structure prevents dense information overload.
- **Progressive Disclosure Strategy**:
    - Top level surfaces high-impact summary metrics: Total Hours, YTD Hours, Burn %, and Section Rank.
    - Granular line-item breakdowns (RCA tags, task descriptions, submission timestamps) are housed inside the `Buku Jam Lembur` tab.
    - Rejection reasons are revealed inline only when clicking/expanding the specific rejected row.
- **ISUZU Clean Industrial Design Standards**:
    - **ISUZU Brand Color Tokens**:
        - **Primary Red**: `#cc0000` (`var(--primary)`) used for brand buttons, active tab indicator, and critical fatigue danger alerts (`🔴 Risiko Kelelahan`).
        - **Safe / Rested**: `#059669` (`bg-emerald-50 text-emerald-700 border-emerald-300`) for normal workload and Safety Score >85%.
        - **Caution / Soft Limit**: `#d97706` (`bg-amber-50 text-amber-700 border-amber-300`) for weekly soft limit warnings (`⚠️ Mendekati Batas`).
        - **CapEx Labor (Capitalized Fixed Asset Labor)**: Sky Blue token (`bg-sky-100 text-sky-800 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800`).
        - **OpEx Labor (Routine Overtime)**: Neutral Slate token (`bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300`).
- **Strictly Single-Level Layout (No Nested Modals)**:
    - Modals inside modals or sheets inside sheets are strictly prohibited. Expanding timesheet rows or clicking chart tooltips occurs in-place.
- **Tabular Figures & Currency Standards**:
    - All hours (`34.5 jam`), NPKs (`ISZ-4091`), timestamps, and financial figures **must** use `font-mono tabular-nums` to eliminate layout jitter.
    - Currency strictly formatted as `Rp 1.234.567` (Indonesian thousand periods, no decimal cents).

---

### 4.3 Trust Signals & Welfare Communication Standards

- **Plain-Language Shopfloor Terminology**:
    - Avoid raw database column names (`overtime_items_sum_total_hours`, `variance_calc`). Use established Indonesian plant terminology:
        - `Jam Lembur Bulan Ini` (Current Month Approved Hours)
        - `Akumulasi Jam Lembur Tahun Ini (YTD)` (Year-to-Date Hours)
        - `Peringkat Beban Kerja Seksi` (Section Workload Ranking)
        - `Hari Kerja Normal (HKN)` vs `Hari Libur / Istirahat (HLR)`
        - `Indeks Burn Karyawan` (Individual Burn Index)
        - `Deviasi Rata-Rata Seksi` (Variance from Section Average)
        - `Skor Keselamatan Kerja` (Safety / Welfare Score)
        - `Batas Jam Mingguan` (Weekly Hours Soft Limit)
        - `Risiko Kelelahan (Fatigue Alert)` (Consecutive Overload Alert)
        - `Buku Jam Lembur` (Chronological Timesheet)
        - `Alasan Penolakan` (Rejection Reason)
- **Role-Based Anonymization Guarantee (Zero Interpersonal Friction)**:
    - For line operators (`User` role), peer benchmarking charts **must never reveal co-workers' names or NPKs**. The chart displays an anonymized distribution curve with an indicator saying: _"Posisi Anda: Peringkat ke-4 dari 24 karyawan"_ and a benchmark line for _"Rata-rata Seksi: 22.4 jam"_.
    - Supervisors (`Team Leader`) and `Manager` roles see full employee names to make informed task allocation decisions.
- **Advisory Soft Alerts (Zero Production Stalling)**:
    - Fatigue warnings must clearly state: _"Indikator ini bersifat anjuran keselamatan untuk pencegahan kelelahan kerja dan tidak memblokir penugasan lembur darurat."_ (This indicator is advisory for fatigue prevention and does not block emergency overtime).
- **Graceful Empty States**:
    - If an employee has 0 hours in the selected month: _"Belum ada jam lembur yang disetujui pada periode [Bulan/Tahun]."_
    - If a section has no individual budget targets: Render `Indeks Burn: N/A` with a helper tooltip: _"Target kuota lembur diatur pada tingkat seksi, bukan per individu."_

---

### 4.4 Epic E06 Specific Risks & Mitigations

| Sub-Epic / Feature                                      | Identified UX Risk                                                                                                                          | Mandatory Design Mitigation                                                                                                                                                                                                                                         |
| :------------------------------------------------------ | :------------------------------------------------------------------------------------------------------------------------------------------ | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **E06-01 (Slow Search & Heavy Payloads)**               | Searching across 1,500 employees on a slow factory Wi-Fi causes input lag and UI freezing.                                                  | Client-side 300ms debounce on input; backend limits search query results to top 10 matches scoped to the user's authorized department/section. "Recent Lookups" reads instantly from `localStorage` without hitting the network.                                    |
| **E06-01 & E06-02 (Unauthorized Cross-Dossier Access)** | A line operator modifies the URL to `/reports/employees/9999` to snoop on their coworker's or manager's overtime hours and earnings.        | Strict controller-level authorization gate: If `auth()->user()->role === 'user'`, the controller aborts with `403 Forbidden` if the requested employee ID does not match `auth()->user()->employee_id`. UI renders a friendly error page.                           |
| **E06-02 (Peer Comparison Envy / Friction)**            | Showing named coworker hours to general operators sparks interpersonal complaints about unfair overtime distribution.                       | Server-side anonymization transformation: When the authenticated user has the `User` role, peer datasets omit `employee_name` and `npk`, retaining only numerical distribution values and the user's own position.                                                  |
| **E06-04 (Alarm Fatigue from Notifications)**           | Recalculation jobs dispatch fatigue alert notifications on every submission approval, spamming the supervisor's inbox 50 times a month.     | Database alert state locking: Notifications for consecutive-week fatigue are deduplicated to fire **at most once per employee per calendar month**. Subsequent approvals in the same month update the visual badge without creating duplicate notification records. |
| **E06-04 (Supervisor Fear of Operational Lockout)**     | A supervisor thinks the red "Fatigue Alert" badge prevents them from scheduling a critical breakdown repair worker.                         | The badge is explicitly marked with an info tooltip and helper text stating: _"Peringatan Anjuran (Advisory) — Tidak membatasi pengajuan lembur operasional."_                                                                                                      |
| **E06-05 (Heavy Timesheet Pagination Lag)**             | An employee with 200 overtime items over multiple years causes heavy browser DOM rendering when viewing the timesheet.                      | Server-side pagination strictly locked to 25 items per page (`LengthAwarePaginator`), combined with eager loading of only needed relations (`overtimeSubmission:id,operational_date,day_type`, `capexProject:id,project_name,project_code`).                        |
| **E06-06 (Mobile Screen Clutter for Operators)**        | Trying to fit desktop analytics, complex histograms, and multi-column tables onto a smartphone screen makes the app unusable for operators. | Dedicated mobile-first layout (`EmployeeSelfService.vue`): Key metrics presented as 3 swipeable cards, followed by a compact list of recent submissions. Detailed timesheet and welfare charts are accessed via tab switching with horizontal scroll protection.    |

---

## 5. Implementation Boundaries (Anti-Splitting Rules)

These rules are strict architectural constraints for the implementing engineer or AI subagents. Any deviation will violate standup velocity, create fragmented navigation, or degrade analytical performance.

### 5.1 Do Not Split — Combine Into One Surface:

- **Unified Employee Dossier Hub (`/reports/employees/{npk}`)**:
    - Employee Header & Search (E06-01), Personal Overtime KPI Dashboard (E06-02), Peer Benchmarking (E06-03), Fatigue & Welfare Indicators (E06-04), and Chronological Timesheet (E06-05) **MUST live on a single primary page**: `resources/js/pages/reports/EmployeeDossier.vue`.
    - **Do NOT** split these views into disjointed separate pages (e.g., do NOT create `/reports/employees/{npk}/kpis`, `/reports/employees/{npk}/peer-benchmark`, or `/reports/employees/{npk}/welfare`).
- **Unified Period & Employee Context**:
    - The Month/Year picker in the dossier header **MUST control all tabs simultaneously**. Switching between the `Ringkasan & Kesejahteraan` tab and `Buku Jam Lembur` tab must preserve the active employee and selected fiscal period.

---

### 5.2 Make a Tab, Not a New Route:

- The chronological audit timesheet (E06-05) **MUST be a tab** (`?tab=timesheet`) inside `EmployeeDossier.vue`, **NOT a separate standalone route** like `/timesheet/view/{id}`.
- Tab switching must execute seamlessly using Inertia partial reloads (`preserveState: true`, `preserveScroll: true`), avoiding jarring full-page browser reloads.

---

### 5.3 Make a Drawer/Sheet or Accordion, Not a Full Page:

- **Rejection Reason Inspection**: Rejection reasons in the timesheet table must expand inline via an accordion row or popover, never opening a full-page redirect.
- **Recent Lookups Drawer on Mobile**: On mobile viewports (<768px), if the recent lookups list exceeds screen width, it must render as a horizontal touch-scrollable pill strip or a lightweight bottom sheet (`Sheet`), not a separate history screen.

---

### 5.4 Backend-Only — Strictly No Dedicated UI:

The following components and services are pure background, algorithmic, or scheduled logic. **Do NOT create dedicated navigation items, pages, or configuration menus for them**:

- **`EmployeeReportService`**: Domain aggregation service computing monthly hours, rankings, category breakdowns, and CALC-06 variances.
- **`OvertimePolicyEvaluator::getEmployeeWelfareStatus`**: Welfare assessment service calculating rolling 4-week totals, consecutive week breaches, and safety score percentages.
- **`FatigueAlertNotification`**: Notification class dispatched to the database channel during background job processing.
- **CSV Streaming Engine (`EmployeeTimesheetExport`)**: Headless file streaming service invoked directly via the `Unduh CSV` action button.
- **Peer Anonymization Pipeline**: Server-side filtering logic stripping identifiable coworker information for `User` role requests.

---

### 5.5 Strictly Forbidden:

1. **Do NOT use legacy Ziggy `route()` helper**: Always use typed **Laravel Wayfinder** functions imported from `@/actions` or `@/routes`.
2. **Do NOT create a separate route or page for Timesheet**: Timesheet must exist as a tab (`?tab=timesheet`) on the employee dossier.
3. **Do NOT expose coworker names to the `User` role**: Peer benchmarking for general operators must be strictly anonymized at the controller/service level before props are sent to Inertia.
4. **Do NOT block overtime submissions based on fatigue indicators**: Soft fatigue warnings and consecutive-week alerts are **advisory only**. Never disable submit or approve buttons based on welfare scores.
5. **Do NOT create duplicate fatigue notifications**: The system must check notification timestamps to ensure a supervisor is notified **at most once per employee per threshold per calendar month**.
6. **Do NOT create nested modals**: Modals or sheets stacked inside other modals or sheets are strictly forbidden.
7. **Do NOT hardcode currency or date formatting**: Monetary amounts must strictly use `Rp` with Indonesian thousand separators; dates, times, and weekly rolling calculations must strictly use `Asia/Jakarta (WIB)`.
8. **Do NOT display raw unformatted errors or NaN values**: If individual budgets are missing or planned hours are zero, render friendly fallback states (`Indeks Burn: N/A` or `Belum ada kuota individu`).
9. **Do NOT force general operators to navigate the Manager Dossier**: When a `User` role logs in, they must land directly on their simplified personal self-service dashboard (`/my/dashboard`), never on the multi-employee search interface.
