import * as React from 'react';
import * as SelectPrimitive from '@radix-ui/react-select';
import { CheckIcon, ChevronDownIcon, ChevronUpIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

function Select({ ...props }: React.ComponentProps<typeof SelectPrimitive.Root>) {
    return <SelectPrimitive.Root data-slot="select" {...props} />;
}

function SelectGroup({ ...props }: React.ComponentProps<typeof SelectPrimitive.Group>) {
    return <SelectPrimitive.Group data-slot="select-group" {...props} />;
}

function SelectValue({ ...props }: React.ComponentProps<typeof SelectPrimitive.Value>) {
    return <SelectPrimitive.Value data-slot="select-value" {...props} />;
}

function SelectTrigger({ className, children, ...props }: React.ComponentProps<typeof SelectPrimitive.Trigger>) {
    return (
        <SelectPrimitive.Trigger
            data-slot="select-trigger"
            className={cn(
                'flex h-10 w-full min-w-0 items-center justify-between gap-2 rounded-[6px] border border-input bg-surface px-3 py-2 text-sm text-foreground whitespace-nowrap shadow-none outline-none transition-colors duration-200',
                'hover:border-primary/35',
                'focus-visible:border-primary focus-visible:ring-1 focus-visible:ring-primary/40 focus-visible:ring-offset-0',
                'data-[state=open]:border-primary data-[state=open]:ring-1 data-[state=open]:ring-primary/40',
                'disabled:cursor-not-allowed disabled:border-input disabled:bg-muted disabled:opacity-70 disabled:hover:border-input',
                'data-[placeholder]:text-muted-foreground',
                '[&>span]:min-w-0 [&>span]:truncate',
                '[&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0 [&_svg]:text-muted-foreground [&_svg]:transition-colors',
                'focus-visible:[&_svg]:text-primary data-[state=open]:[&_svg]:text-primary',
                'aria-invalid:border-danger aria-invalid:hover:border-danger aria-invalid:focus-visible:border-danger aria-invalid:focus-visible:ring-danger/25',
                'aria-invalid:data-[state=open]:border-danger aria-invalid:data-[state=open]:ring-danger/25 aria-invalid:[&_svg]:text-danger',
                'group-data-[error=true]/field:border-danger group-data-[error=true]/field:hover:border-danger group-data-[error=true]/field:focus-visible:border-danger group-data-[error=true]/field:focus-visible:ring-danger/25',
                'group-data-[error=true]/field:data-[state=open]:border-danger group-data-[error=true]/field:data-[state=open]:ring-danger/25 group-data-[error=true]/field:[&_svg]:text-danger',
                className,
            )}
            {...props}
        >
            {children}
            <SelectPrimitive.Icon asChild>
                <ChevronDownIcon className="size-4" />
            </SelectPrimitive.Icon>
        </SelectPrimitive.Trigger>
    );
}

function SelectContent({
    className,
    children,
    position = 'popper',
    ...props
}: React.ComponentProps<typeof SelectPrimitive.Content>) {
    return (
        <SelectPrimitive.Portal>
            <SelectPrimitive.Content
                data-slot="select-content"
                className={cn(
                    'relative z-[90] max-h-96 min-w-32 overflow-hidden rounded-[6px] border border-white/40 bg-[rgba(255,255,255,0.74)] text-popover-foreground shadow-[0_18px_40px_rgba(15,23,42,0.16)] backdrop-blur-xl dark:border-white/12 dark:bg-[rgba(18,28,30,0.82)] dark:shadow-[0_18px_45px_rgba(0,0,0,0.4)]',
                    'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
                    position === 'popper' &&
                        'data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1',
                    className,
                )}
                position={position}
                {...props}
            >
                <SelectPrimitive.ScrollUpButton className="flex cursor-default items-center justify-center py-1">
                    <ChevronUpIcon className="size-4" />
                </SelectPrimitive.ScrollUpButton>
                <SelectPrimitive.Viewport
                    className={cn('p-1', position === 'popper' && 'h-[var(--radix-select-trigger-height)] w-full min-w-[var(--radix-select-trigger-width)]')}
                >
                    {children}
                </SelectPrimitive.Viewport>
                <SelectPrimitive.ScrollDownButton className="flex cursor-default items-center justify-center py-1">
                    <ChevronDownIcon className="size-4" />
                </SelectPrimitive.ScrollDownButton>
            </SelectPrimitive.Content>
        </SelectPrimitive.Portal>
    );
}

function SelectItem({ className, children, ...props }: React.ComponentProps<typeof SelectPrimitive.Item>) {
    return (
        <SelectPrimitive.Item
            data-slot="select-item"
            className={cn(
                'relative flex w-full cursor-default items-center gap-2 rounded-[6px] py-1.5 pr-8 pl-2 text-sm outline-hidden select-none',
                'focus:bg-accent focus:text-accent-foreground',
                'data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
                className,
            )}
            {...props}
        >
            <span className="absolute right-2 flex size-3.5 items-center justify-center">
                <SelectPrimitive.ItemIndicator>
                    <CheckIcon className="size-4" />
                </SelectPrimitive.ItemIndicator>
            </span>
            <SelectPrimitive.ItemText>{children}</SelectPrimitive.ItemText>
        </SelectPrimitive.Item>
    );
}

function SelectLabel({ className, ...props }: React.ComponentProps<typeof SelectPrimitive.Label>) {
    return <SelectPrimitive.Label data-slot="select-label" className={cn('px-2 py-1.5 text-xs text-muted-foreground', className)} {...props} />;
}

function SelectSeparator({ className, ...props }: React.ComponentProps<typeof SelectPrimitive.Separator>) {
    return <SelectPrimitive.Separator data-slot="select-separator" className={cn('-mx-1 my-1 h-px bg-border', className)} {...props} />;
}

export { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectSeparator, SelectTrigger, SelectValue };
