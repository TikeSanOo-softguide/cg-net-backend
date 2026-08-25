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
                {required ? <span className="ms-0.5 text-primary">*</span> : null}
            </Label>
            <div className="mt-1.5">
                {icon || rightSlot ? (
                    <FormControl icon={icon} rightSlot={rightSlot}>
                        {children}
                    </FormControl>
                ) : (
                    children
                )}
            </div>
            {error ? (
                <p className="mt-1.5 flex items-center gap-1.5 text-[12px] font-medium leading-4 text-danger">
                    <span className="inline-flex size-4 shrink-0 items-center justify-center rounded-full bg-danger text-danger-foreground">
                        <span className="text-[10px] font-bold leading-none">!</span>
                    </span>
                    {error}
                </p>
            ) : null}
            {! error && success ? (
                <p className="mt-1.5 flex items-center gap-1.5 text-[12px] font-medium leading-4 text-success">
                    <span className="inline-flex size-4 shrink-0 items-center justify-center rounded-full bg-success text-success-foreground">
                        <CheckIcon className="size-2.5" strokeWidth={3} />
                    </span>
                    {success}
                </p>
            ) : null}
        </div>
    );
}
