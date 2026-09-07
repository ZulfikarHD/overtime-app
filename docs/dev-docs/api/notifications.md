# In-App Notifications API

## Base URL

`/notifications`

## Authentication & Headers

- **Session Auth**: Standard Laravel session authentication (`auth`, `verified`).
- **CSRF Token**: Required for `PATCH` requests via `X-XSRF-TOKEN` or standard Laravel CSRF protection.
- **Headers**:
    - `Accept: application/json`
    - `X-Requested-With: XMLHttpRequest`

---

## Endpoints

### 1. GET /notifications

**Description:** Fetch a list of unread in-app notifications for the authenticated user, up to 50 most recent records.

**Response 200 OK:**

```json
{
    "unread_count": 2,
    "notifications": [
        {
            "id": "9b645163-99b3-4f90-8d19-3f0f7c22cb91",
            "type": "App\\Notifications\\SpklOverdueNotification",
            "data": {
                "submission_id": 42,
                "submission_code": "OT-20260906-STAMP-001",
                "section_id": 3,
                "section_name": "Stamping Press 1",
                "operational_date": "2026-09-06",
                "due_date": "2026-09-08",
                "overdue_days": 1,
                "reminder_type": "overdue",
                "title": "SPKL Terlambat: OT-20260906-STAMP-001",
                "message": "Dokumen SPKL untuk pengajuan OT-20260906-STAMP-001 telah melewati batas waktu 1 hari.",
                "spkl_document_id": 19,
                "total_hours": 12.5
            },
            "read_at": null,
            "created_at": "2026-09-08T08:00:00+07:00",
            "created_at_human": "1 hour ago"
        }
    ]
}
```

**Error Responses:**

| Code | Description     |
| ---- | --------------- |
| 401  | Unauthenticated |

---

### 2. PATCH /notifications/{id}/read

**Description:** Mark a specific notification as read. The notification must belong to the authenticated user.

**URL Parameters:**

| Param | Type          | Required | Description     |
| ----- | ------------- | -------- | --------------- |
| id    | string (UUID) | Yes      | Notification ID |

**Response 200 OK:**

```json
{
    "message": "Notification marked as read.",
    "unread_count": 1
}
```

**Error Responses:**

| Code | Description                                             |
| ---- | ------------------------------------------------------- |
| 401  | Unauthenticated                                         |
| 404  | Notification not found or not owned by the current user |

---

### 3. PATCH /notifications/read-all

**Description:** Mark all unread notifications as read for the authenticated user.

**Response 200 OK:**

```json
{
    "message": "All notifications marked as read.",
    "unread_count": 0
}
```

**Error Responses:**

| Code | Description     |
| ---- | --------------- |
| 401  | Unauthenticated |
