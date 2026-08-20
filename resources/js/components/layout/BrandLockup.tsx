import { Link } from '@inertiajs/react';

import { cn } from '@/lib/utils';

type BrandLockupProps = {
    compact?: boolean;
    className?: string;
    logoClassName?: string;
    href?: string | false;
};

export function BrandLockup({ compact = false, className, logoClassName, href = '/dashboard' }: BrandLockupProps) {
    const content = (
        <>
            <span
                className={cn(
                    'relative shrink-0 overflow-hidden rounded-full bg-brand',
                    compact ? 'size-11' : 'size-10 sm:size-11',
                    logoClassName,
                )}
            >
                <img
                    src="/images/smart-link-logo.jpg"
                    alt=""
                    className="size-full object-cover object-[center_30%] scale-[1.35]"
                />
            </span>
            {compact ? (
                <span className="sr-only">Smart Link</span>
            ) : (
                <span className="truncate font-heading text-base font-semibold tracking-tight text-foreground">
                    Smart Link
                </span>
            )}
        </>
    );

    const classes = cn('flex min-w-0 items-center gap-3', className);

    if (href === false) {
        return <div className={classes}>{content}</div>;
    }

    return (
        <Link href={href} className={classes}>
            {content}
        </Link>
    );
}
