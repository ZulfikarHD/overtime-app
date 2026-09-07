# Overtime Policy Check API

## Base URL

`/overtime/policy-check`

## Authentication

Session-based web authentication (`auth` middleware) restricted to authorized plant operational roles:
`role:admin,manager,team_leader`. Scoped by user department/section access privileges.

## Endpoints

### GET /overtime/policy-check

**Description:** Evaluates company overtime policy thresholds for a designated employee given the operational date and pending additional hours. Returns advisory fatigue status and weekly cumulative metrics.

**Query Parameters:**

| Param                   | Type           | Required | Default            | Description                                                            |
| :---------------------- | :------------- | :------- | :----------------- | :--------------------------------------------------------------------- |
| `employee_id`           | integer        | Yes      | -                  | ID of the employee to evaluate                                         |
| `additional_hours`      | numeric        | No       | 0.0                | Additional hours entered in active timesheet row (min: 0)              |
| `date`                  | string (Y-m-d) | No       | Current Date (WIB) | Operational date of the shift                                          |
| `exclude_submission_id` | integer        | No       | null               | Submission ID to exclude from past calculations (used during re-edits) |

**Response 200 OK:**

```json
{
    "level": "warning",
    "message": "Batas mingguan terlampaui (22.0/20.0 jam)",
    "weekly_total": 22.0,
    "weeklyTotal": 22.0,
    "consecutive_weeks": 1,
    "consecutiveWeeks": 1,
    "weekly_limit": 20.0,
    "weeklyLimit": 20.0,
    "warning": {
        "level": "warning",
        "message": "Batas mingguan terlampaui (22.0/20.0 jam)",
        "weekly_total": 22.0,
        "weeklyTotal": 22.0,
        "consecutive_weeks": 1,
        "consecutiveWeeks": 1,
        "weekly_limit": 20.0,
        "weeklyLimit": 20.0
    }
}
```

**Warning Levels:**

- `none`: Cumulative hours and fatigue patterns are safely within company policy limits.
- `warning`: Weekly hours (including additional pending hours) exceed the department/plant `weekly_soft_limit_hours` threshold (default: 20.0 hrs).
- `danger`: The employee has exceeded the weekly threshold for `consecutive_weeks_alert` or more consecutive weeks (default: 3 weeks).

**Error Responses:**

| Code | Description                                                                                             |
| :--- | :------------------------------------------------------------------------------------------------------ |
| 401  | Unauthenticated: User is not logged in                                                                  |
| 403  | Forbidden: User lacks authorization to access the employee's section                                    |
| 422  | Unprocessable Content: Missing or invalid parameters (e.g., non-existent employee ID or negative hours) |
