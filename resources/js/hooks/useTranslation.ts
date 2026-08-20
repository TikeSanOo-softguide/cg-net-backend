import { usePage } from '@inertiajs/react';

import type { SupportedLocale } from '@/types';

export function useTranslation() {
    const { translations, locale } = usePage().props;

    const t = (key: string): string => translations[key] ?? key;

    return { t, locale: locale as SupportedLocale };
}
