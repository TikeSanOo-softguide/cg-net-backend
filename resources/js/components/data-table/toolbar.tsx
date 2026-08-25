import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

export function ToolbarIconButton({
    label,
    icon: Icon,
    tone = 'primary',
    disabled = false,
    prominent = false,
    href,
    onClick,
}: {
    label: string;
    icon: LucideIcon;
    tone?: 'primary' | 'danger';
    disabled?: boolean;
    prominent?: boolean;
    href?: string;
    onClick?: () => void;
}) {
    const className = cn(
        'inline-flex shrink-0 items-center justify-center rounded-[6px] transition-all duration-200',
        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:outline-none',
        'disabled:pointer-events-none disabled:opacity-50',
        prominent ? 'size-8' : 'size-10',
        tone === 'danger'
            ? 'bg-danger text-[#FFFFFF] hover:bg-danger/12 hover:text-danger'
            : 'bg-primary text-[#FFFFFF] hover:bg-primary/12 hover:text-primary',
    );
    const icon = <Icon className={prominent ? 'size-[18px]' : 'size-4'} strokeWidth={prominent ? 2.5 : 1.85} />;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                {href ? (
                    <Link href={href} aria-label={label} className={className}>
                        {icon}
                    </Link>
                ) : (
                    <button type="button" aria-label={label} disabled={disabled} className={className} onClick={onClick}>
                        {icon}
                    </button>
                )}
            </TooltipTrigger>
            <TooltipContent side="top" className="bg-primary text-primary-foreground">
                {label}
            </TooltipContent>
        </Tooltip>
    );
}

export function ColumnHeaderLabel({
    icon: Icon,
    label,
    className,
}: {
    icon?: LucideIcon;
    label: string;
    className?: string;
}) {
    return (
        <span className={cn('inline-flex items-center gap-1.5', className)}>
            {Icon ? <Icon className="size-3.5 shrink-0 text-muted-foreground transition-colors group-hover/head:text-primary" strokeWidth={1.85} /> : null}
            {label}
        </span>
    );
}
