import { Link } from '@inertiajs/react';

import { cn } from '@/lib/utils';

/** Shared logo frame — same on login and dashboard */
export const brandLogoFrameClass = cn(
    'relative shrink-0 overflow-hidden rounded-[8px] bg-white',
    'border border-primary/20',
    'shadow-[0_2px_10px_hsl(var(--primary)/0.16),0_1px_2px_hsl(var(--primary)/0.10)]',
);

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
    const showWordmark = !compact && expanded;

    const content = (
        <>
            <span className={cn(brandLogoFrameClass, 'size-11', logoClassName)}>
                <img
                    src="/images/cg-net-logo.png?v=3"
                    alt=""
                    className="size-full object-contain p-0.5"
                />
            </span>
            {compact ? (
                <span className="sr-only">Young Ni Oo</span>
            ) : (
                <span
                    className={cn(
                        'overflow-hidden whitespace-nowrap font-heading text-[15px] font-bold tracking-[0.04em] text-primary uppercase sm:text-base',
                        'motion-reduce:transition-none transition-[max-width,opacity,margin] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]',
                        showWordmark ? 'ml-0 max-w-[200px] opacity-100' : 'pointer-events-none max-w-0 opacity-0',
                    )}
                    aria-hidden={!showWordmark}
                >
                    Young Ni Oo
                </span>
            )}
        </>
    );

    const classes = cn(
        'group/brand flex min-w-0 items-center',
        'motion-reduce:transition-none transition-[gap] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]',
        showWordmark ? 'gap-2.5' : 'gap-0',
        href !== false && 'rounded-[10px] outline-none focus-visible:ring-2 focus-visible:ring-primary/30',
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
