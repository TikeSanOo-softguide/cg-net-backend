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
    };
    locale: SupportedLocale;
    translations: TranslationTree;
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
            translations: TranslationTree;
            unreadNotifications: number;
            flash: {
                success: string | null;
            };
        };
    }
}
