import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { MouseEvent } from 'react';

import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type TableActionTone = 'primary' | 'edit' | 'danger' | 'success' | 'neutral';

type TableActionButtonProps = {
    label: string;
    icon: LucideIcon;
    tone?: TableActionTone;
    href?: string;
    onClick?: (event: MouseEvent<HTMLButtonElement>) => void;
    className?: string;
};

const toneClass: Record<TableActionTone, string> = {
    primary: 'bg-primary/12 text-primary hover:bg-primary hover:text-primary-foreground',
    edit: 'bg-primary/12 text-primary hover:bg-primary hover:text-primary-foreground',
    danger: 'bg-danger/12 text-danger hover:bg-danger hover:text-danger-foreground',
    success: 'bg-success/12 text-success hover:bg-success hover:text-success-foreground',
    neutral: 'bg-muted text-muted-foreground hover:bg-foreground hover:text-background',
};

export function TableActionButton({
    label,
    icon: Icon,
    tone = 'primary',
    href,
    onClick,
    className,
}: TableActionButtonProps) {
    const actionClass = cn(
        'inline-flex size-7 shrink-0 items-center justify-center rounded-[6px] leading-none transition-all duration-200 ease-out',
        'hover:scale-105 hover:shadow-sm active:scale-95',
        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:outline-none',
        toneClass[tone],
        className,
    );

    const icon = <Icon className="size-3.5" strokeWidth={1.85} />;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                {href ? (
                    <Link href={href} className={actionClass} aria-label={label} onClick={(event) => event.stopPropagation()}>
                        {icon}
                        <span className="sr-only">{label}</span>
                    </Link>
                ) : (
                    <button type="button" className={actionClass} aria-label={label} onClick={onClick}>
                        {icon}
                        <span className="sr-only">{label}</span>
                    </button>
                )}
            </TooltipTrigger>
            <TooltipContent side="top">{label}</TooltipContent>
        </Tooltip>
    );
}
