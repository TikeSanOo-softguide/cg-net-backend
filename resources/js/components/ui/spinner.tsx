import { memo } from 'react';

import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

const sizeConfig = {
    xs: {
        container: 'size-4',
        borderWidth: 'border',
    },
    sm: {
        container: 'size-6',
        borderWidth: 'border',
    },
    md: {
        container: 'size-12',
        borderWidth: 'border-2',
    },
    lg: {
        container: 'size-24',
        borderWidth: 'border-[3px]',
    },
} as const;

type SpinnerSize = keyof typeof sizeConfig;

type SpinnerProps = {
    size?: SpinnerSize;
    className?: string;
    label?: string;
};

export const Spinner = memo(function Spinner({ size = 'md', className, label }: SpinnerProps) {
    const config = sizeConfig[size];

    return (
        <span
            role="status"
            aria-live="polite"
            aria-label={label}
            className={cn('relative inline-flex items-center justify-center text-primary', config.container, className)}
        >
            {[0, 1, 2].map((index) => (
                <span
                    key={index}
                    className={cn(
                        'spinner-ripple-ring absolute inset-0 rounded-full border-current bg-current/[0.03]',
                        config.borderWidth,
                    )}
                    style={{ animationDelay: `${index * 0.6}s` }}
                />
            ))}
        </span>
    );
});

type SpinnerOverlayProps = {
    label?: string;
    size?: SpinnerSize;
    className?: string;
};

export function SpinnerOverlay({ label, size = 'md', className }: SpinnerOverlayProps) {
    const { t } = useTranslation();
    const text = label ?? t('common.loading');

    return (
        <div
            className={cn(
                'absolute inset-0 z-10 flex min-h-[160px] flex-col items-center justify-center gap-2.5 rounded-[inherit]',
                'bg-[color-mix(in_srgb,hsl(var(--card))_78%,transparent)] backdrop-blur-[3px]',
                className,
            )}
        >
            <Spinner size={size} label={text} />
            <p className="text-[11px] font-medium tracking-wide text-muted-foreground">{text}</p>
        </div>
    );
}
