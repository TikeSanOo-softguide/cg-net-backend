import { usePage } from '@inertiajs/react';

import type { SupportedLocale, TranslationTree } from '@/types';

function lookup(tree: TranslationTree | string | undefined, key: string): string | undefined {
    const value = key.split('.').reduce<TranslationTree | string | undefined>((current, part) => {
        if (current == null || typeof current === 'string') {
            return undefined;
        }

        return current[part];
    }, tree);

    return typeof value === 'string' ? value : undefined;
}

export function useTranslation() {
    const { translations, locale } = usePage().props;

    const t = (key: string): string => lookup(translations, key) ?? key;

    return { t, locale: locale as SupportedLocale };
}
