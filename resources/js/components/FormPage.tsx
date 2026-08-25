import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type FormPageProps = {
    children: ReactNode;
    width?: 'md' | 'lg';
    className?: string;
};

export function FormPage({ children, width = 'md', className }: FormPageProps) {
    return (
        <div className={cn('flex w-full justify-center pt-3 lg:pt-4', className)}>
            <div className={cn('flex w-full flex-col gap-4', width === 'lg' ? 'max-w-4xl' : 'max-w-3xl')}>
                {children}
            </div>
        </div>
    );
}
