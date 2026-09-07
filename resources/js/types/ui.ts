export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type SpklNotificationData = {
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

export type AppNotification = {
    id: string;
    type: string;
    data: SpklNotificationData;
    read_at: string | null;
    created_at?: string;
    created_at_human?: string;
};

export type NotificationsPayload = {
    unread_count: number;
    notifications: AppNotification[];
};
