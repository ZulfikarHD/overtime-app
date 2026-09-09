export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type SpklNotificationData = {
    notification_type?: 'spkl_reminder';
    submission_id: number;
    submission_code: string;
    section_id: number;
    section_name: string;
    operational_date: string;
    due_date: string;
    overdue_days: number;
    reminder_type: 'overdue' | 'predue';
    title: string;
    message: string;
    spkl_document_id: number;
    total_hours: number;
};

export type BudgetThresholdNotificationData = {
    notification_type: 'budget_threshold';
    alert_level: 'warning' | 'danger';
    section_id: number;
    section_name: string;
    section_code: string;
    department_id: number;
    department_name: string;
    burn_index_pct: number;
    threshold_pct: number;
    fiscal_year: number;
    fiscal_month: number;
    title: string;
    message: string;
    url: string;
};

export type FatigueAlertNotificationData = {
    notification_type: 'fatigue_alert';
    alert_level: 'danger' | 'warning';
    employee_id: number;
    employee_npk: string;
    employee_name: string;
    section_id: number;
    section_name: string;
    department_id: number;
    department_name: string;
    consecutive_weeks: number;
    weekly_hours: number;
    weekly_limit: number;
    fiscal_year: number;
    fiscal_month: number;
    title: string;
    message: string;
    url: string;
};

export type CapexBurnAlertNotificationData = {
    notification_type: 'capex_burn_alert';
    project_id: number;
    project_code: string;
    project_name: string;
    department_id: number;
    department_name: string;
    burn_index_pct: number;
    allocated_hours: number;
    consumed_hours: number;
    title: string;
    message: string;
    url: string;
};

export type WelfareRollingWeekItem = {
    week_key: string;
    week_label: string;
    start_date: string;
    end_date: string;
    hours: number;
    is_over_limit: boolean;
    is_current_week: boolean;
};

export type WelfareBadge = {
    type: 'safe' | 'warning' | 'danger';
    label: string;
    message: string;
};

export type WelfareStatusData = {
    employee_id: number;
    current_week_hours: number;
    weekly_limit: number;
    consecutive_weeks: number;
    consecutive_weeks_alert: number;
    exceeded_weeks_count: number;
    safety_score_pct: number;
    alert_level: 'safe' | 'warning' | 'danger';
    badges: WelfareBadge[];
    rolling_weeks: WelfareRollingWeekItem[];
    is_advisory: boolean;
    advisory_message: string;
};

export type NotificationData =
    | SpklNotificationData
    | BudgetThresholdNotificationData
    | FatigueAlertNotificationData
    | CapexBurnAlertNotificationData
    | Record<string, any>;

export type AppNotification = {
    id: string;
    type: string;
    data: NotificationData;
    read_at: string | null;
    created_at?: string;
    created_at_human?: string;
};

export type NotificationsPayload = {
    unread_count: number;
    notifications: AppNotification[];
};
