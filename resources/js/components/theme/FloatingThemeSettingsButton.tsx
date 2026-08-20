import { useState } from 'react';
import { SettingsIcon } from 'lucide-react';

import { ThemeSettingsPanel } from '@/components/theme/ThemeSettingsPanel';
import { Button } from '@/components/ui/button';

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
                className="group fixed right-5 bottom-20 z-50 size-11 rounded-full shadow-md sm:right-6 sm:bottom-6"
            >
                <SettingsIcon className="size-5 animate-[theme-spin_3.5s_linear_infinite] group-hover:[animation-play-state:paused] motion-reduce:animate-none" />
            </Button>
            <ThemeSettingsPanel open={open} onOpenChange={setOpen} />
        </>
    );
}
