export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Branch = {
    id: number;
    name: string;
    display_name?: string;
    code: string;
    warehouse_name?: string | null;
    warehouse_code?: string | null;
    city: string | null;
    is_headquarters: boolean;
    is_active: boolean;
};

export type Auth = {
    user: User;
    activeBranch: Branch | null;
    branches: Branch[];
    canViewAllBranches: boolean;
    roles: string[];
};

export type OperationalAlert = {
    id: string;
    type: 'critical' | 'high' | 'medium' | 'info';
    title: string;
    message: string;
    timestamp: string;
    href: string;
    actionText: string;
    isRead: boolean;
};

export type SharedData = {
    name: string;
    auth: Auth;
    operationalAlerts: OperationalAlert[];
    sidebarOpen: boolean;
    vapidPublicKey: VapidPublicKey;
    [key: string]: unknown;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};

/** Estado de permiso de notificaciones del navegador */
export type NotificationPermission = 'default' | 'granted' | 'denied';

/** Shared prop inyectado por HandleInertiaRequests */
export type VapidPublicKey = string;
