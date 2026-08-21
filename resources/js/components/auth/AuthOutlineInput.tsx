import type { LucideIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import type { ReactNode } from 'react';

import { FormControl } from '@/components/ui/form-control';
import { Input } from '@/components/ui/input';

type AuthOutlineInputProps = Omit<ComponentProps<'input'>, 'className'> & {
    invalid?: boolean;
    leftIcon: LucideIcon;
    rightSlot?: ReactNode;
    className?: string;
};

export function AuthOutlineInput({
    invalid = false,
    leftIcon,
    rightSlot,
    className,
    ...props
}: AuthOutlineInputProps) {
    return (
        <FormControl icon={leftIcon} rightSlot={rightSlot}>
            <Input aria-invalid={invalid} className={className} {...props} />
        </FormControl>
    );
}
