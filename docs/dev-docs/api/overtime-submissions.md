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

### 3. GET /overtime/submissions

**Description:** Paginated history list (20 items per page) of overtime submissions, scoped by user section/department and filtered by query parameters.

**Authentication:** `auth`, Role: `Team Leader`, `Manager`, `Admin`

**Query Parameters:**

| Parameter     | Type                | Required | Description                                                                                                |
| ------------- | ------------------- | -------- | ---------------------------------------------------------------------------------------------------------- |
| `status`      | string              | No       | Single status (`SUBMITTED`, `PARTIALLY_APPROVED`, `APPROVED`, `REJECTED`, `DRAFT`) or comma-separated list |
| `section_id`  | integer             | No       | Section ID filter (validated against user access)                                                          |
| `spkl_status` | string              | No       | `PENDING`, `ATTACHED`, `VERIFIED`, or `OVERDUE`                                                            |
| `date_from`   | string (YYYY-MM-DD) | No       | Operational date lower bound                                                                               |
| `date_to`     | string (YYYY-MM-DD) | No       | Operational date upper bound                                                                               |
| `page`        | integer             | No       | Pagination page number (default: 1)                                                                        |

---

### 4. GET /overtime/submissions/{id}

**Description:** Fetch eager-loaded submission details for read-only modal inspection.

**Authentication:** `auth`, Role: `Team Leader`, `Manager`, `Admin`

**Response (`200 OK` JSON when requested with `Accept: application/json`):**

```json
{
    "submission": {
        "id": 842,
        "submission_code": "OT-20260907-SEC-001",
        "operational_date": "2026-09-07",
        "day_type": "HKN",
        "status": "SUBMITTED",
        "total_hours_cached": 3.5,
        "submission_notes": "Shift 2 catch-up for assembly line 1",
        "department": { "id": 1, "code": "DEPT_ASSY", "name": "Assembly" },
        "section": {
            "id": 4,
            "code": "SEC_ASSY_01",
            "name": "Assembly Line 1"
        },
        "submitted_by": { "id": 12, "name": "Agus Pratama", "npk": "EMP-1002" },
        "spkl_document": {
            "id": 842,
            "status": "PENDING",
            "due_date": "2026-09-10"
        },
        "items": [
            {
                "id": 1501,
                "employee_id": 105,
                "npk_snapshot": "EMP-60001",
                "hours_production": "2.00",
                "hours_tpm": "0.50",
                "hours_project": "1.00",
                "hours_others": "0.00",
                "total_hours": "3.50",
                "hourly_rate_snapshot": "40000.00",
                "total_cost_snapshot": "140000.00",
                "status": "PENDING"
            }
        ]
    }
}
```

---

### 5. PUT /overtime/submissions/{id}

**Description:** Atomically re-save an existing submission with fresh rate snapshots. Strictly guarded: rejected with HTTP 422 if submission status is `APPROVED` or `PARTIALLY_APPROVED`.

**Authentication:** `auth`, Role: `Team Leader`, `Manager`, `Admin`

**Request Body:** Same schema as `POST /overtime/submissions`.

**Response 302 Found:** Redirects to `overtime.submissions.index` with success flash message.

---

### 6. POST /overtime/submissions/{id}/spkl

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

---

### 4. GET /overtime/submissions

**Description:** Paginated history list of overtime submissions with aggregated hours and immutable snapshot total cost.

**Authentication:** `auth`, Role: `Team Leader`, `Manager`, `Admin`

**Query Parameters:**

- `status` (string, optional): Filter by submission status (`SUBMITTED`, `PARTIALLY_APPROVED`, `APPROVED`, `REJECTED`).
- `date_from` (string, optional): Start date filter (`YYYY-MM-DD`).
- `date_to` (string, optional): End date filter (`YYYY-MM-DD`).
- `page` (int, optional): Page number (default: 1, 20 records per page).

**Inertia / JSON Response Data:**

```json
{
    "submissions": {
        "current_page": 1,
        "data": [
            {
                "id": 12,
                "submission_code": "OT-20260908-SECSTP01-001",
                "operational_date": "2026-09-08",
                "day_type": "HKN",
                "status": "SUBMITTED",
                "total_hours_cached": "8.50",
                "total_cost_cached": "305000.00",
                "section": {
                    "id": 4,
                    "name": "Press Stamping",
                    "code": "STP-01"
                },
                "submitted_by": {
                    "id": 2,
                    "name": "Agus Pratama",
                    "npk": "EMP-1002"
                },
                "spkl_document": {
                    "id": 12,
                    "status": "PENDING",
                    "due_date": "2026-09-10"
                }
            }
        ],
        "total": 1
    }
}
```

- `422 Unprocessable Entity`: If `decision = "REJECTED"` and `rejection_reason` is empty.
