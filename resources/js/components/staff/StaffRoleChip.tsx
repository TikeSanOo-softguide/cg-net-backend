import { roleIcon } from '@/lib/staff-roles';
import { cn } from '@/lib/utils';

type StaffRoleChipProps = {
    name: string;
    className?: string;
};

export function StaffRoleChip({ name, className }: StaffRoleChipProps) {
    const Icon = roleIcon(name);

    return (
        <span
            className={cn(
                'inline-flex max-w-full items-center gap-1 rounded-[6px] bg-primary/12 px-1.5 py-0.5 text-[11px] font-medium text-primary',
                className,
            )}
        >
            <Icon className="size-3 shrink-0" strokeWidth={1.9} />
            <span className="truncate">{name}</span>
        </span>
    );
}
