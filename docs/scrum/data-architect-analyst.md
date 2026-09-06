# Architectural Blueprint: Overtime & CapEx Labor Management System (OT-CapEx)
**Lead Database & Systems Architect:** Senior Data Architect Agent  
**Client / Lead Developer:** Zulfikar Hidayatullah (+62 857-1583-8733)  
**Target Stack:** Laravel 11/12 (PHP 8.3+) + Vue 3 Inertia.js (Wayfinder routing) + PostgreSQL 16 / MySQL 8.0 (InnoDB)  
**Standards:** Timezone: `Asia/Jakarta` (WIB) | Currency: Indonesian Rupiah (`Rp`, `IDR`) | Package Manager: `pnpm`

---

## 1. Assumptions & Business Context

### 1.1 Workload & Scale Profile
From auditing `@migration-plan/ba-analyst-reqs-draft.md` and the shop-floor mockups, here is the technical reality of this manufacturing system:
* **Workload Characteristics (Balanced with Burst Writes)**:
  * **Burst Writes at Shift Handover**: 10–50 Team Leaders simultaneously submit batch timesheets (10–30 workers per section) at shift ends (07:00, 15:00, 23:00 WIB).
  * **Concurrent Review Queues**: Department Managers and Section Heads audit lines, execute partial approvals, and reject line items in bulk during morning standups (07:30–09:00 WIB).
  * **Continuous Analytical Reads**: Management dashboards, weekly 5-week burndown trackers, individual employee dossiers, and CapEx capitalization reports.
  * **Background ML & Asynchronous Jobs**: Batch feature extraction, anomaly inference during submission, and scheduled SPKL grace-period escalations.
* **Data Volume Projections**:
  * **Plant Scale**: ~1,500 active employees across 35 sections and 6 departments (Production, Maintenance, Engineering, Quality, Logistics, Tooling).
  * **Daily Volume**: ~800 to 1,500 line items/day (average 25 shifts per month).
  * **Year 1**: ~300,000–450,000 records.
  * **Year 3–5**: ~1.5M–2.5M records.
  * *Architectural takeaway*: This is **not** distributed "Big Data" requiring distributed sharding or Cassandra. It is a **high-integrity relational OLTP & financial governance system**. Vertical scaling on a single primary database (e.g., 4–8 vCPUs, 16–32 GB RAM) with proper B-Tree indexing and selective denormalization will maintain sub-10ms query response times for the next decade.

### 1.2 Consistency & Concurrency Requirements
* **Strict ACID Transactions are Mandatory**:
  * An overtime submission and its child line items must be written atomically.
  * Item-level partial approvals and budget quota adjustments cannot tolerate lost updates or race conditions when two managers review the same section simultaneously.
  * CapEx project labor attribution directly impacts financial capitalization of physical assets (audited by external tax and financial auditors). Orphaned records or floating-point rounding errors are intolerable.
* **Eventual Consistency is Acceptable For**:
  * Pre-aggregated dashboard statistics (weekly burndown snapshots).
  * Supervised ML feature recalculation and prediction cache.
  * SPKL grace-period reminder dispatching via Redis queues.

### 1.3 Open Questions & Architectural Safeguards
1. **Master Organizational Structure**: Production screens show mixed legacy naming (`Assembly Line 1` vs `TCF FS`).  
   * *Architectural Solution*: Implement an explicit `departments` $\rightarrow$ `sections` hierarchical schema with immutable primary keys (`UUID` or `BIGINT`) and natural shop-floor codes (`code`), decoupling UI labels from database foreign keys.
2. **Overtime Costing Baseline**:  
   * *Architectural Solution*: Store standard labor hourly rates at both the Department level (`department_labor_rates`) and Employee level (`employees.hourly_rate`). When an overtime line is approved, the system **takes an immutable snapshot** of the effective hourly rate (`hourly_rate_snapshot`) to prevent historical cost shifts when salaries or standard costing tables are updated in future fiscal years.
3. **SPKL Grace Period**:  
   * *Architectural Solution*: Treat the SPKL attachment workflow as an asynchronous document state machine (`pending` $\rightarrow$ `attached` $\rightarrow$ `verified`) with a configurable grace period parameter (`policy_thresholds.spkl_grace_period_days`, default: 2 business days).

---

## 2. Proposed System & Database Architecture

### 2.1 High-Level Architecture Topology

```
+---------------------------------------------------------------------------------+
|                                 CLIENT TIER                                     |
|               Vue 3 + Inertia.js (pnpm, Wayfinder Route Generation)             |
|        [Daily Timesheet Form]   [Approval Matrix]   [Burn Index / ML Radar]     |
+---------------------------------------------------------------------------------+
                                       |
                               (Inertia HTTP/JSON)
                                       v
+---------------------------------------------------------------------------------+
|                              APPLICATION TIER                                   |
|                      Laravel 11+ (PHP 8.3+, Service Pattern)                    |
|                                                                                 |
|  [Controllers]               [Form Requests]              [Inertia Responses]   |
|         |                            |                             ^            |
|         v                            v                             |            |
|  [Domain Services & Actions] <------------------------------------+             |
|    - SubmitOvertimeAction        - ApproveOvertimeAction                        |
|    - BurnIndexCalculatorService  - CapExAccountingService                       |
|    - AnomalyDetectionDispatcher  - SpklReminderScheduler                        |
+---------------------------------------------------------------------------------+
          |                                                    |
     (Sync OLTP)                                          (Async Jobs)
          v                                                    v
+------------------------------------+             +------------------------------+
|          DATABASE TIER             |             |        REDIS QUEUE           |
|  PostgreSQL 16+ (or MySQL 8.0+)    |             |  - Anomaly Scoring Jobs      |
|  - Strict Foreign Keys (RESTRICT)  |             |  - Burn Snapshot Rollups     |
|  - Generated Stored Columns        |             |  - SPKL Email/WA Reminders   |
|  - Partial Indexes                 |             |  - ML Inference Dispatcher   |
|  - Immutable Audit Event Ledger    |             +------------------------------+
+------------------------------------+                             |
                                                                   v
                                                   +------------------------------+
                                                   |     SUPERVISED ML WORKER     |
                                                   |  Python / FastAPI or Internal|
                                                   |  Scikit-Learn/LightGBM Model |
                                                   |  (Demand, Burn, Anomaly)     |
                                                   +------------------------------+
```

---

### 2.2 Entity Relationship Model (ERD)

```
 [departments] 1 -----< * [sections] 1 -----< * [employees]
       |                      |                       |
       |                      |                       |
       +----------+           |                       |
                  |           |                       |
                  v           v                       |
           [overtime_submissions] 1                   |
                  |                                   |
                  +--- 1:1 --- [spkl_documents]       |
                  |                                   |
                  +--- 1:* --- [overtime_items] >-----+
                                    |
            +-----------------------+-----------------------+
            |                       |                       |
            v                       v                       v
     [capex_projects]     [overtime_item_audits]   [ml_anomaly_logs]

 [operational_calendars]  --> (References calendar_date in submissions)
 [overtime_budgets]       --> (Tracks monthly quotas per department/section)
 [monthly_burn_snapshots] --> (Denormalized pre-calculated burn index)
 [ml_predictions]         --> (Stores ML forecast trajectories & intervals)
```

---

### 2.3 Production-Grade Database DDL (PostgreSQL Dialect)

*Note: Fully compatible with MySQL 8.0 InnoDB with minor syntax adjustments (`JSONB` $\rightarrow$ `JSON`, `TIMESTAMPTZ` $\rightarrow$ `DATETIME/TIMESTAMP`).*

```sql
-- ============================================================================
-- 1. ORGANIZATIONAL & EMPLOYEE DOMAIN
-- ============================================================================

CREATE TABLE departments (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,             -- e.g., 'PROD', 'MAINT', 'ENG'
    name VARCHAR(100) NOT NULL,
    cost_center_code VARCHAR(50) NOT NULL,
    default_hourly_rate NUMERIC(15, 2) NOT NULL DEFAULT 0.00, -- Standard costing baseline (Rp)
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sections (
    id BIGSERIAL PRIMARY KEY,
    department_id BIGINT NOT NULL REFERENCES departments(id) ON DELETE RESTRICT,
    code VARCHAR(50) NOT NULL UNIQUE,             -- e.g., 'ASSY_LINE_1', 'BODY_KS'
    name VARCHAR(100) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE employees (
    id BIGSERIAL PRIMARY KEY,
    npk VARCHAR(20) NOT NULL UNIQUE,              -- Permanent unique corporate identifier
    department_id BIGINT NOT NULL REFERENCES departments(id) ON DELETE RESTRICT,
    section_id BIGINT NOT NULL REFERENCES sections(id) ON DELETE RESTRICT,
    full_name VARCHAR(150) NOT NULL,
    job_position VARCHAR(100) NOT NULL,
    hourly_rate NUMERIC(15, 2) NOT NULL DEFAULT 0.00, -- Individual base rate in Rupiah
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_employees_dept_sec ON employees(department_id, section_id) WHERE is_active = TRUE;

-- ============================================================================
-- 2. OPERATIONAL CALENDAR & POLICY GOVERNANCE
-- ============================================================================

CREATE TYPE day_type_enum AS ENUM ('HKN', 'HLR'); -- HKN: Normal Workday, HLR: Holiday/Rest Day

CREATE TABLE operational_calendars (
    calendar_date DATE PRIMARY KEY,               -- e.g., '2026-09-06'
    day_type day_type_enum NOT NULL DEFAULT 'HKN',
    is_holiday BOOLEAN NOT NULL DEFAULT FALSE,
    holiday_name VARCHAR(100) NULL,
    description TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE policy_thresholds (
    id BIGSERIAL PRIMARY KEY,
    department_id BIGINT NULL REFERENCES departments(id) ON DELETE CASCADE, -- NULL = Plant default
    weekly_soft_limit_hours NUMERIC(4, 1) NOT NULL DEFAULT 20.0,
    consecutive_weeks_alert INTEGER NOT NULL DEFAULT 3,
    spkl_grace_period_days INTEGER NOT NULL DEFAULT 2,
    burn_warning_pct NUMERIC(5, 2) NOT NULL DEFAULT 100.00,
    burn_danger_pct NUMERIC(5, 2) NOT NULL DEFAULT 115.00,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- 3. CAPEX PROJECTS & BUDGETING DOMAIN
-- ============================================================================

CREATE TYPE capex_status_enum AS ENUM ('PLANNING', 'ACTIVE', 'ON_HOLD', 'COMPLETED', 'CLOSED');

CREATE TABLE capex_projects (
    id BIGSERIAL PRIMARY KEY,
    project_code VARCHAR(50) NOT NULL UNIQUE,     -- e.g., 'CPX-2026-ASSY-001'
    asset_code VARCHAR(50) NULL,                  -- Fixed Asset Tag reference
    name VARCHAR(200) NOT NULL,
    department_id BIGINT NOT NULL REFERENCES departments(id) ON DELETE RESTRICT,
    allocated_labor_hours NUMERIC(8, 2) NOT NULL DEFAULT 0.00,
    allocated_labor_budget_idr NUMERIC(15, 2) NOT NULL DEFAULT 0.00,
    physical_progress_pct NUMERIC(5, 2) NOT NULL DEFAULT 0.00,
    status capex_status_enum NOT NULL DEFAULT 'ACTIVE',
    start_date DATE NOT NULL,
    target_end_date DATE NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE overtime_budgets (
    id BIGSERIAL PRIMARY KEY,
    department_id BIGINT NOT NULL REFERENCES departments(id) ON DELETE RESTRICT,
    section_id BIGINT NULL REFERENCES sections(id) ON DELETE RESTRICT, -- Section-level or Dept-level
    fiscal_year SMALLINT NOT NULL,                -- e.g., 2026
    fiscal_month SMALLINT NOT NULL CHECK (fiscal_month BETWEEN 1 AND 12),
    planned_hours NUMERIC(8, 2) NOT NULL,
    planned_cost_idr NUMERIC(15, 2) NOT NULL DEFAULT 0.00,
    week1_planned_hours NUMERIC(8, 2) NOT NULL DEFAULT 0.00,
    week2_planned_hours NUMERIC(8, 2) NOT NULL DEFAULT 0.00,
    week3_planned_hours NUMERIC(8, 2) NOT NULL DEFAULT 0.00,
    week4_planned_hours NUMERIC(8, 2) NOT NULL DEFAULT 0.00,
    week5_planned_hours NUMERIC(8, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_budget_period UNIQUE (department_id, section_id, fiscal_year, fiscal_month)
);

-- ============================================================================
-- 4. OVERTIME TRANSACTION ENGINE (OLTP CORE)
-- ============================================================================

CREATE TYPE submission_status_enum AS ENUM (
    'DRAFT', 
    'SUBMITTED', 
    'PARTIALLY_APPROVED', 
    'APPROVED', 
    'REJECTED'
);

CREATE TABLE overtime_submissions (
    id BIGSERIAL PRIMARY KEY,
    submission_code VARCHAR(50) NOT NULL UNIQUE,  -- e.g., 'OT-20260906-ASSY1-001'
    submission_date DATE NOT NULL,
    operational_date DATE NOT NULL REFERENCES operational_calendars(calendar_date) ON DELETE RESTRICT,
    day_type day_type_enum NOT NULL,              -- Snapshot of day classification
    department_id BIGINT NOT NULL REFERENCES departments(id) ON DELETE RESTRICT,
    section_id BIGINT NOT NULL REFERENCES sections(id) ON DELETE RESTRICT,
    submitted_by_user_id BIGINT NOT NULL,         -- Links to users.id
    status submission_status_enum NOT NULL DEFAULT 'SUBMITTED',
    total_hours_cached NUMERIC(8, 2) NOT NULL DEFAULT 0.00, -- Pragmatic denormalization for fast index
    submission_notes TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_ot_sub_date_sec ON overtime_submissions(operational_date, section_id, status);
CREATE INDEX idx_ot_sub_status ON overtime_submissions(status, department_id);

CREATE TYPE spkl_status_enum AS ENUM ('PENDING', 'ATTACHED', 'VERIFIED');

CREATE TABLE spkl_documents (
    id BIGSERIAL PRIMARY KEY,
    overtime_submission_id BIGINT NOT NULL UNIQUE REFERENCES overtime_submissions(id) ON DELETE CASCADE,
    spkl_number VARCHAR(100) NULL,                -- Formal paper / electronic SPKL reference
    file_path VARCHAR(255) NULL,                  -- Secure storage path (S3 / Local private disk)
    file_name VARCHAR(255) NULL,
    file_size_bytes BIGINT NULL,
    mime_type VARCHAR(100) NULL,
    status spkl_status_enum NOT NULL DEFAULT 'PENDING',
    due_date DATE NOT NULL,                       -- operational_date + grace_period_days
    attached_at TIMESTAMPTZ NULL,
    attached_by_user_id BIGINT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_spkl_pending_due ON spkl_documents(status, due_date) WHERE status = 'PENDING';

CREATE TYPE item_status_enum AS ENUM ('PENDING', 'APPROVED', 'REJECTED');

CREATE TYPE rca_category_enum AS ENUM (
    'MACHINE_BREAKDOWN', 
    'SUPPLIER_DELAY', 
    'QUALITY_REWORK', 
    'CUSTOMER_RUSH', 
    'TRIAL_MODEL', 
    'FACILITY_MAINTENANCE', 
    'OTHER'
);

CREATE TABLE overtime_items (
    id BIGSERIAL PRIMARY KEY,
    overtime_submission_id BIGINT NOT NULL REFERENCES overtime_submissions(id) ON DELETE CASCADE,
    employee_id BIGINT NOT NULL REFERENCES employees(id) ON DELETE RESTRICT,
    npk_snapshot VARCHAR(20) NOT NULL,            -- Permanent snapshot of worker identifier
    capex_project_id BIGINT NULL REFERENCES capex_projects(id) ON DELETE RESTRICT,
    
    -- Category Decimal Allocations (Enforced >= 0.0)
    hours_production NUMERIC(4, 2) NOT NULL DEFAULT 0.00 CHECK (hours_production >= 0.00),
    hours_tpm NUMERIC(4, 2) NOT NULL DEFAULT 0.00 CHECK (hours_tpm >= 0.00),
    hours_project NUMERIC(4, 2) NOT NULL DEFAULT 0.00 CHECK (hours_project >= 0.00),
    hours_others NUMERIC(4, 2) NOT NULL DEFAULT 0.00 CHECK (hours_others >= 0.00),
    
    -- Theoretical Foundation: Stored Generated Column guarantees BR-01 & BR-04 integrity
    total_hours NUMERIC(4, 2) GENERATED ALWAYS AS (
        hours_production + hours_tpm + hours_project + hours_others
    ) STORED,
    
    -- Financial Standard Costing Snapshots (Immutable upon approval)
    hourly_rate_snapshot NUMERIC(15, 2) NOT NULL DEFAULT 0.00,
    total_cost_snapshot NUMERIC(15, 2) NOT NULL DEFAULT 0.00,
    
    -- Root Cause Analysis (Optional per BR-07)
    rca_category rca_category_enum NULL,
    rca_notes TEXT NULL,
    task_description TEXT NULL,
    
    -- Item-Level Independent Approval Lifecycle (BR-10)
    status item_status_enum NOT NULL DEFAULT 'PENDING',
    reviewed_by_user_id BIGINT NULL,
    reviewed_at TIMESTAMPTZ NULL,
    rejection_reason TEXT NULL,                   -- Mandatory if status = 'REJECTED'
    
    -- Concurrency & Audit Control
    lock_version INTEGER NOT NULL DEFAULT 1,      -- Optimistic locking to eliminate race conditions
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Business Constraint: Must have at least 0.5 total hours
    CONSTRAINT chk_min_hours CHECK (
        (hours_production + hours_tpm + hours_project + hours_others) >= 0.50
    ),
    -- Business Constraint: If Project hours > 0, capex_project_id MUST NOT be NULL
    CONSTRAINT chk_capex_attribution CHECK (
        (hours_project = 0.00) OR (hours_project > 0.00 AND capex_project_id IS NOT NULL)
    )
);

CREATE INDEX idx_ot_items_sub_emp ON overtime_items(overtime_submission_id, employee_id);
CREATE INDEX idx_ot_items_emp_date ON overtime_items(employee_id, created_at);
CREATE INDEX idx_ot_items_capex ON overtime_items(capex_project_id) WHERE capex_project_id IS NOT NULL;
CREATE INDEX idx_ot_items_status ON overtime_items(status);

-- ============================================================================
-- 5. IMMUTABLE AUDIT TRAIL (SCAR TISSUE DEFENSE)
-- ============================================================================

CREATE TABLE overtime_item_audits (
    id BIGSERIAL PRIMARY KEY,
    overtime_item_id BIGINT NOT NULL REFERENCES overtime_items(id) ON DELETE CASCADE,
    action VARCHAR(30) NOT NULL,                  -- 'SUBMITTED', 'APPROVED', 'REJECTED', 'UPDATED'
    actor_user_id BIGINT NOT NULL,
    previous_state JSONB NULL,
    new_state JSONB NOT NULL,
    notes TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_item_audits_item ON overtime_item_audits(overtime_item_id);

-- ============================================================================
-- 6. PRAGMATIC ANALYTICAL SNAPSHOTS (BURNDOWN & DASHBOARDS)
-- ============================================================================

CREATE TYPE burn_zone_enum AS ENUM ('ZONE_1_EXCELLENT', 'ZONE_2_GOOD', 'ZONE_3_WARNING', 'ZONE_4_POOR');

CREATE TABLE monthly_burn_snapshots (
    id BIGSERIAL PRIMARY KEY,
    department_id BIGINT NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
    section_id BIGINT NOT NULL REFERENCES sections(id) ON DELETE CASCADE,
    fiscal_year SMALLINT NOT NULL,
    fiscal_month SMALLINT NOT NULL,
    planned_budget_hours NUMERIC(8, 2) NOT NULL DEFAULT 0.00,
    cumulative_actual_hours NUMERIC(8, 2) NOT NULL DEFAULT 0.00,
    cumulative_opex_hours NUMERIC(8, 2) NOT NULL DEFAULT 0.00,
    cumulative_capex_hours NUMERIC(8, 2) NOT NULL DEFAULT 0.00,
    burn_index_pct NUMERIC(6, 2) NOT NULL DEFAULT 0.00,
    burn_velocity NUMERIC(6, 2) NOT NULL DEFAULT 0.00,
    burn_zone burn_zone_enum NOT NULL DEFAULT 'ZONE_1_EXCELLENT',
    last_recalculated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_monthly_section_burn UNIQUE (section_id, fiscal_year, fiscal_month)
);

CREATE INDEX idx_burn_snapshot_lookup ON monthly_burn_snapshots(department_id, fiscal_year, fiscal_month);

-- ============================================================================
-- 7. SUPERVISED MACHINE LEARNING STORE
-- ============================================================================

CREATE TYPE ml_model_type_enum AS ENUM (
    'DEMAND_FORECAST', 
    'BURN_TRAJECTORY', 
    'CAPEX_FORECAST', 
    'ANOMALY_DETECTION'
);

CREATE TABLE ml_models (
    id BIGSERIAL PRIMARY KEY,
    model_key VARCHAR(50) NOT NULL UNIQUE,        -- e.g., 'XGBOOST_BURN_V1'
    model_type ml_model_type_enum NOT NULL,
    version VARCHAR(20) NOT NULL,
    algorithm_name VARCHAR(100) NOT NULL,         -- 'Quantile Gradient Boosting', 'LightGBM'
    hyperparameters JSONB NOT NULL DEFAULT '{}',
    metrics JSONB NOT NULL DEFAULT '{}',          -- Stores {"mape": 9.4, "recall": 88.2}
    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    trained_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ml_predictions (
    id BIGSERIAL PRIMARY KEY,
    ml_model_id BIGINT NOT NULL REFERENCES ml_models(id) ON DELETE RESTRICT,
    target_type VARCHAR(50) NOT NULL,             -- 'SECTION', 'DEPARTMENT', 'CAPEX_PROJECT'
    target_id BIGINT NOT NULL,
    prediction_horizon VARCHAR(30) NOT NULL,      -- 'MONTH_END', 'WEEK_NEXT'
    predicted_value NUMERIC(10, 2) NOT NULL,
    confidence_interval_lower NUMERIC(10, 2) NULL,
    confidence_interval_upper NUMERIC(10, 2) NULL,
    risk_score NUMERIC(5, 4) NULL,                -- e.g. 0.8540 (High Deficit Risk)
    risk_level VARCHAR(20) NULL,                  -- 'LOW', 'MODERATE', 'HIGH'
    feature_impact_json JSONB NULL,               -- Top explainability drivers
    fallback_used BOOLEAN NOT NULL DEFAULT FALSE, -- TRUE if defaulted to historical moving avg
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_ml_pred_lookup ON ml_predictions(target_type, target_id, created_at DESC);

CREATE TABLE ml_anomaly_logs (
    id BIGSERIAL PRIMARY KEY,
    overtime_item_id BIGINT NOT NULL REFERENCES overtime_items(id) ON DELETE CASCADE,
    ml_model_id BIGINT NOT NULL REFERENCES ml_models(id) ON DELETE RESTRICT,
    anomaly_score NUMERIC(5, 4) NOT NULL,         -- 0.0000 to 1.0000
    anomaly_reasons JSONB NOT NULL,               -- Array of contributing factors
    is_dismissed BOOLEAN NOT NULL DEFAULT FALSE,
    dismissed_by_user_id BIGINT NULL,
    dismissed_at TIMESTAMPTZ NULL,
    dismissal_note TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_anomaly_item ON ml_anomaly_logs(overtime_item_id) WHERE is_dismissed = FALSE;
```

---

## 3. Application Architecture (Laravel 11+ & Vue-Inertia)

To adhere strictly to your engineering rules:
- **Routing**: `Wayfinder` is used exclusively for type-safe route generation; legacy Ziggy is completely omitted.
- **Service Pattern**: Controllers are kept lean (less than 30 lines). Business transactions live in single-responsibility Domain Action and Service classes.
- **Financial Standards**: Timezone strictly managed as `Asia/Jakarta`, Currency formatted as Rupiah (`Rp`), and decimal calculations performed via standard arbitrary precision.

```
app/
├── Actions/
│   ├── Overtime/
│   │   ├── SubmitOvertimeAction.php          # Atomic submission + snapshot creation
│   │   ├── ApproveOvertimeItemAction.php     # Item-level review + optimistic lock
│   │   └── AttachSpklDocumentAction.php      # File upload + state transition
│   └── ML/
│       └── DispatchAnomalyCheckAction.php    # Pushes item to ML inspection queue
├── Services/
│   ├── Analytics/
│   │   ├── BurnIndexCalculatorService.php    # CALC-01 to CALC-08 execution
│   │   └── MonthlySnapshotService.php        # Fast dashboard aggregation rollups
│   └── Policy/
│       └── OvertimePolicyEvaluator.php       # Soft warnings (HKN/HLR, fatigue)
├── Http/
│   ├── Controllers/
│   │   ├── OvertimeSubmissionController.php
│   │   ├── OvertimeApprovalController.php
│   │   ├── DashboardBurnIndexController.php
│   │   └── EmployeeReportController.php
│   └── Requests/
│       ├── StoreOvertimeSubmissionRequest.php
│       └── BulkApprovalRequest.php
```

### 3.1 Core Domain Implementation: `SubmitOvertimeAction.php`

```php
<?php

namespace App\Actions\Overtime;

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\SpklDocument;
use App\Jobs\RunAnomalyDetectionJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitOvertimeAction
{
    /**
     * Executes atomic batch overtime submission with snapshot isolation.
     */
    public function execute(array $data, int $userId): OvertimeSubmission
    {
        return DB::transaction(function () use ($data, $userId) {
            $operationalDate = Carbon::parse($data['operational_date'])->format('Y-m-d');
            
            // 1. Resolve Day Classification (HKN vs HLR)
            $calendar = OperationalCalendar::firstOrCreate(
                ['calendar_date' => $operationalDate],
                [
                    'day_type' => Carbon::parse($operationalDate)->isWeekend() ? 'HLR' : 'HKN',
                    'is_holiday' => false,
                ]
            );

            // 2. Lock & Validate Roster Integrity
            $department = Department::findOrFail($data['department_id']);
            $employeeIds = collect($data['items'])->pluck('employee_id')->all();
            
            $roster = Employee::whereIn('id', $employeeIds)
                ->where('department_id', $data['department_id'])
                ->where('section_id', $data['section_id'])
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            if ($roster->count() !== count($employeeIds)) {
                throw ValidationException::withMessages([
                    'items' => 'One or more employees do not belong to the selected department/section or are inactive.'
                ]);
            }

            // 3. Create Parent Submission Header
            $submissionCode = sprintf(
                'OT-%s-%s-%04d',
                Carbon::parse($operationalDate)->format('Ymd'),
                $data['section_id'],
                OvertimeSubmission::whereDate('operational_date', $operationalDate)->count() + 1
            );

            $submission = OvertimeSubmission::create([
                'submission_code' => $submissionCode,
                'submission_date' => now('Asia/Jakarta')->toDateString(),
                'operational_date' => $operationalDate,
                'day_type' => $calendar->day_type,
                'department_id' => $data['department_id'],
                'section_id' => $data['section_id'],
                'submitted_by_user_id' => $userId,
                'status' => 'SUBMITTED',
                'submission_notes' => $data['notes'] ?? null,
            ]);

            // 4. Create Linked Non-Blocking SPKL Container
            $policy = PolicyThreshold::where('department_id', $data['department_id'])->first()
                ?? PolicyThreshold::whereNull('department_id')->first();
            $graceDays = $policy ? $policy->spkl_grace_period_days : 2;

            SpklDocument::create([
                'overtime_submission_id' => $submission->id,
                'status' => 'PENDING',
                'due_date' => Carbon::parse($operationalDate)->addWeekdays($graceDays)->toDateString(),
            ]);

            // 5. Insert Child Items & Snapshot Financial Costs
            $totalHoursAccumulator = 0.0;
            $itemsToInsert = [];

            foreach ($data['items'] as $itemData) {
                /** @var Employee $employee */
                $employee = $roster[$itemData['employee_id']];
                
                $prod = (float) ($itemData['hours_production'] ?? 0);
                $tpm  = (float) ($itemData['hours_tpm'] ?? 0);
                $proj = (float) ($itemData['hours_project'] ?? 0);
                $oth  = (float) ($itemData['hours_others'] ?? 0);
                $lineTotal = $prod + $tpm + $proj + $oth;

                // Immutable Financial Snapshotting
                $rateSnapshot = $employee->hourly_rate > 0 ? $employee->hourly_rate : $department->default_hourly_rate;
                $costSnapshot = bcmul((string) $lineTotal, (string) $rateSnapshot, 2);

                $createdItem = OvertimeItem::create([
                    'overtime_submission_id' => $submission->id,
                    'employee_id'            => $employee->id,
                    'npk_snapshot'           => $employee->npk,
                    'capex_project_id'       => $proj > 0 ? $itemData['capex_project_id'] : null,
                    'hours_production'       => $prod,
                    'hours_tpm'              => $tpm,
                    'hours_project'          => $proj,
                    'hours_others'           => $oth,
                    'hourly_rate_snapshot'   => $rateSnapshot,
                    'total_cost_snapshot'    => $costSnapshot,
                    'rca_category'           => $itemData['rca_category'] ?? null,
                    'rca_notes'              => $itemData['rca_notes'] ?? null,
                    'task_description'       => $itemData['task_description'] ?? null,
                    'status'                 => 'PENDING',
                    'lock_version'           => 1,
                ]);

                $totalHoursAccumulator += $lineTotal;

                // Dispatch Asynchronous ML Anomaly Detection Scan
                RunAnomalyDetectionJob::dispatch($createdItem->id);
            }

            // 6. Update Cached Header Total
            $submission->update(['total_hours_cached' => $totalHoursAccumulator]);

            return $submission->load(['items', 'spklDocument']);
        });
    }
}
```

### 3.2 Granular Item-Level Approval Action: `ApproveOvertimeItemsAction.php`

```php
<?php

namespace App\Actions\Overtime;

use App\Models\OvertimeItem;
use App\Models\OvertimeItemAudit;
use App\Models\OvertimeSubmission;
use App\Jobs\RecalculateMonthlyBurnSnapshotJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveOvertimeItemsAction
{
    /**
     * Executes itemized partial or bulk approvals with optimistic lock verification.
     */
    public function execute(int $submissionId, array $decisions, int $reviewerUserId): OvertimeSubmission
    {
        return DB::transaction(function () use ($submissionId, $decisions, $reviewerUserId) {
            $submission = OvertimeSubmission::with('items')->findOrFail($submissionId);

            foreach ($decisions as $decision) {
                /** @var OvertimeItem $item */
                $item = OvertimeItem::where('id', $decision['item_id'])
                    ->where('overtime_submission_id', $submissionId)
                    ->lockForUpdate() // Concurrency protection against simultaneous approvals
                    ->firstOrFail();

                // Optimistic Locking Check (Scar Tissue Defense)
                if (isset($decision['lock_version']) && $item->lock_version !== $decision['lock_version']) {
                    throw ValidationException::withMessages([
                        'conflict' => "Row for NPK {$item->npk_snapshot} was modified by another reviewer. Please reload."
                    ]);
                }

                $previousState = $item->toArray();
                $newStatus = strtoupper($decision['action']); // 'APPROVED' or 'REJECTED'

                if ($newStatus === 'REJECTED' && empty($decision['rejection_reason'])) {
                    throw ValidationException::withMessages([
                        'rejection_reason' => "A documented reason is mandatory when rejecting hours for NPK {$item->npk_snapshot}."
                    ]);
                }

                $item->update([
                    'status'           => $newStatus,
                    'reviewed_by_user_id' => $reviewerUserId,
                    'reviewed_at'      => now('Asia/Jakarta'),
                    'rejection_reason' => $newStatus === 'REJECTED' ? $decision['rejection_reason'] : null,
                    'lock_version'     => $item->lock_version + 1,
                ]);

                // Record Audit Event
                OvertimeItemAudit::create([
                    'overtime_item_id' => $item->id,
                    'action'           => $newStatus,
                    'actor_user_id'    => $reviewerUserId,
                    'previous_state'   => $previousState,
                    'new_state'        => $item->fresh()->toArray(),
                    'notes'            => $decision['rejection_reason'] ?? 'Approved via review modal.',
                    'ip_address'       => request()->ip(),
                ]);
            }

            // Synchronize Parent Header Status
            $allApproved = $submission->items()->where('status', '!=', 'APPROVED')->doesntExist();
            $allRejected = $submission->items()->where('status', '!=', 'REJECTED')->doesntExist();
            
            $headerStatus = $allApproved ? 'APPROVED' : ($allRejected ? 'REJECTED' : 'PARTIALLY_APPROVED');
            $submission->update(['status' => $headerStatus]);

            // Dispatch Snapshot Rollup recalculation for the affected section
            RecalculateMonthlyBurnSnapshotJob::dispatch(
                $submission->section_id, 
                Carbon::parse($submission->operational_date)->year, 
                Carbon::parse($submission->operational_date)->month
            );

            return $submission->fresh(['items.employee', 'spklDocument']);
        });
    }
}
```

### 3.3 Core Burn Index & Analytics Service: `BurnIndexCalculatorService.php`

```php
<?php

namespace App\Services\Analytics;

use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use Carbon\Carbon;

class BurnIndexCalculatorService
{
    /**
     * Executes CALC-01 through CALC-08 according to the Business Specification.
     */
    public function calculateSectionMetrics(int $sectionId, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $endDate   = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        // 1. Fetch Planned Quota
        $budget = OvertimeBudget::where('section_id', $sectionId)
            ->where('fiscal_year', $year)
            ->where('fiscal_month', $month)
            ->first();

        $plannedHours = $budget ? (float) $budget->planned_hours : 0.0;

        // 2. Fetch Cumulative Realized Hours from Approved Items
        $metrics = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_submissions.section_id', $sectionId)
            ->whereBetween('overtime_submissions.operational_date', [$startDate, $endDate])
            ->where('overtime_items.status', 'APPROVED')
            ->selectRaw('
                COALESCE(SUM(overtime_items.hours_production), 0) as total_prod,
                COALESCE(SUM(overtime_items.hours_tpm), 0) as total_tpm,
                COALESCE(SUM(overtime_items.hours_project), 0) as total_project,
                COALESCE(SUM(overtime_items.hours_others), 0) as total_others,
                COALESCE(SUM(overtime_items.total_hours), 0) as cumulative_hours,
                COALESCE(SUM(overtime_items.total_cost_snapshot), 0) as cumulative_cost_idr
            ')
            ->first();

        $actualHours = (float) $metrics->cumulative_hours;
        $capexHours  = (float) $metrics->total_project;
        $opexHours   = (float) ($metrics->total_prod + $metrics->total_tpm + $metrics->total_others);

        // CALC-03: Burn Index (%)
        $burnIndex = $plannedHours > 0 
            ? round(($actualHours / $plannedHours) * 100, 2) 
            : 0.0;

        // CALC-04: Remaining Budget Hours
        $remainingHours = $plannedHours - $actualHours;

        // CALC-05: Weekly Burn Velocity
        $now = now('Asia/Jakarta');
        $elapsedWeeks = ($now->year === $year && $now->month === $month) 
            ? max(1.0, round($now->day / 7.0, 1)) 
            : 4.3; // Default weeks in closed month
        
        $velocity = round($actualHours / $elapsedWeeks, 2);

        // CALC-08: CapEx vs OpEx Labor Ratio (%)
        $capexRatio = $actualHours > 0 ? round(($capexHours / $actualHours) * 100, 2) : 0.0;
        $opexRatio  = 100.0 - $capexRatio;

        // Budget Control Matrix: Evaluate 4-Quadrant Zone
        $isHighBurn  = $burnIndex > 100.0;
        $isHighHours = $actualHours >= ($plannedHours * 0.75);

        $zone = match (true) {
            !$isHighBurn && !$isHighHours => 'ZONE_1_EXCELLENT',
            !$isHighBurn && $isHighHours  => 'ZONE_2_GOOD',
            $isHighBurn && !$isHighHours  => 'ZONE_3_WARNING',
            default                       => 'ZONE_4_POOR',
        };

        return [
            'planned_hours'         => $plannedHours,
            'actual_hours'          => $actualHours,
            'remaining_hours'       => $remainingHours,
            'burn_index_pct'        => $burnIndex,
            'velocity_weekly'       => $velocity,
            'projected_total_hours' => round($velocity * 4.3, 1),
            'opex_hours'            => $opexHours,
            'capex_hours'           => $capexHours,
            'capex_ratio_pct'       => $capexRatio,
            'opex_ratio_pct'        => $opexRatio,
            'burn_zone'             => $zone,
            'cumulative_cost_idr'   => (float) $metrics->cumulative_cost_idr,
        ];
    }
}
```

---

## 4. Architectural Rationale: Theoretical Defaults vs. Pragmatic Deviations

As a database architect who has lived through production outages and audit investigations, every design decision balances **mathematical rigor** against **factory-floor pragmatic reality**:

| Architecture Component | Textbook Approach | Pragmatic Real-World Decision | Tradeoff & Rationale |
| :--- | :--- | :--- | :--- |
| **Sum of Categories (`total_hours`)** | Computed on the fly via `SUM(c1..c4)` in SELECT queries or handled in client JS. | **Stored Generated Column (`GENERATED ALWAYS AS ... STORED`)** at the DB engine level. | **Zero Drift**: Guarantees that neither a frontend bug nor a third-party API insertion can ever violate $Total = Prod + TPM + Proj + Others$. B-Tree indexing on `total_hours` becomes instant without index expressions. |
| **Labor Cost Calculation (IDR)** | Dynamically join `employees.hourly_rate` or calculate at query time. | **Immutable Rate Snapshots (`hourly_rate_snapshot`, `total_cost_snapshot`)** stored directly on the item. | **Scar Tissue Protection**: If an operator's wage changes or standard costing is revised next quarter, prior historical CapEx asset capitalization and fiscal burndown records **must never change**. |
| **SPKL Document Workflow** | Strict foreign key gate: reject timesheet submission if SPKL is absent. | **Asynchronous Non-Blocking Container with Due-Date State Machine**. | **Prevents Factory Halt**: Shift supervisors cannot be blocked from logging 30 workers at 23:00 WIB because HR hasn't stamped the paper yet. Shifts get logged; reminders enforce compliance before payroll lock. |
| **Dashboard Query Performance** | Aggregate raw `overtime_items` with joins across 5 tables on every dashboard load. | **Denormalized Rollup Table (`monthly_burn_snapshots`)** updated via queued jobs upon approval. | **OLAP vs. OLTP Isolation**: Keeps executive and section dashboards rendering in $< 50\text{ms}$ even when the plant logs millions of historical rows, avoiding read lock contention on active shift tables. |
| **Concurrency on Approvals** | Blind `UPDATE overtime_items SET status = 'APPROVED'`. | **Row-Level Pessimistic Locking (`lockForUpdate()`) + Optimistic `lock_version` check**. | **Prevents Double-Processing**: Eliminates race conditions when two supervisors simultaneously act on the same submission from different terminals. |
| **Currency Storage** | `FLOAT` or `DOUBLE` | `NUMERIC(15, 2)` / PostgreSQL `BIGINT` | **Financial Integrity**: Floating point math produces fractional rounding anomalies (e.g., `0.1 + 0.2 = 0.30000000000000004`). In Indonesian Rupiah, financial audits require exact penny-level precision. |

---

## 5. Supervised Machine Learning (ML) Integration Blueprint

The Business Analysis specification mandates replacing synthetic toy formulas with **Supervised Machine Learning**. Here is the end-to-end data pipeline:

```
+-----------------------------------------------------------------------------------+
|                        ML FEATURE ENGINEERING PIPELINE                            |
+-----------------------------------------------------------------------------------+
| Historical Lagged OT Hours (4 wks)  <-- [overtime_items]                          |
| Production Volume & Model Mix       <-- [ERP / MES Production Table]              |
| Workday vs Holiday Ratio (HKN/HLR)  <-- [operational_calendars]                   |
| Line OEE & Breakdown Hours          <-- [overtime_items.hours_tpm + RCA tags]     |
| CapEx Milestone Physical Progress   <-- [capex_projects.physical_progress_pct]    |
+-----------------------------------------------------------------------------------+
                                        |
                             (Batch Feature Vector)
                                        v
+-----------------------------------------------------------------------------------+
|                           SUPERVISED ML ENGINE RUNTIME                            |
|                                                                                   |
|  [ML-1: Demand Sizing]        --> XGBoost / LightGBM Regressor (Target: Hours)    |
|  [ML-2: Burn Trajectory]      --> Quantile Gradient Boosting (Median, 10th, 90th) |
|  [ML-3: CapEx Labor Forecast] --> Support Vector / ElasticNet Regressor           |
|  [ML-4: Anomaly Detection]    --> Supervised Classifier trained on audit rejects  |
|                                   + Isolation Forest boundary scoring             |
+-----------------------------------------------------------------------------------+
                                        |
                 +----------------------+----------------------+
                 | (High Confidence)                           | (Sparse Data / Cold Start)
                 v                                             v
+----------------------------------+          +-------------------------------------+
| Store in `ml_predictions`        |          | Graceful Fallback: 4-Week Moving Avg|
| Display: "ML Model: ±12 hrs"     |          | Badge: `Calculation: Moving Average`|
+----------------------------------+          +-------------------------------------+
```

### 5.1 Concrete Feature Store Extraction Query
To train or score models for a given section, execute this high-performance feature aggregation:

```sql
-- Feature Vector for Section Overtime Demand & Burn Trajectory
WITH monthly_history AS (
    SELECT 
        s.section_id,
        DATE_TRUNC('month', s.operational_date) AS fiscal_month,
        SUM(i.total_hours) AS total_hours_consumed,
        SUM(CASE WHEN s.day_type = 'HLR' THEN i.total_hours ELSE 0 END) AS holiday_hours,
        SUM(CASE WHEN i.rca_category = 'MACHINE_BREAKDOWN' THEN i.hours_tpm ELSE 0 END) AS breakdown_tpm_hours,
        COUNT(DISTINCT s.operational_date) AS active_shift_days
    FROM overtime_submissions s
    JOIN overtime_items i ON s.id = i.overtime_submission_id
    WHERE i.status = 'APPROVED'
      AND s.operational_date >= CURRENT_DATE - INTERVAL '12 months'
    GROUP BY s.section_id, DATE_TRUNC('month', s.operational_date)
)
SELECT 
    section_id,
    fiscal_month,
    total_hours_consumed,
    holiday_hours,
    breakdown_tpm_hours,
    -- 4-week lagging moving average feature
    AVG(total_hours_consumed) OVER (
        PARTITION BY section_id 
        ORDER BY fiscal_month 
        ROWS BETWEEN 3 PRECEDING AND 1 PRECEDING
    ) AS lagged_3m_avg_hours
FROM monthly_history;
```

---

## 6. Tradeoffs, Failure Modes & Mitigations

### 6.1 Failure Mode 1: High-Concurrency Shift-End Lock Contention
* **The Risk**: At 15:00 WIB, 30 Team Leaders submit timesheets simultaneously. If the system attempted to lock or update the master `overtime_budgets` row in real time inside the same transaction, transactions would queue and trigger deadlocks (`Lock wait timeout exceeded`).
* **The Mitigation**: **Decouple budget reading from transaction locking**. The overtime submission transaction only inserts rows into `overtime_submissions` and `overtime_items`. Burndown calculations and snapshot updates are offloaded to an asynchronous Redis worker (`RecalculateMonthlyBurnSnapshotJob`). The submission path is pure lock-free append.

### 6.2 Failure Mode 2: Uncontrolled File Bloat from SPKL Document Uploads
* **The Risk**: Frontline supervisors uploading uncompressed 15 MB smartphone photos of signed paper SPKLs directly to the web server, filling the web server disk and blowing up backup windows.
* **The Mitigation**:
  * Validation layer restricts files to PDF, JPEG, PNG with a hard maximum of `3 MB`.
  * Store documents in a dedicated private S3-compatible object store (e.g., MinIO or AWS S3), saving only the URI string in `spkl_documents.file_path`.
  * Serve files via short-lived temporary signed URLs generated by the Laravel backend.

### 6.3 Failure Mode 3: Cold-Start ML Hallucinations in New Production Lines
* **The Risk**: A newly commissioned line (e.g., "Battery Pack Assembly") has zero historical training records. An unchecked ML regressor could predict negative overtime or wildly inflated figures, destroying manager confidence.
* **The Mitigation**: **Strict Fallback Circuit Breaker (BR Criteria 5.3)**:
  * The inference pipeline checks sample size: if $n_{\text{historical\_shifts}} < 30$, it flags `fallback_used = true` and applies a rule-based moving average:
    $$\text{Fallback Budget} = \text{Headcount} \times \text{Standard Working Days} \times 0.10$$
  * Displays the UI badge: `Calculation: Moving Average (Insufficient ML Baseline)`.

---

## 7. Alternatives Considered & Rejected

1. **MongoDB / NoSQL Document Store**:
   * *Why Rejected*: Timesheet records are intrinsically relational and tied to general ledger accounting (CapEx capitalization, departmental cost centers). NoSQL lacks declarative ACID foreign key constraints, creating severe risks of orphaned records and silent budget accounting drift.
2. **Pure Event Sourcing (CQRS / Event Store)**:
   * *Why Rejected*: Overengineering for this operational scale. Event sourcing would introduce significant cognitive load and operational friction for a standard manufacturing shop-floor app. The chosen design achieves 100% auditability through a dedicated append-only ledger (`overtime_item_audits`) alongside standard 3NF state tables.
3. **Frontend Calculation of Overtime Sums & Statuses**:
   * *Why Rejected*: Critical business calculations (Burn Index, Day-type rates, category sums) must never be trusted to browser JavaScript. All computations are enforced via database generated columns and server-side domain services (`BurnIndexCalculatorService`).

---

## 8. Development Roadmap & Implementation Steps for Zulfikar

To kick off development cleanly using your preferred toolchain:

1. **Database Schema & Migrations**:
   - Implement the SQL DDL provided in Section 2.3 as modular Laravel migration files (`database/migrations/`).
   - Run `php artisan migrate` to verify all foreign keys and check constraints.
2. **Wayfinder Route Setup**:
   - Install and configure Wayfinder (`pnpm add @wayfinder/vue` or appropriate package) to generate route definitions for Vue Inertia pages.
   - Establish typed API routes for:
     - `POST /overtime/submissions` $\rightarrow$ `OvertimeSubmissionController@store`
     - `POST /overtime/submissions/{id}/approve` $\rightarrow$ `OvertimeApprovalController@approveItems`
     - `POST /overtime/submissions/{id}/spkl` $\rightarrow$ `SpklController@attach`
3. **Core Actions & Services**:
   - Implement `SubmitOvertimeAction`, `ApproveOvertimeItemsAction`, and `BurnIndexCalculatorService`.
4. **Vue 3 Inertia Components**:
   - Modernize the prototype screens (`PlanningIndexOvertime`, `SummaryListOvertime`, `IndexOvertime`) into Vue 3 `<script setup>` SFC components.
   - Run `pnpm lint` and `pnpm build` continuously to ensure bundle integrity.