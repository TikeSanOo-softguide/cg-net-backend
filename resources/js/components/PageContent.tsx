import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type PageContentProps = {
    children: ReactNode;
    className?: string;
};

export function PageContent({ children, className }: PageContentProps) {
    return <div className={cn('flex w-full flex-col gap-4 pt-3 lg:pt-4', className)}>{children}</div>;
}
