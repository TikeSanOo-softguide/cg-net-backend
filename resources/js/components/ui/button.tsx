import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
    [
        'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[6px] text-sm font-medium',
        'transition-colors duration-200 outline-none select-none',
        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
        'disabled:pointer-events-none disabled:cursor-not-allowed disabled:border-muted disabled:bg-muted disabled:text-muted-foreground disabled:opacity-100',
        '[&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*="size-"])]:size-4',
    ].join(' '),
    {
        variants: {
            variant: {
                primary:
                    'border border-transparent bg-primary text-primary-foreground hover:bg-[color-mix(in_srgb,hsl(var(--primary))_88%,black)] active:bg-[color-mix(in_srgb,hsl(var(--primary))_78%,black)]',
                default:
                    'border border-transparent bg-primary text-primary-foreground hover:bg-[color-mix(in_srgb,hsl(var(--primary))_88%,black)] active:bg-[color-mix(in_srgb,hsl(var(--primary))_78%,black)]',
                secondary:
                    'border border-transparent bg-secondary text-secondary-foreground hover:bg-[color-mix(in_srgb,var(--secondary)_86%,black)] active:bg-muted',
                outline:
                    'border border-primary bg-transparent text-primary hover:bg-primary/10 active:bg-primary/15',
                ghost: 'border border-transparent bg-transparent text-foreground hover:bg-muted active:bg-muted/80',
                destructive:
                    'border border-transparent bg-danger text-danger-foreground hover:bg-[color-mix(in_srgb,var(--danger)_88%,black)] active:bg-[color-mix(in_srgb,var(--danger)_78%,black)] focus-visible:ring-danger',
            },
            size: {
                sm: 'h-8 min-h-8 px-3 text-xs has-[>svg]:px-2.5',
                md: 'h-10 min-h-10 px-4 text-sm has-[>svg]:px-3.5',
                lg: 'h-11 min-h-11 px-5 text-sm has-[>svg]:px-4',
                default: 'h-10 min-h-10 px-4 text-sm has-[>svg]:px-3.5',
                icon: 'size-10 min-h-10 min-w-10 p-0',
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
