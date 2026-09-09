# Individual Employee Dossier & Welfare Tracking - User Guide

## What is Individual Employee Dossier?

The **Individual Employee Dossier** module provides plant supervisors (Team Leaders, Managers, and HR Administrators) with an instant, unified hub to look up any subordinate employee by name or Employee ID (NPK). It shows their job role, assigned section, active employment status, and effective overtime rate, serving as the launching point for monitoring overtime hours, fatigue safety indicators, and timesheets.

Line operators logging into the system are automatically directed to their own personal record without exposing other workers' confidential information.

---

## How to Use

### 1. Open the Employee Reports Menu

1. Click **Laporan Karyawan** (or **Employee Reports**) in the left sidebar.
2. The **Employee Reports & Welfare Hub** will open, displaying:
    - Quick search input bar.
    - **Recent Lookups** pill strip (if you have previously viewed employee profiles).
    - A grid of all registered employees in your assigned department or section.

> 💡 **Tip:** Team Leaders will see workers assigned to their specific line section. Department Managers see all workers across their department, while Administrators can view the entire plant.

---

### 2. Search for an Employee

1. In the search box ("Search employee name or NPK..."), type **at least 3 characters** of either the worker's name (e.g., `Budi`) or their NPK (e.g., `4091`).
2. A dropdown list will automatically appear within 300 milliseconds showing matching employees, their NPK, job title, and section.
3. Click any employee from the dropdown list to open their complete dossier immediately.

---

### 3. Use Recent Lookups for Zero-Click Return Access

1. When viewing the main **Employee Reports** hub, the **Recent Lookups** strip displays the last 5 employees you recently inspected.
2. Click any pill to jump straight back into that worker's dossier without typing.
3. To clear your recent search history on this browser, click **Clear History**.

---

### 4. Review the Employee Dossier Header

Once an employee dossier is opened, the top header card shows:

- **Full Name** and **NPK** (in clear monospace font).
- **Active Status Badge**: Green `Active` (`Aktif`) or Slate `Inactive` (`Nonaktif`).
- **Job Position**, **Work Section**, and **Department**.
- **Effective Overtime Hourly Rate**: Hourly rate applied to this employee's overtime calculations.
- **Period Selector**: Month and Year dropdowns (operating on `WIB` timezone) to adjust the reporting period.
- **Switch Employee Search Bar**: Compact search input in the upper-right corner allowing you to switch to another subordinate without leaving the page.
- **Back Button**: Click **Back to Employee Roster** (`Kembali ke Daftar Karyawan`) to return to the roster grid.

---

### 5. Switch Between Dossier Tabs

Beneath the header, use the tab bar to toggle between:

- **Summary & Welfare** (`Ringkasan & Kesejahteraan`): Overview of accumulated monthly hours, burn index, category donut distribution, day-type split, and cost snapshot.
- **Overtime Timesheet** (`Buku Jam Lembur`): Detailed chronological ledger of daily overtime entries and approval histories.

---

### 6. Read the Personal Overtime Dashboard (Summary & Welfare Tab)

The **Summary & Welfare** tab displays 4 responsive KPI cards, a financial cost snapshot, and two visual breakdown charts:

#### Key Performance Indicators (KPI Cards)

1. **Current Month Hours** (`Jam Lembur Bulan Ini`): Total overtime hours that have been approved in the currently selected month and year.
2. **Year-to-Date Hours (YTD)** (`Jam Lembur Tahun Berjalan (YTD)`): Cumulative approved overtime hours since January 1st of the selected year.
3. **Individual Burn Index** (`Indeks Burn Individu`): Percentage comparing the employee's approved monthly hours against the section's per-worker budget allocation.
    - **Safe** (Green, < 85%): Normal operating range.
    - **Caution** (Amber, 85% - 100%): Approaching planned quota allocation.
    - **Exceeded** (ISUZU Red, > 100%): Exceeded individual budget projection.
    - **N/A** (Slate): Displayed when no section budget has been allocated for the selected period.
4. **Section Workload Rank** (`Peringkat Beban Seksi`): Displays the employee's workload ranking relative to other workers in the same line section (e.g., "Rank 4 out of 24 employees").

#### Financial Cost Snapshot

- Displays the **Total Estimated Overtime Cost** in Rupiah (`Rp 1.234.567`) based on rate snapshots captured at the exact moment each submission was approved.

#### Overtime Category Distribution Donut Chart

- Shows how overtime hours are divided between work types:
    - **Production** (`Produksi`): Regular manufacturing output support (Emerald).
    - **TPM** (`Total Productive Maintenance`): Machine cleaning, inspection, and autonomous maintenance (Amber).
    - **CapEx Project** (`CapEx Proyek`): Capitalized line changeovers and improvements (Sky Blue).
    - **Others** (`Lainnya`): Other non-standard shift activities (Slate).

#### Day-Type Distribution (HKN vs HLR)

- Compares overtime worked on **Normal Working Days** (`Hari Kerja Normal / HKN`) versus **Rest / Holiday Days** (`Hari Libur / Istirahat / HLR`).
- Highlights compliance guidance: overtime on weekly rest days (HLR) carries higher fatigue risk and requires careful monitoring of recovery cycles.

---

## Frequently Asked Questions (FAQ)

**Q: Why can't I find an employee from another department when I search?**  
A: For data privacy and organizational hierarchy, Team Leaders can only search and view workers within their assigned section, and Managers can only view workers within their department. Plant Administrators have full plant-wide visibility.

**Q: Are my recent lookups saved on the company server?**  
A: No. Recent lookups are stored securely in your local browser storage (`localStorage`), giving you instant access while preventing unnecessary network calls.

**Q: What happens if an employee has multiple names or nicknames?**  
A: The search engine matches any partial sequence of characters in the worker's official full name or their NPK, regardless of uppercase or lowercase letters.

---

## Troubleshooting

| Issue                                                  | Cause                                                                          | Solution                                                                                          |
| ------------------------------------------------------ | ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------- |
| Search dropdown does not appear after typing           | Fewer than 3 characters were typed                                             | Type at least 3 letters of the employee's name or 3 digits of their NPK.                          |
| "Akses ditolak" (HTTP 403) error when accessing a link | You followed a link to an employee outside your assigned section or department | Verify with your supervisor if you require expanded departmental permissions.                     |
| Recent lookups strip disappeared                       | Browser cache or local storage was cleared                                     | Look up the employee once via the search bar to automatically re-add them to your recent lookups. |
