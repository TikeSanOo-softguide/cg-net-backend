import { cn } from '@/lib/utils';

type UnreadNotificationDotProps = {
    label: string;
    className?: string;
};

export function UnreadNotificationDot({ label, className }: UnreadNotificationDotProps) {
    return (
        <span
            role="img"
            aria-label={label}
            className={cn('inline-flex size-2 shrink-0 rounded-full bg-danger', className)}
        />
    );
}
