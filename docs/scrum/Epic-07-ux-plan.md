# UX Plan — E07: CapEx Project Labor Management

> Created before implementation. This document is a hard constraint for all work in Epic E07.  
> Epic file: `docs/scrum/Epic-07.md`

---

## 1. Navigation Footprint

### 1.1 Executive Philosophy: Capitalized Fixed Asset Traceability & Industrial Ergonomics

In automotive manufacturing and commercial vehicle assembly plants (such as ISUZU Astra Motor Indonesia), overtime labor splits strictly into two distinct accounting streams:

1. **Operating Expenditure (OpEx)**: Routine line production overruns, scheduled Total Productive Maintenance (TPM), and standard shopfloor support categorized as operational expenses on the monthly P&L statement.
2. **Capital Expenditure (CapEx)**: Factory automation upgrades, tooling jigs fabrication, robotic welding cell commissioning, and fixed asset installations. Direct engineering and technician labor dedicated to these capital projects must be **capitalized** onto the balance sheet as part of the asset's acquisition cost under IAS 16 and PSAK 16 statutory accounting standards.

This distinction has critical corporate implications:

- **Tax Depreciation Schedules**: An asset's capitalized cost basis must incorporate direct project overtime labor. Any failure to capture or attribute these hours understates the capital asset value.
- **Statutory Financial Audit Compliance**: External auditors require 100% end-to-end traceability between shopfloor punch records, supervisor approvals, employee hourly wage snapshots, and corporate project codes.
- **Labor Budget Burn Oversight**: Capital projects routinely overrun their labor budgets when physical shopfloor assembly lags behind labor burn velocity.

Epic E07 delivers the **CapEx Project Master Data Management, Labor Burn Cockpit, Portfolio Overview, and Financial Attribution System**. It connects frontline shift scheduling with corporate financial governance.

The target user personas are:

- **The CapEx Project Manager & Department Head (`Manager` Role)**: Line managers and project leaders who need a 60-second health check on active capital projects: _"Is our labor burn outpacing physical progress, and will this project breach its capitalized labor allowance?"_
- **The Frontline Team Leader (`Team Leader` Role)**: Shift supervisors on the factory floor with low patience and a 10–15 minute shift handover window. When scheduling or submitting overtime under the "Project" category, they need an effortless, validated project selector that prevents illegal or closed project attribution (Business Rule **BR-08**).
- **The Corporate Finance Controller & Plant Auditor (`Admin` Role)**: Accounting administrators who must audit immutable cost snapshots, inspect labor attribution schedules, and export audit-ready Excel reports for general ledger capitalization entries.
- **The Production Line Operator (`User` Role)**: Passive consumers who observe their personal CapEx contributions on their individual dossier timesheet (governed by Epic E06) without managing project master data.

The core UX principles for Epic E07 are:

1. **Single Project Management Hub (Zero Menu Fragmentation)**: CapEx Master Data CRUD (E07-01), Multi-Project Portfolio Monitoring (E07-03), and the Financial Labor Attribution Ledger (E07-04) live on **one primary surface** (`/admin/capex-projects`) divided into two intuitive tabs (`Portofolio & Master Data` and `Laporan Atribusi Finansial`).
2. **Dedicated Project Labor Cockpit (`/admin/capex-projects/{id}`)**: A high-density project command center displaying allocated vs. consumed hours, Rupiah cost snapshots, the CapEx Burn Index %, the Milestone Burn Ratio, weekly cumulative burn charts, and the ranked team contribution roster.
3. **In-Place Physical Progress Updates (Zero Full-Page Reloads)**: Project Managers update the physical progress percentage (`0.0% – 100.0%`) directly on the project cockpit with 1-click in-place editing, reactive milestone ratio recalculation, and automated audit logging (E07-05).
4. **Slide-in Master Data Management (Drawer over Page)**: Creating and editing CapEx projects occurs via a slide-in drawer (`CapexProjectDrawer.vue`), preserving background filters, search state, and active table pagination without disorienting full-page redirects.
5. **Enforced Shopfloor Guardrails (Zero-Error Frontline UX)**: The daily overtime submission form automatically limits the project dropdown to `ACTIVE` projects only, ensuring Team Leaders never face confusing post-submission audit rejections.
6. **Industrial Precision & Tabular Consistency**: Monospace tabular numerals (`font-mono tabular-nums`), ISUZU color semantics (Sky Blue for CapEx, Slate for OpEx, ISUZU Red for critical burn overruns), and Indonesian Rupiah currency formatting (`Rp 1.234.567`) guarantee zero visual ambiguity across desktop and mobile screens.

---

### 1.2 Sub-Epic Functional Separation

| Sub-Epic / Story                                                     | Category                                         | User-Facing Surface                                                                                                                                                                                         | Dedicated UI Needed?                                          | Current Status        |
| :------------------------------------------------------------------- | :----------------------------------------------- | :---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :------------------------------------------------------------ | :-------------------- |
| **E07-01**: CapEx Project Master Data Management (Admin/Manager)     | **User-Facing Master Data & Lifecycle**          | CapEx Projects Hub (`/admin/capex-projects?tab=portfolio`), Project Creation/Edit Drawer (`CapexProjectDrawer.vue`), Status Transition Modal (`ProjectStatusTransitionModal.vue`), Status Filter Chips      | ✅ Yes (Part of Unified Hub)                                  | 🟢 Completed (6/6 AC) |
| **E07-02**: CapEx Project Labor Burn Tracking Dashboard (Manager/PM) | **User-Facing Analytics Cockpit**                | CapEx Project Detail Page (`/admin/capex-projects/{id}`), 4 Macro KPI Cards, Timeline Chart (`CapexLaborTimelineChart.vue`), Contributor Roster (`CapexTeamContributionTable.vue`), Zero-Hours Empty Banner | ✅ Yes (Dedicated Detail Route)                               | 🟢 Completed (5/5 AC) |
| **E07-03**: Multi-Project Portfolio Overview (Manager/Admin)         | **User-Facing Portfolio Health Table**           | Consolidated Portfolio Table (`CapexPortfolioTable.vue` inside Hub Tab 1), Department KPI Summary Header, CapEx Burn Index Color Badges, At-Risk Flag Badges (`⚠️`), Cross-Dept Filter                      | ✅ Yes (Unified directly into Hub Tab 1; not a separate page) | 🟢 Completed (7/7 AC) |
| **E07-04**: CapEx Project Labor Attribution Report (Finance/Admin)   | **User-Facing Financial Audit Ledger & Export**  | Financial Attribution Tab (`/admin/capex-projects?tab=attribution`), Detailed Audit Table (`CapexLaborAttributionTable.vue`), Subtotals per Project Group, Direct Streaming `.xlsx` Export Trigger          | ✅ Yes (Tab 2 on Unified Hub; not an isolated report route)   | ⏳ Pending (0/7 AC)   |
| **E07-05**: CapEx Project Physical Progress Update (Manager)         | **In-Place Interactive Control & Audit Logging** | Inline Editable Progress Widget (`InlineProgressEditor.vue` inside Project Detail Page), 100% Completion Milestone Prompt, Immediate Milestone Ratio Recalculation                                          | ✅ Yes (In-place widget on Detail Page; no full page reload)  | 🟢 Completed (5/5 AC) |

---

### 1.3 Minimal Navigation Footprint Decisions

#### Sidebar Navigation Additions:

- **For `Admin` and `Manager` Roles**:
    - Adds **1 primary sidebar item**:
        - **Proyek CapEx** (CapEx Projects) — Route: `/admin/capex-projects`.
        - Icon: `FolderKanban` or `Layers`.
        - Features an amber alert badge if any active project has a Milestone Burn Ratio $> 1.2$ or CapEx Burn Index $> 90\%$.
- **For `Team Leader` Role**:
    - Adds **0 new sidebar items**.
    - Frontline Team Leaders interact with CapEx projects strictly through the existing Timesheet Entry screen (`/overtime/submissions/create` from Epic E03). When project hours are entered (`hours_project > 0`), the dropdown selector automatically fetches active projects.
- **For `User / Operator` Role**:
    - Adds **0 new sidebar items**.
    - General operators view their attributed project hours on their personal timesheet ledger (`/my/dashboard` or `/reports/employees/{npk}?tab=timesheet` from Epic E06).

#### Distinct Routes & Pages:

Across the entire 35-story-point epic, exactly **2 primary routes** are registered:

1. `/admin/capex-projects` — The Unified CapEx Project Hub (`resources/js/pages/Admin/CapexProjects/Index.vue`).
    - Hosts Tab 1: `Portofolio & Master Data` (`?tab=portfolio` - default).
    - Hosts Tab 2: `Laporan Atribusi Finansial` (`?tab=attribution`).
    - Legacy or cross-link routes (`/reports/capex-projects/portfolio` and `/reports/capex-labor`) seamlessly redirect (HTTP 301/302) to this hub with the matching tab query parameter.
2. `/admin/capex-projects/{id}` — CapEx Project Detail & Labor Cockpit (`resources/js/pages/Admin/CapexProjects/Show.vue`).
    - Deep-dive command center for a single project: burn metrics, timeline curves, team composition, and in-place physical progress editing.

#### Surfaces Handled via Drawers/Sheets, Modals, or Tabs (Never Dedicated Routes):

- **Portofolio & Master Data Tab**: Tab 1 (`?tab=portfolio`) on `/admin/capex-projects`. Unifies master data CRUD and portfolio burn tracking into one high-density table.
- **Laporan Atribusi Finansial Tab**: Tab 2 (`?tab=attribution`) on `/admin/capex-projects`. Financial audit ledger with project group subtotals and direct Excel export.
- **Create / Edit Project Drawer**: Slide-in Right Drawer (`CapexProjectDrawer.vue`). Used for adding a new project or updating editable fields without leaving the portfolio table.
- **Status Transition Modal**: Lightweight single-level confirmation dialog (`ProjectStatusTransitionModal.vue`) for transitioning lifecycle states (`PLANNING → ACTIVE`, `ACTIVE → ON_HOLD / COMPLETED`, `COMPLETED → CLOSED`).
- **Physical Progress Editor**: In-place inline edit (`InlineProgressEditor.vue`) on `/admin/capex-projects/{id}` with immediate PATCH save and reactive UI update.
- **Financial Attribution Excel Export**: Toolbar button triggering streamed download (`GET /admin/capex-projects/export-attribution` or `GET /reports/capex-labor/export`).

#### Pure Backend & Headless Logic (No Dedicated UI):

- `CapExAccountingService`: Domain calculation engine computing project labor burn metrics, CapEx Burn Index %, Milestone Burn Ratios, consumed hours, and cumulative snapshot costs.
- `BR-08 Validator`: Intercepts `StoreOvertimeSubmissionRequest` to guarantee `capex_project_id` exists and references a project with status `ACTIVE` whenever `hours_project > 0`.
- `Immutable Project Code Invariant`: Backend validator rejecting any payload attempting to modify `project_code` on `UpdateCapexProjectRequest`.
- `CapexBurnAlertNotification`: Asynchronous database notification dispatched when CapEx Burn Index $> 80\%$, deduplicated to fire at most once per project per calendar month.
- `CapexLaborExport`: Headless Excel generation pipeline (`maatwebsite/excel`) streaming grouped, styled `.xlsx` workbooks directly to the client browser.
- Audit Trail Logger: Automatically records `PROGRESS_UPDATE` and `STATUS_TRANSITION` events with previous value, new value, actor user ID, and timestamp into `capex_project_audits`.

---

## 2. Screen Inventory

| Screen / Panel                                                   | Location                                                                   | What's visible on first open                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  | Further triggers                                                                                                                                                                                                                                                                                                                                                                               | Click depth                                               |
| :--------------------------------------------------------------- | :------------------------------------------------------------------------- | :-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :-------------------------------------------------------- |
| **CapEx Project Hub — Portfolio & Master Data Tab (Tab 1)**      | `/admin/capex-projects?tab=portfolio` (`Index.vue`)                        | • **Page Header**: Title ("Manajemen Proyek CapEx & Portofolio"), WIB Live Clock, Active Role pill<br>• **Department KPI Summary Bar**:<br> - Active Projects counter (`8 Proyek Aktif`)<br> - Consumed vs Allocated Hours (`1.245 / 2.800 jam - 44.5%`)<br> - Total Capitalized Labor Cost (`Rp 87.150.000`)<br> - Overrun Warning Counter (`2 Proyek Berisiko Tinggi`)<br>• **Toolbar & Action Bar**:<br> - Status filter chips (`Semua`, `PLANNING`, `ACTIVE`, `ON_HOLD`, `COMPLETED`, `CLOSED`)<br> - Department filter dropdown (for Admin; Manager pre-scoped)<br> - Search input (debounced by project code, name, asset tag)<br> - **Primary Button**: `+ Tambah Proyek CapEx` (ISUZU Red)<br>• **Unified Portfolio & Master Data Table**:<br> - Columns: `Kode Proyek` (`font-mono tabular-nums`), `Nama Proyek`, `Aset Tetap`, `Departemen`, `Status` (color pill), `Alokasi (Jam)`, `Realisasi (Jam)`, `Indeks Burn (%)`, `Kemajuan Fisik (%)`, `Rasio Burn Milestone`, `Target Selesai`, `Sisa Hari`, `Aksi`<br> - Visual row highlighting: Amber tint for Warning (Milestone Ratio $> 1.2$), Red tint for Deficit (Burn Index $> 90\%$)<br> - Action icons: `Detail →` (Cockpit), `Edit` (Drawer), `Ubah Status` (Modal)<br>• **Pagination Footer**: 20 rows per page                                                                                                                                                                                                                                                                                                                                                            | • Clicking `+ Tambah Proyek CapEx` opens **Project Creation Drawer**<br>• Clicking `Edit` on a row opens **Project Edit Drawer**<br>• Clicking `Ubah Status` opens **Status Transition Modal**<br>• Clicking any row or `Detail →` navigates to **Project Detail Cockpit** (`/admin/capex-projects/{id}`)<br>• Switching to Tab 2 updates URL to `?tab=attribution` with zero full-page reload | **1** (Sidebar > Proyek CapEx)                            |
| **CapEx Project Hub — Financial Attribution Report Tab (Tab 2)** | `/admin/capex-projects?tab=attribution` (`CapexLaborAttributionTable.vue`) | • Same Header & WIB Live Clock<br>• **Financial Report Toolbar**:<br> - Project filter dropdown (`Semua Proyek` or specific project)<br> - Department filter dropdown<br> - Date range picker (`Bulan Ini`, `YTD`, `Kustom Rentang Tanggal`)<br> - Search input for employee NPK / Name / SPKL Code<br> - **Header Action Button**: `Unduh Excel (.xlsx)` (Emerald green icon with loading spinner)<br>• **Itemized Attribution Audit Table** (Grouped by Project):<br> - Group Header: `[CPX-2026-ASSY-001] Pemasangan Lini Robot Welding 2 (Aset: AST-8812)`<br> - Columns: `Tanggal (WIB)`, `NPK`, `Nama Karyawan`, `Jam Proyek`, `Tarif Snapshot/Jam (Rp)`, `Total Biaya (Rp)`, `No. SPKL`, `Tgl Persetujuan`, `Disetujui Oleh`<br> - Group Subtotal Row: `Subtotal Proyek: 142.5 jam • Rp 9.975.000`<br> - Grand Total Row at bottom: `Grand Total Seluruh Proyek: 1.245,0 jam • Rp 87.150.000`<br>• **Empty State**: "Tidak ada catatan lembur CapEx yang disetujui pada filter tanggal ini"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            | • Changing date range or project filter triggers Inertia partial reload (`preserveScroll: true`)<br>• Clicking `Unduh Excel (.xlsx)` initiates streamed file download of formatted spreadsheet<br>• Clicking employee NPK navigates to Employee Dossier (`/reports/employees/{npk}`)                                                                                                           | **1** (Hub > Click Tab 2)                                 |
| **CapEx Project Creation / Edit Drawer**                         | Slide-in Sheet (Right, `CapexProjectDrawer.vue`)                           | • **Drawer Header**: Title (`Tambah Proyek CapEx Baru` or `Edit Proyek: CPX-2026-ASSY-001`), Close button (X)<br>• **Form Body (Grouped in 4 Ergonomic Sections)**:<br> 1. _Identitas Proyek_: `Kode Proyek` (text input; immutable with lock icon in edit mode), `Nama Proyek` (text), `Kode Aset Tetap` (optional text)<br> 2. _Departemen & Penanggung Jawab_: `Departemen Pemilik` (select), `Project Manager` (select)<br> 3. _Alokasi Anggaran Tenaga Kerja_: `Alokasi Jam Lembur (Jam)` (number input, `step="0.5"`), `Alokasi Anggaran Tenaga Kerja (Rp)` (currency input with live `Rp` formatting)<br> 4. _Jadwal Pengerjaan_: `Tanggal Mulai` (date), `Target Tanggal Selesai` (date)<br>• **Drawer Footer**:<br> - Cancel button (`Batal`)<br> - Submit button (`Simpan Proyek` with loading state `Menyimpan...`)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | • Typing currency formats automatically as Indonesian Rupiah (`Rp 35.000.000`)<br>• In Edit mode, `Kode Proyek` input is disabled with explanatory tooltip: _"Kode proyek bersifat permanen dan tidak dapat diubah setelah dibuat demi kepatuhan audit."_<br>• Clicking `Simpan Proyek` validates and updates portfolio table via Inertia partial reload                                       | **2** (Hub > Click `+ Tambah Proyek` or `Edit`)           |
| **CapEx Project Status Transition Modal**                        | Single-Level Modal (`ProjectStatusTransitionModal.vue`)                    | • **Modal Header**: `Ubah Status Proyek: [Nama Proyek]`<br>• **Current Status Pill**: e.g., `Status Saat Ini: ACTIVE (Aktif)`<br>• **Target Status Selector**: Radio group showing valid transitions according to state machine rules:<br> - From `PLANNING`: `ACTIVE`<br> - From `ACTIVE`: `ON_HOLD`, `COMPLETED`<br> - From `ON_HOLD`: `ACTIVE`, `COMPLETED`<br> - From `COMPLETED`: `CLOSED`<br>• **Audit Impact Warning Box**:<br> - If selecting `CLOSED`: Red alert callout: _"⚠️ Peringatan Audit: Menutup proyek (CLOSED) akan mengunci proyek secara permanen dari pengajuan lembur baru. Tindakan ini tidak dapat dibatalkan."_<br>• **Reason / Note Input**: Optional textarea for transition notes<br>• **Action Buttons**: `Batal`, `Konfirmasi Perubahan Status`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | • Selecting a new status radio button dynamically shows the relevant audit impact notice<br>• Clicking `Konfirmasi Perubahan Status` sends `PATCH /admin/capex-projects/{id}/status`, closes modal, and refreshes status pill with a success toast                                                                                                                                             | **2** (Hub or Cockpit > Click `Ubah Status`)              |
| **CapEx Project Detail & Labor Burn Cockpit**                    | `/admin/capex-projects/{id}` (`Show.vue`)                                  | • **Persistent Project Header**:<br> - Project Code badge (`CPX-2026-ASSY-001`, `font-mono tabular-nums`), Asset Code badge, Department name<br> - Project Name header with active Status pill (`ACTIVE`)<br> - Target End Date & Countdown badge: `Sisa 45 Hari (Target: 31 Des 2026)`<br> - Quick action: `Ubah Status Proyek` button<br>• **4 Macro KPI Cockpit Cards**:<br> 1. `Jam Tenaga Kerja`: `342.5 / 500.0 jam` (Consumed vs Allocated) + Progress bar<br> 2. `Biaya Tenaga Kerja Terkapitalisasi`: `Rp 23.975.000 / Rp 35.000.000` (Snapshot cost vs Budget)<br> 3. `Indeks Burn CapEx`: `68.5%` (Color-coded ring: Emerald $<85\%$, Blue $85–100\%$, Amber $101–115\%$, Red $>115\%$)<br> 4. `Rasio Burn Milestone`: `1.37` (with warning badge: `⚠️ Pembakaran Jam Lebih Cepat dari Kemajuan Fisik`)<br>• **Physical Progress Widget** (`InlineProgressEditor.vue`):<br> - Current Physical Progress display (`50.0%`)<br> - In-place slider / number input with `Simpan` button<br> - Last updated timestamp & author ("Diperbarui oleh Budi Santoso, 2 hari lalu")<br>• **Timeline & Contribution Split Row** (Desktop 2-col, Mobile stacked):<br> - **Labor Hours Timeline Chart** (`CapexLaborTimelineChart.vue`): Cumulative hours consumed week-by-week vs. linear planned allocation curve<br> - **Team Contributor Roster** (`CapexTeamContributionTable.vue`): Ranked table of employees contributing to this project (NPK, Name, Section, Approved Hours, Total Snapshot Cost, % of Project Labor)<br>• **Zero Hours Empty State** (if no hours logged): "Belum ada jam lembur tercatat — Proyek dalam tahap alokasi" | • Dragging physical progress slider updates percentage live; clicking `Simpan` saves in-place without page reload<br>• If physical progress reaches 100%, reveals the **Completion Milestone Prompt**<br>• Clicking an employee row links to their Individual Dossier (`/reports/employees/{npk}`)<br>• Hovering on timeline chart data points displays weekly hours and date ranges in WIB    | **2** (Hub > Click Project Row or Code)                   |
| **Completion Milestone Prompt Banner**                           | Inside Project Detail Cockpit (`Show.vue`)                                 | • Appears immediately when `physical_progress_pct` reaches `100.0%`<br>• Sky-blue highlight callout banner:<br> - Title: `🎉 Kemajuan Fisik Mencapai 100%`<br> - Message: `Proyek telah selesai secara fisik. Apakah Anda ingin memperbarui status proyek menjadi COMPLETED?`<br> - Action Buttons: `Ubah Status ke COMPLETED Sekarang` (Primary), `Nanti Saja` (Secondary)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | • Clicking `Ubah Status ke COMPLETED Sekarang` opens the Status Transition Modal with `COMPLETED` pre-selected<br>• Clicking `Nanti Saja` dismisses the banner                                                                                                                                                                                                                                 | **0** (Auto-triggers on in-place progress update to 100%) |
| **Frontline Timesheet CapEx Selector Dropdown**                  | Inside `/overtime/submissions/create` (`OvertimeItemRow.vue`)              | • Appears in the Daily Overtime Submission row whenever `hours_project > 0`<br>• Dropdown list populated strictly with `ACTIVE` projects:<br> - Display: `[CPX-2026-ASSY-001] Pemasangan Lini Robot Welding 2`<br>• Required validation asterisk: Project selection is strictly mandatory when project hours $> 0$ (BR-08)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | • Selecting a project binds `capex_project_id` to the overtime item<br>• If user clears project hours (`hours_project = 0`), dropdown hides and `capex_project_id` resets to `null`                                                                                                                                                                                                            | **Part of Daily Shift Entry Flow** (Epic E03)             |
| **CapEx Burn Alert Notification Bell Item**                      | Topbar Notification Bell (`NotificationBell.vue`)                          | • **Warning Item**: Amber bell badge `⚠️ Peringatan Alokasi CapEx: Proyek [CPX-2026-ASSY-001] telah mencapai 82.5% dari alokasi jam kerja.`<br>• **Danger Item**: Red bell badge `🚨 Risiko Defisit CapEx: Rasio Burn Milestone Proyek [CPX-2026-ASSY-001] mencapai 1.45 (Kemajuan Fisik tertinggal)!`<br>• Timestamp: Relative Indonesian time (`20 menit yang lalu`)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | • Clicking notification navigates directly to `/admin/capex-projects/{id}` with the relevant metric card highlighted                                                                                                                                                                                                                                                                           | **1** (Click notification bell from any page)             |

---

## 3. User Journey Maps

### Journey 1: Admin or CapEx Manager Creates a New Machinery Installation Project (Master Data Setup)

```
Goal: Register a new capital project record with immutable project code, allocated labor hours, and Rupiah budget
Starts at: /admin/capex-projects (CapEx Project Hub)
Steps:
  1. Admin clicks "Proyek CapEx" on the sidebar to land on the CapEx Project Hub.
  2. Admin clicks the primary button "+ Tambah Proyek CapEx". The slide-in drawer opens smoothly from the right.
  3. Admin fills in the 4 concise sections:
     - Project Code: "CPX-2026-ASSY-002", Name: "Otomasi Feeder Robot Assy 3", Asset Code: "AST-99412".
     - Department: "Assembly 2", Project Manager: "Hendra Wijaya".
     - Allocated Hours: "400.0 jam", Allocated Budget: "Rp 28.000.000".
     - Schedule: Start "2026-10-01", Target End "2026-12-31".
  4. Admin clicks "Simpan Proyek". The drawer closes, a success toast appears ("Proyek CapEx berhasil didaftarkan"), and the new project appears at the top of the portfolio table in PLANNING status.
Done: Project registered in under 45 seconds with complete audit-compliant parameters.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

### Journey 2: Frontline Team Leader Attributes Overtime to an Active CapEx Project (Shopfloor Shift Handover - BR-08)

```
Goal: Team Leader enters 2.5 project overtime hours for a line technician fabricating a machine bracket during shift handover
Starts at: /overtime/submissions/create (Daily Overtime Submission Form)
Steps:
  1. Team Leader opens the submission form, selects technician "Agus Setiawan (NPK: 5120)", and enters "2.5" in the "Proyek" hours input column.
  2. The form instantly reveals the "Pilih Proyek CapEx" dropdown (validated by BR-08).
  3. Team Leader selects "[CPX-2026-ASSY-001] Pemasangan Lini Robot Welding 2" from the active project list (inactive/closed projects are filtered out).
Done: Team Leader submits the shift overtime form in 3 steps without ever seeing invalid project codes or getting audit rejection errors.
Step count: 3
Status: ✅ OK (≤4 steps)
```

---

### Journey 3: Project Manager Inspects Burn Health and Updates Physical Progress In-Place (Weekly PM Review - E07-02, E07-05)

```
Goal: Project Manager checks labor burn against project physical completion and updates progress from 40% to 55%
Starts at: /admin/capex-projects
Steps:
  1. Project Manager clicks "Proyek CapEx" in the sidebar and clicks on project "CPX-2026-ASSY-001".
  2. The Project Labor Cockpit loads. PM reviews the 4 KPI cards:
     - Consumed Hours: 260.0 / 400.0 hrs (Burn Index: 65.0%).
     - Current Physical Progress: 40.0%.
     - Milestone Burn Ratio: 1.63 (Amber warning badge: "⚠️ Pembakaran Jam Lebih Cepat dari Kemajuan Fisik").
  3. PM drags the in-place physical progress slider to "55.0%" and clicks "Simpan".
Done: Progress updates instantly via Inertia patch without a full page reload; Milestone Burn Ratio re-evaluates to 1.18, warning badge relaxes, and the update is logged in the audit trail.
Step count: 3
Status: ✅ OK (≤4 steps)
```

---

### Journey 4: Department Manager Audits Multi-Project Portfolio for Capital Overrun Risks (Weekly Standup Review - E07-03)

```
Goal: Department Manager scans all departmental CapEx projects to prioritize critical reviews during weekly management standup
Starts at: /admin/capex-projects
Steps:
  1. Department Manager arrives at the CapEx Project Hub.
  2. Manager glances at the Department KPI Summary Bar: "8 Proyek Aktif | 1.245 / 2.800 jam (44.5%) | 2 Proyek Berisiko Tinggi".
  3. Manager filters by status "ACTIVE" and sorts the table by Milestone Burn Ratio descending:
     - Project "CPX-2026-WELD-004" is flagged with ⚠️ (Burn Index 92%, Milestone Ratio 1.45, 12 days remaining).
Done: Manager identifies the single critical project requiring immediate schedule/manpower intervention in under 20 seconds.
Step count: 3
Status: ✅ OK (≤4 steps)
```

---

### Journey 5: Finance Controller Exports Labor Attribution Ledger to Excel for Fixed Asset Capitalization (Monthly Accounting Closing - E07-04)

```
Goal: Finance Controller generates and downloads the detailed CapEx labor attribution schedule with immutable cost snapshots for corporate accounting
Starts at: /admin/capex-projects
Steps:
  1. Controller clicks "Proyek CapEx" on the sidebar and switches to Tab 2: "Laporan Atribusi Finansial".
  2. Controller selects the closing month ("Agustus 2026") from the date range selector.
  3. Controller reviews the grouped table: subtotal hours and Rupiah amounts per project group, with Grand Total: "1.245,0 jam • Rp 87.150.000".
  4. Controller clicks the "Unduh Excel (.xlsx)" button.
Done: Formatted Excel spreadsheet (`Laporan-Atribusi-CapEx-2026-08.xlsx`) streams directly to the controller's computer with immutable cost snapshots and audit signatures, ready for journal voucher entry.
Step count: 4
Status: ✅ OK (≤4 steps)
```

---

## 4. Indonesian UX Guardrails

### 4.1 Patience Thresholds

Automotive factory supervisors and shopfloor operators operate under tight shift cadences where prolonged screen interaction causes production bottlenecks:

- **Daily Shift Overtime Attribution (Daily Frontline Task - BR-08)**: Max **2–3 clicks / taps**:
    - Enter project hours → Select active CapEx project from dropdown → Submit shift.
- **Weekly Project Health Check (Weekly PM Task)**: Max **2 clicks**:
    - Click `Proyek CapEx` → Click target project row to view cockpit.
- **In-Place Physical Progress Update (Weekly PM Routine)**: Max **1–2 clicks**:
    - Adjust slider/input on cockpit → Click `Simpan` (no page navigation, no nested modals).
- **Portfolio Health Audit (Weekly Standup Review)**: Max **2 clicks**:
    - Open `Proyek CapEx` → Filter by status/sort by risk flag.
- **Financial Attribution Excel Export (Monthly Finance Closing)**: Max **2 clicks**:
    - Switch to `Laporan Atribusi Finansial` tab → Click `Unduh Excel (.xlsx)`.

---

### 4.2 Cognitive Load Budget

- **Maximum 3–4 Primary Interactive Controls on Screen**:
    - Hub View: Status filter chips, Department selector, Search bar, and `+ Tambah Proyek CapEx` primary button.
    - Project Cockpit View: Back button, Period selector, `Ubah Status Proyek` button, and in-place progress slider.
- **Progressive Disclosure Strategy**:
    - Top level surfaces macro portfolio health: Total Active Projects, Consumed vs Allocated Hours, Total Capitalized Cost, and At-Risk Project Counter.
    - Granular employee line-item attributions and SPKL audit codes are housed in Tab 2 (`Laporan Atribusi Finansial`) or inside the project detail cockpit.
    - Inactive and closed projects are collapsed or filtered out by default to avoid cluttering daily operations.
- **Strictly Single-Level Layout (No Nested Modals)**:
    - Modals inside modals or drawers inside drawers are **strictly prohibited**.
    - Project creation/editing uses a slide-in drawer (`Sheet`).
    - Status transitions use a focused single-level dialog.
    - Physical progress edits occur in-place on the page surface.
- **ISUZU Industrial Color Tokens & Visual Semantics**:
    - **ISUZU Brand Primary Red**: `#cc0000` (`var(--primary)`) used for primary call-to-action buttons, active tab indicators, and critical overrun alerts (`🔴 Indeks Burn > 90%`).
    - **CapEx Labor (Capitalized Fixed Asset Labor)**: Sky Blue token (`bg-sky-100 text-sky-800 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800`).
    - **OpEx Labor (Routine Operational Overtime)**: Neutral Slate token (`bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300`).
    - **Status Lifecycle Tokens**:
        - `PLANNING`: Slate neutral (`bg-slate-100 text-slate-700 border-slate-300`).
        - `ACTIVE`: Emerald green (`bg-emerald-50 text-emerald-700 border-emerald-300`).
        - `ON_HOLD`: Amber warning (`bg-amber-50 text-amber-700 border-amber-300`).
        - `COMPLETED`: Sky blue (`bg-sky-50 text-sky-700 border-sky-300`).
        - `CLOSED`: Zinc muted (`bg-zinc-100 text-zinc-600 border-zinc-300`).
- **Tabular Figures & Currency Standards**:
    - All Project Codes (`CPX-2026-ASSY-001`), Fixed Asset Tags (`AST-8812`), NPKs (`ISZ-4091`), hours (`142.5 jam`), and currency amounts **must** use `font-mono tabular-nums` to eliminate layout jitter during live updates.
    - Currency strictly formatted as `Rp 1.234.567` (Indonesian thousand periods, no decimal cents).

---

### 4.3 Trust Signals & Financial Compliance

- **Plain-Language Shopfloor Terminology**:
    - Avoid raw database column names or developer jargon. Use established Indonesian plant terminology:
        - `Proyek CapEx` (Capital Expenditure Project)
        - `Kode Aset Tetap` (Fixed Asset Code / Tag)
        - `Alokasi Jam Kerja` (Allocated Labor Hours)
        - `Realisasi Jam Kerja` (Consumed Labor Hours)
        - `Biaya Tenaga Kerja Terkapitalisasi` (Capitalized Labor Cost)
        - `Kemajuan Fisik Proyek (%)` (Physical Progress %)
        - `Indeks Burn CapEx (%)` (CapEx Burn Index %)
        - `Rasio Burn Milestone` (Milestone Burn Ratio)
        - `Laporan Atribusi Finansial` (Labor Attribution Audit Report)
        - `Tarif Snapshot per Jam` (Immutable Hourly Rate Snapshot)
- **Immutable Project Code Integrity**:
    - Once created, a project code (`project_code`) cannot be changed. The edit drawer renders the field disabled with a lock icon and an explanatory note:
        > _"Kode proyek bersifat permanen dan tidak dapat diubah setelah dibuat demi menjaga jejak audit akuntansi dan kepatuhan pajak."_
- **Irreversible Project Closure Safeguard**:
    - Transitioning to `CLOSED` permanently locks the project from accepting any new overtime submissions. The confirmation modal displays a plain-language consequence description:
        > _"Menutup proyek (CLOSED) akan mengunci proyek secara permanen dari pengajuan lembur baru. Apakah Anda yakin ingin menutup proyek ini?"_
- **Milestone Burn Ratio Warning Callout**:
    - When Milestone Burn Ratio $> 1.2$, display an amber badge with a plain-language explanation:
        > _"⚠️ Peringatan: Jam lembur terpakai lebih cepat dibanding kemajuan fisik pekerjaan (Rasio: :ratio). Periksa kecepatan pengerjaan fisik."_
- **Immutable Financial Cost Snapshot Principle**:
    - The financial attribution ledger **never** recalculates historical labor costs using current employee salaries. It strictly presents the immutable `total_cost_snapshot` recorded at the time of submission approval, satisfying statutory tax and audit requirements.
- **Graceful Zero-Data Fallbacks**:
    - If a new project has no hours logged yet, display:
        > _"Belum ada jam lembur tercatat — Proyek dalam tahap alokasi anggaran."_

---

### 4.4 Risks Specific to This Epic & Mitigations

| Sub-Epic / Feature                                                 | Identified UX / Technical Risk                                                                                                                                                  | Mandatory Design Mitigation                                                                                                                                                                                                                              |
| :----------------------------------------------------------------- | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **E07-01 (Non-Standard Project Codes)**                            | Users enter arbitrary project code formats (e.g. `test-proj`, `123`), making ERP integration and accounting audits fail.                                                        | Enforce strict regex validation on `StoreCapexProjectRequest` (`^CPX-\d{4}-[A-Z0-9]+-\d{3,}$`, e.g., `CPX-2026-ASSY-001`). Provide an auto-formatting placeholder and instant inline regex validation feedback.                                          |
| **E07-01 (Accidental Code Modification)**                          | A manager renames a project code after 50 overtime hours have been logged, corrupting historical attribution links.                                                             | Lock `project_code` immutably on `UpdateCapexProjectRequest`. Strip the attribute from the update whitelist and render the input read-only with a lock icon in the edit drawer.                                                                          |
| **E07-01 & BR-08 (Shopfloor Inactive Project Selection)**          | Team Leaders select closed or on-hold projects, causing unexpected validation errors during busy shift handovers.                                                               | Filter the timesheet project dropdown strictly to `status = 'ACTIVE'`. Inactive, on-hold, or closed projects are never rendered in the selection options.                                                                                                |
| **E07-02 (CapEx Burn Alert Notification Spam)**                    | As overtime items are approved in batches, Project Managers receive 20 alert notifications in one day when Burn Index exceeds 80%.                                              | Deduplicate notification dispatches in `CapexAccountingService` / `CapexBurnAlertNotification`. Check `burn_alerted_at` timestamp on `capex_projects` to ensure at most one alert fires per project per calendar month.                                  |
| **E07-03 (Fragmented Navigation Between Master Data & Portfolio)** | Developing a master data list page at `/admin/capex-projects` and a separate portfolio page at `/reports/capex-projects/portfolio` confuses users who don't know where to look. | **Merge both concepts into one unified table** on Tab 1 of `/admin/capex-projects`. Include master data attributes and real-time burn health columns in a single, high-density industrial view. Redirect legacy portfolio links to this unified surface. |
| **E07-04 (Heavy Excel Export Crashing Browser Memory)**            | Exporting plant-wide attribution records spanning 5 years across 50,000 overtime items exhausts PHP memory and times out the browser.                                           | Use streamed chunking (`FromQuery` with cursor-based batching in `CapexLaborExport`). Scope default date range to the active fiscal month, and display a loading spinner on the `Unduh Excel` button while streaming.                                    |
| **E07-05 (Erroneous Physical Progress Entry)**                     | A user accidentally enters `500%` or `-20%`, corrupting the Milestone Burn Ratio calculation.                                                                                   | Strict input bounds: Slider is constrained between `0.0` and `100.0` with `step="0.5"`. Manual numeric inputs clamp automatically on blur. Backend request validates `numeric                                                                            | between:0,100`. |
| **E07-05 (Disorienting Full-Page Reload on Progress Save)**        | Updating physical progress triggers a full browser reload, resetting scroll position and causing screen flicker.                                                                | Use Inertia's `router.patch` with `preserveScroll: true` and partial reloads (`only: ['project', 'metrics']`), or an optimistic in-place client state update with rollback on error.                                                                     |

---

## 5. Implementation Boundaries (Anti-Splitting Rules)

These rules are strict architectural constraints for the implementing engineer or AI subagents. Any deviation will violate standup velocity, create fragmented navigation, or degrade analytical performance.

### 5.1 Do Not Split — Combine Into One Surface:

- **Unified CapEx Project Hub (`/admin/capex-projects`)**:
    - Master Data CRUD (E07-01), Multi-Project Portfolio Monitoring (E07-03), and Financial Attribution Reporting (E07-04) **MUST live under a single primary page**: `resources/js/pages/Admin/CapexProjects/Index.vue`.
    - **Do NOT** split these into separate disjointed pages (e.g., do NOT create a separate `/reports/capex-projects/portfolio` page or `/reports/capex-labor` page).
    - The unified table on Tab 1 must display both project metadata (code, name, department, status, target date) AND real-time labor burn analytics (allocated hours, consumed hours, Burn Index %, physical progress %, Milestone Burn Ratio).

---

### 5.2 Make a Tab, Not a New Route:

- The plant-wide Financial Labor Attribution Ledger (E07-04) **MUST be a tab** (`?tab=attribution`) inside `Index.vue`, **NOT an isolated standalone route**.
- Any incoming request to `/reports/capex-labor` must be redirected via an HTTP 301/302 route redirect in `routes/web.php` to `/admin/capex-projects?tab=attribution`.
- Tab switching must execute seamlessly using Inertia partial reloads (`preserveState: true`, `preserveScroll: true`), avoiding jarring full-page browser reloads.

---

### 5.3 Make a Drawer/Sheet or Modal, Not a Full Page:

- **Project Creation & Editing**: Adding a new project or updating project parameters MUST occur inside a slide-in drawer (`CapexProjectDrawer.vue` using Shadcn/Radix `Sheet`), **NOT a standalone route** like `/admin/capex-projects/create` or `/admin/capex-projects/{id}/edit`.
- **Status Lifecycle Transitions**: Changing project status (`PLANNING → ACTIVE → ON_HOLD / COMPLETED → CLOSED`) MUST occur inside a lightweight single-level modal (`ProjectStatusTransitionModal.vue`), **NOT a separate workflow page**.
- **Physical Progress Editing**: Updating `physical_progress_pct` (E07-05) MUST be handled in-place on the Project Detail Cockpit via `<InlineProgressEditor>`, **NOT a separate modal or page**.

---

### 5.4 Backend-Only — Strictly No Dedicated UI:

The following components and services are pure background, algorithmic, or scheduled logic. **Do NOT create dedicated navigation items, pages, or configuration menus for them**:

- **`CapExAccountingService`**: Domain calculation service computing project burn metrics, Milestone Burn Ratios, consumed hours, and snapshot costs.
- **`BR-08 Validator`**: Controller/Request validation layer enforcing project active status in `StoreOvertimeSubmissionRequest`.
- **`CapexBurnAlertNotification`**: Notification class dispatched to the database channel when Burn Index $> 80\%$.
- **`CapexLaborExport`**: Headless Excel generation class implementing `FromQuery` and `WithHeadings` from `maatwebsite/excel`.
- **Project Code Immutability Guard**: Request validation rule rejecting changes to `project_code` on update.
- **Audit Logger**: Backend event listener writing `PROGRESS_UPDATE` and `STATUS_TRANSITION` audit entries into `capex_project_audits`.

---

### 5.5 Strictly Forbidden:

1. **Do NOT use legacy Ziggy `route()` helper**: Always use typed **Laravel Wayfinder** functions imported from `@/actions` or `@/routes`.
2. **Do NOT create separate pages for project creation or editing**: Use `CapexProjectDrawer.vue` (slide-in drawer), never `/create` or `/{id}/edit` full-page views.
3. **Do NOT create a separate standalone route for `/reports/capex-projects/portfolio`**: Unify portfolio monitoring into Tab 1 of `/admin/capex-projects`.
4. **Do NOT allow `project_code` editing after creation**: Project codes are immutable accounting identifiers. Reject updates to `project_code` on the backend and render it disabled in the UI.
5. **Do NOT allow non-ACTIVE projects in the shopfloor overtime dropdown**: Timesheet dropdowns must strictly filter by `status = 'ACTIVE'`.
6. **Do NOT recalculate historical labor costs in the attribution report**: The attribution report must strictly use the immutable `total_cost_snapshot` stored on `overtime_items`.
7. **Do NOT trigger full-page reloads when updating physical progress**: In-place edits must use `router.patch` with partial reloads (`only: ['project', 'metrics']`) or an optimistic update with error rollback.
8. **Do NOT create nested modals**: Modals or sheets stacked inside other modals or sheets are strictly forbidden.
9. **Do NOT hardcode currency or date formatting**: Monetary amounts must strictly use `Rp` with Indonesian thousand separators; dates and times must strictly use `Asia/Jakarta (WIB)`.
10. **Do NOT display unformatted numbers or raw NaN/division-by-zero errors**: If physical progress is 0%, Milestone Burn Ratio must render as `N/A` or `0.0` with a friendly explanatory tooltip, never `Infinity` or `NaN`.
