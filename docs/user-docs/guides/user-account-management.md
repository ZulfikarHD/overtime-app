# User Account Management - User Guide

## What is User Account Management?

User Account Management allows factory administrators to maintain system login accounts, define role-based access permissions, assign department/section scopes, and manage credentials.

The system enforces four distinct roles matching factory operational hierarchies:

- **Administrator (`Admin`)**: Full authority over all plant master data, policy thresholds, user accounts, and overtime records.
- **Manager (`Manager`)**: Department-level verification, overtime approvals, and monthly overtime budget planning.
- **Team Leader (`Team Leader`)**: Section-level overtime entry, employee roster selection, and physical SPKL document attachment.
- **Operator / User (`Operator`)**: Read-only personal timesheet reviews, profile details, and UI preferences.

## How to Use

### Accessing User Accounts

1. Click the **Administration** menu in the main sidebar.
2. Select the **User Accounts** tab at the top of the workspace.
3. Review summary counters:
    - **Total Accounts**: Total registered system users.
    - **Active Logins**: Users currently permitted to authenticate.
    - **Deactivated**: Accounts blocked from signing in.
    - **Role Breakdown**: Distribution across Admins, Managers, and Team Leaders.

### Adding a New User Account

1. In the **User Accounts** tab, click **+ Add User Account** (top right).
2. In the slide-in drawer:
    - **Full Name**: Enter employee's full name (e.g., `Agus Supriyanto`).
    - **Email Address**: Enter unique corporate login email (e.g., `agus.stamping@plant.local`).
    - **Password**: Enter an initial password (minimum 8 characters).
    - **System Role**: Select `Administrator`, `Manager`, `Team Leader`, or `Operator`. The box below explains the role's permissions.
    - **Assigned Department & Section**: Optional; choose to restrict overtime visibility to specific production lines.
    - **Employee NPK**: Optional; link to the employee's roster record.
    - **Account Active**: Checked by default.
3. Click **Save Account**.

### Searching and Filtering Users

- **Search**: Enter a name, email, or NPK in the search bar and press Enter or click **Filter**.
- **Role Filters**: Click role pills (**All Roles**, **Admin**, **Manager**, **Team Leader**, **Operator**) to filter the list instantly.
- **Status Filters**: Click **All Status**, **Active**, or **Inactive**.
- **Department Filter**: Use the department dropdown to view accounts assigned to a specific department.

### Editing an Account & Role Promotion

1. Locate the user in the table and click the **Edit** (pencil) icon.
2. Update name, email, role, or departmental assignments.
3. Leave the **Password** field empty if the user does not need their password changed.
4. Click **Save Account**.

> 🔒 **Self-Protection Note:** When editing your own logged-in account, the **Role** and **Account Active** controls are locked to protect against accidental administrator lockout.

### Deactivating or Activating an Account

1. Click the toggle icon on any user row (amber **UserX** icon to deactivate, green **UserCheck** icon to activate).
2. Read the confirmation dialog explaining the operational impact:
    - Deactivated users cannot log into the system, but their past overtime submissions and historical records remain intact.
3. Click **Yes, Deactivate Account** or **Yes, Activate Account**.

### Sending a Password Reset Link

1. Click the blue **Key** icon on the user's row.
2. Review the confirmation dialog showing the recipient email.
3. Click **Send Reset Link**. A secure password reset link valid for 60 minutes will be emailed to the user.

### Deleting a User Account

- If a user has never submitted or approved overtime, the red **Trash** icon is available.
- Click **Trash** and confirm in the dialog to permanently delete the account.
- If a user has historical overtime submissions or reviews, the delete button is hidden for data integrity. Use **Deactivate Account** instead.

## Frequently Asked Questions (FAQ)

**Q: Can I deactivate my own administrator account?**  
A: No. The system strictly blocks self-deactivation and self-role demotion to ensure the plant always retains an active administrator.

**Q: What happens to historical overtime records if a Team Leader leaves the company?**  
A: Deactivating the user account prevents them from signing in, but all past overtime entries, timestamps, and approved items remain fully preserved in system audits and reports.

**Q: Can a Manager create or delete users?**  
A: No. User management is restricted exclusively to the **Administrator** role.
