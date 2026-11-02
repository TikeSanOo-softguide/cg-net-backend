import { cn } from '@/lib/utils';

type StaffListAvatarProps = {
    username: string;
    className?: string;
    unreadLabel?: string;
};

export function staffInitials(username: string): string {
    const parts = username
        .trim()
        .split(/[\s._-]+/)
        .filter(Boolean);

    if (parts.length >= 2) {
        return `${parts[0].charAt(0)}${parts[1].charAt(0)}`.toUpperCase();
    }

    return username.trim().slice(0, 2).toUpperCase();
}

export function StaffListAvatar({ username, className, unreadLabel }: StaffListAvatarProps) {
    return (
        <span className="relative inline-flex size-8 shrink-0">
            <span
                className={cn(
                    'flex size-8 items-center justify-center rounded-full bg-primary/12 text-[11px] font-semibold tracking-wide text-primary uppercase',
                    className,
                )}
                aria-hidden
            >
                {staffInitials(username)}
            </span>
            {unreadLabel ? (
                <span
                    role="img"
                    aria-label={unreadLabel}
                    className="absolute -top-0.5 -left-0.5 size-1.5 rounded-full bg-danger ring-2 ring-background"
                />
            ) : null}
        </span>
    );
}
