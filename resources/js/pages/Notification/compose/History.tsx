import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { BellIcon, EyeIcon } from 'lucide-react';

import { BackButton } from '@/components/BackButton';
import { DataTable, type DataTableColumn } from '@/components/DataTable';
import { FormDialog } from '@/components/FormDialog';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { FormField } from '@/components/ui/form-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TableActionButton } from '@/components/TableActionButton';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDateTime, truncateText } from '@/lib/utils';

type PushNotificationHistoryItem = {
    id: string;
    title_en: string;
    title_zh: string;
    title_my: string;
    status: 'pending' | 'sent' | 'failed' | 'no_recipients' | 'cancelled';
    delivery_type: 'scheduled' | 'immediate';
    scheduled_at: string | null;
    sent_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};

type Filters = {
    status: string;
    delivery_type: string;
    sort: string;
    direction: 'asc' | 'desc';
};

type Props = {
    notifications: Paginated<PushNotificationHistoryItem>;
    filters: Filters;
};

export default function PushNotificationHistory({ notifications, filters }: Props) {
    const { t, locale } = useTranslation();
    const [selectedNotification, setSelectedNotification] = useState<PushNotificationHistoryItem | null>(null);

    const visit = (next: Partial<Filters>) => {
        router.get(
            '/notifications/compose/history',
            {
                status: (next.status ?? filters.status) || undefined,
                delivery_type: (next.delivery_type ?? filters.delivery_type) || undefined,
                sort: (next.sort ?? filters.sort) || undefined,
                direction: next.direction ?? filters.direction,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const title = (item: PushNotificationHistoryItem): string => {
        if (locale === 'my') return item.title_my || item.title_en || '—';
        if (locale === 'zh') return item.title_zh || item.title_en || '—';
        return item.title_en || '—';
    };

    const columns: DataTableColumn<PushNotificationHistoryItem>[] = [
        {
            id: 'title',
            header: t('notification.push.title'),
            className: 'font-medium',
            mobile: 'title',
            cell: (item) => truncateText(title(item), 60),
        },
        {
            id: 'delivery_type',
            header: t('notification.push.delivery_type'),
            cell: (item) => t(`notification.push.delivery_${item.delivery_type}`),
        },
        {
            id: 'status',
            header: t('common.status'),
            cell: (item) => <StatusBadge status={item.status} />,
        },
        {
            id: 'scheduled_at',
            header: t('notification.push.scheduled_at'),
            className: 'text-muted-foreground',
            sortable: true,
            cell: (item) => formatDateTime(item.scheduled_at),
        },
        {
            id: 'sent_at',
            header: t('notification.push.sent_at'),
            className: 'text-muted-foreground',
            cell: (item) => formatDateTime(item.sent_at),
        },
    ];

    return (
        <>
            <Head title={t('notification.push.history_title')} />
            <PageContent>
                <PageHeader
                    title={t('notification.push.history_title')}
                    description={t('notification.push.history_description')}
                    actions={<BackButton href="/notifications/compose" />}
                />
                <DataTable
                    data={notifications.data}
                    columns={columns}
                    getRowId={(item) => item.id}
                    showSearch={false}
                    pagination={notifications}
                    sort={filters.sort}
                    direction={filters.direction}
                    onSort={(column) => {
                        if (filters.sort !== column) {
                            visit({ sort: column, direction: 'asc' });
                            return;
                        }
                        if (filters.direction === 'asc') {
                            visit({ sort: column, direction: 'desc' });
                            return;
                        }
                        visit({ sort: '', direction: 'desc' });
                    }}
                    emptyLabel={t('notification.push.history_empty')}
                    directActions
                    actions={(item) => (
                        <TableActionButton
                            label={t('common.view')}
                            icon={EyeIcon}
                            onClick={(event) => {
                                event.stopPropagation();
                                setSelectedNotification(item);
                            }}
                        />
                    )}
                    filters={
                        <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
                            <FormField
                                label={t('notification.push.delivery_type')}
                                htmlFor="push-history-delivery-type"
                                className="w-full shrink-0 sm:w-48 mr-3"
                                labelClassName="text-[13px]"
                            >
                                <Select
                                    value={filters.delivery_type || 'all'}
                                    onValueChange={(value) => visit({ delivery_type: value === 'all' ? '' : value })}
                                >
                                    <SelectTrigger id="push-history-delivery-type">
                                        <SelectValue placeholder={t('notification.push.delivery_type')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        {(['immediate', 'scheduled'] as const).map((deliveryType) => (
                                            <SelectItem key={deliveryType} value={deliveryType}>
                                                {t(`notification.push.delivery_${deliveryType}`)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label={t('common.status')}
                                htmlFor="push-history-status"
                                className="w-full shrink-0 sm:w-40 mr-3"
                                labelClassName="text-[13px]"
                            >
                                <Select
                                    value={filters.status || 'all'}
                                    onValueChange={(value) => visit({ status: value === 'all' ? '' : value })}
                                >
                                    <SelectTrigger id="push-history-status">
                                        <SelectValue placeholder={t('common.status')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        {(['pending', 'sent', 'failed', 'no_recipients', 'cancelled'] as const).map(
                                            (status) => (
                                                <SelectItem key={status} value={status}>
                                                    {t(`status.${status}`)}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>
                    }
                />
            </PageContent>
            <FormDialog
                open={selectedNotification !== null}
                onOpenChange={(open) => !open && setSelectedNotification(null)}
                title={t('notification.push.details')}
                icon={BellIcon}
                size="lg"
            >
                {selectedNotification ? (
                    <div className="space-y-3 overflow-y-auto p-4 sm:p-5">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <PushNotificationDetail
                                label={t('notification.push.delivery_type')}
                                value={t(`notification.push.delivery_${selectedNotification.delivery_type}`)}
                            />
                            <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5">
                                <p className="text-xs font-medium text-muted-foreground">{t('common.status')}</p>
                                <StatusBadge status={selectedNotification.status} className="mt-1" />
                            </div>
                            <PushNotificationDetail
                                label={t('notification.push.title_en')}
                                value={selectedNotification.title_en}
                            />
                            <PushNotificationDetail
                                label={t('notification.push.title_my')}
                                value={selectedNotification.title_my}
                            />
                            <PushNotificationDetail
                                label={t('notification.push.title_zh')}
                                value={selectedNotification.title_zh}
                            />
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <PushNotificationDetail
                                label={t('notification.push.scheduled_at')}
                                value={formatDateTime(selectedNotification.scheduled_at)}
                            />
                            <PushNotificationDetail
                                label={t('notification.push.sent_at')}
                                value={formatDateTime(selectedNotification.sent_at)}
                            />
                            <PushNotificationDetail
                                label={t('common.created_at')}
                                value={formatDateTime(selectedNotification.created_at)}
                            />
                            <PushNotificationDetail
                                label={t('common.updated_at')}
                                value={formatDateTime(selectedNotification.updated_at)}
                            />
                        </div>
                    </div>
                ) : null}
            </FormDialog>
        </>
    );
}

function PushNotificationDetail({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-1 break-words text-sm font-semibold text-foreground">{value || '—'}</p>
        </div>
    );
}
