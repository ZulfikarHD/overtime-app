# Overtime Budget Planning - User Guide

## What is Overtime Budget Planning?

Overtime Budget Planning allows Plant Administrators and Department Managers to set monthly overtime hour allocations for each production section. Setting these targets provides the foundation for the plant-wide Overtime Burn Index, enabling department heads to monitor whether overtime spending stays within safe operational limits.

## Who Can Use This Feature?

- **Plant Administrators**: Can view and configure budget targets for all active manufacturing departments and sections.
- **Department Managers**: Can view and configure targets for sections within their assigned department.
- **Team Leaders & Operators**: Do not have access to budget planning and will not see the menu item in the sidebar.

## How to Use

### Viewing the Budget Planning Matrix

1. Click **Budget Planning** in the left sidebar menu.
2. Use the filter bar at the top to select:
    - **Fiscal Year**: Select the target year (e.g., 2026).
    - **Fiscal Month**: Select the target month (e.g., September).
    - **Department**: (Administrators only) Choose which department to review. Managers are automatically locked to their assigned department.
3. Review the top KPI summary cards:
    - **Total Planned Hours**: The sum of planned overtime hours across all sections in the department.
    - **Estimated Overtime Cost**: Total estimated labor cost calculated in Rupiah (IDR) using the department's standard hourly rate.
    - **Section Coverage Configured**: The number and percentage of sections that have a formal budget target set for this month.
4. Review the sections table below to see the status of each section (`Configured` in green or `Not Configured` in amber).

### Setting or Editing a Section Budget

1. In the sections table, find the section you wish to update.
2. Click **Set Budget** (or **Edit Budget** if an allocation already exists).
3. A drawer panel will slide in from the right:
    - **Monthly Target Hours**: Enter the total overtime hours planned for the month (e.g., `120`).
    - Notice the **Estimated Overtime Cost** card updates automatically in Rupiah based on the department's hourly rate.
4. (Optional) Check **Configure 5-Week Breakdown (Optional)** to review weekly distributions:
    - By default, the system automatically divides the monthly target evenly across 4.3 weeks.
    - You can manually adjust hours for Week 1 through Week 5.
    - If your weekly sum does not equal the monthly total, an amber advisory badge will appear. This advisory message will not prevent you from saving, allowing flexible shift scheduling during peak maintenance or holiday periods.
5. Click **Save Budget**.
6. The drawer will close and the sections table will immediately reflect the updated hours and weekly breakdown badges.

### Importing Budgets via CSV

When planning across many sections or multiple months, you can upload a CSV spreadsheet using the two-stage safety import:

1. Click **Budget Planning** in the left sidebar.
2. In the top right corner, click **Import CSV**.
3. A drawer panel will slide in from the right:
    - If you do not have a spreadsheet yet, click **Download CSV Template** to download an official template with sample data.
4. Drag and drop your `.csv` file into the upload box (or click to browse your computer).
5. The system performs **Stage 1: Pre-Commit Validation**:
    - It checks that every section code exists and belongs to your department.
    - It checks that fiscal year (2020-2050), month (1-12), and target hours are valid.
    - The summary shows **Total Rows**, **Ready to Import** (green), and **Has Errors** (red).
    - An audit table displays each line with a status indicator and specific error descriptions if any line has an issue.
6. Once satisfied, click **Confirm & Run Import** (**Stage 2: Commit**).
7. The verified rows are imported into the database, and a success notification will appear.

> 💡 **Tip for Managers:** You can only import budgets for sections that belong to your own department. If your spreadsheet contains rows for sections in other departments, the audit preview will flag those rows with an error and prevent them from being imported.
