import * as React from 'react';

import {
    borderRadiusClass,
    cardStyleClass,
    DEFAULT_THEME_SETTINGS,
    shadowStyleClass,
} from '@/lib/theme-settings';
import { useOptionalThemeSettings } from '@/providers/ThemeSettingsProvider';
import { cn } from '@/lib/utils';

function Card({ className, ...props }: React.ComponentProps<'div'>) {
    const settings = useOptionalThemeSettings();
    const radius = settings?.borderRadius ?? DEFAULT_THEME_SETTINGS.borderRadius;
    const style = settings?.cardStyle ?? DEFAULT_THEME_SETTINGS.cardStyle;
    const shadow = settings?.shadowStyle ?? DEFAULT_THEME_SETTINGS.shadowStyle;

    return (
        <div
            data-slot="card"
            className={cn(
                'flex flex-col gap-5 bg-card py-5 text-card-foreground',
                borderRadiusClass[radius],
                cardStyleClass[style],
                shadowStyleClass[shadow],
                className,
            )}
            {...props}
        />
    );
}

function CardHeader({ className, ...props }: React.ComponentProps<'div'>) {
    return <div data-slot="card-header" className={cn('grid auto-rows-min items-start gap-1 px-5', className)} {...props} />;
}

function CardTitle({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-title"
            className={cn('font-heading text-base leading-snug font-semibold tracking-tight', className)}
            {...props}
        />
    );
}

function CardDescription({ className, ...props }: React.ComponentProps<'div'>) {
    return <div data-slot="card-description" className={cn('text-[13px] leading-5 text-muted-foreground', className)} {...props} />;
}

function CardContent({ className, ...props }: React.ComponentProps<'div'>) {
    return <div data-slot="card-content" className={cn('px-5', className)} {...props} />;
}

function CardFooter({ className, ...props }: React.ComponentProps<'div'>) {
    return <div data-slot="card-footer" className={cn('flex items-center px-5', className)} {...props} />;
}

export { Card, CardHeader, CardFooter, CardTitle, CardDescription, CardContent };
