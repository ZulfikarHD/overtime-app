# Department & Section Management - User Guide

## What is Department & Section Management?

Department & Section Management allows factory administrators to maintain the organizational structure of the manufacturing plant. In this system, every employee and every overtime request is linked to a specific Department (such as Stamping or Assembly) and Section (such as Press Line or Trim Installation). Keeping this hierarchy accurate ensures that overtime submissions flow to the right supervisors for approval and that labor costs are charged to the correct cost centers.

## How to Use

### Viewing the Organizational Structure

1. Open the **Master Data** menu in the sidebar.
2. Select the **Departments & Sections** tab (this tab is selected by default).
3. The page displays summary cards at the top showing the total number of departments, total sections, and active departments.
4. In the list below, click the arrow icon next to any department to expand or collapse its list of production sections.
5. Use the search bar to quickly find a department or section by name or code, or use the status buttons to filter by Active or Inactive.

### Adding a New Department

1. In the **Master Data** screen, click the **+ Add Department** button at the top right of the table.
2. Fill in the required fields:
    - **Department Code**: A short, unique uppercase identifier (for example, `STP` or `ASY`).
    - **Department Name**: The full name of the department (for example, `Stamping Department`).
    - **Cost Center Code**: The internal accounting code (for example, `CC-STP-101`).
    - **Default Hourly Rate (Rp)**: The standard overtime labor rate per hour in Rupiah. The system displays a live Rupiah preview as you type.
    - **Active Status**: Keep checked for active departments.
3. Click **Save**. The new department will appear immediately in the list with a confirmation notification.

### Adding a Section Under a Department

1. In the **Master Data** screen, locate the parent department in the list.
2. Click the **+ Add Section** button located on that department's row.
3. In the dialog that appears, verify the parent department indicated at the top.
4. Enter the **Section Code** (for example, `PRESS-01`) and **Section Name** (for example, `Transfer Press Line`).
5. Ensure **Active Status** is checked and click **Save**.
6. The section will appear directly nested under its parent department.

### Editing a Department or Section

1. Locate the department or section you want to modify.
2. Click the **Edit** (pencil) icon on that row.
3. Update the name, cost center, default hourly rate, or active status as needed.
    > 💡 **Tip:** In accordance with factory policy, the Code cannot be edited after creation to preserve data integrity across historical timesheets.
4. Click **Save**.

### Deactivating vs. Deleting

- **Deactivating (Recommended)**: If a line or department is temporarily closed, click the status toggle icon to deactivate it. Deactivated departments and sections remain in historical reports but are hidden from new overtime entry forms.
    > ⚠️ **Note:** A department cannot be deactivated while it still has active sections. Deactivate all child sections first.
- **Deleting**: Deletion is only allowed for new departments or sections that have never had registered employees, child sections, or overtime records. If any records are linked, the system safely blocks deletion to prevent payroll data corruption.

## Frequently Asked Questions (FAQ)

**Q: Why can't I edit the Department Code or Section Code?**  
A: Department and Section codes are permanent unique identifiers used by historical timesheets, payroll reports, and budget records. Once created, they cannot be changed. If a name has changed, update the Department Name or Section Name instead.

**Q: Why is the Delete button disabled or blocked for my department?**  
A: To protect financial audit records and avoid orphaned employee records, the system prevents deleting any department that has child sections, registered workers, or past overtime records. You should use **Deactivate** instead.

**Q: Who can access the Master Data menu?**  
A: Only plant personnel with the **Administrator** role can view and make changes to Master Data. Managers, Team Leaders, and Operators do not see this menu.

## Troubleshooting

| Issue                                                                        | Solution                                                                                                                                                 |
| ---------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Error: "Cannot deactivate department because it still has active section(s)" | Click the department arrow to view its child sections, click the status toggle on each child section to deactivate them, then deactivate the department. |
| Error: "Department code has already been registered"                         | Choose a different unique code. Department codes must be unique across the entire factory.                                                               |
| I don't see the Master Data menu in the sidebar                              | Master Data is restricted to users with the Administrator role. Check your role badge on the dashboard or contact your IT/HR administrator.              |
| Rate displays `Rp 0,00`                                                      | Enter a positive number without currency symbols (for example, `35000`) in the Default Hourly Rate field.                                                |
