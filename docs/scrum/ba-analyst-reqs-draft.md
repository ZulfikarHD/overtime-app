# Functional Business Analysis Report

**Application:** Overtime & CapEx Labor Management System (OT-CapEx System – Manufacturing)  
**Analysis Type:** Legacy Modernization & Advanced Functional Specification (Legacy Analysis Mode + Client Directives)  
**Analyst:** Senior Business Analyst Agent  
**Target Audience:** Plant Management, Product Owner, Database Architect, and Engineering Team

---

## 1. App Purpose & User Roles

### App Purpose

The **Overtime & CapEx Labor Management System (OT-CapEx System)** is an operational control, budgeting, and analytical platform built for a manufacturing enterprise (specifically tailored to automotive/industrial production lines, tooling workshops, and maintenance divisions).

The primary business objective of the system is to **plan, record, govern, and analyze employee overtime (jam lembur)** across departments and production sections, while establishing clear transparency between **Operating Expense (OpEx) overtime** and **Capital Expenditure (CapEx) project-capitalized labor**.

The platform transforms overtime management from a disconnected administrative paperwork task into an active cost-control, operational planning, and predictive analytics engine by:

1. **Operational Time Capture & Flexible SPKL Governance**: Enabling frontline shift supervisors to log actual overtime quickly without administrative bottlenecks, with flexible post-shift attachment and tracking of formal **Surat Perintah Kerja Lembur (SPKL)** documents.
2. **Day-Type & Policy-Based Governance**: Distinguishing between normal workdays (**Hari Kerja Normal / HKN**) and weekend/rest days (**Hari Libur / HLR**), applying internal company policy thresholds (_kebijakan internal perusahaan_) rather than rigid external blocks.
3. **Budget & Burn Index Control**: Continuously tracking actual overtime consumption against monthly section budgets via a normalized **Burn Index**, preventing budget exhaustion before period ends.
4. **CapEx Labor Accounting**: Clearly isolating overtime dedicated to capitalizable assets (e.g., new assembly line installation, machine fabrication, major overhaul maintenance) from daily operational catch-up.
5. **Supervised Machine Learning (ML) Integration**: Leveraging supervised regression and classification models to replace arbitrary static formulas with data-driven predictive forecasts for overtime demand, month-end Burn Index trajectory, CapEx labor allocation, and operational anomaly detection.

---

### Functional User Roles & Permissions

| Role                | Business Title / Persona                                                                     | Functional Scope & Responsibility                                                                                                                                                                                                                                                                                                                                                        |
| :------------------ | :------------------------------------------------------------------------------------------- | :--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Admin**           | System Administrator / HR Operations Lead                                                    | • Plant-wide access across all departments and production lines.<br>• Manages master data: user accounts, department/section hierarchies, employee rosters (NPK), and operational calendars.<br>• Configures baseline budget quotas, internal policy alert thresholds, and system settings.<br>• Monitors ML model health/accuracy metrics and data export compliance.                   |
| **Manager**         | Department Head (e.g., Production Manager, Plant Maintenance Manager, CapEx Project Manager) | • Departmental and cross-section financial oversight.<br>• Reviews department-level Burn Index, cumulative budget consumption, and CapEx vs. OpEx labor splits.<br>• Performs single and bulk approvals, with authority to approve/reject specific line items partially.<br>• Evaluates ML-driven capacity forecasts, budget deficit alerts, and anomaly reports.                        |
| **Team Leader**     | Senior Team Leader / Section Supervisor (e.g., Senior Team Leader – Assembly Line 1)         | • Frontline operational manager handling daily shifts.<br>• Enters daily overtime hours for team members in their section.<br>• Assigns work categories (Production, TPM, Project/CapEx, Others) and optional Root Cause Reason codes.<br>• Uploads or links SPKL document attachments post-shift.<br>• Receives system reminders for unattached SPKLs and section budget burn velocity. |
| **User (Employee)** | Line Operator / Maintenance Technician / Tooling Specialist                                  | • Individual contributor working operational shifts.<br>• Views personal overtime reports, monthly/yearly hours, timesheet history, and individual Burn Index.<br>• Benchmarks personal hours against departmental averages.<br>• Self-service personal profile and display preference management.                                                                                       |

---

## 2. Core Workflows

### Workflow 1: Daily Overtime Entry & Flexible SPKL Attachment

1. **Section Selection**: The Team Leader selects the operational **Date (`tanggal`)**, **Department**, and **Section**.
2. **Day Classification**: The system automatically identifies the calendar date as either **Hari Kerja Normal (HKN)** or **Hari Libur / Istirahat Mingguan (HLR)**, which can be verified or toggled by the supervisor.
3. **Roster Filtering**: The system populates the employee roster displaying only active personnel assigned to that specific Department and Section.
4. **Employee Line Addition**: The supervisor selects an employee from the roster; their unique identifier (**NPK**) is automatically loaded and locked as read-only.
5. **Hour Allocation by Category**: The supervisor enters decimal hours (minimum `0.5` hours) across four core business classifications:
    - _Production (OpEx)_: Daily production quota catch-up, takt time adjustment.
    - _TPM (Total Productive Maintenance)_: Routine preventive maintenance or line checks.
    - _Project (CapEx / Special Project)_: Capitalizable line installation, tooling manufacture, or major machine overhaul.
    - _Others_: Non-standard tasks, housekeeping, 5S, safety briefings.
6. **Optional Root Cause / Operational Reason (RCA)**: The supervisor may optionally select a standardized reason tag (e.g., _Line Stop / Mesin Breakdown_, _Part Shortage / Delay Supplier_, _Quality Rework_, _Customer Delivery Rush_, _Trial Model Baru_). **This is non-blocking and optional.**
7. **Immediate Submission (Non-Blocking)**: The supervisor submits the timesheet immediately to record the shift.
8. **Flexible SPKL Post-Attachment Flow**:
    - The entry is successfully recorded even without an SPKL physical document attached.
    - The record status displays a clear operational badge: `SPKL: Pending Attachment (Belum Ada Lampiran)`.
    - The system triggers passive notifications and dashboard reminders to the Team Leader until the formal SPKL reference or document scan is attached.
    - Attaching the SPKL document transitions the badge to `SPKL: Complete (Terlampir)`.

---

### Workflow 2: Granular Verification & Approval Lifecycle

1. **Pending Queue**: Submitted records land in the supervisor/manager review queue.
2. **Itemized Audit (Quick-View Modal)**: The reviewer inspects:
    - Date, Day Type (HKN vs HLR), submitting supervisor, and Department/Section.
    - Employee identification (Name, NPK, assigned position).
    - Hour distribution across Production, TPM, Project (CapEx), and Others.
    - Optional root cause tag and task description.
    - SPKL attachment status (`Attached` vs `Pending`).
    - Individual Burn Index indicator.
3. **Item-Level Partial Decision (Granular Control)**:
    - The reviewer can approve all workers, or approve specific employees while rejecting others (partial approval).
    - If an item or submission is rejected, the reviewer must provide a mandatory business reason note (e.g., _"Budget ceiling reached"_, _"Rescheduled to regular shift"_, _"Incorrect category allocation"_).
4. **Batch/Bulk Processing**: Managers can filter by Department, Date, or Status and execute **Bulk Approve**, **Bulk Reject**, or **Export to CSV/Excel**.
5. **Modification Locking**: Approved records are locked against operator deletion or tampering.

---

### Workflow 3: Monthly & Weekly Budget Burndown Monitoring

1. **Periodic Budgeting**: Overtime budgets are tracked over a 5-week monthly cycle based on planned labor hour allocations.
2. **Weekly Consumption Comparison**: For each week (Week 1 through Week 5), the system computes actual cumulative hours against planned milestones.
3. **Burn Velocity Tracking**: The system calculates the average burn rate:
   $$\text{Velocity} = \frac{\text{Cumulative Hours Consumed}}{\text{Elapsed Weeks}}$$
   It projects whether current velocity will result in an end-of-month budget deficit.
4. **CapEx vs. OpEx Split Monitoring**: Segregates cumulative overtime hours into Operational expenses (Production, routine TPM) versus Capitalized project labor (New installations, machine overhauls), allowing financial controllers to track CapEx utilization.
5. **Policy-Based Threshold Alerts**: Flags when remaining budget falls below safe thresholds (50%, 75%, 90%, 100%) based on internal company policy (_kebijakan internal_).

---

### Workflow 4: Individual Performance & Welfare Tracking (Report Individu)

1. **Employee Lookup**: Supervisors or individual employees search by Name or NPK, or pick from recent lookups.
2. **Individual Profile Dashboard**:
    - Current Month Overtime Hours & Year-to-Date Hours.
    - Individual Burn Index (%) and Departmental Ranking.
    - Day-Type Breakdown: Hours on normal workdays (HKN) vs rest/holiday days (HLR).
    - Category Allocation: Proportion of hours spent on Production, TPM, CapEx Projects, and Others.
3. **Peer Benchmarking**: Compares the employee's overtime load against the departmental average to identify workload distribution imbalances.
4. **Safety & Fatigue Soft Indicators**: Highlights employees approaching high workload thresholds according to company health and safety guidelines.
5. **Audit Timesheet**: Chronological timesheet showing date, day name, total hours, task notes, category, and approval status.

---

### Workflow 5: Supervised Machine Learning Analytics & Decision Intelligence

_(See Section 5 for deep functional ML specifications)_

1. **Overtime Demand Forecasting**: Supervised regression predicts expected overtime hours required for upcoming weeks/months based on production schedules, product mix, and machine uptime history.
2. **Burn Index Trajectory & Deficit Early Warning**: Predicts the final month-end Burn Index and alerts managers by mid-month if a department is on track to overrun its budget.
3. **CapEx Labor Forecasting**: Models the labor hours required to complete capital projects and predicts whether capitalized labor spend will stay within project allocations.
4. **Operational Anomaly Detection**: Identifies unusual data entries (e.g., spikes in non-productive hours, discrepancies between reported overtime and actual production output) for review.

---

### Workflow 6: Administration, Security & Preference Configuration

1. **User Master Data Management**:
    - Create, edit, and deactivate accounts with role-based access control (Admin, Manager, Team Leader, User).
    - Enforce password complexity policies.
2. **Company Policy Threshold Configuration**:
    - Configure internal overtime warning limits (e.g., weekly soft thresholds, monthly department caps).
    - Configure day-type rules (HKN/HLR) and plant holiday calendars.
3. **User Preferences & Display Localization**:
    - UI Theme (Light, Dark, Auto).
    - Regional localization: Indonesian Rupiah (Rp), Timezone (WIB - Asia/Jakarta), Date formatting (`DD/MM/YYYY`), Number formatting (`1.234,56`).
4. **Notification Settings**:
    - Toggle notification channels and frequency for SPKL reminders, approval notices, and budget threshold alerts.

---

## 3. Business Rules & Calculations

### Business Rules (Plain Language)

#### 1. Roster and Assignment Governance

- Overtime can only be logged for employees formally registered in the selected Department and Section.
- An employee's NPK is permanently bound to their employee record and cannot be edited during daily timesheet submission.

#### 2. Entry & Unit Granularity Rules

- Overtime duration must be entered in decimal hours with a minimum step of **0.5 hours (30 minutes)**; negative numbers are forbidden.
- Total daily overtime for an individual is the direct arithmetic sum of their hours across the four defined categories (_Production_, _TPM_, _Project_, _Others_).
- An overtime entry cannot be submitted without at least one employee and a valid calendar date.

#### 3. Day Classification (HKN vs HLR)

- Every overtime record is tagged by day type:
    - **HKN (Hari Kerja Normal)**: Standard operational weekday shift.
    - **HLR (Hari Libur / Istirahat Mingguan)**: Weekend, designated rest day, or national public holiday.
- Day classification drives analytical reporting and cost variance analysis between regular and holiday overtime.

#### 4. SPKL Lifecycle & Non-Blocking Rules

- **Non-Blocking Submission**: A Team Leader can submit daily overtime without an attached SPKL document so that shift operations and timesheet records are not delayed.
- **Pending SPKL Status**: Entries submitted without an SPKL are flagged with a `Pending SPKL` status badge.
- **Mandatory Finalization**: In accordance with company audit standards, the SPKL document (reference number or file upload) must be attached prior to final financial/payroll period closing.
- **Automated System Reminders**: The system generates persistent reminders for records that have not attached an SPKL after a configurable grace period (e.g., 2 working days).

#### 5. Company Policy Limits (Soft Safeguards)

- Overtime safety thresholds are governed by **internal company policy ("kebijakan perusahaan")**, rather than hard legal system blocks:
    - **Weekly Soft Warning Threshold**: Overtime exceeding a configurable company threshold (e.g., 18 or 20 hours/week) generates a visual soft warning indicator.
    - **Consecutive High-Load Alert**: An employee logging above the policy threshold for 3 consecutive weeks generates an operational workload alert for supervisor review.
    - **No Hard Block**: The system does not lock out submissions solely based on threshold breaches, allowing operational flexibility while ensuring management visibility.

#### 6. Root Cause Tracking (RCA) Policy

- Root Cause Reason tags are **optional and non-blocking**.
- When provided, supervisors choose from standardized operational reasons:
    - _Line Stop / Mesin Breakdown_
    - _Shortage Part / Delay Supplier_
    - _Quality Sorting / Rework Defect_
    - _Customer Delivery Rush / Order Spike_
    - _Trial Model Baru / Project Commissioning_
    - _Other Operational Justification_

#### 7. CapEx vs. OpEx Labor Classification Rules

- Overtime categorized under **Project** represents capitalized labor (CapEx) associated with identifiable capital asset creation, tooling builds, or major overhaul projects.
- Overtime under **Production, TPM, and Others** represents routine operational expenditure (OpEx).
- Capitalized hours must reference the associated Project / Asset code to ensure audit compliance for financial capitalization.

#### 8. Burn Index & Budget Control Matrix

The system uses the **Burn Index** to evaluate budget compliance and resource efficiency:

- **Burn Index Formula**:
  $$\text{Burn Index} = \left(\frac{\text{Actual Overtime Hours}}{\text{Planned / Budgeted Overtime Hours}}\right) \times 100\%$$
- **Status Classifications**:
    - **Under Budget (High Efficiency)**: $\text{Burn Index} < 85\%$  
      _Operational Status_: Budget consumption well below target; surplus capacity available.
    - **On Track (Budget Compliant)**: $85\% \le \text{Burn Index} \le 100\%$  
      _Operational Status_: Progressing within budget targets; maintain current shift pace.
    - **Warning (Slight Overrun)**: $100\% < \text{Burn Index} \le 115\%$  
      _Operational Status_: Approaching or slightly exceeding budget ceiling; evaluate task distribution.
    - **Over Budget (Deficit)**: $\text{Burn Index} > 115\%$  
      _Operational Status_: Significant budget deficit; requires immediate management review.

- **Budget Control Matrix (Hours vs. Burn Rate)**:
    - **Zone 1: EXCELLENT** = Low Burn ($\le 100\%$) and Low Cumulative Hours.
    - **Zone 2: GOOD** = Low Burn ($\le 100\%$) and High Cumulative Hours.
    - **Zone 3: WARNING** = High Burn ($> 100\%$) and Low Cumulative Hours.
    - **Zone 4: POOR** = High Burn ($> 100\%$) and High Cumulative Hours.

---

### Core Calculation Formulas

- **CALC-01: Individual Daily Overtime Hours**
  $$\text{Total Hours} = \text{Production Hours} + \text{TPM Hours} + \text{Project (CapEx) Hours} + \text{Others Hours}$$

- **CALC-02: Section Total Overtime Hours**
  $$\text{Section Total} = \sum_{i=1}^{n} \text{Total Hours}_i \quad (\text{for all employees } i \text{ in the section})$$

- **CALC-03: Burn Index (%)**
  $$\text{Burn Index} = \left(\frac{\text{Cumulative Actual Overtime Hours}}{\text{Planned Budget Overtime Hours}}\right) \times 100\%$$

- **CALC-04: Remaining Budget Hours**
  $$\text{Remaining Budget} = \text{Planned Budget Hours} - \text{Cumulative Actual Hours}$$

- **CALC-05: Weekly Burn Velocity**
  $$\text{Velocity} = \frac{\text{Cumulative Actual Hours Consumed}}{\text{Elapsed Weeks in Current Period}}$$

- **CALC-06: Projected Period-End Total (Heuristic Baseline)**
  $$\text{Projected Total Hours} = \text{Velocity} \times \text{Total Weeks in Period}$$

- **CALC-07: Departmental Peer Variance**
  $$\text{Variance} = \text{Individual Total Hours} - \text{Department Average Hours per Employee}$$

- **CALC-08: CapEx vs. OpEx Distribution Ratio**
  $$\text{CapEx Labor Ratio (\%)} = \left(\frac{\sum \text{Project Hours}}{\sum \text{Total Overtime Hours}}\right) \times 100\%$$
  $$\text{OpEx Labor Ratio (\%)} = 100\% - \text{CapEx Labor Ratio (\%)} $$

---

## 4. Features Pruned & Refined (Legacy Prototype Cleanup)

To transition from prototype concepts to a robust production system, the following items from the legacy prototype have been pruned or modified:

| Prototype Item                                                   | Status           | Action Taken & Business Rationale                                                                                                                                                                        |
| :--------------------------------------------------------------- | :--------------- | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Arbitrary What-If ROI & Well-being Sliders**                   | **REMOVED**      | Pruned synthetic formulas (`ROI = 156% + 1.2 * OT%`, `Well-being = 7.5 - 0.05 * OT%`). Replaced with transparent budget variance analysis and data-driven Supervised Machine Learning models.            |
| **4-Tier Subjective Difficulty Index (Index I–IV: Rp 50k–150k)** | **REMOVED**      | Pruned arbitrary difficulty grading. Overtime costing is now structured around departmental standard costing and clear OpEx vs. CapEx project attribution.                                               |
| **Consistency 1-to-5 Star Rating**                               | **REMOVED**      | Pruned the star rating that rewarded operators working $> 3$ hours/shift. Overtime is an operational cost, not an indicator of personal competence. Replaced with workload safety indicators.            |
| **Government Statutory Hard Blocks (PP 35/2021 Hard Locks)**     | **MODIFIED**     | Maintained the distinction of **HKN vs HLR** day types, but converted statutory limits into **internal company policy soft indicators**, giving management discretion without locking operational entry. |
| **Mandatory Prior SPKL Gate**                                    | **MODIFIED**     | Shifted SPKL from a pre-overtime gate to a **flexible post-shift attachment workflow** with reminder notifications, reflecting Indonesian plant operational realities.                                   |
| **Logistics Flags (Meals / Transport / Shift Context)**          | **DEFERRED**     | Excluded from the current scope per stakeholder direction; no current business requirement.                                                                                                              |
| **Redundant Summary Screens**                                    | **CONSOLIDATED** | Merged three overlapping prototype summary pages into two streamlined modules: (1) **Daily Operational Register & Approval Queue**, and (2) **Budget Control & Predictive Management Dashboard**.        |

---

## 5. Supervised Machine Learning (ML) Integration Specification

### 5.1 Business Objectives & Rationale

In modern manufacturing, simple static rules (e.g., multiplying unit volume by `0.125` hours) fail to capture the complexity of product mix changes, machine breakdown distributions, line changeover delays, and seasonal demand surges. Furthermore, finance and operations teams require early notice of budget overruns rather than retrospective post-mortems.

The system incorporates **Supervised Machine Learning** across four core business use cases:

1. **Predictive Overtime Demand Sizing**: Accurately estimating future weekly/monthly overtime needs.
2. **Burn Index Trajectory & Budget Deficit Early Warning**: Projecting period-end budget burn status by mid-month.
3. **CapEx Labor Allocation & Project Overtime Forecasting**: Estimating labor capitalizable into fixed assets vs operating expenses.
4. **Supervised Operational Anomaly Detection**: Flagging irregular overtime claims for audit.

---

### 5.2 Supervised ML Use Cases & Functional Definitions

#### Use Case ML-1: Overtime Demand Forecasting (Regression)

- **Business Problem**: Supervisors either overestimate overtime budgets (creating idle labor costs) or underestimate them (resulting in missed shipment deadlines).
- **Target Variable ($Y$)**:
    - Total Overtime Hours required per Department / Section for the target week or month ($\hat{Y}_{\text{hours}} \in \mathbb{R}^+$).
- **Candidate Supervised Algorithms**: Gradient Boosted Trees (XGBoost, LightGBM), Random Forest Regressor, ElasticNet / Quantile Regression.
- **Input Features ($X$)**:
    - _Production Target Volume_: Scheduled unit production count by product model / part family.
    - _Product Complexity Weight_: Standard manufacturing labor hour factor per unit model.
    - _Historical Line Availability_: Rolling average line uptime / Overall Equipment Effectiveness (OEE).
    - _Work Calendar Features_: Planned working days, count of HKN vs HLR days, holiday proximity indicator.
    - _Headcount & Shift Structure_: Active roster headcount, planned regular shift counts.
    - _Historical Lagged Overtime_: Prior 4 weeks of realized overtime consumption.
- **Functional Decision Output**:
    - The system displays a **Recommended Overtime Budget** with a confidence interval (e.g., _Predicted OT: 185 hrs ± 12 hrs at 90% confidence_).
    - The supervisor can accept the recommendation or input a justified variance.

#### Use Case ML-2: Month-End Burn Index Trajectory Prediction (Regression / Classification)

- **Business Problem**: Budget deficits are often realized too late (during Week 4 or 5), when corrective interventions are no longer possible.
- **Target Variable ($Y$)**:
    - Predicted Period-End Burn Index percentage ($\hat{Y}_{\text{burn}} \in \mathbb{R}^+$).
    - Binary Early Warning Flag: Risk of budget overrun ($Y \in \{0, 1\}$, where $1$ indicates predicted Burn Index $> 100\%$).
- **Candidate Supervised Algorithms**: Quantile Gradient Boosting (predicting median, 10th, and 90th percentile trajectories), Logistic Regression / Random Forest Classifier for overrun risk probability.
- **Input Features ($X$)**:
    - Elapsed days / weeks in current monthly cycle.
    - Current cumulative hours consumed to date (HKN vs HLR).
    - Current Burn Velocity and week-over-week acceleration.
    - Remaining production backlog / delivery schedule for the period.
    - Historical period-end burn patterns for the specific section.
- **Functional Decision Output**:
    - **Early Warning Radar**: By Day 10–14 of the month, the system assigns a risk score: _Low_, _Moderate_, or _High Risk of Budget Exhaustion_.
    - Displays a projected trajectory curve comparing planned burndown against ML-projected consumption.

#### Use Case ML-3: CapEx Project Labor Sizing & Variance Forecasting

- **Business Problem**: Industrial capital projects (e.g., new line fabrication, die refurbishment, machine overhauls) frequently exceed capitalized labor allowances, leading to budget reallocations and audit scrutiny.
- **Target Variable ($Y$)**:
    - Total Capitalizable Project Overtime Hours required to complete the project milestone ($\hat{Y}_{\text{capex}}$).
    - Probability of CapEx labor budget overrun ($P(\text{Overrun}) \in [0, 1]$).
- **Candidate Supervised Algorithms**: Gradient Boosting Regressor, Support Vector Regression (SVR).
- **Input Features ($X$)**:
    - Capital Project Type (New Line Setup, Tooling Build, Major Machine Overhaul, Facility Upgrade).
    - Total allocated CapEx budget (labor portion).
    - Planned project duration and current percentage of physical completion (Milestone progress %).
    - Cumulative project overtime hours logged to date.
    - Skill level / trade qualification of assigned technical personnel.
- **Functional Decision Output**:
    - **CapEx Variance Alert**: Compares the capitalized labor forecast against the capital appropriation budget.
    - **Milestone Burn Rate**: Warns project managers if project labor burn is outpacing physical milestone progress.

#### Use Case ML-4: Supervised Operational Anomaly & Outlier Detection

- **Business Problem**: Timesheet entries occasionally contain administrative errors, misallocated categories, or unjustified hours that bypass human review in high-volume batches.
- **Target Variable ($Y$)**:
    - Anomaly Likelihood Score ($0.0\text{ to }1.0$) or Anomaly Class ($0 = \text{Normal}, 1 = \text{Flagged Anomaly}$).
- **Candidate Approaches**: Supervised learning trained on historical audit adjustments and rejected timesheet logs, combined with isolation methods (Isolation Forests, One-Class SVM) and rule-based boundary checks.
- **Input Features ($X$)**:
    - Ratio of overtime hours to standard shift hours.
    - Discrepancy between reported overtime hours and line output during that shift.
    - High proportion of generic categories (_Others_) relative to direct _Production_.
    - Individual employee hours exceeding 3 standard deviations from their 60-day moving average.
    - Repeated weekend/holiday (HLR) overtime without linked project or production order references.
- **Functional Decision Output**:
    - **Audit Flag Badge**: Flagged records display an advisory tag: `Review Recommended: Unusual Category Allocation` or `Output Discrepancy`.
    - Flags do not block submission, but are highlighted in the Manager's approval queue for inspection.

---

### 5.3 Business Acceptance & Evaluation Criteria for ML Models

To ensure practical adoption and operational reliability on the factory floor, ML integration must adhere to strict functional standards:

1. **Interpretability & Explainability (Feature Impact)**:
    - Plant managers will not trust "black box" numbers. Predictions must provide top contributing factors (e.g., _"Forecast increased by +35 hours primarily due to planned holiday production on Saturday and 12% lower machine uptime last week"_).
2. **Acceptance Error Thresholds**:
    - Overtime Demand Forecast: Target **Mean Absolute Percentage Error (MAPE) $\le 12\%$** on section-level monthly aggregations.
    - Month-End Deficit Early Warning: Minimum **Recall $\ge 85\%$** for detecting budget overruns by Day 15 of the period.
3. **Graceful Fallback Mechanism**:
    - If historical training data is insufficient (e.g., newly created section or missing data feed), the system automatically defaults to standard historical moving averages, accompanied by a clear badge: `Calculation: Moving Average (Insufficient ML baseline)`.
4. **Human-in-the-Loop Override**:
    - ML predictions serve as **decision support**, not autonomous decisions. Approving managers retain full authority to adjust budgets and overtime quotas with an audit note.

---

## 6. Quirks & Open Questions (Updated Legacy Audit Findings)

Following stakeholder directives, the legacy audit observations have been refined:

### 1. Department and Section Master Data Reconciliation

- **Finding**: The legacy prototype had conflicting naming conventions across different screens (`Assembly Line 1/2, Welding, Painting` vs. shop-floor codes like `TCF FS, BODY KS, PCD`).
- **Current Status**: Needs formal confirmation from the plant operations team to establish the definitive master organizational hierarchy for production lines and support departments.

### 2. Definitive Operational Baseline for Overtime Costing

- **Finding**: The legacy prototype used arbitrary tier-based pricing (Rp 50k–150k/hr).
- **Current Status**: Client confirmed ignoring arbitrary tiers. Standard costing will utilize either departmental standard hourly rates or base labor rates for financial tracking.

### 3. Grace Period for SPKL Attachment

- **Finding**: SPKL documents will be attached after shifts rather than serving as a prior blocker.
- **Stakeholder Question**: What is the agreed internal grace period (e.g., 2 working days, or before the 25th of the monthly payroll cutoff) before an unattached SPKL escalates to a supervisor reminder?

### 4. Historical Data Availability for Supervised ML Training

- **Finding**: The client plans to implement supervised ML models for forecasting, index burn, and CapEx labor.
- **Stakeholder Question**: How many months of clean historical overtime timesheets, production volume records, and maintenance logs are available for model training (ideally $\ge 12$ to 24 months to account for annual seasonality)?

---

## 7. Functional Requirements Summary (Handoff Artifact)

```
================================================================================
FUNCTIONAL REQUIREMENTS SUMMARY
Handoff Artifact for Database Architect & Development Team
================================================================================

1. ENTITIES INVOLVED (Business Terms & Conceptual Scope)
--------------------------------------------------------------------------------
- User Account:
    System user record capturing credentials, name, email, role (Admin, Manager,
    Team Leader, User), status, and assigned departmental scope.
- Employee Profile:
    Plant personnel record tracking unique NPK, Full Name, Department, Section,
    and Job Position.
- Department & Section Hierarchy:
    Organizational units representing manufacturing departments (e.g., Production,
    Maintenance, Quality) and subordinate shop-floor sections/lines.
- Operational Calendar & Day Type:
    Calendar tracking operational dates with classifications:
    HKN (Hari Kerja Normal) vs HLR (Hari Libur / Istirahat Mingguan / Libur Nasional).
- Overtime Submission:
    Parent record capturing shift overtime batch: Date, Day Type, Department,
    Section, Submitting Supervisor, Submission Timestamp, and Overall Status.
- Overtime Item / Detail:
    Line item for an individual worker: Employee (NPK), Hours by Category
    (Production, TPM, Project/CapEx, Others), Total Hours, Optional Root Cause (RCA)
    Tag, Task Description, and Line Approval Status.
- SPKL Attachment Record:
    Physical document attachment or formal reference number linked to an Overtime
    Submission; supports post-shift attachment with status tracking (Attached / Pending).
- CapEx Project / Asset Allocation:
    Capital project or equipment identifier associated with "Project" category
    overtime to substantiate capitalized labor expenses.
- Overtime Budget Plan:
    Budgeted overtime hours allocated to a department/section for a fiscal period
    (e.g., Monthly 5-week cycle).
- ML Model Output & Prediction Log:
    Predicted overtime demand, projected Burn Index trajectory, CapEx labor
    forecasts, confidence intervals, and anomaly risk scores.

2. CORE WORKFLOWS
--------------------------------------------------------------------------------
- Workflow 1: Daily Overtime Entry with Flexible SPKL
    Supervisor selects Date, Department, Section -> System assigns Day Type
    (HKN/HLR) -> Supervisor adds employees from section roster -> Enters decimal
    hours across categories -> Optionally tags Root Cause -> Submits immediately.
    SPKL can be attached post-shift; system displays pending status badge and
    triggers gentle notifications.
- Workflow 2: Granular Verification & Item-Level Approval
    Reviewer audits pending submissions -> Can execute bulk approval or partial
    item-level approval (approving essential workers, rejecting others with a
    mandatory explanation note) -> Approved entries lock against editing.
- Workflow 3: Periodic Budget Burndown & Burn Index Tracking
    System aggregates cumulative actual hours over 5 weekly milestones ->
    Computes Burn Index ((Actual / Planned) * 100%) -> Evaluates burn velocity
    and budget consumption against internal company policy thresholds.
- Workflow 4: Individual Employee Dossier & Welfare Tracking
    Lookup by NPK/Name -> Displays personal hours, HKN vs HLR balance, individual
    Burn Index, departmental peer variance, and safety threshold alerts.
- Workflow 5: Supervised ML Predictive Planning & Anomaly Auditing
    ML models generate overtime demand forecasts from scheduled production
    volumes -> Predict period-end Burn Index and budget deficit risk by mid-month
    -> Forecast CapEx project labor -> Flag anomalous timesheet claims for review.
- Workflow 6: Administration & Policy Configuration
    Admin manages master data, user accounts, company policy threshold parameters,
    calendar classifications, and notification rules.

3. BUSINESS RULES
--------------------------------------------------------------------------------
- BR-01: Decimal Overtime Granularity
    Overtime must be recorded in decimal hours with a minimum step of 0.5 hours.
- BR-02: Employee Assignment Integrity
    Only employees active in the chosen department and section can be added to
    that section's overtime log.
- BR-03: Immutability of Identification
    NPK is a read-only identifier during operational overtime logging.
- BR-04: Category Summation
    An employee's total overtime must strictly equal the sum of their Production,
    TPM, Project (CapEx), and Others hours.
- BR-05: Non-Blocking SPKL Attachment
    Timesheets can be submitted without an SPKL attachment. Missing SPKLs display
    a "Pending SPKL" status and generate passive reminders until attached.
- BR-06: Company Policy Limits (Soft Alerts)
    Overtime safety limits are governed by internal company policy (kebijakan
    perusahaan). Breaches trigger visual warnings and notifications, but do NOT
    hard-block timesheet submission.
- BR-07: Optional Root Cause Tagging
    Root Cause Reason (RCA) selection is optional and non-blocking at entry.
- BR-08: CapEx Project Labor Attribution
    Hours logged under the "Project" category represent capitalized labor and
    must link to a valid CapEx project or asset reference for financial accounting.
- BR-09: Burn Index Governance
    * Under Budget: < 85%
    * On Track: 85% - 100%
    * Warning: 101% - 115%
    * Over Budget: > 115%
- BR-10: Item-Level Approval Independence
    Approvers have the authority to approve or reject individual line items
    within a submission. Rejections require a documented explanation note.

4. CALCULATIONS
--------------------------------------------------------------------------------
- CALC-01: Total Employee Overtime Hours
    Total Hours = Production + TPM + Project (CapEx) + Others
- CALC-02: Burn Index (%)
    Burn Index = (Cumulative Actual Hours / Planned Budget Hours) * 100%
- CALC-03: Remaining Budget Hours
    Remaining Budget = Planned Budget Hours - Cumulative Actual Hours
- CALC-04: Burn Velocity
    Velocity = Cumulative Actual Hours Consumed / Elapsed Weeks in Period
- CALC-05: Departmental Peer Variance
    Variance = Individual Hours - Department Average Hours per Employee
- CALC-06: CapEx Labor Ratio (%)
    CapEx Ratio = (Total Project Hours / Total Overtime Hours) * 100%
- CALC-07: ML Prediction Error Metrics (Model Health)
    MAE = (1/n) * SUM(|Actual - Predicted|)
    MAPE = (100%/n) * SUM(|(Actual - Predicted) / Actual|)

5. USER ROLES & PERMISSIONS (Functional Level)
--------------------------------------------------------------------------------
- Admin:
    Full access across all departments; user account administration; baseline
    budget configuration; company policy threshold configuration; ML model
    monitoring and master data export.
- Manager:
    Department-wide read and approval access; partial and bulk approval authority;
    view budget burndown, CapEx labor splits, ML forecasts, and anomaly alerts.
- Team Leader:
    Create and edit daily overtime submissions for own section; attach SPKL
    documents post-shift; view section-level dashboards and employee timesheets.
- User (Employee):
    Read-only access restricted to individual profile, personal timesheet history,
    personal Burn Index, and peer benchmark variance.

6. OPEN QUESTIONS & ASSUMPTIONS
--------------------------------------------------------------------------------
- Assumption 1: Master organizational data will be standardized across departments
  and production lines, resolving prototype naming discrepancies.
- Assumption 2: SPKL attachments will have a configurable grace period (e.g., 2 working
  days) before escalating to supervisor reminders.
- Assumption 3: Overtime costing will use departmental standard costing rates for
  operational budgeting and CapEx capital allocation.
- Assumption 4: Supervised ML models will train on historical operational and production
  data, with automated fallback to moving averages when training data is sparse.
================================================================================
```
