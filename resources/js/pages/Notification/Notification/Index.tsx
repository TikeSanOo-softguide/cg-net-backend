import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    BellIcon,
    CalendarIcon,
    CheckIcon,
    CircleDotIcon,
    ClipboardListIcon,
    EyeIcon,
    UserRoundIcon,
} from 'lucide-react';

import { DataTable, type DataTableColumn } from '@/components/DataTable';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import type { Paginated } from '@/components/Pagination';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { DatePicker } from '@/components/ui/date-picker';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDateTime } from '@/lib/utils';
import type { AdminNotification } from '@/types';

type NotificationRow = AdminNotification & {
    customer_name: string | null;
    request_status: string | null;
};

type Filters = {
    search: string;
    type: string;
    request_type: string;
    status: string;
    from: string;
    to: string;
};

type Props = {
    notifications: Paginated<NotificationRow>;
    categories: string[];
    filters: Filters;
};

const requestTypes = [
    'installation_application',
    'change_password_request',
    'change_plan_request',
    'failure_report',
    'relocation_request',
] as const;

export default function AdminNotificationsIndex({ notifications, categories, filters }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const debounce = useRef<number>(0);

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const visit = (next: Partial<Filters>) => {
        window.clearTimeout(debounce.current);
        router.get(
            '/dashboard/notifications',
            {
                search: (next.search ?? search) || undefined,
                type: (next.type ?? filters.type) || undefined,
                request_type: (next.request_type ?? filters.request_type) || undefined,
                status: (next.status ?? filters.status) || undefined,
                from: (next.from ?? filters.from) || undefined,
                to: (next.to ?? filters.to) || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const updateSearch = (value: string) => {
        setSearch(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(() => visit({ search: value }), 300);
    };

    const notificationHref = (notification: NotificationRow): string | undefined => {
        if (!notification.href) {
            return undefined;
        }

        return notification.reference_id === null
            ? notification.href
            : `${notification.href}?status=all&open_request=${notification.reference_id}`;
    };

    const categoryLabel = (category: string): string => {
        const translationKey = `admin_notifications.categories.${category}`;
        const translated = t(translationKey);

        return translated === translationKey
            ? category.replace(/[_-]+/g, ' ').replace(/\b[a-z]/g, (letter) => letter.toUpperCase())
            : translated;
    };

    const columns: DataTableColumn<NotificationRow>[] = [
        {
            id: 'customer_name',
            header: t('admin_notifications.customer_name'),
            cell: (row) => row.customer_name || '—',
        },
        {
            id: 'title',
            header: t('common.notifications'),
            className: 'font-medium',
            cell: (row) => (
                <div className="max-w-sm">
                    <p className="truncate">{row.title}</p>
                    <p className="truncate text-xs font-normal text-muted-foreground">{row.message}</p>
                </div>
            ),
        },
        {
            id: 'type',
            header: t('admin_notifications.category'),
            cell: (row) => categoryLabel(row.type),
        },
        {
            id: 'request_type',
            header: t('admin_notifications.request_type'),
            cell: (row) => (row.reference_type ? t(`admin_notifications.request_types.${row.reference_type}`) : '—'),
        },
        {
            id: 'request_status',
            header: t('admin_notifications.request_status'),
            cell: (row) => (row.request_status ? <StatusBadge status={row.request_status} /> : '—'),
        },
        {
            id: 'status',
            header: t('common.status'),
            cell: (row) => <StatusBadge status={row.read_at === null ? 'unread' : 'read'} />,
        },
        {
            id: 'created_at',
            header: t('dashboard.date'),
            className: 'text-muted-foreground',
            cell: (row) => formatDateTime(row.created_at),
        },
    ];

    return (
        <>
            <Head title={t('common.notifications')} />
            <PageContent>
                <PageHeader title={t('common.notifications')} description={t('admin_notifications.description')} />
                <DataTable
                    data={notifications.data}
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    search={search}
                    onSearchChange={updateSearch}
                    showSearch={false}
                    pagination={notifications}
                    emptyLabel={t('admin_notifications.empty')}
                    directActions
                    filters={
                        <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
                            <SearchInput
                                value={search}
                                onChange={updateSearch}
                                placeholder={t('admin_notifications.search_placeholder')}
                                size="sm"
                                className="w-full shrink-0 sm:max-w-64"
                            />
                            <FormControl icon={BellIcon} compact className="w-full shrink-0 sm:w-48">
                                <Select
                                    value={filters.type || 'all'}
                                    onValueChange={(value) => visit({ type: value === 'all' ? '' : value })}
                                >
                                    <SelectTrigger
                                        className="h-8 text-[11px]"
                                        aria-label={t('admin_notifications.category')}
                                    >
                                        <SelectValue placeholder={t('admin_notifications.category')} />
                                    </SelectTrigger>
                                    <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        {categories.map((category) => (
                                            <SelectItem key={category} value={category}>
                                                {categoryLabel(category)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormControl icon={ClipboardListIcon} compact className="w-full shrink-0 sm:w-52">
                                <Select
                                    value={filters.request_type || 'all'}
                                    onValueChange={(value) => visit({ request_type: value === 'all' ? '' : value })}
                                >
                                    <SelectTrigger
                                        className="h-8 text-[11px]"
                                        aria-label={t('admin_notifications.request_type')}
                                    >
                                        <SelectValue placeholder={t('admin_notifications.request_type')} />
                                    </SelectTrigger>
                                    <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        {requestTypes.map((type) => (
                                            <SelectItem key={type} value={type}>
                                                {t(`admin_notifications.request_types.${type}`)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormControl icon={CircleDotIcon} compact className="w-full shrink-0 sm:w-48">
                                <Select
                                    value={filters.status || 'all'}
                                    onValueChange={(value) => visit({ status: value === 'all' ? '' : value })}
                                >
                                    <SelectTrigger
                                        className="h-8 text-[11px]"
                                        aria-label={t('admin_notifications.read_status')}
                                    >
                                        <SelectValue placeholder={t('admin_notifications.read_status')} />
                                    </SelectTrigger>
                                    <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        <SelectItem value="unread">{t('admin_notifications.unread')}</SelectItem>
                                        <SelectItem value="read">{t('admin_notifications.read')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormControl icon={CalendarIcon} compact className="w-full shrink-0 sm:w-40">
                                <DatePicker
                                    id="admin-notification-from"
                                    value={filters.from}
                                    max={filters.to || undefined}
                                    placeholder={t('common.start_date')}
                                    aria-label={t('common.start_date')}
                                    className="h-8 text-[11px]"
                                    onChange={(value) => visit({ from: value })}
                                />
                            </FormControl>
                            <FormControl icon={CalendarIcon} compact className="w-full shrink-0 sm:w-40">
                                <DatePicker
                                    id="admin-notification-to"
                                    value={filters.to}
                                    min={filters.from || undefined}
                                    placeholder={t('common.end_date')}
                                    aria-label={t('common.end_date')}
                                    className="h-8 text-[11px]"
                                    onChange={(value) => visit({ to: value })}
                                />
                            </FormControl>
                        </div>
                    }
                    actions={(row) => (
                        <div className="flex items-center gap-1">
                            {row.href ? (
                                <TableActionButton
                                    label={t('common.view')}
                                    icon={EyeIcon}
                                    href={notificationHref(row)}
                                />
                            ) : null}
                            {row.read_at === null ? (
                                <TableActionButton
                                    label={t('admin_notifications.mark_read')}
                                    icon={CheckIcon}
                                    tone="success"
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        router.put(
                                            `/dashboard/notifications/${row.id}/read`,
                                            {},
                                            { preserveScroll: true },
                                        );
                                    }}
                                />
                            ) : null}
                        </div>
                    )}
                />
            </PageContent>
        </>
    );
}
