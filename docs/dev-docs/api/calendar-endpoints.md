# Calendar API Endpoints

## Overview

The Calendar API provides endpoints for checking operational calendar classifications (`HKN` normal workdays vs `HLR` holidays and rest days) and managing calendar data. It is primarily consumed by the Overtime Submission (SPKL) wizard to dynamically determine rate multipliers and regulatory compliance under Indonesian Government Regulation PP 35/2021.

## Base URL & Authentication

- **Base URL**: `/api/calendar`
- **Authentication**: Laravel Session (`auth:sanctum` or standard session cookies). Requests must include valid authentication headers or session tokens.
- **Role Requirement**: Any authenticated employee, team leader, manager, or administrator.

---

### 1. Get Day Classification

Retrieve the calendar classification, public holiday status, and descriptions for a specific date.

#### Endpoint

`GET /api/calendar/{date}`

#### URL Parameters

| Parameter | Type   | Required | Description                                               |
| --------- | ------ | -------- | --------------------------------------------------------- |
| `date`    | string | Yes      | Date string formatted as `YYYY-MM-DD` (e.g. `2026-08-17`) |

#### Successful Response (200 OK) - National Holiday / Rest Day

```json
{
    "date": "2026-08-17",
    "day_type": "HLR",
    "is_holiday": true,
    "holiday_name": "Hari Kemerdekaan RI ke-81",
    "description": "Hari Libur Nasional: Hari Kemerdekaan RI ke-81"
}
```

#### Successful Response (200 OK) - Normal Workday

```json
{
    "date": "2026-08-18",
    "day_type": "HKN",
    "is_holiday": false,
    "holiday_name": null,
    "description": "Hari Kerja Normal"
}
```

#### Error Responses

- **422 Unprocessable Content** (Invalid date format)
    ```json
    {
        "message": "Format tanggal tidak valid. Gunakan format YYYY-MM-DD."
    }
    ```
- **401 Unauthorized** (Unauthenticated user)
    ```json
    {
        "message": "Unauthenticated."
    }
    ```

---

## Admin Management Endpoints

These endpoints reside under `/admin/calendar` and require the `role:admin` middleware.

### 2. Update Day Classification

- **Endpoint**: `PUT /admin/calendar/{date}`
- **Payload**:
    ```json
    {
        "day_type": "HLR",
        "is_holiday": true,
        "holiday_name": "Shutdown Maintenance",
        "description": "Annual plant maintenance"
    }
    ```
- **Response**: Redirect back with flash notification.

### 3. Dry-Run Preview Holiday CSV

- **Endpoint**: `POST /admin/calendar/import-holidays/preview`
- **Payload**: Multipart form with `file` or JSON with `csv_content`.
- **Response (200 OK)**:
    ```json
    {
        "total": 5,
        "valid_count": 4,
        "error_count": 1,
        "rows": [
            {
                "row_number": 2,
                "date": "2026-08-17",
                "holiday_name": "Hari Kemerdekaan RI",
                "description": "Hari Libur Nasional",
                "day_type": "HLR",
                "is_holiday": true,
                "is_valid": true,
                "errors": []
            }
        ]
    }
    ```

### 4. Commit Holiday CSV

- **Endpoint**: `POST /admin/calendar/import-holidays`
- **Payload**:
    ```json
    {
        "rows": [
            {
                "date": "2026-08-17",
                "holiday_name": "Hari Kemerdekaan RI",
                "description": "Hari Libur Nasional",
                "is_valid": true
            }
        ]
    }
    ```
- **Response**: Redirect back with success message.

### 5. Download Template CSV

- **Endpoint**: `GET /admin/calendar/template`
- **Response**: Streamed CSV file `national_holidays_template.csv`.
