import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { FormControl } from '@/components/ui/form-control';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type FormFieldProps = {
    label: string;
    htmlFor: string;
    error?: string;
    icon?: LucideIcon;
    rightSlot?: ReactNode;
    className?: string;
    children: ReactNode;
};

export function FormField({ label, htmlFor, error, icon, rightSlot, className, children }: FormFieldProps) {
    return (
        <div className={cn('group/field', className)} data-error={error ? 'true' : undefined}>
            <Label htmlFor={htmlFor}>{label}</Label>
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
