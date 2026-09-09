# CapEx Project Labor & Portfolio Monitoring — User Guide

## What is CapEx Project Labor Monitoring?

In industrial manufacturing environments, capital project labor (CapEx) involves engineering work, machine installation, tooling jig fabrication, and automation cell assembly. Direct overtime labor spent on these initiatives is capitalized onto fixed assets on the balance sheet under statutory accounting standards (PSAK 16 / IAS 16), rather than expensed as operational costs (OpEx).

The **CapEx Project Labor Management** module enables Department Managers and Administrators to:

- Monitor multi-project portfolio health and labor burn velocity across active capital projects (**Story E07-03**).
- Identify projects where labor burn outpaces physical construction using the **Milestone Burn Ratio** and at-risk alerts (**⚠️**).
- Deep-dive into an individual project's command center (**Story E07-02**) to review weekly burndown curves, contributor rosters, and update physical progress in-place.

---

## 1. Accessing the CapEx Projects Hub

1. Open the main sidebar navigation and click **CapEx Projects**.
2. You will land on the **Portfolio & Master Data** tab on the CapEx Project Hub (`/admin/capex-projects`).
3. Department Managers automatically see projects scoped to their assigned department, while Administrators have access to cross-department portfolios.

---

## 2. Reading the Department Portfolio KPI Summary

At the top of the **Portfolio & Master Data** tab, the portfolio summary header and 4 macro KPI cards provide a 30-second health check:

| KPI Indicator                    | Description & Formula                                                      | Visual Meaning                                                                 |
| :------------------------------- | :------------------------------------------------------------------------- | :----------------------------------------------------------------------------- |
| **Active Projects**              | Total count of projects currently in physical execution (`ACTIVE` status). | Green folder icon with active project count.                                   |
| **Consumed vs. Allocated Hours** | Cumulative approved overtime hours vs. total planned labor ceiling.        | Blue progress display showing overall burn rate percentage.                    |
| **Capitalized Labor Cost**       | Cumulative approved overtime cost based on immutable wage snapshots.       | Currency in Indonesian Rupiah (`Rp 87.150.000`) for statutory accounting.      |
| **High-Risk Projects**           | Count of projects where labor is burning faster than physical completion.  | Amber/Red warning card if any project has Milestone Ratio > 1.2 or Burn > 90%. |

The **Department Summary Banner** directly above the table displays:

> `Dept Total: 8 Active Projects | 1,245.0 / 2,800.0 jam (44.5%) • 2 Proyek Berisiko Tinggi`

---

## 3. Using the Multi-Project Portfolio Table

The consolidated portfolio table lists all projects in high-density industrial format:

### Key Columns

- **Status Risiko (⚠️)**: Highlights projects requiring urgent managerial intervention when Milestone Ratio > 1.2 or Burn Index > 90%.
- **Kode Proyek**: Authoritative project code (`CPX-YYYY-DEPT-NNN`). Clicking opens the project detail cockpit.
- **Nama Proyek & Aset Tetap**: Project name and optional fixed asset tag reference.
- **Departemen**: Sponsoring department (visible to Admins or cross-department views).
- **Status**: Lifecycle pill (`PLANNING`, `ACTIVE`, `ON_HOLD`, `COMPLETED`, `CLOSED`).
- **Alokasi (Jam) & Realisasi (Jam)**: Planned vs. consumed approved hours.
- **Indeks Burn (%)**: Percentage of budget consumed: `(Consumed / Allocated) × 100%`. Color-coded from Emerald (<85%), Sky Blue (85–100%), Amber (>100%), to Red (>115%).
- **Kemajuan Fisik (%)**: Reported physical completion percentage from shopfloor assembly.
- **Rasio Burn Milestone**: Ratio of labor burn to physical progress (`Burn Index / Physical Progress`). Values above 1.2 indicate labor is consuming faster than physical work. If physical progress is 0%, shows `N/A`.
- **Target Selesai & Sisa Hari**: Target completion date and remaining calendar days (highlighted in red if overdue).
- **Aksi**: Quick actions to view Cockpit (`→`), edit project details via drawer, update lifecycle status, or delete (only if 0 hours logged).

### Table Row Highlighting

- **Critical / Deficit (Red border & tint)**: Projects with Burn Index > 100%, or Burn Index > 90% combined with Milestone Ratio > 1.2.
- **Caution / At Risk (Amber border & tint)**: Projects with Burn Index between 85% and 100%, or flagged with risk warning.
- **Controlled / Safe (Neutral surface)**: Projects progressing normally with Burn Index < 85%.

---

## 4. Sorting and Filtering the Portfolio

### Sorting Columns

Click any column header with an arrow icon to sort ascending or descending:

- Project Code, Name, Department, Status
- Allocated Hours, Consumed Hours, Burn Index %
- Physical Progress %, Milestone Burn Ratio
- Target End Date, Days Remaining

### Filtering

- **Status Filter Chips**: Click `Semua Status`, `Planning`, `Active`, `On Hold`, `Completed`, or `Closed` to instantly filter rows.
- **Target Date Range**: Enter `Target Selesai Dari` and `Target Selesai Hingga` to focus on projects scheduled for completion in specific calendar windows.
- **Department Dropdown** (Admin only): Filter to inspect specific department portfolios.
- **Search Bar**: Type any project code, project name, or asset tag to filter results in real time.
- **Reset Button**: Click **Reset** (`RotateCcw`) to restore default view filters.

---

## 5. Inspecting Individual Project Cockpits

Click any Project Code or the **Detail** (`→`) action icon to navigate to `/admin/capex-projects/{id}`:

1. **Burndown Timeline**: Review weekly actual hours vs. linear planned allocation curves.
2. **Team Contributor Roster**: See all technicians and operators contributing approved hours to the project.
3. **In-Place Progress Editor**: Update physical progress percentage with a single click and slider adjustment.
4. **Completion Prompt**: When physical progress reaches 100%, trigger 1-click status transition to `COMPLETED`.

---

## Frequently Asked Questions (FAQ)

**Q: Why does the Milestone Burn Ratio show N/A for some projects?**  
A: If a project has 0% physical progress recorded, the ratio cannot be computed (avoiding division by zero). Once physical progress is updated, the ratio calculates automatically.

**Q: Why can't I edit the Project Code in the edit drawer?**  
A: In accordance with IAS 16 and PSAK 16 statutory audit requirements, project codes are immutable financial identifiers once registered.

**Q: When does a project get flagged with the ⚠️ warning?**  
A: A project is flagged as at-risk if it is active or on hold and its Milestone Burn Ratio exceeds 1.20 (labor burning faster than physical build) or its CapEx Burn Index exceeds 90%.
