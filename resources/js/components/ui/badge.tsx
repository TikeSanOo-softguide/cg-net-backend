import * as React from 'react';

import { cn } from '@/lib/utils';

function Badge({
    className,
    variant = 'default',
    ...props
}: React.ComponentProps<'span'> & {
    variant?: 'default' | 'secondary' | 'outline' | 'destructive' | 'warning';
}) {
    return (
        <span
            data-slot="badge"
            className={cn(
                'inline-flex items-center rounded-md border px-2 py-0.5 text-[12px] font-medium w-fit whitespace-nowrap',
                variant === 'default' && 'border-transparent bg-primary text-primary-foreground',
                variant === 'secondary' && 'border-transparent bg-secondary text-secondary-foreground',
                variant === 'outline' && 'border-border text-foreground',
                variant === 'destructive' && 'border-transparent bg-danger text-danger-foreground',
                variant === 'warning' && 'border-transparent bg-warning text-warning-foreground',
                className,
            )}
            {...props}
        />
    );
}

export { Badge };
