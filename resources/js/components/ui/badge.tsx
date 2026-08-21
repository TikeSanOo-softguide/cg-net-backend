import * as React from 'react';

import { cn } from '@/lib/utils';

function Badge({
    className,
    variant = 'default',
    ...props
}: React.ComponentProps<'span'> & {
    variant?: 'default' | 'secondary' | 'outline' | 'destructive' | 'warning' | 'success';
}) {
    return (
        <span
            data-slot="badge"
            className={cn(
                'inline-flex w-fit items-center rounded-[6px] px-2 py-0.5 text-[12px] font-medium whitespace-nowrap',
                variant === 'default' && 'bg-primary/12 text-primary',
                variant === 'success' && 'bg-success/12 text-success',
                variant === 'secondary' && 'bg-muted text-muted-foreground',
                variant === 'outline' && 'bg-muted/70 text-muted-foreground',
                variant === 'destructive' && 'bg-danger/12 text-danger',
                variant === 'warning' && 'bg-warning/18 text-warning-foreground',
                className,
            )}
            {...props}
        />
    );
}

export { Badge };
