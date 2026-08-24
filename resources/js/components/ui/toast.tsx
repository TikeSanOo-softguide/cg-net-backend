import * as React from 'react';
import * as ToastPrimitives from '@radix-ui/react-toast';
import { cva, type VariantProps } from 'class-variance-authority';
import { CheckIcon, InfoIcon, XIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

const ToastProvider = ToastPrimitives.Provider;

export const TOAST_DURATION = 4500;

function ToastViewport({ className, ...props }: React.ComponentProps<typeof ToastPrimitives.Viewport>) {
    return (
        <ToastPrimitives.Viewport
            data-slot="toast-viewport"
            className={cn(
                'fixed top-4 right-4 z-[100] flex max-h-screen w-[min(100%-2rem,380px)] flex-col gap-3 outline-none',
                className,
            )}
            {...props}
        />
    );
}

const toastVariants = cva(
    [
        'group pointer-events-auto relative flex h-[75px] min-h-[75px] w-full overflow-hidden rounded-[8px] border border-border/70 bg-[#FFFFFF]',
        'shadow-[0_8px_24px_rgb(23_50_54/0.10),0_2px_6px_rgb(23_50_54/0.06)]',
        'transition-all data-[swipe=cancel]:translate-x-0 data-[swipe=end]:translate-x-[var(--radix-toast-swipe-end-x)] data-[swipe=move]:translate-x-[var(--radix-toast-swipe-move-x)] data-[swipe=move]:transition-none',
        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[swipe=end]:animate-out',
        'data-[state=closed]:fade-out-80 data-[state=closed]:slide-out-to-right-full',
        'data-[state=open]:slide-in-from-top-full',
        'dark:bg-card dark:shadow-[0_8px_24px_rgb(0_0_0/0.28)]',
    ].join(' '),
    {
        variants: {
            variant: {
                success: '',
                error: '',
                info: '',
                warning: '',
                default: '',
                destructive: '',
            },
        },
        defaultVariants: {
            variant: 'success',
        },
    },
);

type ToastVariant = NonNullable<VariantProps<typeof toastVariants>['variant']>;

const variantStyle: Record<
    ToastVariant,
    {
        bar: string;
        iconWrap: string;
        icon: React.ReactNode;
    }
> = {
    success: {
        bar: 'bg-primary',
        iconWrap: 'bg-primary',
        icon: <CheckIcon className="size-3.5 text-primary-foreground" strokeWidth={2.4} />,
    },
    error: {
        bar: 'bg-[#F43F5E]',
        iconWrap: 'bg-[#F43F5E]',
        icon: <XIcon className="size-3.5 text-white" strokeWidth={2.4} />,
    },
    destructive: {
        bar: 'bg-[#F43F5E]',
        iconWrap: 'bg-[#F43F5E]',
        icon: <XIcon className="size-3.5 text-white" strokeWidth={2.4} />,
    },
    info: {
        bar: 'bg-[#3B82F6]',
        iconWrap: 'bg-[#3B82F6]',
        icon: <InfoIcon className="size-3.5 text-white" strokeWidth={2.4} />,
    },
    warning: {
        bar: 'bg-[#F59E0B]',
        iconWrap: 'bg-[#F59E0B]',
        icon: <span className="text-[13px] leading-none font-bold text-white">!</span>,
    },
    default: {
        bar: 'bg-primary',
        iconWrap: 'bg-primary',
        icon: <InfoIcon className="size-3.5 text-white" strokeWidth={2.4} />,
    },
};

function Toast({
    className,
    variant = 'success',
    duration = TOAST_DURATION,
    onOpenChange,
    children,
    ...props
}: React.ComponentProps<typeof ToastPrimitives.Root> & VariantProps<typeof toastVariants>) {
    const style = variantStyle[variant ?? 'success'];

    return (
        <ToastPrimitives.Root
            data-slot="toast"
            duration={Infinity}
            className={cn(toastVariants({ variant }), className)}
            onOpenChange={onOpenChange}
            {...props}
        >
            <span className={cn('w-1.5 shrink-0 self-stretch', style.bar)} aria-hidden />
            <div className="flex min-w-0 flex-1 items-center gap-3 px-3.5 pr-9">
                <span className={cn('flex size-7 shrink-0 items-center justify-center rounded-full', style.iconWrap)}>
                    {style.icon}
                </span>
                <div className="grid min-w-0 flex-1 gap-0.5">{children}</div>
            </div>
            <span
                aria-hidden
                className={cn('toast-progress pointer-events-none absolute bottom-0 left-0 h-[2px] w-full', style.bar)}
                style={{ animationDuration: `${duration}ms` }}
                onAnimationEnd={(event) => {
                    if (event.animationName !== 'toast-progress') {
                        return;
                    }

                    onOpenChange?.(false);
                }}
            />
        </ToastPrimitives.Root>
    );
}

function ToastAction({ className, ...props }: React.ComponentProps<typeof ToastPrimitives.Action>) {
    return (
        <ToastPrimitives.Action
            data-slot="toast-action"
            className={cn(
                'inline-flex h-8 shrink-0 items-center justify-center rounded-md border bg-transparent px-3 text-sm font-medium transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}

function ToastClose({ className, ...props }: React.ComponentProps<typeof ToastPrimitives.Close>) {
    return (
        <ToastPrimitives.Close
            data-slot="toast-close"
            className={cn(
                'absolute top-2.5 right-2.5 rounded-md p-1 text-muted-foreground/70 opacity-70 transition-opacity hover:opacity-100 hover:text-foreground focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                className,
            )}
            toast-close=""
            {...props}
        >
            <XIcon className="size-3.5" strokeWidth={1.85} />
            <span className="sr-only">Close</span>
        </ToastPrimitives.Close>
    );
}

function ToastTitle({ className, ...props }: React.ComponentProps<typeof ToastPrimitives.Title>) {
    return (
        <ToastPrimitives.Title
            data-slot="toast-title"
            className={cn('truncate text-[13px] leading-5 font-semibold text-foreground', className)}
            {...props}
        />
    );
}

function ToastDescription({ className, ...props }: React.ComponentProps<typeof ToastPrimitives.Description>) {
    return (
        <ToastPrimitives.Description
            data-slot="toast-description"
            className={cn('line-clamp-1 text-[12px] leading-4 text-muted-foreground', className)}
            {...props}
        />
    );
}

type ToastProps = React.ComponentProps<typeof Toast>;
type ToastActionElement = React.ReactElement<typeof ToastAction>;

export {
    Toast,
    ToastAction,
    ToastClose,
    ToastDescription,
    ToastProvider,
    ToastTitle,
    ToastViewport,
    type ToastActionElement,
    type ToastProps,
};
