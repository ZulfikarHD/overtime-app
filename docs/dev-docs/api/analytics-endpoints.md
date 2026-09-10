# Analytics & Decision Intelligence API

## Base URL

`/analytics`

## Authentication

Session-based authentication via Laravel web guard (`auth`, `verified`, `role:admin,manager`).

---

## Endpoints

### GET /analytics

**Description:** Render the Analytics & Decision Intelligence Hub shell and active tab view via Inertia.

**Query Parameters:**

| Param           | Type                | Required | Default                             | Description                                                                                 |
| :-------------- | :------------------ | :------- | :---------------------------------- | :------------------------------------------------------------------------------------------ |
| `tab`           | string              | No       | `predictive`                        | Active tab name (`predictive`, `cost`, `correlation`, `scenario`, `insights`, `comparison`) |
| `department_id` | string \| int       | No       | `all` (Admin) / User Dept (Manager) | Filter by department ID or `all`                                                            |
| `start_date`    | string (YYYY-MM-DD) | No       | First day of current month          | Analysis start date                                                                         |
| `end_date`      | string (YYYY-MM-DD) | No       | Last day of current month           | Analysis end date                                                                           |

**Response 200 (Inertia Props):**

```json
{
    "component": "Analytics/Index",
    "props": {
        "currentTab": "predictive",
        "userRole": "admin",
        "userDepartmentId": null,
        "departments": [
            {
                "id": 1,
                "code": "DEPT_ASSY",
                "name": "Assembly Department"
            }
        ],
        "filters": {
            "tab": "predictive",
            "department_id": "all",
            "start_date": "2026-09-01",
            "end_date": "2026-09-30"
        }
    }
}
```

**Error Responses:**

| Code  | Description                                 |
| :---- | :------------------------------------------ |
| `401` | Unauthenticated (redirects to `/login`)     |
| `403` | Forbidden (User is Operator or Team Leader) |

---

### GET /analytics/export

**Description:** Export executive summary PDF or raw tab dataset as a streamed CSV file.

**Query Parameters:**

| Param           | Type                | Required | Default                             | Description                                  |
| :-------------- | :------------------ | :------- | :---------------------------------- | :------------------------------------------- |
| `format`        | string              | No       | `csv`                               | Export file format (`pdf` or `csv`)          |
| `tab`           | string              | No       | `predictive`                        | Target analytical domain for data extraction |
| `department_id` | string \| int       | No       | `all` (Admin) / User Dept (Manager) | Department scope for export                  |
| `start_date`    | string (YYYY-MM-DD) | No       | First day of current month          | Date range start                             |
| `end_date`      | string (YYYY-MM-DD) | No       | Last day of current month           | Date range end                               |

**Response 200 (CSV):**

- **Content-Type:** `text/csv; charset=UTF-8`
- **Content-Disposition:** `attachment; filename="analytics-cost-report-2026-09-10.csv"`

**Response 200 (PDF):**

- **Content-Type:** `application/pdf`
- **Content-Disposition:** `attachment; filename="analytics-executive-summary-2026-09-10.pdf"`

**Error Responses:**

| Code  | Description                                                                  |
| :---- | :--------------------------------------------------------------------------- |
| `401` | Unauthenticated                                                              |
| `403` | Forbidden (Role not permitted or Manager attempting cross-department export) |
