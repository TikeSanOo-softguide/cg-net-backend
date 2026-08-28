import { CheckIcon, EyeIcon, PlusIcon, ShieldIcon, SquarePenIcon, Trash2Icon, type LucideIcon } from 'lucide-react';

import { navigation, viewPermissionForHref } from '@/lib/navigation';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

export type PermissionMatrixGroup = {
    module: string;
    labelKey: string;
    permissions: {
        name: string;
        action: string;
        labelKey: string;
    }[];
};

type PermissionMatrixProps = {
    groups: PermissionMatrixGroup[];
    value: string[];
    onChange: (value: string[]) => void;
    locked?: boolean;
};

export const PERMISSION_ACTIONS = ['view', 'create', 'update', 'delete'] as const;

export const PERMISSION_ACTION_META: Record<string, { icon: LucideIcon; danger?: boolean }> = {
    view: { icon: EyeIcon },
    create: { icon: PlusIcon },
    update: { icon: SquarePenIcon },
    delete: { icon: Trash2Icon, danger: true },
};

export function permissionModuleIcon(module: string): LucideIcon {
    const group = navigation.find((item) => item.id === module);

    if (group) {
        return group.icon;
    }

    const viewPermission = `${module}.view`;

    for (const item of navigation) {
        const child = item.children?.find((entry) => viewPermissionForHref(entry.href) === viewPermission);

        if (child) {
            return child.icon;
        }
    }

    return ShieldIcon;
}

function PermSwitch({
    checked,
    disabled,
    label,
    onToggle,
}: {
    checked: boolean;
    disabled?: boolean;
    label: string;
    onToggle: () => void;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            disabled={disabled}
            onClick={onToggle}
            className={cn(
                'relative h-[18px] w-8 shrink-0 rounded-full transition-colors',
                checked ? 'bg-primary' : 'bg-[#d5dde2] dark:bg-muted',
                disabled && 'cursor-not-allowed opacity-50',
            )}
        >
            <span
                className={cn(
                    'absolute top-[2px] size-[14px] rounded-full bg-white shadow-sm transition-[left]',
                    checked ? 'left-[16px]' : 'left-[2px]',
                )}
            />
        </button>
    );
}

export function PermissionMatrix({ groups, value, onChange, locked = false }: PermissionMatrixProps) {
    const { t } = useTranslation();

    const toggle = (name: string) => {
        if (locked) {
            return;
        }

        if (value.includes(name)) {
            onChange(value.filter((item) => item !== name));

            return;
        }

        onChange([...value, name]);
    };

    const toggleGroup = (group: PermissionMatrixGroup) => {
        if (locked) {
            return;
        }

        const names = group.permissions.map((permission) => permission.name);
        const allSelected = names.every((name) => value.includes(name));

        if (allSelected) {
            onChange(value.filter((item) => ! names.includes(item)));

            return;
        }

        onChange([...new Set([...value, ...names])]);
    };

    return (
        <div className="flex flex-col gap-2 sm:col-span-2">
            {groups.map((group) => {
                const names = group.permissions.map((permission) => permission.name);
                const selectedCount = names.filter((name) => value.includes(name)).length;
                const allSelected = selectedCount === names.length && names.length > 0;
                const Icon = permissionModuleIcon(group.module);

                return (
                    <section
                        key={group.module}
                        className="overflow-hidden rounded-[12px] border border-border/70 bg-[#f7f9fa] dark:bg-muted/20"
                    >
                        <div className="flex items-center gap-2.5 px-2.5 py-2 sm:px-3">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-[8px] bg-primary/12 text-primary">
                                <Icon className="size-4" strokeWidth={1.8} />
                            </span>
                            <div className="min-w-0 flex-1">
                                <h3 className="min-w-0 text-[13px] font-semibold leading-[1.75] text-primary">{t(group.labelKey)}</h3>
                                <p className="text-[10px] leading-3.5 text-muted-foreground">
                                    {selectedCount}/{names.length}
                                </p>
                            </div>
                            <PermSwitch
                                checked={allSelected}
                                disabled={locked}
                                label={allSelected ? t('permissions.clear') : t('permissions.select_all')}
                                onToggle={() => toggleGroup(group)}
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-1.5 px-2.5 pb-2.5 sm:grid-cols-4 sm:px-3">
                            {group.permissions.map((permission) => {
                                const checked = value.includes(permission.name);
                                const meta = PERMISSION_ACTION_META[permission.action];
                                const ActionIcon = meta?.icon;
                                const danger = Boolean(meta?.danger);

                                return (
                                    <button
                                        key={permission.name}
                                        type="button"
                                        role="checkbox"
                                        aria-checked={checked}
                                        disabled={locked}
                                        onClick={() => toggle(permission.name)}
                                        className={cn(
                                            'flex items-center gap-1.5 rounded-[8px] border px-1.5 py-1.5 text-left transition-colors',
                                            checked
                                                ? danger
                                                    ? 'border-danger/35 bg-danger/[0.08]'
                                                    : 'border-primary/45 bg-white shadow-[0_1px_2px_rgb(23_50_54/0.06)] dark:bg-card'
                                                : 'border-transparent bg-white/80 hover:border-primary/20 hover:bg-white dark:bg-card/70 dark:hover:bg-card',
                                            locked && 'cursor-not-allowed opacity-70',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'flex size-[16px] shrink-0 items-center justify-center rounded-[4px] border transition-colors',
                                                checked
                                                    ? danger
                                                        ? 'border-danger bg-danger text-danger-foreground'
                                                        : 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-input bg-white dark:bg-card',
                                            )}
                                        >
                                            {checked ? <CheckIcon className="size-2.5" strokeWidth={3} /> : null}
                                        </span>
                                        <span
                                            className={cn(
                                                'flex size-6 shrink-0 items-center justify-center rounded-[6px]',
                                                checked
                                                    ? danger
                                                        ? 'bg-danger/12 text-danger'
                                                        : 'bg-primary/12 text-primary'
                                                    : 'bg-muted text-muted-foreground',
                                            )}
                                        >
                                            {ActionIcon ? <ActionIcon className="size-3" strokeWidth={1.9} /> : null}
                                        </span>
                                        <span
                                            className={cn(
                                                'min-w-0 text-[11px] font-medium leading-[1.75]',
                                                checked
                                                    ? danger
                                                        ? 'text-danger'
                                                        : 'text-primary'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {t(permission.labelKey)}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </section>
                );
            })}
        </div>
    );
}
