type StaffListAvatarProps = {
    username: string;
};

export function staffInitials(username: string): string {
    const parts = username.trim().split(/[\s._-]+/).filter(Boolean);

    if (parts.length >= 2) {
        return `${parts[0].charAt(0)}${parts[1].charAt(0)}`.toUpperCase();
    }

    return username.trim().slice(0, 2).toUpperCase();
}

export function StaffListAvatar({ username }: StaffListAvatarProps) {
    return (
        <span
            className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/12 text-[11px] font-semibold tracking-wide text-primary uppercase"
            aria-hidden
        >
            {staffInitials(username)}
        </span>
    );
}
