import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type FormCardProps = {
    title?: string;
    description?: string;
    icon?: LucideIcon;
    className?: string;
    contentClassName?: string;
    children: ReactNode;
};

export function FormCard({ title, description, icon: Icon, className, contentClassName, children }: FormCardProps) {
    return (
        <Card className={cn('w-full gap-0 overflow-hidden py-0', className)}>
            {title || Icon ? (
                <CardHeader className="flex flex-row items-start gap-3 border-b border-border/70 px-5 py-5 sm:px-6">
                    {Icon ? (
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Icon className="size-[22px]" strokeWidth={1.85} />
                        </div>
                    ) : null}
                    <div className="min-w-0">
                        {title ? <CardTitle className="text-lg font-semibold">{title}</CardTitle> : null}
                        {description ? <CardDescription>{description}</CardDescription> : null}
                    </div>
                </CardHeader>
            ) : null}
            <CardContent className={cn('px-5 py-5 pb-24 sm:px-6 sm:py-6 sm:pb-6', contentClassName)}>{children}</CardContent>
        </Card>
    );
}
