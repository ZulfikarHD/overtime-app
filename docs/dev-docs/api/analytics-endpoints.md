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

### GET /analytics/predictive

**Description:** Fetch statistical and machine-learning predictive analytics data for the next month, including KPI cards, section-level forecasts with confidence bounds, 6-month historical/projected trajectory, and 12-month annual seasonality.

**Query Parameters:**

| Param           | Type          | Required | Default                             | Description                      |
| :-------------- | :------------ | :------- | :---------------------------------- | :------------------------------- |
| `department_id` | string \| int | No       | `all` (Admin) / User Dept (Manager) | Filter by department ID or `all` |
| `start_date`    | string        | No       | First day of current month          | Optional analysis start date     |
| `end_date`      | string        | No       | Last day of current month           | Optional analysis end date       |

**Response 200 (JSON):**

```json
{
    "kpi": {
        "predicted_hours": 380.0,
        "ci_lower": 350.0,
        "ci_upper": 410.0,
        "margin": 30.0,
        "formatted_prediction": "380.0 jam ± 30.0 jam",
        "model_name": "LightGBM Regressor",
        "fallback_used": false,
        "accuracy_rate": 91.8,
        "mape": 8.2,
        "accuracy_label": "91.8%",
        "accuracy_description": "Tingkat akurasi model LightGBM Regressor pada data validasi.",
        "seasonal_pattern": "Puncak",
        "seasonal_description": "+14.2% dibanding rata-rata tahunan",
        "seasonal_variance_pct": 14.2,
        "trend_direction": "increasing",
        "trend_label": "↑ Meningkat",
        "trend_growth_rate_pct": 5.4,
        "trend_description": "+5.4% dibanding rata-rata 3 bulan lalu"
    },
    "section_forecast": {
        "sections": [
            {
                "section_id": 1,
                "section_code": "SEC_TRIM",
                "section_name": "Trim Line",
                "predicted_hours": 190.0,
                "ci_lower": 175.0,
                "ci_upper": 205.0,
                "margin": 15.0,
                "fallback_used": false
            }
        ],
        "chart_data": {
            "labels": ["Trim Line"],
            "datasets": [
                {
                    "label": "Prediksi Jam Lembur",
                    "data": [190.0],
                    "ci_lower": [175.0],
                    "ci_upper": [205.0]
                }
            ]
        }
    },
    "trend_projection": {
        "labels": ["Jul", "Agu", "Sep", "Okt (P)", "Nov (P)", "Des (P)"],
        "historical_data": [320.0, 345.0, 360.0, null, null, null],
        "projected_data": [null, null, 360.0, 380.0, 395.0, 410.0],
        "ci_lower_data": [null, null, 360.0, 350.0, 360.0, 370.0],
        "ci_upper_data": [null, null, 360.0, 410.0, 430.0, 450.0]
    },
    "seasonal_pattern": {
        "labels": [
            "Jan",
            "Feb",
            "Mar",
            "Apr",
            "Mei",
            "Jun",
            "Jul",
            "Agu",
            "Sep",
            "Okt",
            "Nov",
            "Des"
        ],
        "monthly_averages": [
            280.0, 290.0, 310.0, 240.0, 250.0, 320.0, 330.0, 340.0, 350.0,
            400.0, 420.0, 450.0
        ],
        "peak_season": "Oktober - Desember (Q4)",
        "low_season": "April - Mei",
        "cycle_duration": "12 Bulan - Tahunan"
    }
}
```

---

### GET /analytics/cost

**Description:** Fetch financial cost analysis metrics, including 4 KPI cards (Total Cost, Remaining Budget, Avg Cost/Employee, CapEx Ratio), department spend rankings, 6-month stacked OpEx vs CapEx trends, and budget vs actual comparisons.

**Query Parameters:**

| Param           | Type          | Required | Default                             | Description                      |
| :-------------- | :------------ | :------- | :---------------------------------- | :------------------------------- |
| `department_id` | string \| int | No       | `all` (Admin) / User Dept (Manager) | Filter by department ID or `all` |
| `start_date`    | string        | No       | First day of current month          | Optional analysis start date     |
| `end_date`      | string        | No       | Last day of current month           | Optional analysis end date       |

**Response 200 (JSON):**

```json
{
    "kpi": {
        "total_cost": 125500000.0,
        "formatted_total_cost": "Rp 125,5 Jt",
        "remaining_budget": 24500000.0,
        "formatted_remaining_budget": "Rp 24,5 Jt",
        "budget_consumption_pct": 83.7,
        "budget_burn_zone": "safe",
        "is_over_budget": false,
        "planned_budget": 150000000.0,
        "active_employee_count": 50,
        "avg_cost_per_employee": 2510000.0,
        "formatted_avg_cost_per_employee": "Rp 2,5 Jt",
        "capex_cost": 37650000.0,
        "formatted_capex_cost": "Rp 37,7 Jt",
        "opex_cost": 87850000.0,
        "formatted_opex_cost": "Rp 87,9 Jt",
        "capex_ratio_pct": 30.0
    },
    "department_costs": [
        {
            "department_id": 1,
            "department_code": "DEPT_ASSY",
            "department_name": "Assembly Department",
            "total_hours": 1200.0,
            "total_cost": 60000000.0,
            "formatted_total_cost": "Rp 60.000.000",
            "planned_cost": 70000000.0,
            "formatted_planned_cost": "Rp 70.000.000",
            "budget_consumption_pct": 85.7,
            "is_over_budget": false,
            "burn_zone": "on_track",
            "avg_rate_per_hour": 50000.0,
            "formatted_avg_rate": "Rp 50.000",
            "capex_cost": 18000000.0,
            "opex_cost": 42000000.0,
            "trend": "up",
            "trend_variance_pct": 5.2
        }
    ],
    "monthly_trend_6m": {
        "labels": [
            "Apr 2026",
            "Mei 2026",
            "Jun 2026",
            "Jul 2026",
            "Agu 2026",
            "Sep 2026"
        ],
        "opex_costs": [
            70000000, 75000000, 80000000, 82000000, 85000000, 87850000
        ],
        "capex_costs": [
            20000000, 22000000, 25000000, 30000000, 32000000, 37650000
        ]
    },
    "budget_vs_actual": [
        {
            "department_name": "Assembly Department",
            "planned_cost": 70000000.0,
            "actual_cost": 60000000.0,
            "is_over_budget": false
        }
    ],
    "scope": {
        "department_id": null,
        "start_date": "2026-09-01",
        "end_date": "2026-09-30",
        "fiscal_year": 2026,
        "fiscal_month": 6
    }
}
```

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
