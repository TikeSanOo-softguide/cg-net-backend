import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import { CheckIcon, GlobeIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/useTranslation';
import type { SupportedLocale } from '@/types';
import { cn } from '@/lib/utils';

const LOCALE_STORAGE_KEY = 'locale';

const locales: { code: SupportedLocale; label: string }[] = [
    { code: 'en', label: 'English' },
    { code: 'mm', label: 'မြန်မာ' },
    { code: 'zh', label: '中文' },
];

export default function LanguageSwitcher({ className, compact = false }: { className?: string; compact?: boolean }) {
    const { locale, t } = useTranslation();

    useEffect(() => {
        const stored = localStorage.getItem(LOCALE_STORAGE_KEY) as SupportedLocale | null;

        if (stored && locales.some((entry) => entry.code === stored) && stored !== locale) {
            switchLocale(stored, false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const switchLocale = (nextLocale: SupportedLocale, persist = true) => {
        if (nextLocale === locale) {
            return;
        }

        if (persist) {
            localStorage.setItem(LOCALE_STORAGE_KEY, nextLocale);
        }

        router.post(
            `/locale/${nextLocale}`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['locale', 'translations'],
            },
        );
    };

    const current = locales.find((entry) => entry.code === locale) ?? locales[0];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size={compact ? 'icon' : 'sm'}
                    className={cn('h-10 gap-2 rounded-[8px] text-sm text-foreground', compact && 'size-10', className)}
                    aria-label={t('common.language')}
                >
                    <GlobeIcon className="size-5" strokeWidth={1.9} />
                    {! compact ? <span className="hidden lg:inline">{current.label}</span> : null}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" side="bottom" className="min-w-48">
                {locales.map((entry) => (
                    <DropdownMenuItem key={entry.code} onSelect={() => switchLocale(entry.code)}>
                        <span className="flex-1">{entry.label}</span>
                        {entry.code === locale ? <CheckIcon className="size-4 text-primary" /> : null}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
