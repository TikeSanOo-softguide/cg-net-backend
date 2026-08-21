import type { ReactNode } from 'react';
import type { ComponentProps } from 'react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type AuthOutlineInputProps = Omit<ComponentProps<'input'>, 'className'> & {
    invalid?: boolean;
    leftIcon: ReactNode;
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
        <div className="relative">
            <span className="pointer-events-none absolute inset-y-0 left-0 z-[1] flex w-12 items-center justify-center text-muted-foreground">
                {leftIcon}
            </span>
            <Input
                aria-invalid={invalid}
                className={cn(
                    'h-11 rounded-[8px] border border-input bg-transparent text-foreground shadow-none transition-colors duration-200',
                    'pl-12 text-sm',
                    rightSlot ? 'pr-12' : 'pr-3',
                    'hover:border-primary/35',
                    'focus-visible:border-primary focus-visible:ring-1 focus-visible:ring-primary/40 focus-visible:ring-offset-0',
                    'aria-invalid:border-danger aria-invalid:ring-0 aria-invalid:hover:border-danger aria-invalid:focus-visible:border-danger aria-invalid:focus-visible:ring-danger/20',
                    className,
                )}
                {...props}
            />
            {rightSlot ? (
                <div className="absolute inset-y-0 right-0 flex w-12 items-center justify-center">{rightSlot}</div>
            ) : null}
        </div>
    );
}
