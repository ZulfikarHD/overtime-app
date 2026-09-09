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

export type NotificationData =
    | SpklNotificationData
    | BudgetThresholdNotificationData
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
