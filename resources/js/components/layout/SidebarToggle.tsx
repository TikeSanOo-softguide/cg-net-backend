import { ChevronLeftIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

type SidebarToggleProps = {
    expanded: boolean;
    onToggle: () => void;
    label: string;
};

export function SidebarToggle({ expanded, onToggle, label }: SidebarToggleProps) {
    return (
        <button
            type="button"
            onClick={onToggle}
            aria-label={label}
            aria-expanded={expanded}
            aria-controls="app-sidebar"
            className={cn(
                'absolute top-[calc(var(--navbar-height)/2)] right-0 z-[70] flex size-8 -translate-y-1/2 translate-x-1/2 items-center justify-center rounded-full sm:size-7',
                'touch-manipulation bg-primary text-primary-foreground shadow-[0_2px_8px_rgb(23_50_54/0.28)]',
                'transition-colors duration-200 hover:bg-primary-hover',
                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none',
                'dark:bg-[#0c1517] dark:text-white dark:shadow-[0_2px_8px_rgb(0_0_0/0.45)] dark:hover:bg-[#152022]',
            )}
        >
            <ChevronLeftIcon
                className={cn('size-4 transition-transform duration-300 ease-out', !expanded && 'rotate-180')}
                strokeWidth={2.25}
            />
        </button>
    );
}
