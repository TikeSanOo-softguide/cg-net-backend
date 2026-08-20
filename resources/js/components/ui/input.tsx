import * as React from 'react';

import { cn } from '@/lib/utils';

function Input({ className, type, ...props }: React.ComponentProps<'input'>) {
    return (
        <input
            type={type}
            data-slot="input"
            className={cn(
                'flex h-10 w-full min-w-0 rounded-md border border-input bg-surface px-3 py-2 text-sm shadow-none transition-colors duration-200 outline-none',
                'file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium',
                'placeholder:text-muted-foreground',
                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                'aria-invalid:border-danger aria-invalid:ring-danger/20',
                className,
            )}
            {...props}
        />
    );
}

export { Input };
