import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors duration-200 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*="size-"])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background aria-invalid:ring-danger/20 aria-invalid:border-danger',
    {
        variants: {
            variant: {
                primary: 'bg-primary text-primary-foreground hover:bg-primary-hover',
                default: 'bg-primary text-primary-foreground hover:bg-primary-hover',
                secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
                outline: 'border border-border bg-surface text-foreground hover:bg-muted',
                ghost: 'text-foreground hover:bg-muted',
                destructive: 'bg-danger text-danger-foreground hover:bg-danger/90 focus-visible:ring-danger',
            },
            size: {
                sm: 'h-8 rounded-md px-3 text-xs has-[>svg]:px-2.5',
                md: 'h-10 rounded-md px-4 text-sm has-[>svg]:px-3',
                lg: 'h-12 rounded-md px-6 text-base has-[>svg]:px-4',
                default: 'h-10 rounded-md px-4 text-sm has-[>svg]:px-3',
                icon: 'size-10',
            },
        },
        defaultVariants: {
            variant: 'primary',
            size: 'md',
        },
    },
);

function Button({
    className,
    variant,
    size,
    asChild = false,
    ...props
}: React.ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
    }) {
    const Comp = asChild ? Slot : 'button';

    return <Comp data-slot="button" className={cn(buttonVariants({ variant, size, className }))} {...props} />;
}

export { Button, buttonVariants };
