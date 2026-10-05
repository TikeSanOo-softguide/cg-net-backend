export type AuthUser = {
    id: number;
    username: string;
};

export type SupportedLocale = 'en' | 'my' | 'zh';

export type TranslationTree = {
    [key: string]: string | TranslationTree;
};

export type AdminNotification = {
    id: number;
    type: 'request' | 'security' | 'other';
    title: string;
    message: string;
    reference_type:
        | 'installation_application'
        | 'change_password_request'
        | 'change_plan_request'
        | 'failure_report'
        | 'relocation_request'
        | null;
    reference_id: number | null;
    read_at: string | null;
    created_at: string;
    category: 'request' | 'security' | 'other';
    href: string | null;
};

export type CustomNotification = {
    id: number;
    source: 'custom';
    title: string;
    body: string;
    category: 'service_update' | 'account' | 'promotion';
    is_read: boolean;
    time: string;
};

export type RecentNotification = AdminNotification | CustomNotification;

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: AuthUser | null;
        permissions: string[];
        roles: string[];
        is_super_admin: boolean;
    };
    locale: SupportedLocale;
    translations: TranslationTree;
    unreadNotifications: number;
    recentNotifications: RecentNotification[];
    flash: {
        success: string | null;
        error: string | null;
        count: number | null;
        token: string | null;
    };
};

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            auth: {
                user: AuthUser | null;
                permissions: string[];
                roles: string[];
                is_super_admin: boolean;
            };
            locale: SupportedLocale;
            translations: TranslationTree;
            unreadNotifications: number;
            recentNotifications: RecentNotification[];
            flash: {
                success: string | null;
                error: string | null;
                count: number | null;
                token: string | null;
            };
        };
    }
}
