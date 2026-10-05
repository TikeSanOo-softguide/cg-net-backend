import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { CalendarIcon, CircleDotIcon } from 'lucide-react';

import { DataTable, type DataTableColumn } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { DatePicker } from '@/components/ui/date-picker';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { useCan } from '@/hooks/useCan';
import { formatTopUpAmount } from '@/lib/top-up-cards';
import { formatDateTime } from '@/lib/utils';

type BillPaymentRow = {
    id: number;
    transaction_no: string | null;
    amount: number | null;
    customer_name: string | null;
    customer_phone: string | null;
    broadband_account_number: string | null;
    status: string;
    external_bill_ref: string | null;
    external_payment_ref: string | null;
    created_at: string | null;
    confirmed_at: string | null;
};

type Filters = {
    search: string;
    status: string;
    payment_id: number;
    from: string;
    to: string;
};

type Props = {
    payments: Paginated<BillPaymentRow>;
    filters: Filters;
    statuses: string[];
};

export default function BillPaymentIndex({ payments, filters, statuses }: Props) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const debounce = useRef<number>(0);

    useEffect(() => setSearch(filters.search), [filters.search]);
    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const visit = (next: Partial<Filters>) => {
        window.clearTimeout(debounce.current);
        router.get(
            '/billing/bill-payments',
            {
                search: (next.search ?? search) || undefined,
                status: (next.status ?? filters.status) || undefined,
                payment_id: filters.payment_id || undefined,
                from: (next.from ?? filters.from) || undefined,
                to: (next.to ?? filters.to) || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const exportPayments = () => {
        const query = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value) query.set(key, String(value));
        });
        window.location.href = `/billing/bill-payments/export?${query.toString()}`;
    };

    const columns: DataTableColumn<BillPaymentRow>[] = [
        {
            id: 'payment_id',
            header: t('transactions.detail_fields.bill_payment_id'),
            cell: (payment) => payment.id,
        },
        {
            id: 'transaction_no',
            header: t('transactions.number'),
            className: 'font-medium',
            cell: (payment) => payment.transaction_no || '—',
        },
        {
            id: 'customer',
            header: t('transactions.customer'),
            cell: (payment) => (
                <div className="flex flex-col">
                    <span>{payment.customer_name || '—'}</span>
                    {payment.customer_phone ? (
                        <span className="text-xs text-muted-foreground">{payment.customer_phone}</span>
                    ) : null}
                </div>
            ),
        },
        {
            id: 'account',
            header: t('bill_payments.account_number'),
            cell: (payment) => payment.broadband_account_number || '—',
        },
        {
            id: 'amount',
            header: t('transactions.amount'),
            className: 'font-medium tabular-nums',
            cell: (payment) => (payment.amount === null ? '—' : formatTopUpAmount(payment.amount)),
        },
        {
            id: 'status',
            header: t('common.status'),
            cell: (payment) => <StatusBadge status={payment.status} />,
        },
        {
            id: 'reference',
            header: t('bill_payments.payment_reference'),
            cell: (payment) => payment.external_payment_ref || payment.external_bill_ref || '—',
        },
        {
            id: 'confirmed_at',
            header: t('bill_payments.confirmed_at'),
            cell: (payment) => formatDateTime(payment.confirmed_at ?? payment.created_at),
        },
    ];

    return (
        <>
            <Head title={t('menu.bill_payment')} />
            <PageContent>
                <PageHeader title={t('menu.bill_payment')} description={t('menu.bill_payment_description')} />
                <DataTable
                    data={payments.data}
                    columns={columns}
                    getRowId={(payment) => String(payment.id)}
                    showSearch={false}
                    showExport={can('system.export')}
                    onExport={can('system.export') ? exportPayments : undefined}
                    pagination={payments}
                    emptyLabel={t('bill_payments.empty')}
                    filters={
                        <div className="flex w-full flex-wrap items-center gap-2">
                            <FormControl icon={CircleDotIcon} compact className="w-full shrink-0 sm:w-40">
                                <Select
                                    value={filters.status || 'all'}
                                    onValueChange={(value) => visit({ status: value === 'all' ? '' : value })}
                                >
                                    <SelectTrigger id="bill-payment-status" aria-label={t('common.status')}>
                                        <SelectValue placeholder={t('common.status')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        {statuses.map((status) => (
                                            <SelectItem key={status} value={status}>
                                                {t(`status.${status}`)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormControl icon={CalendarIcon} compact className="w-full shrink-0 sm:w-40">
                                <DatePicker
                                    id="bill-payment-from"
                                    value={filters.from}
                                    max={filters.to || undefined}
                                    placeholder={t('common.start_date')}
                                    aria-label={t('common.start_date')}
                                    onChange={(value) => visit({ from: value })}
                                />
                            </FormControl>
                            <FormControl icon={CalendarIcon} compact className="w-full shrink-0 sm:w-40">
                                <DatePicker
                                    id="bill-payment-to"
                                    value={filters.to}
                                    min={filters.from || undefined}
                                    placeholder={t('common.end_date')}
                                    aria-label={t('common.end_date')}
                                    onChange={(value) => visit({ to: value })}
                                />
                            </FormControl>
                        </div>
                    }
                />
            </PageContent>
        </>
    );
}
