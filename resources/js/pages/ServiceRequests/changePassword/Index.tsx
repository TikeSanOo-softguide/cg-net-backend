import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { CircleCheckBig, ClipboardList, KeyRoundIcon, PhoneIcon, ScanEye, UserIcon, WifiIcon } from 'lucide-react';

import { DataTable } from '@/components/DataTable';
import { CopyValueButton } from '@/components/CopyValueButton';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { FormControl } from '@/components/ui/form-control';
import { StaffListAvatar } from '@/components/staff/StaffListAvatar';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { FormDialog } from '@/components/FormDialog';
import { useTranslation } from '@/hooks/useTranslation';
import { StatCard } from '@/components/StatCard';

type RequestItem = {
    id: number;
    contact_name: string | null;
    contact_phone: string | null;
    new_wifi_name: string | null;
    new_password: string | null;
    status: string;
    user: { id: number; name: string; phone: string } | null;
    broadband_account: { id: number; account_number: string } | null;
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
    const [search, setSearch] = useState(filters.search);
    const [selectedRequest, setSelectedRequest] = useState<RequestItem | null>(null);
    const debounce = useRef<number>(0);

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
            icon: CircleCheckBig,
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
                    searchPlaceholder={t('common.search')}
                    pagination={requests}
                    onView={(row) => setSelectedRequest(row)}
                    directActions
                    filters={
                        <FormControl icon={WifiIcon} compact className="w-full shrink-0 sm:w-48">
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(status) =>
                                    visit({ ...filters, status: status === 'all' ? '' : status })
                                }
                            >
                                <SelectTrigger className="w-full">
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
                        </FormControl>
                    }
                    columns={[
                        {
                            id: 'customer',
                            header: t('menu.customer_management'),
                            mobile: 'title',
                            cell: (request) => (
                                <span className="flex min-w-0 items-center gap-2.5">
                                    <StaffListAvatar username={(request.user?.name ?? request.contact_name) || ''} />
                                    <div>
                                        <p className="truncate font-semibold text-primary">
                                            {request.user?.name ?? request.contact_name}
                                        </p>
                                        <p className="truncate font-mono text-xs text-muted-foreground">
                                            {request.broadband_account?.account_number}
                                        </p>
                                    </div>
                                </span>
                            ),
                            searchValue: (request) =>
                                `${request.broadband_account?.account_number ?? ''} ${request.user?.name ?? ''}`,
                        },
                        {
                            id: 'contact',
                            header: t('change_password.contact'),
                            mobile: 'subtitle',
                            cell: (request) => (
                                <div className="min-w-0 text-xs text-muted-foreground">
                                    <p className="truncate font-medium text-foreground">{request.contact_name}</p>
                                    <p className="truncate">{request.contact_phone}</p>
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
                onOpenChange={(open) => !open && setSelectedRequest(null)}
            />
        </>
    );
}

function ChangePasswordDetailDialog({
    request,
    open,
    onOpenChange,
}: {
    request: RequestItem | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);
    if (!request) return null;
    const nextStatus = request.status === 'under_review' ? 'approved' : 'under_review';
    const nextButton = request.status === 'under_review' ? 'approve' : 'review';

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
            description={request.broadband_account?.account_number ?? ''}
            icon={KeyRoundIcon}
            size="lg"
        >
            <div className="space-y-3 p-2">
                {/* Customer & Account Details Grid */}
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5 space-y-1">
                        <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            {t('change_password.contact')}
                        </p>
                        <p className="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                            <UserIcon className="size-3.5 text-muted-foreground/70" />
                            {request.contact_name}
                        </p>
                        <p className="flex items-center gap-1 text-xs text-muted-foreground">
                            <PhoneIcon className="size-3" />
                            <span className={request.status === 'cancelled' ? 'select-none' : ''}>
                                {request.contact_phone}
                            </span>
                            {request.status !== 'cancelled' && (
                                <CopyValueButton
                                    value={request.contact_phone}
                                    label={t('change_password.copy_contact_phone')}
                                />
                            )}
                        </p>
                    </div>

                    <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5 space-y-1">
                        <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            {t('change_password.broadband_account')}
                        </p>
                        <p className="flex items-center gap-1.5 font-mono text-sm font-semibold text-foreground">
                            <WifiIcon className="size-3.5 text-muted-foreground/70" />
                            {request.broadband_account?.account_number}
                        </p>
                        <p className="flex items-center gap-1 text-xs text-muted-foreground">
                            <UserIcon className="size-3" />
                            <span className="font-mono">{request.user?.name}</span>
                            <span className={`font-mono ${request.status === 'cancelled' ? 'select-none' : ''}`}>
                                ({request.user?.phone})
                            </span>
                            {request.status !== 'cancelled' && (
                                <CopyValueButton
                                    value={request.user?.phone}
                                    label={t('change_password.copy_account_phone')}
                                />
                            )}
                        </p>
                    </div>
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
                    {request?.status !== 'cancelled' && (
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
            <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                <Icon className="size-3.5" />
                {label}
            </p>
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
