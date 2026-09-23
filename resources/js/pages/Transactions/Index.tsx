import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    ArrowDownLeftIcon,
    ArrowLeftIcon,
    ArrowLeftRightIcon,
    ArrowUpRightIcon,
    CalendarIcon,
    EyeIcon,
    SearchIcon,
    UserIcon,
} from 'lucide-react';

import { DataTable } from '@/components/DataTable';
import { FormDialog } from '@/components/FormDialog';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { FormControl } from '@/components/ui/form-control';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlStateClass } from '@/lib/form-control';
import { formatTopUpAmount, formatTopUpNumber, TOP_UP_CARD_CURRENCY } from '@/lib/top-up-cards';
import { cn, formatDateTime } from '@/lib/utils';

type Customer = {
    id: number;
    name: string;
    phone: string;
};

export type TransactionRow = {
    id: number;
    transaction_no: string;
    type: string;
    status: string;
    direction: 'credit' | 'debit' | null;
    amount: string;
    created_at: string | null;
    wallet_id: number;
    customer: Customer | null;
    actor_type: string | null;
    actor_id: number | null;
    ip_address: string | null;
    user_agent: string | null;
    idempotency_key: string | null;
    reversal_of: string | null;
    wallet_entry: {
        type: string;
        balance_before: number;
        balance_after: number;
    } | null;
    wallet_transfer: {
        from_wallet_id: number;
        to_wallet_id: number;
        amount: number;
        note: string | null;
    } | null;
    related: {
        bill_payment_id: number | null;
        package_order_id: number | null;
        top_up_card_id: number | null;
    };
};

export type Filters = {
    customer_id: number | null;
    search: string;
    actor_type: string;
    status: string;
    direction: string;
    from: string;
    to: string;
};

export type TransactionsProps = {
    transactions: Paginated<TransactionRow>;
    filters: Filters;
    filterOptions: {
        actor_types: string[];
        statuses: string[];
    };
    scope: 'global' | 'customer';
};

type TransactionsTableProps = TransactionsProps & {
    baseUrl?: string;
    embeddedCustomer?: boolean;
};

function visitIndex(filters: Filters, baseUrl: string, embeddedCustomer = false) {
    router.get(
        baseUrl,
        {
            customer_id: filters.customer_id || undefined,
            search: filters.search || undefined,
            actor_type: filters.actor_type || undefined,
            status: filters.status || undefined,
            direction: filters.direction || undefined,
            from: filters.from || undefined,
            to: filters.to || undefined,
            transactions: embeddedCustomer ? 'all' : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function transactionLabel(type: string, t: (key: string) => string): string {
    const key = `transactions.types.${type}`;
    const translated = t(key);

    return translated === key
        ? type.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
        : translated;
}

function actorLabel(actorType: string, t: (key: string) => string): string {
    const key = `transactions.actors.${actorType}`;
    const translated = t(key);

    return translated === key ? actorType : translated;
}

function DetailItem({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div>
            <dt className="text-[13px] text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 break-words text-[13px] font-medium">{value || '—'}</dd>
        </div>
    );
}

export function TransactionsTable({
    transactions,
    filters,
    filterOptions,
    scope,
    baseUrl = '/billing/transactions',
    embeddedCustomer = false,
}: TransactionsTableProps) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [selected, setSelected] = useState<TransactionRow | null>(null);
    const [dateError, setDateError] = useState<string>();
    const debounce = useRef<number>(0);

    useEffect(() => setSearch(filters.search), [filters.search]);
    useEffect(() => setFrom(filters.from), [filters.from]);
    useEffect(() => setTo(filters.to), [filters.to]);
    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const updateSearch = (value: string) => {
        setSearch(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(
            () => visitIndex({ ...filters, search: value }, baseUrl, embeddedCustomer),
            300,
        );
    };

    const updateDate = (field: 'from' | 'to', value: string) => {
        const nextFrom = field === 'from' ? value : from;
        const nextTo = field === 'to' ? value : to;

        if (nextFrom && nextTo && nextTo < nextFrom) {
            setDateError(t('transactions.invalid_date_range'));
            return;
        }

        setDateError(undefined);
        field === 'from' ? setFrom(value) : setTo(value);
        visitIndex({ ...filters, from: nextFrom, to: nextTo }, baseUrl, embeddedCustomer);
    };

    const updateFilter = (field: 'actor_type' | 'status' | 'direction', value: string) => {
        visitIndex({ ...filters, [field]: value === 'all' ? '' : value }, baseUrl, embeddedCustomer);
    };

    return (
        <>
            <DataTable
                data={transactions.data}
                getRowId={(row) => String(row.id)}
                search={scope === 'global' ? search : undefined}
                onSearchChange={scope === 'global' ? updateSearch : undefined}
                searchPlaceholder={t('transactions.search_placeholder')}
                showSearch={false}
                pagination={transactions}
                emptyLabel={t('transactions.empty')}
                directActions
                actions={(row) => (
                    <TableActionButton
                        label={t('common.view')}
                        icon={EyeIcon}
                        onClick={(event) => {
                            event.stopPropagation();
                            setSelected(row);
                        }}
                    />
                )}
                filters={
                    <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
                        {scope === 'global' ? (
                            <>
                                <FormField
                                    label={t('transactions.actor_type')}
                                    htmlFor="transaction-actor"
                                    icon={UserIcon}
                                    className="w-full shrink-0 sm:w-40 mr-3"
                                    labelClassName="text-[13px]"
                                >
                                    <Select
                                        value={filters.actor_type || 'all'}
                                        onValueChange={(value) => updateFilter('actor_type', value)}
                                    >
                                        <SelectTrigger id="transaction-actor" className={formControlStateClass('idle')}>
                                            <SelectValue placeholder={t('common.all')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('common.all')}</SelectItem>
                                            {filterOptions.actor_types.map((value) => (
                                                <SelectItem key={value} value={value}>
                                                    {actorLabel(value, t)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <FormField
                                    label={t('transactions.search')}
                                    htmlFor="transaction-search"
                                    icon={SearchIcon}
                                    className="w-full shrink-0 sm:w-60 mr-3"
                                    labelClassName="text-[13px]"
                                >
                                    <Input
                                        value={search}
                                        onChange={(event) => updateSearch(event.target.value)}
                                        placeholder={t('transactions.search_placeholder')}
                                        aria-label={t('transactions.search_placeholder')}
                                    />
                                </FormField>
                            </>
                        ) : null}

                        <FormField
                            label={t('transactions.direction')}
                            htmlFor="transaction-direction"
                            className="w-full shrink-0 sm:w-40 mr-3"
                            labelClassName="text-[13px]"
                        >
                            <Select
                                value={filters.direction || 'all'}
                                onValueChange={(value) => updateFilter('direction', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder={t('transactions.direction')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('common.all')}</SelectItem>
                                    <SelectItem value="credit">{t('transactions.credit')}</SelectItem>
                                    <SelectItem value="debit">{t('transactions.debit')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField
                            label={t('common.status')}
                            htmlFor="transaction-status"
                            className="w-full shrink-0 sm:w-40 mr-3"
                            labelClassName="text-[13px]"
                        >
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(value) => updateFilter('status', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder={t('common.status')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('common.all')}</SelectItem>
                                    {filterOptions.statuses.map((value) => (
                                        <SelectItem key={value} value={value}>
                                            {t(`status.${value}`)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField
                            label={t('common.start_date')}
                            htmlFor="transaction-from"
                            error={dateError}
                            icon={CalendarIcon}
                            className="w-full shrink-0 sm:w-40 mr-3"
                            labelClassName="text-[13px]"
                        >
                            <DatePicker
                                id="transaction-from"
                                value={from}
                                max={to || undefined}
                                onChange={(value) => updateDate('from', value)}
                            />
                        </FormField>
                        <FormField
                            label={t('common.end_date')}
                            htmlFor="transaction-to"
                            error={dateError}
                            icon={CalendarIcon}
                            className="w-full shrink-0 sm:w-40 mr-3"
                            labelClassName="text-[13px]"
                        >
                            <DatePicker
                                id="transaction-to"
                                value={to}
                                min={from || undefined}
                                onChange={(value) => updateDate('to', value)}
                            />
                        </FormField>
                    </div>
                }
                columns={[
                    {
                        id: 'transaction_no',
                        header: t('transactions.number'),
                        mobile: 'title',
                        className: 'font-mono text-[12px]',
                        cell: (row) => row.transaction_no,
                    },
                    {
                        id: 'customer',
                        header: t('transactions.customer'),
                        mobile: 'subtitle',
                        cell: (row) => row.customer?.name ?? '—',
                    },
                    {
                        id: 'type',
                        header: t('transactions.type'),
                        mobile: 'meta',
                        cell: (row) => transactionLabel(row.type, t),
                    },
                    {
                        id: 'direction',
                        header: t('transactions.direction'),
                        mobile: 'badge',
                        cell: (row) =>
                            row.direction ? (
                                <span
                                    className={cn(
                                        row.direction === 'credit' ? 'text-emerald-600' : 'text-red-600',
                                        'font-semibold',
                                    )}
                                >
                                    {t(`transactions.${row.direction}`)}
                                </span>
                            ) : (
                                '—'
                            ),
                    },
                    {
                        id: 'amount',
                        header: t('transactions.amount'),
                        className: 'tabular-nums',
                        cell: (row) => (
                            <span className="font-heading">
                                <span
                                    className={cn(
                                        row.direction === 'credit' ? 'text-emerald-600' : 'text-red-600',
                                        'tabular-nums text-[14px]',
                                    )}
                                >
                                    {formatTopUpNumber(row.amount)}
                                </span>
                                <span className="ml-1 text-[12px]">{TOP_UP_CARD_CURRENCY}</span>
                            </span>
                        ),
                    },
                    {
                        id: 'status',
                        header: t('common.status'),
                        mobile: 'badge',
                        cell: (row) => <StatusBadge status={row.status} />,
                    },
                    {
                        id: 'created_at',
                        header: t('common.created_at'),
                        className: 'text-muted-foreground text-[12px]',
                        cell: (row) => formatDateTime(row.created_at),
                    },
                ]}
            />
            <FormDialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) setSelected(null);
                }}
                title={selected?.transaction_no ?? t('transactions.detail')}
                description={t('transactions.detail_description')}
                icon={ArrowLeftRightIcon}
                size="lg"
            >
                {selected ? (
                    <div className="min-h-0 overflow-y-auto px-4 py-4 sm:px-5">
                        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <DetailItem label={t('transactions.number')} value={selected.transaction_no} />
                            <DetailItem
                                label={t('transactions.detail_fields.reversal_of')}
                                value={selected.reversal_of}
                            />

                            <DetailItem label={t('transactions.customer')} value={selected.customer?.name} />
                            <DetailItem
                                label={t('transactions.detail_fields.customer_phone')}
                                value={selected.customer?.phone}
                            />
                            <DetailItem label={t('transactions.detail_fields.wallet_id')} value={selected.wallet_id} />
                            <DetailItem label={t('transactions.type')} value={transactionLabel(selected.type, t)} />
                            <DetailItem
                                label={t('transactions.direction')}
                                value={selected.direction ? t(`transactions.${selected.direction}`) : null}
                            />
                            <DetailItem label={t('transactions.amount')} value={formatTopUpAmount(selected.amount)} />
                            <DetailItem label={t('common.status')} value={t(`status.${selected.status}`)} />

                            <DetailItem
                                label={t('transactions.detail_fields.idempotency_key')}
                                value={selected.idempotency_key}
                            />
                            <DetailItem
                                label={t('transactions.actor_type')}
                                value={selected.actor_type ? actorLabel(selected.actor_type, t) : null}
                            />
                            <DetailItem label={t('transactions.detail_fields.actor_id')} value={selected.actor_id} />
                            <DetailItem
                                label={t('transactions.detail_fields.ip_address')}
                                value={selected.ip_address}
                            />
                            <DetailItem label={t('transactions.detail_fields.device')} value={selected.user_agent} />
                            <DetailItem
                                label={t('transactions.detail_fields.balance_before')}
                                value={selected.wallet_entry?.balance_before}
                            />
                            <DetailItem
                                label={t('transactions.detail_fields.balance_after')}
                                value={selected.wallet_entry?.balance_after}
                            />
                            <DetailItem
                                label={t('transactions.detail_fields.transfer_from_wallet')}
                                value={selected.wallet_transfer?.from_wallet_id}
                            />
                            <DetailItem
                                label={t('transactions.detail_fields.transfer_to_wallet')}
                                value={selected.wallet_transfer?.to_wallet_id}
                            />
                            <DetailItem
                                label={t('transactions.detail_fields.transfer_note')}
                                value={selected.wallet_transfer?.note}
                            />
                            <DetailItem
                                label={t('transactions.detail_fields.bill_payment_id')}
                                value={selected.related.bill_payment_id}
                            />
                            <DetailItem
                                label={t('transactions.detail_fields.package_order_id')}
                                value={selected.related.package_order_id}
                            />
                            <DetailItem
                                label={t('transactions.detail_fields.top_up_card_id')}
                                value={selected.related.top_up_card_id}
                            />
                            <DetailItem label={t('common.created_at')} value={formatDateTime(selected.created_at)} />
                        </dl>
                    </div>
                ) : null}
            </FormDialog>
        </>
    );
}

export default function TransactionsIndex(props: TransactionsProps) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.transactions')} />
            <PageContent>
                <PageHeader title={t('menu.transactions')} description={t('transactions.description')} />
                <TransactionsTable {...props} />
            </PageContent>
        </>
    );
}
