import { useState } from 'react';
import { SettingsIcon } from 'lucide-react';

import { ThemeSettingsPanel } from '@/components/theme/ThemeSettingsPanel';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function FloatingThemeSettingsButton() {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                type="button"
                size="icon"
                variant="primary"
                aria-label="Theme settings"
                aria-expanded={open}
                onClick={() => setOpen(true)}
                className={cn(
                    'group fixed right-4 bottom-5 z-[90] size-11 shadow-md sm:right-6 sm:bottom-6',
                    open && 'hidden',
                )}
            >
                <SettingsIcon className="size-5 animate-[theme-spin_3.5s_linear_infinite] group-hover:[animation-play-state:paused] motion-reduce:animate-none" />
            </Button>
            <ThemeSettingsPanel open={open} onOpenChange={setOpen} />
        </>
    );
}
