import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';

import type { SupportedLocale } from '@/types';

export function useDocumentLang() {
    const locale = usePage().props.locale as SupportedLocale;

    useEffect(() => {
        document.documentElement.lang = locale;
    }, [locale]);
}
