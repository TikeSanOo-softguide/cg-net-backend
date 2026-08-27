import { router, usePage } from '@inertiajs/react';
import { CheckIcon, LogOutIcon, MoonIcon, MoreHorizontalIcon, SunIcon, UserRoundIcon } from 'lucide-react';

import LanguageSwitcher from '@/components/LanguageSwitcher';
import { NotificationDropdown } from '@/components/layout/NotificationDropdown';
import { ThemeToggle } from '@/components/ThemeToggle';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/useTranslation';
import { resolvePageTitle } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { useTheme } from '@/providers/ThemeProvider';
import type { SupportedLocale } from '@/types';

const locales: { code: SupportedLocale; label: string }[] = [
    { code: 'en', label: 'English' },
    { code: 'my', label: 'မြန်မာ' },
    { code: 'zh', label: '中文' },
];

type TopBarProps = {
    sidebarOpen?: boolean;
};

const navbarActionClass =
    'text-primary hover:bg-primary/12 hover:text-primary active:bg-primary/18 dark:text-foreground dark:hover:bg-muted dark:hover:text-foreground dark:active:bg-muted/80 transition-all duration-200 ease-out hover:scale-105 active:scale-95 motion-reduce:transition-colors motion-reduce:hover:scale-100 motion-reduce:active:scale-100';

export function TopBar({ sidebarOpen = false }: TopBarProps) {
    const { t, locale } = useTranslation();
    const { theme, toggleTheme } = useTheme();
    const page = usePage();
    const { auth, unreadNotifications } = page.props;
    const user = auth?.user;
    const title = resolvePageTitle(t, page.url.split('?')[0]);
    const unread = unreadNotifications ?? 0;

    const logout = () => {
        router.post('/logout');
    };

    const switchLocale = (nextLocale: SupportedLocale) => {
        if (nextLocale === locale) {
            return;
        }

        localStorage.setItem('locale', nextLocale);
        router.post(
            `/locale/${nextLocale}`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['locale', 'translations', 'flash'],
            },
        );
    };

    return (
        <header className="app-navbar sticky top-0 z-[60] shrink-0 shadow-navbar">
            <div className="flex h-[var(--navbar-height)] items-center justify-between gap-2 px-3 sm:px-5">
                <div className="flex min-w-0 items-center gap-2.5">
                    <h1 className="truncate font-heading text-sm font-medium tracking-tight text-primary/70 sm:text-[15px]">
                        {title}
                    </h1>
                </div>

                <div className="flex items-center gap-0.5">
                    <NotificationDropdown unread={unread} />

                    <div className="hidden items-center gap-0.5 sm:flex">
                        <LanguageSwitcher />
                        <ThemeToggle />
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="sm" className={cn('group h-10 gap-2 px-2', navbarActionClass)}>
                                    <span className="flex size-7 items-center justify-center rounded-full bg-primary/15 text-primary transition-transform duration-200 ease-out group-hover:scale-110 motion-reduce:transition-none motion-reduce:group-hover:scale-100 dark:bg-muted dark:text-foreground">
                                        <UserRoundIcon className="size-4" strokeWidth={1.9} />
                                    </span>
                                    <span className="hidden max-w-40 truncate text-sm font-medium lg:inline">{user?.name ?? 'Staff'}</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" side="bottom" className="w-64">
                                <DropdownMenuLabel className="px-2.5 py-2 font-normal">
                                    <div className="flex items-center gap-2.5">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary dark:bg-muted dark:text-foreground">
                                            <UserRoundIcon className="size-4.5" strokeWidth={1.9} />
                                        </span>
                                        <div className="flex min-w-0 flex-col gap-0.5">
                                            <span className="truncate text-sm font-semibold">{user?.name ?? 'Staff'}</span>
                                            <span className="truncate font-mono text-xs text-muted-foreground">{user?.email ?? ''}</span>
                                        </div>
                                    </div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem disabled>
                                    <UserRoundIcon className="size-4" />
                                    {t('common.profile')}
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={logout} variant="destructive">
                                    <LogOutIcon className="size-4" />
                                    {t('common.logout')}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button type="button" variant="ghost" size="icon" className="size-10 text-foreground sm:hidden" aria-label={t('menu.more')}>
                                <MoreHorizontalIcon className="size-5" strokeWidth={1.9} />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" side="bottom" className="w-64">
                            <DropdownMenuLabel>{user?.name ?? 'Staff'}</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                                {t('common.language')}
                            </DropdownMenuLabel>
                            {locales.map((entry) => (
                                <DropdownMenuItem key={entry.code} onSelect={() => switchLocale(entry.code)}>
                                    <span className="flex-1">{entry.label}</span>
                                    {entry.code === locale ? <CheckIcon className="size-4 text-foreground" /> : null}
                                </DropdownMenuItem>
                            ))}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onSelect={(event) => {
                                    event.preventDefault();
                                    toggleTheme();
                                }}
                            >
                                {theme === 'dark' ? <SunIcon className="size-4" /> : <MoonIcon className="size-4" />}
                                {t('common.toggle_theme')}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem disabled>
                                <UserRoundIcon className="size-4" />
                                {t('common.profile')}
                            </DropdownMenuItem>
                            <DropdownMenuItem onSelect={logout} variant="destructive">
                                <LogOutIcon className="size-4" />
                                {t('common.logout')}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </header>
    );
}
