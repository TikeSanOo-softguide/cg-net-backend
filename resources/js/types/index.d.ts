export type AuthUser = {
    id: number;
    name: string;
    email: string;
};

export type SupportedLocale = 'en' | 'mm' | 'zh';

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: AuthUser | null;
    };
    locale: SupportedLocale;
    translations: Record<string, string>;
    unreadNotifications: number;
    flash: {
        success: string | null;
    };
};

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            auth: {
                user: AuthUser | null;
            };
            locale: SupportedLocale;
            translations: Record<string, string>;
            unreadNotifications: number;
            flash: {
                success: string | null;
            };
        };
    }
}
