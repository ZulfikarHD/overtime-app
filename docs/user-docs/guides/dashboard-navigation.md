# Dashboard & Operational Navigation - User Guide

## What is the Dashboard?

The **Dashboard** is the main screen of the OT-CapEx system. Designed specifically for Indonesian automotive manufacturing operations, it provides plant supervisors, department heads, and operators with an immediate view of their active shift, current assignment, role permissions, and system announcements.

---

## How to Use

### Understanding the Redesigned Sidebar

The application sidebar is tailored to the **ISUZU Manufacturing Design System** (`style-guide.html`):

1. **Header Branding**: Displays the official **ISUZU** logo with plant identification: _ISUZU OT-CapEx · Karawang Assembly_.
2. **Modular Industrial Groups**:
    - **Operasional & Lembur**: Dashboard, Input Lembur, Persetujuan Lembur, Laporan Karyawan.
    - **Finansial & Tata Kelola**: Proyek CapEx, Burn Index, Analitik & Keputusan, Budget Planning.
    - **Sistem & Konfigurasi**: Master Data, Administration.
3. **Plant Telemetry Card**: Positioned above the user profile, providing quick shift intelligence:
    - **Fasilitas**: Karawang Assembly
    - **Sistem Shift**: 3 Shift / 24 Jam
    - **Ambang Depnaker**: Maks 14 Jam/Minggu (highlighted in red)
4. **Responsive Collapse & Ergonomics**:
    - **Medium Screens / Tablets (`md` breakpoint, 768px – 1023px)**: Automatically collapses to compact icon mode by default to maximize table and chart workspace.
    - **Desktop (`1024px` and above)**: Opens fully expanded by default.
    - **Manual Control**: You can toggle between expanded and icon modes at any time using the topbar toggle button, dragging the right sidebar rail border, or pressing `Ctrl+B` (`Cmd+B`). Your preference is remembered automatically across visits.

### Checking Your Active Shift and Time

1. Look at the top-right header of the application.
2. The **Live WIB Clock** displays the current plant time in Western Indonesian Time (`Asia/Jakarta`, WIB).
3. The **Active Shift Badge** displays your current factory shift:
    - **Shift 1**: 07:00 – 15:00 WIB
    - **Shift 2**: 15:00 – 23:00 WIB
    - **Shift 3**: 23:00 – 07:00 WIB

### Reviewing Your Plant Assignment

1. On the main **Dashboard** view, examine the **Assignment Overview** card.
2. Verify that your **Department**, **Section**, and **NPK** match your physical work area on the plant floor.
3. Review your **Role Capabilities** card to see which operational tasks (such as Overtime Entry or Timesheet Approval) are authorized for your account.

### Signing Out at Shift Handover

To ensure that the next shift does not record overtime under your account name:

1. Click on your **User Profile Pill** (showing your name and NPK) in the sidebar footer or topbar.
2. Review your assigned department and section in the popover menu.
3. Click the red **Sign Out** button.
4. An inline confirmation prompt will ask: _"Sign out of your account? Please ensure your current shift tasks are saved before leaving."_
5. Click **Yes, Sign Out** to securely end your session.

> 💡 **Tip:** Always sign out at the line workstation before leaving for shift handover to protect your identity and timesheet records.

---

## Frequently Asked Questions (FAQ)

**Q: Why do I only see "Dashboard" in the sidebar menu?**  
A: The system has been initialized for Sprint 1. Additional operational menus (such as Daily Overtime Entry, Approvals, and Master Data) will activate as upcoming release phases roll out across the plant.

**Q: What should I do if my Department or Section assignment is incorrect?**  
A: Contact your HR Administrator or IT Support desk to update your organizational assignment in master data.

**Q: Can I use the system on a smartphone or mobile terminal on the line?**  
A: Yes. On mobile devices, the sidebar collapses into a convenient menu button on the top-left of the screen.

---

## Troubleshooting

| Issue                               | Solution                                                                                                                                   |
| ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Topbar clock shows incorrect time   | Ensure your workstation or device timezone is configured properly; all plant records operate in `WIB` (`Asia/Jakarta`).                    |
| "Access Restricted" message appears | Your current role does not have permission to view the requested section. Click **Return to Dashboard** to return to your authorized area. |
| Accidental sign-out clicked         | Click the **Cancel** button in the sign-out confirmation box to remain logged in.                                                          |
