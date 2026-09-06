# Policy Threshold Configuration - User Guide

## What is Policy Threshold Configuration?

Policy Threshold Configuration allows factory administrators to set the operational rules, safety limits, and warning levels for overtime across the manufacturing plant. This includes setting how many overtime hours an employee can work per week before triggering fatigue alerts, how many days supervisors have to submit signed physical SPKL forms, and at what percentage of monthly overtime budget consumption warning alarms should appear.

The system uses a **Plant-wide Default** baseline that applies to all departments automatically, while allowing administrators to define custom **Department Overrides** for specialized production units that require different limits.

## How to Use

### Viewing Current Policies and Department Status

1. Open the **Administration** menu in the sidebar.
2. The **Policy Thresholds** tab is selected by default.
3. Review the **Plant-wide Default Policy** card at the top to see current factory baseline standards.
4. Review the summary counters to see how many departments use the baseline standard versus custom overrides.
5. In the **Department Overrides** table below, browse all plant departments to check their current limits and whether they inherit the plant default or use a custom override.
6. Use the search bar to filter by department name or code, or click the status buttons to filter by **All**, **Custom Overrides**, or **Plant Default**.

### Editing the Plant-wide Default Policy

1. In the **Administration** screen, locate the **Plant-wide Default Policy** card at the top.
2. Click the **Edit Plant Default** button.
3. A drawer will slide in from the right. Adjust the baseline values:
    - **Weekly Soft Limit (Hours)**: The maximum overtime hours per week before employee fatigue warnings trigger (standard: 20 hours).
    - **Consecutive Weeks Alert (Weeks)**: The number of consecutive weeks with overtime before a welfare review is required (standard: 3 weeks).
    - **SPKL Grace Period (Days)**: The number of days allowed between overtime execution and physical SPKL form submission (standard: 2 days).
    - **Burn Warning Percentage (%)**: The budget consumption level that triggers an amber warning (standard: 100%).
    - **Burn Danger Percentage (%)**: The budget consumption level that triggers a red critical alarm (standard: 115%).
4. Click **Save Policy**. All departments inheriting the baseline standard will immediately reflect the new settings.

> 💡 **Tip:** Policy changes apply to newly submitted overtime and upcoming budget calculations. Past approved timesheets are never changed retroactively.

### Adding a Custom Department Override

1. In the **Administration** screen, click the **+ Add Department Override** button above the table (or click **Override** on any department row currently inheriting the default).
2. In the drawer that appears, select the target department from the dropdown.
3. Specify the custom threshold values for that department.
4. Verify that the **Burn Danger Percentage** is equal to or higher than the **Burn Warning Percentage**.
5. Click **Save Policy**.
6. The department's status in the table will update to **Custom Override Active**.

### Editing or Deleting a Department Override

- **Editing**: Find the department in the table and click the **Edit** (pencil) icon on its row. Adjust the numbers and click **Save Policy**.
- **Deleting / Reverting to Default**: If a department no longer requires custom limits, click the **Delete** (red trash can) icon on its row. A confirmation dialog will appear. Click **Yes, Delete Override**. The department will immediately revert to inheriting the plant-wide default policy.

## Frequently Asked Questions (FAQ)

**Q: What happens if I update the SPKL Grace Period from 2 days to 3 days?**  
A: The new 3-day deadline will apply to all overtime requests created after the change is saved. Overtime requests submitted prior to the change maintain their original calculated deadlines.

**Q: Can I delete the Plant-wide Default Policy?**  
A: No. The plant-wide default is the foundational baseline for the entire factory and cannot be deleted. You can edit its values anytime to adjust plant standards.

**Q: Why does the system prevent me from saving when Burn Danger is lower than Burn Warning?**  
A: The Burn Danger threshold represents a critical alert level and mathematically must be greater than or equal to the Burn Warning alert level (for example, 100% warning and 115% danger).

**Q: Who can view and configure policy thresholds?**  
A: Only personnel with the **Administrator** role can view and modify settings in the **Administration** workspace. Managers, Team Leaders, and Operators cannot access this screen.

## Troubleshooting

| Issue                                              | Solution                                                                                                          |
| -------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| The Save button is disabled in the override drawer | Ensure a department is selected and that the Burn Danger percentage is not less than the Burn Warning percentage. |
| A department is missing from the override dropdown | Check that the department is active in Master Data and does not already have an existing custom override.         |
| An override was accidentally deleted               | Click **Override** on that department's row to reconfigure custom limits anytime.                                 |
