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

### GET /analytics/correlation

**Description:** Fetch empirical bivariate correlation analysis, linear regression parameters, sweet spot zones, and correlation matrix data.

**Query Parameters:**

| Param           | Type                | Required | Default                             | Description                                 |
| :-------------- | :------------------ | :------- | :---------------------------------- | :------------------------------------------ |
| `department_id` | string \| int       | No       | `all` (Admin) / User Dept (Manager) | Filter by department ID or `all`            |
| `start_date`    | string (YYYY-MM-DD) | No       | 5 months prior to current month     | Range start date for historical correlation |
| `end_date`      | string (YYYY-MM-DD) | No       | Last day of current month           | Range end date for historical correlation   |

**Response 200 (JSON):**

```json
{
    "kpi": {
        "sweet_spot_min": 12.0,
        "sweet_spot_max": 18.0,
        "peak_efficiency_hours": 15.2,
        "warning_threshold_hours": 20.0,
        "current_weekly_avg_hours": 14.8,
        "current_zone": "sweet_spot",
        "current_zone_label": "Zona Lembur Wajar (Sweet Spot)"
    },
    "overtime_vs_production": {
        "erp_connected": true,
        "message": null,
        "correlation_r": 0.814,
        "r_squared": 0.663,
        "regression": {
            "slope": 0.185,
            "intercept": 42.1,
            "formula": "y = 0.185x + 42.1"
        },
        "trend_line": [
            { "x": 800.0, "y": 190.1 },
            { "x": 1600.0, "y": 338.1 }
        ],
        "scatter_points": [
            {
                "section_id": 1,
                "section_code": "ASY-TRIM",
                "section_name": "Trim Line",
                "month": "Agu 2026",
                "year_month": "2026-08",
                "x": 1420.0,
                "y": 305.0
            }
        ],
        "sections": [
            { "id": "all", "code": "ALL", "name": "Semua Seksi" },
            { "id": 1, "code": "ASY-TRIM", "name": "Trim Line" }
        ]
    },
    "overtime_vs_quality": {
        "available": false,
        "message": "Menunggu integrasi data kualitas dari ERP",
        "subtext": "Analisis korelasi produksi tetap aktif",
        "correlation_r": null,
        "scatter_points": []
    },
    "optimal_zone_chart": {
        "labels": ["0.0 jam", "4.0 jam", "15.2 jam", "20.0 jam", "32.0 jam"],
        "hours_series": [0.0, 4.0, 15.2, 20.0, 32.0],
        "efficiency_series": [60.0, 70.5, 100.0, 82.0, 45.0],
        "current_weekly_avg": 14.8,
        "zones": {
            "under_utilized": {
                "min": 0.0,
                "max": 12.0,
                "color": "#10b981",
                "label": "Di Bawah Kapasitas (< 12 jam)"
            },
            "sweet_spot": {
                "min": 12.0,
                "max": 18.0,
                "color": "#0284c7",
                "label": "Zona Wajar (12–18 jam)"
            },
            "over_threshold": {
                "min": 20.0,
                "max": 32.0,
                "color": "#cc0000",
                "label": "Ambang Kelelahan (> 20 jam)"
            }
        }
    },
    "correlation_matrix": {
        "variables": [
            { "key": "overtime", "label": "Jam Lembur" },
            { "key": "production", "label": "Volume Produksi" },
            { "key": "quality", "label": "Metrik Kualitas" },
            { "key": "efficiency", "label": "Efisiensi Output" },
            { "key": "cost", "label": "Biaya Lembur" }
        ],
        "matrix": [
            [
                { "r": 1.0, "strength": "strong", "color": "green" },
                { "r": 0.81, "strength": "strong", "color": "green" },
                { "r": null, "strength": "erp_pending", "color": "amber" },
                { "r": -0.42, "strength": "moderate", "color": "blue" },
                { "r": 0.98, "strength": "strong", "color": "green" }
            ]
        ],
        "legend": []
    },
    "scope": {
        "department_id": null,
        "department_name": "Semua Departemen (Lintas Pabrik)",
        "start_date": "2026-04-01",
        "end_date": "2026-09-30"
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

---

### GET /analytics/scenario

**Description:** Retrieve baseline metrics, section historical labor factors, category proportions, department options, and user's saved scenarios for what-if simulation (E09-10).

**Query Parameters:**

| Param           | Type                | Required | Default                             | Description                         |
| :-------------- | :------------------ | :------- | :---------------------------------- | :---------------------------------- |
| `department_id` | string \| int       | No       | `all` (Admin) / User Dept (Manager) | Department scope for simulation     |
| `start_date`    | string (YYYY-MM-DD) | No       | First day of current month          | Start date for baseline calculation |
| `end_date`      | string (YYYY-MM-DD) | No       | Last day of current month           | End date for baseline calculation   |

**Response 200 (JSON):**

```json
{
    "baseline": {
        "department_id": 1,
        "department_name": "Assembly Department",
        "actual_hours": 420.0,
        "actual_cost": 21000000,
        "formatted_actual_cost": "Rp 21.000.000",
        "budget_cost": 25000000,
        "formatted_budget_cost": "Rp 25.000.000",
        "budget_hours": 500.0,
        "burn_index_pct": 84.0,
        "active_headcount": 18,
        "avg_hourly_rate": 50000,
        "formatted_avg_hourly_rate": "Rp 50.000",
        "safety_risk_score": 8.5
    },
    "sections": [
        {
            "id": 1,
            "code": "SEC_TRIM",
            "name": "Trim Line",
            "department_id": 1,
            "department_name": "Assembly",
            "labor_factor": 0.18,
            "hourly_rate": 50000
        }
    ],
    "departments": [
        { "id": 1, "code": "DEPT_ASSY", "name": "Assembly Department" }
    ],
    "policy": {
        "weekly_soft_limit_hours": 20.0,
        "consecutive_weeks_alert": 3
    },
    "correlation_r": 0.78,
    "saved_scenarios": [],
    "initial_calculator_result": null,
    "initial_builder_result": {
        "overtime_change_pct": 0,
        "budget_allocation": 25000000,
        "projected_hours": 420.0,
        "projected_cost": 21000000,
        "formatted_projected_cost": "Rp 21.000.000",
        "cost_impact": 0,
        "formatted_cost_impact": "Rp 0",
        "projected_burn_index": 84.0,
        "burn_zone": "safe",
        "safety_risk_score": 8.5,
        "production_volume_impact_pct": 0.0
    },
    "scope": {
        "department_id": 1,
        "department_name": "Assembly Department",
        "start_date": "2026-09-01",
        "end_date": "2026-09-30",
        "fiscal_year": 2026,
        "fiscal_month": 9
    }
}
```

---

### POST /analytics/scenario/calculate

**Description:** Executes real-time calculation for either Production Planning (units -> hours/headcount/cost/categories) or Scenario Builder (-50% to +50% slider).

**Request Body (Production Planning):**

```json
{
    "target_volume": 1500,
    "period": "monthly",
    "section_id": 1
}
```

**Response 200 (Production Planning):**

```json
{
    "type": "production_planning",
    "target_volume": 1500,
    "period": "monthly",
    "period_label": "Bulanan",
    "section_id": 1,
    "section_name": "Trim Line",
    "labor_factor": 0.18,
    "estimated_hours": 270.0,
    "estimated_cost": 13500000,
    "formatted_cost": "Rp 13.500.000",
    "headcount_needed": 4,
    "efficiency_pct": 100.0,
    "categories": [
        {
            "key": "production",
            "label": "Produksi Reguler",
            "hours": 189.0,
            "cost": 9450000,
            "formatted_cost": "Rp 9.450.000",
            "percentage": 70.0
        },
        {
            "key": "project",
            "label": "Proyek CapEx",
            "hours": 40.5,
            "cost": 2025000,
            "formatted_cost": "Rp 2.025.000",
            "percentage": 15.0
        }
    ]
}
```

**Request Body (Scenario Builder):**

```json
{
    "overtime_change_pct": 20.0,
    "budget_allocation": 30000000,
    "department_id": 1
}
```

**Response 200 (Scenario Builder):**

```json
{
    "type": "scenario_builder",
    "overtime_change_pct": 20.0,
    "budget_allocation": 30000000,
    "projected_hours": 504.0,
    "projected_cost": 25200000,
    "formatted_projected_cost": "Rp 25.200.000",
    "cost_impact": 4200000,
    "formatted_cost_impact": "+Rp 4.200.000",
    "projected_burn_index": 84.0,
    "burn_zone": "safe",
    "safety_risk_score": 10.6,
    "production_volume_impact_pct": 15.6
}
```

---

### POST /analytics/scenario/save

**Description:** Saves a customized scenario preset into the authenticated user's preferences (`users.preferences['saved_scenarios']`, capped at 10 items).

**Request Body:**

```json
{
    "name": "Surge Produksi Q4 2026",
    "overtime_change_pct": 20.0,
    "budget_allocation": 30000000,
    "projected_hours": 504.0,
    "projected_cost": 25200000,
    "projected_burn_index": 84.0,
    "burn_zone": "safe",
    "safety_risk_score": 10.6,
    "production_volume_impact_pct": 15.6
}
```

**Response 200 (JSON):**

```json
{
    "message": "Skenario berhasil disimpan.",
    "saved_scenarios": [
        {
            "id": "scen_66e01234abcd",
            "name": "Surge Produksi Q4 2026",
            "overtime_change_pct": 20.0,
            "budget_allocation": 30000000,
            "projected_hours": 504.0,
            "projected_cost": 25200000,
            "formatted_cost": "Rp 25.200.000",
            "projected_burn_index": 84.0,
            "burn_zone": "safe",
            "safety_risk_score": 10.6,
            "production_volume_impact_pct": 15.6,
            "created_at": "10/09/2026 13:45"
        }
    ]
}
```

---

### DELETE /analytics/scenario/{id}

**Description:** Deletes a saved scenario preset from `users.preferences['saved_scenarios']`.

**Path Parameters:**

| Param | Type   | Required | Description                  |
| :---- | :----- | :------- | :--------------------------- |
| `id`  | string | Yes      | Unique scenario ID to remove |

**Response 200 (JSON):**

```json
{
    "message": "Skenario berhasil dihapus.",
    "saved_scenarios": []
}
```

**Error Responses:**

| Code  | Description                                                                  |
| :---- | :--------------------------------------------------------------------------- |
| `401` | Unauthenticated                                                              |
| `403` | Forbidden (Role not permitted or Manager attempting cross-department export) |
