import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import { CheckIcon, LanguagesIcon } from 'lucide-react';

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
                    className={cn(
                        'h-10 gap-2 rounded-full border border-primary/15 bg-surface/80 px-1.5 pr-3 text-foreground shadow-[0_8px_24px_rgb(23_50_54/0.08)] backdrop-blur-md',
                        'hover:border-warning/50 hover:bg-surface',
                        compact && 'size-10 pr-1.5',
                        className,
                    )}
                    aria-label={t('common.language')}
                >
                    <span className="flex size-7 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-[inset_0_1px_0_rgb(255_255_255/0.18)]">
                        <LanguagesIcon className="size-3.5" strokeWidth={1.8} />
                    </span>
                    {! compact ? (
                        <span className="pr-1 text-[11px] font-semibold tracking-[0.18em] uppercase">
                            {current.code}
                        </span>
                    ) : null}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                side="bottom"
                className="min-w-48 rounded-[12px] border-primary/10 p-1.5 shadow-[0_18px_50px_rgb(23_50_54/0.16)]"
            >
                {locales.map((entry) => (
                    <DropdownMenuItem
                        key={entry.code}
                        onSelect={() => switchLocale(entry.code)}
                        className="rounded-[10px] gap-2.5"
                    >
                        <span
                            className={cn(
                                'flex size-7 items-center justify-center rounded-full text-[10px] font-semibold tracking-[0.12em] uppercase',
                                entry.code === locale
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground',
                            )}
                        >
                            {entry.code}
                        </span>
                        <span className="flex-1">{entry.label}</span>
                        {entry.code === locale ? <CheckIcon className="size-4 text-warning" /> : null}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
