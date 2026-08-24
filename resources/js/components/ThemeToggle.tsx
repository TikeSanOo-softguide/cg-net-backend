import { MoonIcon, SunIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { useTheme } from '@/providers/ThemeProvider';
import { cn } from '@/lib/utils';

export function ThemeToggle({ className }: { className?: string }) {
    const { theme, toggleTheme } = useTheme();
    const { t } = useTranslation();

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className={cn(
                'group size-10 text-primary hover:bg-primary/12 hover:text-primary active:bg-primary/18',
                'dark:text-foreground dark:hover:bg-muted dark:hover:text-foreground dark:active:bg-muted/80',
                'transition-all duration-200 ease-out hover:scale-105 active:scale-95',
                'motion-reduce:transition-colors motion-reduce:hover:scale-100 motion-reduce:active:scale-100',
                className,
            )}
            onClick={toggleTheme}
            aria-label={t('common.toggle_theme')}
        >
            {theme === 'dark' ? (
                <SunIcon
                    className="size-5 transition-transform duration-200 ease-out group-hover:scale-110 group-hover:rotate-45 motion-reduce:transition-none motion-reduce:group-hover:scale-100 motion-reduce:group-hover:rotate-0"
                    strokeWidth={1.9}
                />
            ) : (
                <MoonIcon
                    className="size-5 transition-transform duration-200 ease-out group-hover:scale-110 group-hover:-rotate-12 motion-reduce:transition-none motion-reduce:group-hover:scale-100 motion-reduce:group-hover:rotate-0"
                    strokeWidth={1.9}
                />
            )}
        </Button>
    );
}
