import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { BellRingIcon, CalendarIcon } from 'lucide-react';

import { DataTable, type DataTableColumn } from '@/components/DataTable';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import type { Paginated } from '@/components/Pagination';
import { SearchInput } from '@/components/SearchInput';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { FormField } from '@/components/ui/form-field';
import { useCan } from '@/hooks/useCan';
import { toast } from '@/hooks/use-toast';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDate } from '@/lib/utils';

type Alert = {
    user_id: number;
    customer_name: string;
    account_number: string;
    due_date: string;
    action_id: string;
    notification_id: number | null;
    state: 'not_recorded' | 'unsent' | 'sent';
    has_device: boolean;
};

type Props = {
    alerts: Paginated<Alert>;
    filters: { search: string; due_date: string };
};

export default function BillDueAlertsIndex({ alerts, filters }: Props) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [dueDate, setDueDate] = useState(filters.due_date);
    const [workingIds, setWorkingIds] = useState<Set<string>>(() => new Set());
    const [queuedIds, setQueuedIds] = useState<Set<string>>(() => new Set());
    const debounce = useRef<number>(0);
    const maySend = can('notifications.create');

    useEffect(() => setSearch(filters.search), [filters.search]);
    useEffect(() => setDueDate(filters.due_date), [filters.due_date]);
    useEffect(() => () => window.clearTimeout(debounce.current), []);
    useEffect(() => {
        const sentIds = new Set(
            alerts.data.filter((row) => row.state === 'sent').map((row) => `${row.user_id}-${row.action_id}`),
        );
        if (sentIds.size === 0) {
            return;
        }

        setQueuedIds((current) => new Set([...current].filter((id) => !sentIds.has(id))));
    }, [alerts.data]);

    const visit = (nextSearch: string, nextDueDate: string) => {
        window.clearTimeout(debounce.current);
        router.get(
            '/notifications/bill-due-alerts',
            {
                search: nextSearch || undefined,
                due_date: nextDueDate || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const updateSearch = (value: string) => {
        setSearch(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(() => visit(value, dueDate), 300);
    };

    const updateDueDate = (value: string) => {
        window.clearTimeout(debounce.current);
        setDueDate(value);
        visit(search, value);
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
                    due_date: row.due_date,
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
            id: 'due_date',
            header: t('notification.bill_due_alerts.due_date'),
            mobile: 'meta',
            cell: (row) => formatDate(row.due_date),
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

                return row.state === 'sent' ? null : (
                    <Button
                        size="sm"
                        className="w-25 justify-center whitespace-nowrap"
                        disabled={workingIds.has(id) || queued || !row.has_device}
                        onClick={() => queueAlert(row)}
                    >
                        <BellRingIcon />
                        {t(
                            queued
                                ? 'notification.bill_due_alerts.states.queued'
                                : row.state === 'unsent'
                                  ? 'notification.bill_due_alerts.retry'
                                  : 'notification.bill_due_alerts.send',
                        )}
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
                <DataTable
                    title={t('notification.bill_due_alerts.results_title')}
                    description={t('notification.bill_due_alerts.results_description')}
                    data={alerts.data}
                    columns={columns}
                    getRowId={(row) => `${row.user_id}-${row.action_id}`}
                    emptyLabel={t('notification.bill_due_alerts.no_due_bills')}
                    pagination={alerts}
                    showSearch={false}
                    filters={
                        <>
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
                            <FormField
                                label={t('notification.bill_due_alerts.due_date')}
                                htmlFor="bill-due-alert-date"
                                icon={CalendarIcon}
                                className="mr-3 w-full shrink-0 sm:w-48"
                                labelClassName="text-[13px]"
                            >
                                <DatePicker
                                    id="bill-due-alert-date"
                                    value={dueDate}
                                    placeholder={t('notification.bill_due_alerts.due_date')}
                                    aria-label={t('notification.bill_due_alerts.due_date')}
                                    className="h-8 text-[11px]"
                                    onChange={updateDueDate}
                                />
                            </FormField>
                        </>
                    }
                />
            </PageContent>
        </>
    );
}
