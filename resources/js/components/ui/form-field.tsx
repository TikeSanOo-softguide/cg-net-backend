import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { FormControl } from '@/components/ui/form-control';
import { Label } from '@/components/ui/label';
import { formLabelClass } from '@/lib/form-control';
import { cn } from '@/lib/utils';

type FormFieldProps = {
    label: string;
    htmlFor: string;
    error?: string;
    icon?: LucideIcon;
    rightSlot?: ReactNode;
    required?: boolean;
    className?: string;
    children: ReactNode;
};

export function FormField({ label, htmlFor, error, icon, rightSlot, required = false, className, children }: FormFieldProps) {
    return (
        <div className={cn('group/field', className)} data-error={error ? 'true' : undefined}>
            <Label htmlFor={htmlFor} className={formLabelClass}>
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
            {error ? <p className="mt-1 text-xs text-danger">{error}</p> : null}
        </div>
    );
}
