# Overtime Submissions & Approvals API

## Base URL

`/api/v1/overtime` (or Inertia actions at `/overtime/*`)

## Authentication & Headers

- **Session Auth**: Standard Laravel session authentication with CSRF token for web/Inertia requests.
- **API Auth**: `Bearer <token>` via Laravel Sanctum (if accessing via external integration).
- **Timezone**: All dates and timestamps are interpreted and serialized in `Asia/Jakarta` (WIB, UTC+7).

---

## Endpoints

### 1. POST /overtime/submissions

**Description:** Submit an atomic batch of overtime records for a section shift. Automatically initializes the non-blocking SPKL document container.

**Authentication:** `auth`, Role: `Team Leader`, `Admin`, `Manager`

**Request Body:**

```json
{
    "operational_date": "2026-09-07",
    "day_type": "HKN",
    "department_id": 1,
    "section_id": 4,
    "submission_notes": "Shift 2 catch-up for assembly line 1",
    "items": [
        {
            "employee_id": 105,
            "hours_production": 2.0,
            "hours_tpm": 0.5,
            "hours_project": 1.0,
            "hours_others": 0.0,
            "capex_project_id": 12,
            "rca_category": "MACHINE_BREAKDOWN",
            "rca_notes": "Conveyor motor replacement",
            "task_description": "Replaced drive sprocket and tested line feed"
        }
    ]
}
```

### 2. GET /overtime/submissions/roster/{sectionId}

**Description:** Fetch active employee roster and current month budget burn indicator for a specific section.

**Authentication:** `auth`, Role: `Team Leader`, `Admin`, `Manager`

**Response (`200 OK`):**

```json
{
    "employees": [
        {
            "id": 105,
            "npk": "EMP-60001",
            "full_name": "Hendro Setiawan",
            "job_position": "Senior Line Operator",
            "hourly_rate": "40000.00"
        }
    ],
    "burn_indicator": {
        "planned_hours": 200.0,
        "actual_hours": 85.0,
        "burn_pct": 42.5,
        "burn_zone": "safe"
    }
}
```

````

**Parameters Validation:**

| Field                      | Type                | Required    | Rules                                                   |
| -------------------------- | ------------------- | ----------- | ------------------------------------------------------- |
| `operational_date`         | string (YYYY-MM-DD) | Yes         | Valid date, not more than 7 days in the past            |
| `department_id`            | integer             | Yes         | Exists in `departments.id`                              |
| `section_id`               | integer             | Yes         | Exists in `sections.id` under `department_id`           |
| `items`                    | array               | Yes         | Min 1 element                                           |
| `items.*.employee_id`      | integer             | Yes         | Exists in `employees.id`, active in section             |
| `items.*.hours_*`          | numeric             | Yes         | Min `0.0`, step `0.5`                                   |
| `items.*.total_hours`      | -                   | -           | Computed: Prod + TPM + Proj + Others must be $\ge 0.5$  |
| `items.*.capex_project_id` | integer             | Conditional | **Required** if `hours_project > 0.0`, must be `ACTIVE` |

**Response 201 Created:**

```json
{
    "status": "success",
    "message": "Pengajuan lembur berhasil disimpan.",
    "data": {
        "submission_id": 842,
        "submission_code": "OT-20260906-ASSY1-001",
        "operational_date": "2026-09-06",
        "day_type": "HKN",
        "status": "SUBMITTED",
        "total_hours_cached": 3.5,
        "spkl_document": {
            "id": 842,
            "status": "PENDING",
            "due_date": "2026-09-08",
            "days_remaining": 2
        }
    }
}
````

**Error Responses:**

- `422 Unprocessable Entity`: Roster mismatch, minimum hour failure, or missing CapEx ID.
- `403 Forbidden`: Unauthorized user submitting for a section outside their jurisdiction.

---

### 2. POST /overtime/submissions/{id}/spkl

**Description:** Upload and attach a scanned physical or digital SPKL document (PDF/JPG/PNG) to an existing submission.

**Authentication:** `auth`, Role: `Team Leader`, `Manager`, `Admin`

**Form Data:**

| Field         | Type          | Required | Description                                                        |
| ------------- | ------------- | -------- | ------------------------------------------------------------------ |
| `spkl_number` | string        | No       | Physical document reference number (e.g., `SPKL/PROD/2026/09/014`) |
| `file`        | binary / file | Yes      | Max 10MB; Allowed mimes: `pdf, jpg, jpeg, png`                     |

**Response 200 OK:**

```json
{
    "status": "success",
    "message": "Dokumen SPKL berhasil dilampirkan.",
    "data": {
        "spkl_id": 842,
        "status": "ATTACHED",
        "spkl_number": "SPKL/PROD/2026/09/014",
        "file_name": "spkl_assy1_20260906.pdf",
        "attached_at": "2026-09-06T17:30:00+07:00"
    }
}
```

---

### 3. POST /overtime/approvals/bulk-review

**Description:** Execute granular item-level approvals or rejections for multiple workers across submissions.

**Authentication:** `auth`, Role: `Manager`, `Admin`

**Request Body:**

```json
{
    "decisions": [
        {
            "item_id": 4120,
            "lock_version": 1,
            "decision": "APPROVED"
        },
        {
            "item_id": 4121,
            "lock_version": 1,
            "decision": "REJECTED",
            "rejection_reason": "Overtime quota exceeded for this line; shift rescheduled."
        }
    ]
}
```

**Response 200 OK:**

```json
{
    "status": "success",
    "message": "Keputusan verifikasi lembur berhasil diproses.",
    "data": {
        "processed_count": 2,
        "approved_count": 1,
        "rejected_count": 1
    }
}
```

**Error Responses:**

- `409 Conflict`: If `lock_version` does not match the current database record (concurrent update detected).
- `422 Unprocessable Entity`: If `decision = "REJECTED"` and `rejection_reason` is empty.
