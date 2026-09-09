# Employee Self-Service Dashboard - User Guide

## What is the Employee Self-Service Dashboard?

The **Employee Self-Service Dashboard** is a simplified personal homepage designed specifically for factory floor operators and line workers (`User` role).

When you log into the Overtime System from your smartphone or workstation, you are immediately taken to your personal dashboard without having to navigate through complex management menus. It gives you instant answers to three key questions:

1. _How many overtime hours have I worked and had approved this month?_
2. _What is my estimated overtime earnings snapshot in Rupiah?_
3. _Were my recent shift overtime submissions approved, pending, or rejected?_

---

## How to Access Your Dashboard

1. Open the application in your browser (desktop or mobile).
2. On the login page, enter your **Email** or **NPK** (e.g., `EMP-4091`) and your password.
3. Click **Log in to System** (**Masuk ke Sistem**).
4. The system automatically redirects you to **Dashboard Pribadi** (`/my/dashboard`).

> 💡 **Tip:** You do not need to memorize a special URL. Clicking the **Dashboard** icon in the sidebar or tapping the **ISUZU** logo will always return you straight to your personal summary.

---

## Dashboard Screen Walkthrough

### 1. Identity & Active Shift Banner

At the top of the screen, you will find:

- **Greeting**: "Halo, [Your Name]!" with your assigned role badge (`Operator`).
- **NPK Badge**: Your unique employee identification number.
- **Position & Assignment**: Your job title, department, and section (e.g., `Senior Welder`, `Assembly Stamping Plant`, `Door Line 1`).
- **Live Plant Clock & Shift Indicator**: Shows the active manufacturing shift (`Shift 1: 07:00–15:00`, `Shift 2: 15:00–23:00`, or `Shift 3: 23:00–07:00 WIB`) with live seconds clock in Western Indonesia Time (`WIB`).

---

### 2. Three Compact Summary KPI Cards

Directly below the greeting header are three primary summary cards:

| Card                                                       | Description                                                                   | What to Look For                                                                                                                                                                                                                                                                                                                        |
| :--------------------------------------------------------- | :---------------------------------------------------------------------------- | :-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Total Lembur Bulan Ini** (_This Month's Total Overtime_) | Shows your approved overtime hours for the current calendar month.            | Large bold hours (e.g. `14.5 jam`) and an estimated gross overtime compensation snapshot in Indonesian Rupiah (`Rp`).                                                                                                                                                                                                                   |
| **Total Tahun Ini (YTD)** (_Total This Year YTD_)          | Cumulative approved overtime hours since January 1st of the current year.     | Useful for monitoring your long-term annual overtime quota.                                                                                                                                                                                                                                                                             |
| **Status Kesejahteraan** (_Welfare Status_)                | Real-time fatigue and work-rest balance indicator evaluated by safety policy. | Shows your safety rating: <br>• 🟢 **Aman** (_Safe_): Normal workload.<br>• 🟡 **Perlu Rotasi** (_Needs Rotation_): Approaching weekly limits.<br>• 🔴 **Risiko Kelelahan** (_Fatigue Risk_): High consecutive weekly overtime.<br>Also displays your current week's hours against the weekly advisory limit (e.g., `12.0 / 14.0 jam`). |

---

### 3. Overtime Category & Workday Distribution

This section provides visual clarity on the nature of your overtime hours during the current month:

- **Visual Progress Bar**: Displays the proportion of your hours allocated across:
    - 🟦 **Produksi** (_Production line support_)
    - 🟨 **TPM** (_Total Productive Maintenance_)
    - 🟪 **CapEx / Proyek** (_Capital expenditure & special engineering projects_)
    - ⬜ **Lainnya** (_Other operational tasks_)
- **Day-Type Badges**:
    - **HKN** (_Hari Kerja Normal_): Overtime conducted on regular working days (before or after standard shifts).
    - **HLR** (_Hari Libur / Istirahat_): Overtime conducted on scheduled rest days or public holidays.

---

### 4. Recent Overtime Submissions (5 Latest Entries)

A real-time list of your 5 most recent shift overtime records:

- **Date & Day**: Operational date and day name (e.g., `05 Sep 2026 (Sabtu)`).
- **Day Type**: Marked with an `HKN` or `HLR` pill.
- **Hours**: Exact duration worked (e.g., `4.0 jam`).
- **Status Badge**:
    - 🟢 **Disetujui** (_Approved_): Verified and approved by your supervisor.
    - 🟡 **Menunggu** (_Pending_): Awaiting review in the approval queue.
    - 🔴 **Ditolak** (_Rejected_): Rejected by supervisory review.
- **Supervisor Rejection Feedback**: If a submission was rejected, a prominent red notification box appears directly beneath the item displaying the supervisor's specific reason (e.g., _"Alasan Penolakan: Kelebihan alokasi jam kerja shift"_), allowing you to understand why it was not approved.

---

### 5. Quick Navigation Buttons

Two large, thumb-friendly touch buttons are located at the bottom of the dashboard for quick navigation:

1. **Lihat Buku Lembur Lengkap (Buka Timesheet)**:
    - Takes you directly to the chronological timesheet table on your employee dossier (`/reports/employees/{npk}?tab=timesheet`).
    - Here you can view your entire history, filter by date ranges, search specific work tasks, and download your monthly timesheet as a CSV file.
2. **Lihat Grafik Kesejahteraan & Beban Kerja**:
    - Takes you to the detailed welfare overview (`/reports/employees/{npk}?tab=overview`).
    - Here you can inspect your 4-week rolling fatigue trend, safety score breakdown, and workload distribution.

---

## Frequently Asked Questions (FAQ)

### Q: Why do I see an alert saying "Akun Belum Terhubung Karyawan"?

**A:** This message indicates that your user login email or username has not yet been linked to your official employee profile (NPK) in the factory master data. Please contact HR or the IT Administrator (Ext: 1204 / WA: +62 857-1583-8733) to link your NPK.

### Q: Can other operators see my overtime hours or earnings?

**A:** No. Strict privacy safeguards ensure that each operator can only view their own overtime hours, status badges, and compensation snapshot. Even in peer benchmarking views, all co-worker data is strictly anonymized.

### Q: When do my overtime earnings update?

**A:** The estimated earnings and approved hours update in real-time as soon as your Team Leader or Department Manager approves your overtime submission in the review queue.
