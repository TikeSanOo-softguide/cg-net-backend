import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { BellRingIcon, CircleAlert, RotateCcwIcon, SendIcon } from 'lucide-react';

import { DataTable, type DataTableColumn } from '@/components/DataTable';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import type { Paginated } from '@/components/Pagination';
import { SearchInput } from '@/components/SearchInput';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { useCan } from '@/hooks/useCan';
import { toast } from '@/hooks/use-toast';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDate } from '@/lib/utils';

type Alert = {
    user_id: number;
    customer_name: string;
    account_number: string;
    endtime: string;
    action_id: string;
    notification_id: number | null;
    state: 'not_recorded' | 'unsent' | 'sent';
    has_device: boolean;
};

type Props = {
    alerts: Paginated<Alert>;
    errorKey: string | null;
    filters: { search: string };
};

export default function BillDueAlertsIndex({ alerts, errorKey, filters }: Props) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [workingIds, setWorkingIds] = useState<Set<string>>(() => new Set());
    const [queuedIds, setQueuedIds] = useState<Set<string>>(() => new Set());
    const debounce = useRef<number>(0);
    const maySend = can('notifications.create');

    useEffect(() => setSearch(filters.search), [filters.search]);
    useEffect(() => () => window.clearTimeout(debounce.current), []);
    useEffect(() => {
        const recordedIds = new Set(
            alerts.data
                .filter((row) => row.state === 'sent' || (row.state === 'unsent' && !row.has_device))
                .map((row) => `${row.user_id}-${row.action_id}`),
        );
        if (recordedIds.size === 0) {
            return;
        }

        setQueuedIds((current) => new Set([...current].filter((id) => !recordedIds.has(id))));
    }, [alerts.data]);

    const visit = (nextSearch: string) => {
        window.clearTimeout(debounce.current);
        router.get(
            '/notifications/bill-due-alerts',
            {
                search: nextSearch || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const updateSearch = (value: string) => {
        setSearch(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(() => visit(value), 300);
    };

    const queueAlert = async (row: Alert) => {
        const id = `${row.user_id}-${row.action_id}`;
        setWorkingIds((current) => new Set(current).add(id));

        try {
            const xsrfCookie = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='));
            const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie.slice('XSRF-TOKEN='.length)) : '';
            const response = await fetch('/notifications/bill-due-alerts/send', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                },
                body: JSON.stringify({
                    user_id: row.user_id,
                    account_number: row.account_number,
                    endtime: row.endtime,
                    action_id: row.action_id,
                }),
            });
            const payload = await response.json().catch(() => null);

            if (!response.ok) {
                throw new Error(payload?.message ?? t('toast.error'));
            }

            setQueuedIds((current) => new Set(current).add(id));
            toast({
                variant: 'success',
                title: t('toast.success'),
                description: payload?.message ?? t('notification.bill_due_alerts.queued'),
            });
            router.reload({ only: ['alerts'] });
        } catch (error) {
            toast({
                variant: 'error',
                title: t('toast.error'),
                description: error instanceof Error ? error.message : t('toast.error'),
            });
        } finally {
            setWorkingIds((current) => new Set([...current].filter((workingId) => workingId !== id)));
        }
    };

    const columns: DataTableColumn<Alert>[] = [
        {
            id: 'customer_name',
            header: t('notification.bill_due_alerts.customer'),
            className: 'font-medium',
            mobile: 'title',
            cell: (row) => row.customer_name,
        },
        {
            id: 'account_number',
            header: t('notification.bill_due_alerts.account_number'),
            mobile: 'subtitle',
            cell: (row) => row.account_number,
        },
        {
            id: 'endtime',
            header: t('notification.bill_due_alerts.end_time'),
            mobile: 'meta',
            cell: (row) => `${formatDate(row.endtime)} ${row.endtime.slice(11)}`,
        },
        {
            id: 'record_status',
            header: t('notification.bill_due_alerts.record_status'),
            mobile: 'badge',
            cell: (row) =>
                t(
                    `notification.bill_due_alerts.states.${queuedIds.has(`${row.user_id}-${row.action_id}`) ? 'queued' : row.state}`,
                ),
        },
        {
            id: 'device',
            header: t('notification.bill_due_alerts.device'),
            mobile: false,
            cell: (row) =>
                t(
                    row.has_device
                        ? 'notification.bill_due_alerts.device_available'
                        : 'notification.bill_due_alerts.no_device',
                ),
        },
    ];

    if (maySend) {
        columns.push({
            id: 'actions',
            header: t('common.actions'),
            headerClassName: 'w-44 text-right',
            headerLabelClassName: 'w-full justify-end',
            className: 'w-44 whitespace-nowrap text-right',
            mobile: false,
            cell: (row) => {
                const id = `${row.user_id}-${row.action_id}`;
                const queued = queuedIds.has(id);
                const actionLabel = queued
                    ? 'notification.bill_due_alerts.states.queued'
                    : row.state === 'unsent'
                      ? row.has_device
                          ? 'notification.bill_due_alerts.retry'
                          : 'notification.bill_due_alerts.send'
                      : row.has_device
                        ? 'notification.bill_due_alerts.send'
                        : 'notification.bill_due_alerts.alert';
                const ActionIcon =
                    row.state === 'unsent'
                        ? row.has_device
                            ? RotateCcwIcon
                            : SendIcon
                        : row.has_device
                          ? SendIcon
                          : BellRingIcon;

                return row.state === 'sent' ? null : (
                    <Button
                        size="sm"
                        className="w-auto shrink-0 gap-1.5 px-2.5 text-[11px]"
                        disabled={workingIds.has(id) || queued || (row.state === 'unsent' && !row.has_device)}
                        onClick={() => queueAlert(row)}
                    >
                        <ActionIcon />
                        {t(actionLabel)}
                    </Button>
                );
            },
        });
    }

    return (
        <>
            <Head title={t('menu.bill_due_alerts')} />
            <PageContent>
                <PageHeader title={t('menu.bill_due_alerts')} />
                {errorKey ? (
                    <div
                        role="alert"
                        className="flex w-full items-center gap-2 rounded-md ring-1 ring-inset px-3 py-2.5 text-sm font-semibold ring-danger bg-danger/10 text-danger"
                    >
                        <CircleAlert className="size-4 shrink-0" />
                        <span>{t(errorKey)}</span>
                    </div>
                ) : null}
                <DataTable
                    title={t('notification.bill_due_alerts.results_title')}
                    description={t('notification.bill_due_alerts.results_description')}
                    data={alerts.data}
                    columns={columns}
                    getRowId={(row) => `${row.user_id}-${row.action_id}`}
                    emptyLabel={errorKey ? t('common.no_results') : t('notification.bill_due_alerts.no_due_bills')}
                    pagination={alerts}
                    showSearch={false}
                    filters={
                        <FormField
                            label={t('common.search')}
                            htmlFor="bill-due-alert-search"
                            className="mr-3 w-full shrink-0 sm:w-60"
                            labelClassName="text-[13px]"
                        >
                            <SearchInput
                                id="bill-due-alert-search"
                                value={search}
                                onChange={updateSearch}
                                placeholder={t('notification.bill_due_alerts.search_placeholder')}
                                ariaLabel={t('common.search')}
                                size="sm"
                            />
                        </FormField>
                    }
                />
            </PageContent>
        </>
    );
}
