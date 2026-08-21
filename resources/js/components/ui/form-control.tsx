import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { formControlIconClass } from '@/lib/form-control';
import { cn } from '@/lib/utils';

type FormControlProps = {
    icon?: LucideIcon;
    rightSlot?: ReactNode;
    className?: string;
    children: ReactNode;
};

export function FormControl({ icon: Icon, rightSlot, className, children }: FormControlProps) {
    return (
        <div className={cn('group/control relative min-w-0 w-full', className)}>
            {Icon ? (
                <span
                    className={cn(
                        'pointer-events-none absolute left-0 z-[1] flex w-10 items-center justify-center',
                        'inset-y-0 group-has-[[data-slot=textarea]]/control:inset-y-auto group-has-[[data-slot=textarea]]/control:top-0 group-has-[[data-slot=textarea]]/control:h-10',
                        formControlIconClass,
                    )}
                >
                    <Icon className="size-4" strokeWidth={1.75} />
                </span>
            ) : null}
            <div
                className={cn(
                    Icon && '[&_[data-slot=input]]:pl-10 [&_[data-slot=textarea]]:pl-10 [&_[data-slot=select-trigger]]:pl-10',
                    rightSlot && '[&_[data-slot=input]]:pr-10',
                )}
            >
                {children}
            </div>
            {rightSlot ? (
                <div className="absolute inset-y-0 right-0 z-[1] flex w-10 items-center justify-center">{rightSlot}</div>
            ) : null}
        </div>
    );
}
