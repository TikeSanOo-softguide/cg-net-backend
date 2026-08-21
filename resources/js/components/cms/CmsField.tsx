import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { FormField } from '@/components/ui/form-field';

export function CmsField({
    label,
    htmlFor,
    error,
    icon,
    className,
    children,
}: {
    label: string;
    htmlFor: string;
    error?: string;
    icon?: LucideIcon;
    className?: string;
    children: ReactNode;
}) {
    return (
        <FormField label={label} htmlFor={htmlFor} error={error} icon={icon} className={className}>
            {children}
        </FormField>
    );
}
