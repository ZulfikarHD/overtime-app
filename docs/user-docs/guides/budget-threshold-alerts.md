# Policy Threshold Alerts & Budget Warnings - User Guide

## What is the Budget Threshold Alert System?

In plant manufacturing operations, keeping overtime hours within approved department and section quotas is critical for cost control and labor compliance.

The **Policy Threshold Alert System** automatically monitors your section's cumulative overtime hours against its monthly budget. When a section approaches or exceeds its allocated quota, the system alerts Department Managers and Plant Administrators directly in the top navigation bar and visually flags the section on the **Burn Index Hub**.

---

## Alert Levels & Thresholds

The system evaluates two key policy thresholds defined for your plant or department:

| Level                       | Default Threshold  | Visual Badge                                      | Dashboard Card Indicator                       | Meaning                                                                                                              |
| --------------------------- | ------------------ | ------------------------------------------------- | ---------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| **Peringatan (Warning)**    | **100%** of budget | Amber Outline Badge (`Peringatan (101.5%)`)       | Amber pulsing border (`burn-card--warning`)    | The section has reached or slightly exceeded 100% of its monthly quota. Immediate overtime pacing is required.       |
| **Defisit Kritis (Danger)** | **115%** of budget | Red Destructive Badge (`Defisit Kritis (118.4%)`) | ISUZU Red pulsing border (`burn-card--danger`) | The section has significantly overrun its budget (>115%). Urgent managerial review and corrective action are needed. |

> ℹ️ **Anti-Fatigue Protection**: You will receive **only one notification per threshold level per section each fiscal month**. You will not be bombarded with duplicate alerts each time an overtime request is approved.

---

## How to View and Resolve Alerts

### 1. Spotting Alerts in the Topbar

1. Look at the **Bell icon** in the top navigation bar (next to the live WIB clock).
2. A red badge with the number of unread alerts indicates new notifications.
3. Click the **Bell icon** to open your notification popover.

---

### 2. 1-Click Investigation via Section Drawer (User Journey 4)

From the notification popover, you can instantly inspect the offending section without manual searching:

1. Locate the budget alert card (marked with an amber warning or red danger icon).
2. Click the **Lihat Burn Index** button (or click anywhere on the notification card).
3. The system automatically navigates to `/dashboard/burn-index?tab=sections` and **immediately slides open the Section Burndown Sheet drawer**.
4. Inside the drawer, you can inspect:
    - Cumulative hours vs planned quota.
    - Current Burn Velocity (hours consumed per week).
    - Trajectory forecast (On Pace, Trending Over, or Will Overrun).
    - Daily breakdown of overtime consumption across shifts.

---

### 3. Visual Cues on the Burn Index Dashboard

When navigating the **Burn Index** dashboard (`/dashboard/burn-index`):

- High-burn section cards feature an **active pulsing border**:
    - **Amber pulse**: Section is in Zone 3 Warning (>100%).
    - **ISUZU Red pulse**: Section is in Zone 4 Deficit (>115%).
- Clicking any pulsing card opens its detailed burndown drawer.

---

### 4. Managing Your Alert Preferences

If you wish to customize whether you receive budget threshold alerts:

1. Click your profile avatar in the top right corner and open **Settings → Preferences**.
2. Scroll to the **Notification Channels & Alerts** section.
3. Toggle the **Budget Threshold Alerts** switch on or off.
4. Click **Save Preferences**.

---

## Frequently Asked Questions (FAQ)

### Who receives budget threshold notifications?

Notifications are sent to **Plant Administrators** and **Department Managers** assigned to the department that owns the section. Team Leaders and general operators do not receive budget alerts.

### What happens when a new month begins?

At the start of each new fiscal month (e.g., transitioning from September to October), section alert locks automatically reset. If a section crosses warning thresholds in the new month, you will receive a fresh notification.

### Why did I not receive an alert for a section that exceeded its quota?

Possible reasons:

1. An alert for that threshold level was already sent earlier in the current fiscal month.
2. The section has no configured budget for the month (planned hours = 0).
3. You have turned off `Budget Threshold Alerts` in **Settings → Preferences**.
