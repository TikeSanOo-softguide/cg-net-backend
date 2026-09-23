import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type MetaCellProps = {
    icon: LucideIcon;
    children: ReactNode;
    muted?: boolean;
    className?: string;
};

/** Compact list/detail cell: small icon + truncated text. */
export function MetaCell({ icon: Icon, children, muted = false, className }: MetaCellProps) {
    return (
        <span
            className={cn(
                'inline-flex max-w-full items-center gap-1.5 text-[12px] leading-normal',
                muted ? 'text-muted-foreground' : 'text-foreground',
                className,
            )}
        >
            <Icon className="size-3 shrink-0 self-center opacity-70" strokeWidth={1.85} />
            <span className="min-w-0 break-words whitespace-normal leading-[1.3]">{children}</span>
        </span>
    );
}
