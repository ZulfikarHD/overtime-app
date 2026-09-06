# Employee Roster Management & CSV Import - User Guide

## What is Employee Roster Management?

Employee Roster Management allows plant administrators to maintain the master list of active shop-floor workers across manufacturing departments and production sections. Every worker in the plant is registered with a unique employee number (NPK), assigned to a section, and designated an hourly overtime rate.

Team Leaders and Supervisors rely on this roster when submitting daily overtime requests and timesheets. Maintaining an up-to-date roster ensures that overtime hours are correctly credited, verified, and costed according to plant labor rate standards.

---

## How to Use

### Viewing the Employee Roster

1. Click **Master Data** in the sidebar.
2. Select the **Employees** tab at the top of the page.
3. Summary cards at the top show:
    - **Total Registered Employees**: All active and inactive employees in the plant.
    - **Active Roster Workers**: Employees currently available for overtime assignments.
    - **Inactive Employees**: Deactivated workers retained for historical records.
4. Below the cards, the employee table displays 25 workers per page with their NPK, Full Name, Department, Section, Job Position, Hourly Rate, and Status.
5. Use the pagination bar at the bottom to navigate between pages.

### Searching and Filtering Workers

- **Search Bar**: Type any part of an employee's NPK (e.g. `10023`), full name (e.g. `Budi`), or section name into the search box. The table updates automatically as you type.
- **Department Filter**: Click the department dropdown to view workers belonging to a specific department.
- **Status Filter**: Click **All Status**, **Only Active**, or **Only Inactive** to filter workers by operational status.

### Adding a New Employee

1. In the **Master Data** screen on the **Employees** tab, click **+ Add Employee**.
2. A side panel will slide in from the right.
3. Fill in the required fields:
    - **NPK (Employee Number)**: Enter the unique uppercase factory employee ID (e.g. `EMP-10023`).
    - **Full Name**: Enter the employee's full official name.
    - **Department**: Choose the parent department (e.g. `Production Department`).
    - **Section**: Select the specific work section. The list of sections dynamically updates based on the selected department.
    - **Job Position**: Enter the employee's role (e.g. `Line Operator`, `Team Leader`, `Die Specialist`).
    - **Hourly Rate (Rp)**: _(Optional)_ Enter a custom hourly labor rate if this worker has a special rate. Leave this field empty to automatically apply the department's standard default rate.
    - **Active Status**: Keep checked to make the worker immediately available on timesheets.
4. Click **Save Employee**. A confirmation message will appear and the worker will be added to the roster.

### Editing an Employee (NPK Protection Rule)

1. Find the employee you wish to update in the roster table.
2. Click the **Edit** (pencil) icon in the **Actions** column on that row.
3. Update the Full Name, Department, Section, Job Position, Hourly Rate, or Active Status.
    > 🔒 **Policy Rule (BR-03):** To prevent payroll discrepancies and maintain compliance with plant historical audits, the **NPK cannot be modified after registration**. If an NPK was entered incorrectly during registration, delete the record before timesheets are created, or deactivate it and create a new record.
4. Click **Save Employee**.

### Deactivating vs. Deleting Employees

- **Deactivating (Recommended)**: If an employee leaves the company or moves to another role, click the status toggle icon (checkmark/cross) and confirm deactivation. Deactivated workers are hidden from Team Leaders on new overtime forms, but all their previous overtime records and payroll reports remain intact.
- **Deleting**: Deletion is only allowed for new employee entries that have never participated in any overtime submissions. If an employee has even a single recorded overtime item, the system prevents deletion to safeguard audit compliance.

---

## Bulk CSV Import with Live Audit

Instead of registering workers one-by-one, administrators can import hundreds of employees at once using a CSV file.

### Step 1: Download the Standard Template

1. In the **Employees** tab, click the **Import CSV** button.
2. In the side drawer, click **Download Template**.
3. Open the downloaded CSV file in Excel or any spreadsheet editor. Ensure the column headers remain exactly as provided:
   `npk,full_name,department_code,section_code,job_position,hourly_rate`

### Step 2: Fill in Employee Data

- **npk**: Unique employee number (e.g. `EMP-20001`).
- **full_name**: Official worker name.
- **department_code**: The uppercase code of the department (e.g. `PROD`, `MAINT`).
- **section_code**: The code of the section belonging to that department (e.g. `STP`, `ASSY`).
- **job_position**: Job title or role.
- **hourly_rate**: Numeric rate in Rupiah without currency symbols (e.g. `38000`), or leave blank to use the department rate.

### Step 3: Drag & Drop to Preview Audit

1. Drag and drop your `.csv` file into the upload zone or click to select the file.
2. The system instantly performs an **in-memory dry run audit** without modifying the database.
3. Review the preview metrics:
    - **Total Rows**: Total entries in the file.
    - **Valid Rows** _(Green)_: Rows ready for import.
    - **Error Rows** _(Red)_: Entries with issues (e.g. duplicate NPKs, invalid section codes).
4. Hover or view error rows to see specific explanations (e.g. _"NPK already registered on system"_ or _"Section code not found under specified department"_).

### Step 4: Confirm Import

1. If errors are present, only valid rows will be committed. You may either proceed with valid rows or fix the file and re-upload.
2. Click **Confirm & Import Employees**.
3. All validated workers are registered simultaneously in the database and appear immediately in the roster.

---

## Frequently Asked Questions (FAQ)

**Q: Can I change an employee's NPK if they change job roles?**  
A: No. Under factory policy (Rule BR-03), an employee's NPK is permanently bound to their profile to guarantee continuity in historical timesheet and payroll audit logs. If their section, department, or job title changes, update those fields while keeping the NPK unchanged.

**Q: What happens if I leave the Hourly Rate field blank?**  
A: The system automatically inherits the default hourly rate configured for the employee's parent department. In the roster table, it will display the department rate marked with `(Dept Default)`.

**Q: Can I delete an employee who has resigned?**  
A: If the employee has recorded overtime hours in the past, the system blocks deletion to protect audit records. Use **Deactivate** instead; the worker will no longer appear for new overtime submissions.

**Q: What should I do if my CSV import reports section errors?**  
A: Verify that the `section_code` in your CSV belongs to the `department_code` in the same row. You can check valid codes under the **Departments & Sections** tab in Master Data.

---

## Troubleshooting

| Problem                                                           | Cause                                                           | Solution                                                                       |
| ----------------------------------------------------------------- | --------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| "Employee NPK has already been registered"                        | An employee with the exact NPK already exists.                  | Verify the NPK in the search bar. NPKs must be unique across the entire plant. |
| "The selected section does not belong to the selected department" | The chosen section is registered under a different department.  | Select the appropriate department first, then pick from its child sections.    |
| CSV import flags "Duplicate in CSV file"                          | The same NPK was entered on multiple lines in your spreadsheet. | Remove duplicate rows in your spreadsheet before uploading.                    |
| Delete button is disabled                                         | The employee has historical overtime records.                   | Click the status toggle button to deactivate the employee instead of deleting. |
