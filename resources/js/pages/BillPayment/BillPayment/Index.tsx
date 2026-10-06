import { useEffect, useRef, useState, type ReactElement } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarIcon, CircleDotIcon, EyeIcon, ReceiptIcon } from 'lucide-react';

import { DataTable, type DataTableColumn } from '@/components/DataTable';
import { BackButton } from '@/components/BackButton';
import { FormDialog } from '@/components/FormDialog';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { DatePicker } from '@/components/ui/date-picker';
import { FormField } from '@/components/ui/form-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { useCan } from '@/hooks/useCan';
import { useReturnTo } from '@/hooks/useReturnTo';
import { formatTopUpAmount } from '@/lib/top-up-cards';
import { formatDateTime } from '@/lib/utils';
import { formatPhoneInternational } from '@/lib/phone';

type BillPaymentRow = {
    id: number;
    ledger_transaction_id: number;
    transaction_no: string | null;
    amount: number | null;
    customer_id: number | null;
    customer_name: string | null;
    customer_phone: string | null;
    broadband_account_number: string | null;
    status: string;
    external_bill_ref: string | null;
    external_payment_ref: string | null;
    external_response: unknown;
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
    return_to?: string | null;
};

function transactionHref(transactionNo: string): string {
    const query = new URLSearchParams({
        search: transactionNo,
        open_transaction: transactionNo,
        return_to: `${window.location.pathname}${window.location.search}`,
    });

    return `/billing/transactions?${query.toString()}`;
}

export default function BillPaymentIndex({ payments, filters, statuses }: Props) {
    const { t } = useTranslation();
    const can = useCan();
    const returnTo = useReturnTo('');
    const [search, setSearch] = useState(filters.search);
    const [selectedPayment, setSelectedPayment] = useState<BillPaymentRow | null>(null);
    const debounce = useRef<number>(0);

    useEffect(() => setSearch(filters.search), [filters.search]);
    useEffect(() => {
        if (filters.payment_id <= 0) return;

        const payment = payments.data.find((row) => row.id === filters.payment_id);
        if (payment) setSelectedPayment(payment);
    }, [filters.payment_id, payments.data]);
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
                return_to: returnTo || undefined,
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
            id: 'transaction_no',
            header: t('transactions.number'),
            cell: (payment) =>
                payment.transaction_no ? (
                    <Link
                        href={transactionHref(payment.transaction_no)}
                        className="text-primary underline-offset-4 hover:underline"
                    >
                        {payment.transaction_no}
                    </Link>
                ) : (
                    '—'
                ),
        },
        {
            id: 'external_bill_ref',
            header: t('bill_payments.external_bill_ref'),
            cell: (payment) => payment.external_bill_ref || '—',
        },
        {
            id: 'external_payment_ref',
            header: t('bill_payments.external_payment_ref'),
            cell: (payment) => payment.external_payment_ref || '—',
        },

        {
            id: 'account',
            header: t('bill_payments.account_number'),
            cell: (payment) => payment.broadband_account_number || '—',
        },
        {
            id: 'customer',
            header: t('transactions.customer'),
            cell: (payment) =>
                payment.customer_id && payment.customer_name ? (
                    <Link
                        href={`/customers/${payment.customer_id}`}
                        className="text-primary underline-offset-4 hover:underline"
                    >
                        {payment.customer_name}
                    </Link>
                ) : (
                    payment.customer_name || '—'
                ),
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
            id: 'confirmed_at',
            header: t('bill_payments.confirmed_at'),
            cell: (payment) => (payment.confirmed_at ? formatDateTime(payment.confirmed_at) : '—'),
        },
    ];

    return (
        <>
            <Head title={t('menu.bill_payment')} />
            <PageContent>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <PageHeader title={t('menu.bill_payment')} description={t('menu.bill_payment_description')} />
                    {returnTo ? <BackButton href={returnTo} /> : null}
                </div>
                <DataTable
                    data={payments.data}
                    columns={columns}
                    getRowId={(payment) => String(payment.id)}
                    showSearch={false}
                    showExport={can('system.export')}
                    onExport={can('system.export') ? exportPayments : undefined}
                    pagination={payments}
                    emptyLabel={t('bill_payments.empty')}
                    directActions
                    actions={(payment) => (
                        <TableActionButton
                            label={t('common.view')}
                            icon={EyeIcon}
                            onClick={(event) => {
                                event.stopPropagation();
                                setSelectedPayment(payment);
                            }}
                        />
                    )}
                    filters={
                        <div className="flex w-full flex-wrap items-start gap-2">
                            <FormField
                                label={t('common.status')}
                                htmlFor="bill-payment-status"
                                icon={CircleDotIcon}
                                className="w-full shrink-0 sm:w-40"
                                labelClassName="text-[11px]"
                            >
                                <Select
                                    value={filters.status || 'all'}
                                    onValueChange={(value) => visit({ status: value === 'all' ? '' : value })}
                                >
                                    <SelectTrigger id="bill-payment-status">
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
                            </FormField>
                            <FormField
                                label={t('common.start_date')}
                                htmlFor="bill-payment-from"
                                icon={CalendarIcon}
                                className="w-full shrink-0 sm:w-40"
                                labelClassName="text-[11px]"
                            >
                                <DatePicker
                                    id="bill-payment-from"
                                    value={filters.from}
                                    max={filters.to || undefined}
                                    placeholder={t('common.start_date')}
                                    onChange={(value) => visit({ from: value })}
                                />
                            </FormField>
                            <FormField
                                label={t('common.end_date')}
                                htmlFor="bill-payment-to"
                                icon={CalendarIcon}
                                className="w-full shrink-0 sm:w-40"
                                labelClassName="text-[11px]"
                            >
                                <DatePicker
                                    id="bill-payment-to"
                                    value={filters.to}
                                    min={filters.from || undefined}
                                    placeholder={t('common.end_date')}
                                    onChange={(value) => visit({ to: value })}
                                />
                            </FormField>
                        </div>
                    }
                />
            </PageContent>
            <BillPaymentDetailDialog
                payment={selectedPayment}
                open={selectedPayment !== null}
                onOpenChange={(open) => {
                    if (!open) setSelectedPayment(null);
                }}
            />
        </>
    );
}

function BillPaymentDetailDialog({
    payment,
    open,
    onOpenChange,
}: {
    payment: BillPaymentRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    if (!payment) return null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('bill_payments.details')}
            description={payment.transaction_no || t('bill_payments.details_description')}
            icon={ReceiptIcon}
            size="lg"
        >
            <div className="space-y-3 overflow-y-auto p-4 sm:p-5">
                <div className="grid gap-3 sm:grid-cols-2">
                    <PaymentInfo label={t('bill_payments.bill_payment_id')} value={payment.id} />
                    <PaymentInfo
                        label={t('transactions.number')}
                        value={payment.transaction_no}
                        href={payment.transaction_no ? transactionHref(payment.transaction_no) : undefined}
                    />
                    <PaymentInfo label={t('bill_payments.external_bill_ref')} value={payment.external_bill_ref} />
                    <PaymentInfo label={t('bill_payments.external_payment_ref')} value={payment.external_payment_ref} />
                    <PaymentInfo
                        label={t('bill_payments.ledger_transaction_id')}
                        value={payment.ledger_transaction_id}
                    />

                    <PaymentInfo label={t('bill_payments.account_number')} value={payment.broadband_account_number} />
                    <PaymentInfo label={t('transactions.customer')} value={payment.customer_name} />
                    <PaymentInfo
                        label={t('customers.phone')}
                        value={formatPhoneInternational(payment.customer_phone)}
                    />
                    <PaymentInfo
                        label={t('transactions.amount')}
                        value={payment.amount === null ? null : formatTopUpAmount(payment.amount)}
                    />
                    <PaymentInfo label={t('common.status')} value={t(`status.${payment.status}`)} />
                    <PaymentInfo
                        label={t('common.created_at')}
                        value={payment.created_at ? formatDateTime(payment.created_at) : null}
                    />
                    <PaymentInfo
                        label={t('bill_payments.confirmed_at')}
                        value={payment.confirmed_at ? formatDateTime(payment.confirmed_at) : null}
                    />
                </div>
                {payment.external_response ? (
                    <div className="overflow-hidden rounded-xl border border-border/60 bg-muted/10">
                        <div className="border-b border-border/60 bg-muted/20 px-3 py-2 text-xs font-semibold text-muted-foreground sm:px-4">
                            {t('bill_payments.external_response')}
                        </div>
                        <div className="divide-y divide-border/50">
                            {renderExternalResponseRows(payment.external_response)}
                        </div>
                    </div>
                ) : null}
            </div>
        </FormDialog>
    );
}

function renderExternalResponseRows(value: unknown): ReactElement[] {
    if (Array.isArray(value)) {
        return value.map((item, index) => (
            <ExternalResponseRow key={`response-${index}`} label={`[${index}]`} value={item} />
        ));
    }

    if (value !== null && typeof value === 'object') {
        const entries = Object.entries(value as Record<string, unknown>);

        return entries.length > 0
            ? entries.map(([key, child]) => <ExternalResponseRow key={key} label={key} value={child} />)
            : [<ExternalResponseRow key="response-empty" label="-" value={null} />];
    }

    return [<ExternalResponseRow key="response-value" label="-" value={value} />];
}

function ExternalResponseRow({ label, value }: { label: string; value: unknown }) {
    const displayValue =
        value === null || value === undefined || value === ''
            ? '—'
            : typeof value === 'object'
              ? JSON.stringify(value)
              : String(value);

    return (
        <div className="grid grid-cols-[minmax(120px,0.8fr)_minmax(0,1fr)] gap-3 px-3 py-3 text-xs sm:px-4">
            <span className="font-medium text-foreground">{label}</span>
            <span className="min-w-0 break-words text-foreground">{displayValue}</span>
        </div>
    );
}

function PaymentInfo({
    label,
    value,
    href,
}: {
    label: string;
    value: string | number | null | undefined;
    href?: string;
}) {
    return (
        <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-1 break-words text-sm font-semibold text-foreground">
                {href && value !== null && value !== undefined ? (
                    <Link href={href} className="text-primary underline-offset-4 hover:underline">
                        {value}
                    </Link>
                ) : (
                    (value ?? '—')
                )}
            </p>
        </div>
    );
}
