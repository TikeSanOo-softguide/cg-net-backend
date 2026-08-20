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
            className={cn('size-10 text-foreground', className)}
            onClick={toggleTheme}
            aria-label={t('common.toggle_theme')}
        >
            {theme === 'dark' ? <SunIcon className="size-5" strokeWidth={1.9} /> : <MoonIcon className="size-5" strokeWidth={1.9} />}
        </Button>
    );
}
