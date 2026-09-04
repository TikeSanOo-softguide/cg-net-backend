import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { CheckIcon } from 'lucide-react';

import { FormControl } from '@/components/ui/form-control';
import { Label } from '@/components/ui/label';
import { formLabelClass } from '@/lib/form-control';
import { cn } from '@/lib/utils';

type FormFieldProps = {
    label: string;
    htmlFor: string;
    error?: string;
    success?: string;
    icon?: LucideIcon;
    rightSlot?: ReactNode;
    required?: boolean;
    className?: string;
    children: ReactNode;
};

export function FormField({
    label,
    htmlFor,
    error,
    success,
    icon,
    rightSlot,
    required = false,
    className,
    children,
}: FormFieldProps) {
    const state = error ? 'error' : success ? 'success' : 'idle';

    return (
        <div
            className={cn('group/field', className)}
            data-error={state === 'error' ? 'true' : undefined}
            data-success={state === 'success' ? 'true' : undefined}
        >
            <Label
                htmlFor={htmlFor}
                className={cn(
                    formLabelClass,
                    state === 'error' && 'text-danger',
                    state === 'success' && 'text-success',
                )}
            >
                {label}
                {required ? (
                    <span
                        className={cn(
                            'ms-0.5',
                            state === 'error' ? 'text-danger' : state === 'success' ? 'text-success' : 'text-primary',
                        )}
                    >
                        *
                    </span>
                ) : null}
            </Label>
            <div className="mt-2.5">
                {icon || rightSlot ? (
                    <FormControl icon={icon} rightSlot={rightSlot}>
                        {children}
                    </FormControl>
                ) : (
                    children
                )}
            </div>
            <div
                className="mt-1.5 flex min-h-4 items-start gap-1.5 text-[12px] font-medium leading-4"
                aria-live="polite"
            >
                {error ? (
                    <>
                        <span className="inline-flex size-4 shrink-0 items-center justify-center rounded-full bg-danger text-danger-foreground">
                            <span className="text-[10px] font-bold leading-none">!</span>
                        </span>
                        <span className="text-danger">{error}</span>
                    </>
                ) : success ? (
                    <>
                        <span className="inline-flex size-4 shrink-0 items-center justify-center rounded-full bg-success text-success-foreground">
                            <CheckIcon className="size-2.5" strokeWidth={3} />
                        </span>
                        <span className="text-success">{success}</span>
                    </>
                ) : (
                    <span className="invisible select-none" aria-hidden>
                        &nbsp;
                    </span>
                )}
            </div>
        </div>
    );
}
