export type AuthUser = {
    id: number;
    name: string;
    email: string;
};

export type SupportedLocale = 'en' | 'my' | 'zh';

export type TranslationTree = {
    [key: string]: string | TranslationTree;
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
    flash: {
        success: string | null;
        count: number | null;
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
            flash: {
                success: string | null;
                count: number | null;
            };
        };
    }
}
