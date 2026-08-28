import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { EyeIcon, PlusIcon, SquarePenIcon, Trash2Icon, UsersIcon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import {
    PERMISSION_ACTION_META,
    PERMISSION_ACTIONS,
    permissionModuleIcon,
    type PermissionMatrixGroup,
} from '@/components/PermissionMatrix';
import { SearchInput } from '@/components/SearchInput';
import { TableActionButton } from '@/components/TableActionButton';
import { RoleFormDialog, type RoleFormItem } from '@/components/staff/RoleFormDialog';
import { Card } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { ToolbarIconButton } from '@/components/data-table/toolbar';
import { EDGE_PAD } from '@/components/data-table/styles';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { roleDescription, roleIcon } from '@/lib/staff-roles';
import { cn } from '@/lib/utils';

type RoleRow = RoleFormItem & {
    permissions_count: number;
    users_count: number;
};

type RolesIndexProps = {
    roles: RoleRow[];
    matrix: PermissionMatrixGroup[];
    filters: { search: string };
};

export default function RolesIndex({ roles, matrix, filters }: RolesIndexProps) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [pendingIds, setPendingIds] = useState<number[]>([]);
    const [formOpen, setFormOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<RoleFormItem | null>(null);
    const debounce = useRef<number>(0);
    const canUpdate = can('roles.update');
    const canDelete = can('roles.delete');

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const openEdit = (row: RoleRow) => {
        setEditingRole({
            id: row.id,
            name: row.name,
            is_locked: row.is_locked,
            permissions: row.permissions,
        });
        setFormOpen(true);
    };

    return (
        <>
            <Head title={t('menu.roles')} />
            <PageContent>
                <PageHeader />
                <Card className="flex min-h-0 flex-col gap-0 overflow-hidden border-0 py-0 shadow-[0_4px_16px_rgb(23_50_54/0.06)] dark:shadow-[0_4px_16px_rgb(0_0_0/0.22)]">
                    <div className={cn('flex flex-col gap-2.5 py-3 sm:flex-row sm:items-center', EDGE_PAD)}>
                        <SearchInput
                            value={search}
                            onChange={(value) => {
                                setSearch(value);
                                window.clearTimeout(debounce.current);
                                debounce.current = window.setTimeout(() => {
                                    router.get('/roles', { search: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
                                }, 300);
                            }}
                            placeholder={t('staff.search_roles')}
                            size="sm"
                            className="w-full sm:max-w-64"
                        />
                        {can('roles.create') ? (
                            <div className="flex shrink-0 items-center justify-end sm:ms-auto">
                                <ToolbarIconButton
                                    label={t('staff.create_role')}
                                    icon={PlusIcon}
                                    prominent
                                    onClick={() => {
                                        setEditingRole(null);
                                        setFormOpen(true);
                                    }}
                                />
                            </div>
                        ) : null}
                    </div>
                    <div className={cn(EDGE_PAD, 'grid gap-3 pb-4 lg:grid-cols-2')}>
                        {roles.length === 0 ? (
                            <p className="col-span-full py-12 text-center text-[13px] text-muted-foreground">{t('common.no_results')}</p>
                        ) : null}
                        {roles.map((role) => {
                            const Icon = roleIcon(role.name);

                            return (
                                <article
                                    key={role.id}
                                    className="flex items-start gap-2.5 rounded-[12px] border border-border/70 bg-white p-3 shadow-[0_2px_8px_rgb(23_50_54/0.06)] dark:bg-card dark:shadow-[0_2px_8px_rgb(0_0_0/0.22)] sm:p-4"
                                >
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-primary/12 text-primary">
                                        <Icon className="size-5" strokeWidth={1.8} />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <h2 className="truncate text-[15px] font-semibold text-primary">{role.name}</h2>
                                        <p className="mt-0.5 text-[12px] leading-5 text-muted-foreground">{roleDescription(role.name, t)}</p>
                                        <div className="mt-1.5 flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
                                            <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 font-medium text-primary">
                                                {role.permissions_count} {t('staff.permissions')}
                                            </span>
                                            <span className="inline-flex items-center gap-1">
                                                <UsersIcon className="size-3" strokeWidth={1.9} />
                                                {role.users_count} {t('staff.assigned_staff')}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1">
                                        <DropdownMenu>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <DropdownMenuTrigger asChild>
                                                        <button
                                                            type="button"
                                                            aria-label={t('common.view')}
                                                            className="inline-flex size-7 shrink-0 items-center justify-center rounded-[6px] bg-primary/12 text-primary leading-none transition-all duration-200 ease-out hover:scale-105 hover:bg-primary hover:text-primary-foreground hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:outline-none active:scale-95"
                                                        >
                                                            <EyeIcon className="size-3.5" strokeWidth={1.85} />
                                                            <span className="sr-only">{t('common.view')}</span>
                                                        </button>
                                                    </DropdownMenuTrigger>
                                                </TooltipTrigger>
                                                <TooltipContent side="top">{t('common.view')}</TooltipContent>
                                            </Tooltip>
                                            <DropdownMenuContent
                                                align="end"
                                                side="bottom"
                                                className="z-[90] w-[min(calc(100vw-2rem),420px)] max-h-[min(70vh,520px)] overflow-y-auto overflow-x-hidden p-2.5"
                                                onCloseAutoFocus={(event) => event.preventDefault()}
                                            >
                                                <p className="mb-2 px-0.5 text-[11px] font-medium text-muted-foreground">{t('staff.permissions')}</p>
                                                <RolePermissionPreview matrix={matrix} permissions={role.permissions} />
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                        {canUpdate ? (
                                            <TableActionButton
                                                label={t('common.edit')}
                                                icon={SquarePenIcon}
                                                tone="edit"
                                                onClick={() => openEdit(role)}
                                            />
                                        ) : null}
                                        {canDelete && ! role.is_locked ? (
                                            <TableActionButton
                                                label={t('common.delete')}
                                                icon={Trash2Icon}
                                                tone="danger"
                                                onClick={() => setPendingIds([role.id])}
                                            />
                                        ) : null}
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                </Card>
            </PageContent>
            <RoleFormDialog
                open={formOpen}
                onOpenChange={(open) => {
                    setFormOpen(open);

                    if (! open) {
                        setEditingRole(null);
                    }
                }}
                role={editingRole}
                matrix={matrix}
            />
            <ConfirmDialog
                open={pendingIds.length === 1}
                onOpenChange={(open) => {
                    if (! open) {
                        setPendingIds([]);
                    }
                }}
                title={t('staff.delete_role_title')}
                description={t('staff.delete_role_description')}
                destructive
                confirmLabel={t('common.delete')}
                onConfirm={() => {
                    if (pendingIds.length !== 1) {
                        return;
                    }

                    router.delete(`/roles/${pendingIds[0]}`, {
                        onFinish: () => setPendingIds([]),
                    });
                }}
            />
        </>
    );
}

function RolePermissionPreview({
    matrix,
    permissions,
}: {
    matrix: PermissionMatrixGroup[];
    permissions: string[];
}) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col gap-1.5">
            {matrix.map((group) => {
                const Icon = permissionModuleIcon(group.module);
                const selectedCount = group.permissions.filter((permission) => permissions.includes(permission.name)).length;

                return (
                    <div
                        key={group.module}
                        className="flex items-center gap-2 rounded-[10px] bg-[#f7f9fa] px-2 py-1.5 dark:bg-muted/20"
                    >
                        <span className="flex size-7 shrink-0 items-center justify-center rounded-[7px] bg-primary/12 text-primary">
                            <Icon className="size-3.5" strokeWidth={1.8} />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-[12px] font-semibold leading-[1.75] text-primary">{t(group.labelKey)}</p>
                            <p className="text-[10px] text-muted-foreground">
                                {selectedCount}/{group.permissions.length}
                            </p>
                        </div>
                        <div className="flex shrink-0 items-center gap-1">
                            {PERMISSION_ACTIONS.map((action) => {
                                const permission = group.permissions.find((item) => item.action === action);
                                const meta = PERMISSION_ACTION_META[action];
                                const ActionIcon = meta.icon;
                                const danger = Boolean(meta.danger);

                                if (! permission) {
                                    return <span key={action} className="size-7" />;
                                }

                                const checked = permissions.includes(permission.name);

                                return (
                                    <span
                                        key={action}
                                        title={`${t(group.labelKey)} · ${t(permission.labelKey)}`}
                                        className={cn(
                                            'inline-flex size-7 items-center justify-center rounded-[7px] border',
                                            checked
                                                ? danger
                                                    ? 'border-danger/35 bg-danger/10 text-danger'
                                                    : 'border-primary/40 bg-white text-primary shadow-[0_1px_2px_rgb(23_50_54/0.06)] dark:bg-card'
                                                : 'border-transparent bg-white/80 text-muted-foreground dark:bg-card/60',
                                        )}
                                    >
                                        {checked ? (
                                            <ActionIcon className="size-3" strokeWidth={2.2} />
                                        ) : (
                                            <ActionIcon className="size-3 opacity-45" strokeWidth={1.9} />
                                        )}
                                    </span>
                                );
                            })}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
