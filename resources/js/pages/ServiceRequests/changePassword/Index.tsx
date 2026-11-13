import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    CalendarIcon,
    CircleCheckBig,
    ClipboardList,
    KeyRoundIcon,
    OctagonX,
    PhoneIcon,
    ScanEye,
    UserIcon,
    WifiIcon,
} from 'lucide-react';

import { DataTable } from '@/components/DataTable';
import { SearchInput } from '@/components/SearchInput';
import { CopyValueButton } from '@/components/CopyValueButton';
import {
    ServiceRequestInfoCard,
    ServiceRequestSectionHeading,
} from '@/components/service-requests/ServiceRequestInfoCard';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { StaffListAvatar } from '@/components/staff/StaffListAvatar';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { FormDialog } from '@/components/FormDialog';
import { useTranslation } from '@/hooks/useTranslation';
import { useCan } from '@/hooks/useCan';
import { StatCard } from '@/components/StatCard';
import { useOpenRequestFromQuery } from '@/hooks/useOpenRequestFromQuery';
import { formatPhoneInternational } from '@/lib/phone';
import { formatDate } from '@/lib/utils';

type RequestItem = {
    id: number;
    notification_unread: boolean;
    ticket_number: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    new_wifi_name: string | null;
    new_password: string | null;
    created_at: string;
    status: string;
    broadband_account_number: string | null;
    user: { id: number; name: string; phone: string } | null;
    admin: { id: number; username: string } | null;
};

type Filters = { search: string; status: string };

type Props = {
    requests: Paginated<RequestItem>;
    filters: Filters;
    statuses: string[];
    stats: {
        total_requests: number;
        under_reviews_requests: number;
        approved_requests: number;
        cancelled_requests: number;
    };
};

function visit(filters: Filters) {
    router.get(
        '/service-requests/change-password',
        {
            search: filters.search || undefined,
            status: filters.status || 'all',
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

export default function ChangePasswordIndex({ requests, filters, statuses, stats }: Props) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [selectedRequest, setSelectedRequest] = useState<RequestItem | null>(null);
    const debounce = useRef<number>(0);
    const closeRequestDetails = useOpenRequestFromQuery(
        requests.data,
        selectedRequest,
        setSelectedRequest,
        'change_password_request',
    );

    const cards = [
        {
            key: 'change_password.total_requests',
            title: t('change_password.total_requests'),
            value: stats.total_requests.toLocaleString(),
            icon: ClipboardList,
        },
        {
            key: 'change_password.under_review_requests',
            title: t('change_password.under_review_requests'),
            value: stats.under_reviews_requests.toLocaleString(),
            icon: ScanEye,
        },
        {
            key: 'change_password.approved_requests',
            title: t('change_password.approved_requests'),
            value: stats.approved_requests.toLocaleString(),
            icon: CircleCheckBig,
        },
        {
            key: 'change_password.cancelled_requests',
            title: t('change_password.cancelled_requests'),
            value: stats.cancelled_requests.toLocaleString(),
            icon: OctagonX,
        },
    ];

    useEffect(() => setSearch(filters.search), [filters.search]);
    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const onSearchChange = (value: string) => {
        setSearch(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(() => visit({ ...filters, search: value }), 300);
    };

    return (
        <>
            <Head title={t('menu.change_password_requests')} />
            <PageContent>
                <PageHeader />
                <StatCard items={cards} className="xl:grid-cols-4" />
                <DataTable
                    data={requests.data}
                    getRowId={(row) => String(row.id)}
                    search={search}
                    onSearchChange={onSearchChange}
                    showSearch={false}
                    pagination={requests}
                    onView={(row) => setSelectedRequest(row)}
                    directActions
                    filters={
                        <>
                            <FormField
                                label={t('common.search')}
                                htmlFor="change-password-search"
                                className="w-full shrink-0 sm:w-60"
                                labelClassName="text-[13px]"
                            >
                                <SearchInput
                                    id="change-password-search"
                                    value={search}
                                    onChange={onSearchChange}
                                    placeholder={t('change_password.search_placeholder')}
                                    size="sm"
                                />
                            </FormField>
                            <FormField
                                label={t('common.status')}
                                htmlFor="change-password-status"
                                className="w-full shrink-0 sm:w-48"
                                labelClassName="text-[13px]"
                            >
                                <Select
                                    value={filters.status || 'all'}
                                    onValueChange={(status) =>
                                        visit({ ...filters, status: status === 'all' ? '' : status })
                                    }
                                >
                                    <SelectTrigger id="change-password-status" className="w-full">
                                        <SelectValue placeholder={t('common.status')} />
                                    </SelectTrigger>
                                    <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        {statuses.map((status) => (
                                            <SelectItem key={status} value={status}>
                                                {t(`status.${status}`)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </>
                    }
                    columns={[
                        {
                            id: 'customer',
                            header: t('menu.customer_management'),
                            mobile: 'title',
                            cell: (request) => (
                                <span className="flex min-w-0 items-center gap-2.5">
                                    <div className="grid min-w-0 grid-cols-[auto_minmax(0,1fr)] gap-x-2.5">
                                        <div className="row-span-2">
                                            <StaffListAvatar
                                                username={request.user?.name || ''}
                                                unreadLabel={
                                                    request.notification_unread
                                                        ? t('admin_notifications.unread')
                                                        : undefined
                                                }
                                            />
                                        </div>
                                        <span className="line-clamp-2 min-w-0 font-medium leading-5">{request.user?.name || ''}</span>
                                        <p className="truncate font-mono text-xs text-muted-foreground">
                                            {request.broadband_account_number ?? '—'}
                                        </p>
                                    </div>
                                </span>
                            ),
                            searchValue: (request) => `${request.broadband_account_number} ${request.user?.name ?? ''}`,
                        },
                        {
                            id: 'ticket_number',
                            header: t('broadband_application.ticket_number'),
                            mobile: 'subtitle',
                            cell: (request) => {
                                const ticket = request.ticket_number?.trim();

                                return ticket ? (
                                    <span className="inline-flex max-w-full items-center rounded-md bg-primary/10 px-2 py-0.5 font-mono text-[11px] font-medium text-primary">
                                        {ticket}
                                    </span>
                                ) : (
                                    <span>—</span>
                                );
                            },
                            searchValue: (request) => request.ticket_number ?? '',
                        },
                        {
                            id: 'contact',
                            header: t('requests.contact_information'),
                            mobile: 'subtitle',
                            cell: (request) => (
                                <div className="min-w-0 text-xs text-muted-foreground">
                                    <p className="truncate font-medium leading-6 text-foreground">{request.contact_name}</p>
                                    <p className="truncate">{formatPhoneInternational(request.contact_phone)}</p>
                                </div>
                            ),
                            searchValue: (request) => `${request.contact_name ?? ''} ${request.contact_phone ?? ''}`,
                        },
                        {
                            id: 'wifi_name',
                            header: t('change_password.wifi_name'),
                            mobile: 'meta',
                            cell: (request) => <span className="text-xs">{request.new_wifi_name}</span>,
                            searchValue: (request) => request.new_wifi_name ?? '',
                        },
                        {
                            id: 'password',
                            header: t('change_password.new_password'),
                            mobile: false,
                            cell: (request) => <span className="font-mono text-xs">{request.new_password}</span>,
                        },
                        {
                            id: 'created_at',
                            header: t('requests.requested_date'),
                            mobile: 'meta',
                            sortable: true,
                            cell: (request) => (
                                <span className="inline-flex items-center gap-1.5">
                                    <CalendarIcon className="size-3.5" />
                                    {formatDate(request.created_at)}
                                </span>
                            ),
                            searchValue: (request) => formatDate(request.created_at),
                        },
                        {
                            id: 'status',
                            header: t('common.status'),
                            mobile: 'badge',
                            cell: (request) => <StatusBadge status={request.status} />,
                            searchValue: (request) => request.status,
                        },
                    ]}
                />
            </PageContent>
            <ChangePasswordDetailDialog
                request={selectedRequest}
                open={selectedRequest !== null}
                canUpdate={can('service-requests.update')}
                onOpenChange={(open) => !open && closeRequestDetails()}
            />
        </>
    );
}

function ChangePasswordDetailDialog({
    request,
    open,
    canUpdate,
    onOpenChange,
}: {
    request: RequestItem | null;
    open: boolean;
    canUpdate: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);
    if (!request) return null;
    const nextStatus = request.status === 'under_review' ? 'approved' : 'under_review';
    const nextButton = request.status === 'under_review' ? 'approve' : 'review';
    const ticket = request.ticket_number?.trim() || '';
    const serviceType = request.broadband_account_number?.startsWith('FTTH')
        ? 'FTTH'
        : request.broadband_account_number?.startsWith('CG')
          ? t('change_plan.cg_router')
          : '—';

    const updateStatus = () => {
        setProcessing(true);
        router.patch(
            `/service-requests/change-password/${request.id}/status`,
            { status: nextStatus },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('change_password.request_title')}
            description={request.broadband_account_number ?? ''}
            icon={KeyRoundIcon}
            size="lg"
        >
            <div className="space-y-3 p-2">
                <div className="rounded-xl border border-primary/20 bg-primary/5 p-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="min-w-0 space-y-1">
                            <p className="text-xs font-medium text-muted-foreground">
                                {t('broadband_application.ticket_number')}
                            </p>
                            <div className="flex min-w-0 items-center gap-1.5">
                                <span className="break-all font-mono text-sm font-medium">{ticket || '—'}</span>
                                {ticket && (
                                    <CopyValueButton
                                        value={ticket}
                                        label={t('broadband_application.ticket_number')}
                                    />
                                )}
                            </div>
                        </div>
                        <div className="flex flex-col items-start gap-2 text-xs text-muted-foreground sm:items-end">
                            <span className="rounded-full bg-background px-2.5 py-1 font-medium text-foreground">
                                {serviceType}
                            </span>
                            <span className="inline-flex items-center gap-1">
                                <CalendarIcon className="size-3.5" />
                                <span className="font-medium text-foreground">{formatDate(request.created_at)}</span>
                            </span>
                        </div>
                    </div>
                </div>
                {/* Customer & Account Details Grid */}
                <div className="grid gap-3 sm:grid-cols-2">
                    <ServiceRequestInfoCard
                        icon={UserIcon}
                        title={t('broadband_application.app_user')}
                        fields={[
                            { label: t('customers.name'), value: request.user?.name },
                            {
                                label: t('requests.phone'),
                                value: formatPhoneInternational(request.user?.phone),
                                copy: request.user?.phone,
                                copyLabel: t('change_password.copy_account_phone'),
                                copyFormat: 'phone',
                                isCancelled: request.status === 'cancelled',
                            },
                            {
                                label: t('requests.broadband_account'),
                                value: request.broadband_account_number,
                                copy: request.broadband_account_number,
                                copyLabel: t('requests.broadband_account'),
                            },
                        ]}
                    />
                    <ServiceRequestInfoCard
                        icon={PhoneIcon}
                        title={t('change_password.contact')}
                        fields={[
                            { label: t('customers.name'), value: request.contact_name },
                            {
                                label: t('requests.phone'),
                                value: formatPhoneInternational(request.contact_phone),
                                copy: request.contact_phone,
                                copyLabel: t('change_password.copy_contact_phone'),
                                copyFormat: 'phone',
                                isCancelled: request.status === 'cancelled',
                            },
                        ]}
                    />
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                    <Info
                        label={t('change_password.wifi_name')}
                        value={request.new_wifi_name}
                        icon={WifiIcon}
                        isCancelled={request.status === 'cancelled'}
                    />
                    <Info
                        label={t('change_password.new_password')}
                        value={request.new_password}
                        icon={KeyRoundIcon}
                        isCancelled={request.status === 'cancelled'}
                    />
                </div>
                <div className="flex items-center justify-between rounded-xl border border-border/60 bg-muted/10 p-3">
                    <StatusBadge status={request.status} />
                    {canUpdate && request?.status !== 'cancelled' && (
                        <Button type="button" size="sm" disabled={processing} onClick={updateStatus}>
                            {t(`status.${nextButton}`)}
                        </Button>
                    )}
                </div>
            </div>
        </FormDialog>
    );
}

function Info({
    label,
    value,
    icon: Icon,
    isCancelled = false,
}: {
    label: string;
    value: string | null | undefined;
    icon: typeof WifiIcon;
    isCancelled?: boolean;
}) {
    return (
        <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5">
            <div className="mb-2">
                <ServiceRequestSectionHeading icon={Icon} title={label} />
            </div>
            <div className="mt-1 flex items-center gap-1">
                <p
                    className={`min-w-0 truncate text-sm font-semibold text-foreground ${
                        isCancelled ? 'select-none' : ''
                    }`}
                >
                    {value || '-'}
                </p>
                {!isCancelled && <CopyValueButton value={value} label={`Copy ${label}`} />}
            </div>
        </div>
    );
}
