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

### 7. Inspect Peer Benchmarking & Workload Distribution (CALC-06)

Below the KPI cards and category charts on the **Summary & Welfare** tab, the **Peer Benchmarking** panel gives instant visibility into whether overtime is distributed fairly across the line section:

#### Section Average vs Individual Hours & CALC-06 Variance

- **Employee Overtime Hours** (`Jam Lembur Karyawan`): Current month approved hours for the selected employee.
- **Section Average** (`Rata-rata Seksi`): Per-capita overtime hours across all active members in the section ($\frac{\text{Total Section Hours}}{\text{Total Employees}}$).
- **Workload Deviation Pill** (`Deviasi Beban Kerja / CALC-06`):
    - **Overloaded** (Amber, `+X.X jam di atas rata-rata seksi`): The worker has logged more overtime than section peers, indicating potential fatigue or scheduling concentration.
    - **Rested / Underloaded** (Emerald, `-X.X jam di bawah rata-rata seksi`): The worker has logged less overtime than the section average.
    - **Balanced** (Slate, `0.0 jam sama dengan rata-rata seksi`): The worker's hours align with the section average.

#### Section Members Overtime Distribution Histogram

- A bar chart visualizes all members of the line section sorted by overtime hours.
- The currently selected employee is prominently highlighted in **ISUZU Red** (`#cc0000`).
- Co-workers are rendered in neutral **Slate** (`#94a3b8`).
- Hovering over any bar reveals exact approved hours, variance from the section mean, and section ranking.

#### Top 5 Highest & Bottom 5 Lowest Overtime Lists

- **Top 5 Highest Hours** (`5 Jam Tertinggi`): Highlights operators carrying the heaviest overtime load this month, aiding supervisors in avoiding repeated weekend dispatch of the same operators.
- **Top 5 Lowest Hours** (`5 Jam Terendah`): Highlights operators with the lowest overtime hours, providing candidates for fair rotation on upcoming weekend shifts.

#### Operator Role Privacy Guarantee (Mode Privasi Operator)

- When an operator (`User` role) views their own dossier, co-worker names and NPKs are strictly anonymized as `Karyawan #1`, `Karyawan #2`, etc.
- This ensures operators understand their workload distribution standing without sparking peer friction, gossip, or perceived favoritism on the factory floor.
- Supervisors (Team Leaders, Managers, Admins) see full employee names to make informed shift dispatch decisions.

---

### 8. Monitor Safety & Fatigue Soft Indicators (Rolling 4-Week Trend & Safety Score)

Located directly below the Peer Benchmarking panel on the **Summary & Welfare** tab, the **Safety & Fatigue Soft Indicators** panel alerts supervisors to accumulated physical fatigue before safety incidents happen:

#### Rolling 4-Week Workload Bar Chart (`Tren Beban Kerja Rolling 4 Minggu`)

- Displays approved overtime hours across 4 weekly cycles (from 3 weeks ago up to the current active week: `Minggu -3`, `Minggu -2`, `Minggu -1`, `Minggu Ini`).
- Compares each week's total against the department's configured safety limit (e.g., `20.0 jam / minggu`).
- Bars are color-coded:
    - **Emerald Green** (`#059669`): Hours within safe limits ($\le 20.0$ hours).
    - **Amber Warning** (`#d97706`): Approaching or exceeding weekly limits ($> 20.0$ hours).
    - **ISUZU Red** (`#cc0000`): Triggered when an active consecutive-week fatigue alert is present.
- A summary row below the chart provides exact weekly hours and remaining buffer or over-limit values.

#### Safety Score & Recovery Gauge (`Skor Keselamatan & Kelelahan`)

- A circular progress arc calculates the employee's 4-week physical recovery rating:
  $$\text{Safety Score} = 100\% - \left(\frac{\text{Overloaded Weeks}}{4} \times 100\%\right)$$
- **Score Ratings**:
    - **100% - 75%** (Emerald): _Tingkat Istirahat Terjaga_ (Rest level maintained).
    - **50% - 74%** (Amber): _Perlu Rotasi Istirahat_ (Rest rotation needed).
    - **< 50%** (ISUZU Red): _Risiko Kelelahan Tinggi_ (High fatigue risk).
- Displays key workload vitals:
    - **Hours This Week** (`Jam Minggu Ini`): Current weekly hours vs weekly threshold.
    - **Consecutive Streak** (`Streak Berturut-turut`): Consecutive weeks exceeding limits.
    - **Overloaded Weeks** (`Overload 4 Minggu`): Number of overloaded weeks in the rolling 4-week window.

#### Industrial Advisory Notice (Zero Operational Roadblocks)

- All fatigue warnings and gauges are **strictly advisory**:
    > _"Peringatan Anjuran (Advisory): Indikator ini bersifat anjuran keselamatan untuk pencegahan kelelahan kerja dan tidak memblokir penugasan lembur darurat."_
- Supervisors can still submit and approve urgent overtime for emergency breakdown repairs without software locks.

#### In-App Alerts in Notification Bell for Team Leaders

- When a subordinate breaches the consecutive-week fatigue threshold (e.g., 3 consecutive weeks over limit) for the first time in a calendar month, an in-app alert is dispatched to the section's Team Leader.
- Clicking the notification bell in the topbar displays the fatigue warning with the employee's name and NPK.
- Clicking **Buka Dossier** navigates directly to the employee's dossier overview tab.
- Subsequent approvals in the same calendar month update the visual badges without sending repetitive duplicate notifications.

---

## Frequently Asked Questions (FAQ)

**Q: Why can't I find an employee from another department when I search?**  
A: For data privacy and organizational hierarchy, Team Leaders can only search and view workers within their assigned section, and Managers can only view workers within their department. Plant Administrators have full plant-wide visibility.

**Q: Why are other employees' names masked as "Karyawan #1" when I log in as an operator?**  
A: To protect worker privacy and prevent interpersonal conflict, general operators only see their own name and position relative to the anonymized section distribution. Supervisors can see all names to balance shift assignments.

**Q: Does a red fatigue indicator block me from approving an urgent overtime submission?**  
A: No. All fatigue indicators are strictly advisory for safety prevention. Emergency and critical line-stoppage work can proceed without system-enforced lockouts.

**Q: How often will Team Leaders receive in-app notifications for an overworked employee?**  
A: To prevent notification fatigue, the system deduplicates alerts to at most once per employee per calendar month. The visual indicators on the dossier continue to update reactively after every approved submission.

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
