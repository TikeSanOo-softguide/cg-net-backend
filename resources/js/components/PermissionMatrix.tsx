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
        <div className="flex flex-col gap-3 sm:col-span-2">
            {groups.map((group) => {
                const names = group.permissions.map((permission) => permission.name);
                const selectedCount = names.filter((name) => value.includes(name)).length;
                const allSelected = selectedCount === names.length && names.length > 0;

                return (
                    <section
                        key={group.module}
                        className="overflow-hidden rounded-[6px] border border-border/80 bg-muted/20"
                    >
                        <div className="flex items-center justify-between gap-3 border-b border-border/60 px-3 py-2.5 sm:px-4">
                            <div className="min-w-0">
                                <h3 className="truncate text-sm font-semibold text-foreground">{t(group.labelKey)}</h3>
                                <p className="text-[11px] text-muted-foreground">
                                    {selectedCount}/{names.length}
                                </p>
                            </div>
                            <button
                                type="button"
                                disabled={locked}
                                onClick={() => toggleGroup(group)}
                                className={cn(
                                    'rounded-[6px] px-2.5 py-1 text-[11px] font-medium transition-colors',
                                    allSelected
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-primary/12 text-primary hover:bg-primary/18',
                                    locked && 'cursor-not-allowed opacity-70',
                                )}
                            >
                                {allSelected ? t('permissions.clear') : t('permissions.select_all')}
                            </button>
                        </div>
                        <div className="grid grid-cols-2 gap-1.5 p-3 sm:grid-cols-4 sm:px-4">
                            {group.permissions.map((permission) => {
                                const checked = value.includes(permission.name);

                                return (
                                    <label
                                        key={permission.name}
                                        className={cn(
                                            'flex cursor-pointer items-center gap-2 rounded-[6px] border px-2.5 py-2 text-sm transition-colors',
                                            checked
                                                ? 'border-primary/40 bg-primary/10 text-primary'
                                                : 'border-transparent bg-card text-foreground hover:border-primary/25 hover:bg-primary/6',
                                            locked && 'cursor-not-allowed',
                                        )}
                                    >
                                        <input
                                            type="checkbox"
                                            className="size-3.5 accent-primary"
                                            checked={checked}
                                            disabled={locked}
                                            onChange={() => toggle(permission.name)}
                                        />
                                        <span className="truncate font-medium">{t(permission.labelKey)}</span>
                                    </label>
                                );
                            })}
                        </div>
                    </section>
                );
            })}
        </div>
    );
}
