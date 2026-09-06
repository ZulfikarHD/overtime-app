export type UserRole = 'admin' | 'manager' | 'team_leader' | 'user';

export type UserDepartment = {
    id: number;
    code: string;
    name: string;
};

export type UserSection = {
    id: number;
    department_id: number;
    code: string;
    name: string;
};

export type User = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    npk?: string | null;
    department_id?: number | null;
    section_id?: number | null;
    is_active?: boolean;
    department?: UserDepartment | null;
    section?: UserSection | null;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
