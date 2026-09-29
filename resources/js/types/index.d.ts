export type AuthUser = {
    id: number;
    username: string;
};

export type SupportedLocale = 'en' | 'my' | 'zh';

export type TranslationTree = {
    [key: string]: string | TranslationTree;
};

export type RecentNotification = {
    id: number;
    title: string;
    body: string;
    category: 'service_update' | 'account' | 'promotion';
    is_read: boolean;
    time: string;
};

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
