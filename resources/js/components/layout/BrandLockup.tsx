import { Link } from '@inertiajs/react';

import { cn } from '@/lib/utils';

type BrandLockupProps = {
    compact?: boolean;
    expanded?: boolean;
    className?: string;
    logoClassName?: string;
    href?: string | false;
};

export function BrandLockup({
    compact = false,
    expanded = true,
    className,
    logoClassName,
    href = '/dashboard',
}: BrandLockupProps) {
    const showWordmark = ! compact && expanded;

    const content = (
        <>
            <span
                className={cn(
                    'relative size-11 shrink-0 overflow-hidden rounded-full bg-brand',
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
                <span
                    className={cn(
                        'overflow-hidden whitespace-nowrap font-heading text-base font-semibold tracking-tight text-foreground',
                        'motion-reduce:transition-none transition-[max-width,opacity,margin] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]',
                        showWordmark ? 'ml-0 max-w-[140px] opacity-100' : 'pointer-events-none max-w-0 opacity-0',
                    )}
                    aria-hidden={! showWordmark}
                >
                    Smart Link
                </span>
            )}
        </>
    );

    const classes = cn(
        'flex min-w-0 items-center',
        'motion-reduce:transition-none transition-[gap] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]',
        showWordmark ? 'gap-3' : 'gap-0',
        className,
    );

    if (href === false) {
        return <div className={classes}>{content}</div>;
    }

    return (
        <Link href={href} className={classes}>
            {content}
        </Link>
    );
}
