# ADR-014: Automated SPKL Document Reminders and In-App Notifications Architecture

**Date:** 2026-09-08  
**Status:** accepted  
**Supersedes:** None

## Context

Under ISUZU factory shift operations, overtime timesheets are submitted immediately at shift-end while physical paper SPKL documents with wet signatures are allowed a 48-hour grace period (BR-05 Non-blocking SPKL). However, without proactive monitoring, Team Leaders risk forgetting to attach signed documents before payroll or audit deadlines.

Key technical and operational requirements:

1. Daily automated evaluation at 08:00 WIB (Asia/Jakarta) checking for pending SPKL records approaching or past due dates.
2. Distinct notification types for overdue submissions (`due_date <= today`) and pre-due warnings (`due_date == tomorrow`).
3. In-app topbar notification delivery respecting user preferences (`spkl_pending_reminder`).
4. User Journey 5 zero-friction resolution: allowing Team Leaders to open the `SpklUploadSheet` directly from the notification card and attach documents without navigating away from their current page.
5. Absolute enforcement of Business Rule BR-05: reminders must remain strictly advisory and never block or lock users from submitting new shift overtime.

## Decision

1. **Standard Database Notifications:** Utilize Laravel's built-in `notifications` table storing polymorphic database notifications with structured JSON payloads (`SpklOverdueNotification` and `SpklPreDueNotification`).
2. **Scheduled Command & Background Job:**
    - Register `DispatchSpklRemindersCommand` (`php artisan overtime:spkl-reminders`) scheduled daily at `08:00` with `Asia/Jakarta` timezone in `routes/console.php`.
    - Execute evaluation in `SendSpklReminderJob` with daily idempotency checks (`whereDate('created_at', today)`) to prevent notification spamming on multiple executions.
3. **Dedicated In-App Notification Endpoints:**
    - `GET /notifications`: Returns unread notifications and badge counter.
    - `PATCH /notifications/{id}/read`: Marks single notification as read and updates count.
    - `PATCH /notifications/read-all`: Marks all user notifications as read.
4. **Topbar Bell Integration & Zero-Friction Drawer Flow:**
    - Place `<NotificationBell />` in `AppSidebarHeader.vue` and `AppHeader.vue` adjacent to the live WIB clock.
    - Embed `SpklUploadSheet` inside the bell component to enable 1-click attachment directly from the notification card, automatically resolving the notification and decrementing the badge upon successful upload.
5. **Shared Inertia Count:** Expose `unread_notifications_count` in `HandleInertiaRequests` for immediate badge rendering across initial page visits without blocking initial paint.

## Consequences

### Positive

- **Audit Compliance:** Eliminates missing physical SPKL files by proactively reminding Team Leaders before payroll cutoffs.
- **Shopfloor Ergonomics (User Journey 5):** Team Leaders can clear pending document requirements in under 3 clicks directly from the topbar popover.
- **Zero-Block Guarantees:** Shifts are never delayed by document reminders; supervisors retain full ability to submit emergency overtime.
- **Idempotent Background Jobs:** Rerunning jobs or commands during the day does not generate duplicate alerts.

### Negative

- **Database Growth:** Database notification records accumulate over time; periodic archival or cleanup commands will be needed as the system scales to multiple factory plants.

### Neutral

- Notifications are tied to the submitting Team Leader account rather than the entire section crew to prevent inbox clutter for operators.
