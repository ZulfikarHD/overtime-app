# Analytics, Burn Index & Machine Learning API

## Base URL

`/api/v1/analytics` (or Inertia endpoints at `/dashboard/*`, `/reports/*`, `/analytics/*`)

## Authentication & Headers

- **Session Auth**: Standard Laravel session authentication for web dashboards.
- **Role Enforcement**: Scoped by user authority (Manager reads department, Team Leader reads section).
- **Timezone**: `Asia/Jakarta` (WIB, UTC+7)

---

## Endpoints

### 1. GET /dashboard/burn-index

**Description:** Retrieve pre-calculated monthly Burn Index metrics and operational zones for all sections within the authenticated user's scope.

**Authentication:** `auth`, Role: `Team Leader`, `Manager`, `Admin`

**Query Parameters:**

| Parameter       | Type    | Required | Default                     | Description          |
| --------------- | ------- | -------- | --------------------------- | -------------------- |
| `year`          | integer | No       | Current year (e.g., `2026`) | Fiscal year          |
| `month`         | integer | No       | Current month (`1`–`12`)    | Fiscal month         |
| `department_id` | integer | No       | Auth user department        | Filter by department |

**Response 200 OK:**

```json
{
    "status": "success",
    "data": {
        "fiscal_period": "2026-09",
        "department_summary": {
            "planned_hours": 1200.0,
            "actual_hours": 980.5,
            "burn_index_pct": 81.71,
            "burn_zone": "ZONE_1_EXCELLENT",
            "capex_hours": 240.0,
            "opex_hours": 740.5
        },
        "sections": [
            {
                "section_id": 4,
                "section_name": "Assembly Line 1",
                "planned_budget_hours": 200.0,
                "cumulative_actual_hours": 178.5,
                "remaining_hours": 21.5,
                "burn_index_pct": 89.25,
                "burn_velocity": 44.63,
                "burn_zone": "ZONE_2_GOOD",
                "projected_month_end_hours": 195.0,
                "last_recalculated_at": "2026-09-06T16:45:10+07:00"
            }
        ]
    }
}
```

---

### 2. GET /reports/employees/{npk}

**Description:** Retrieve the comprehensive overtime dossier for a specific employee, including historical breakdown, peer benchmarks, and welfare risk indicators.

**Authentication:** `auth`, Role: `User` (self only), `Team Leader` (section only), `Manager`, `Admin`

**Query Parameters:**

| Parameter | Type    | Required | Default       | Description     |
| --------- | ------- | -------- | ------------- | --------------- |
| `year`    | integer | No       | Current year  | Reporting year  |
| `month`   | integer | No       | Current month | Reporting month |

**Response 200 OK:**

```json
{
    "status": "success",
    "data": {
        "employee": {
            "npk": "NPK-09281",
            "full_name": "Budi Santoso",
            "job_position": "Senior Line Technician",
            "department": "Production",
            "section": "Assembly Line 1"
        },
        "kpis": {
            "current_month_hours": 24.5,
            "year_to_date_hours": 182.0,
            "section_rank": "4 of 28",
            "peer_variance_hours": 4.2,
            "estimated_overtime_cost_idr": 1837500.0
        },
        "welfare_alerts": {
            "is_fatigued_alert": false,
            "consecutive_high_weeks": 1,
            "weekly_soft_limit_hours": 20.0
        },
        "category_distribution": {
            "production": 14.0,
            "tpm": 4.5,
            "project": 6.0,
            "others": 0.0
        },
        "day_type_distribution": {
            "hkn_hours": 16.5,
            "hlr_hours": 8.0
        }
    }
}
```

---

### 3. GET /analytics/ml-radar/{sectionId}/forecast

**Description:** Fetch supervised ML forecast intervals and trajectory curves for a section, including $P_{10}$, $P_{50}$ (expected), and $P_{90}$ confidence ribbons.

**Authentication:** `auth`, Role: `Manager`, `Admin`

**Response 200 OK:**

```json
{
    "status": "success",
    "data": {
        "section_id": 4,
        "model_key": "XGBOOST_BURN_V1",
        "algorithm": "Quantile Gradient Boosting",
        "mape_score": 8.92,
        "cold_start_fallback": false,
        "forecast_horizon": "2026-W37",
        "forecast_metrics": {
            "p10_optimistic_hours": 38.0,
            "p50_expected_hours": 46.5,
            "p90_pessimistic_hours": 58.2
        },
        "top_contributing_factors": [
            {
                "feature": "breakdown_tpm_hours",
                "impact": "+6.4 hrs (Line 1 Motor Overheat)"
            },
            {
                "feature": "holiday_shifts",
                "impact": "+4.0 hrs (HLR Weekend Operation)"
            }
        ]
    }
}
```
