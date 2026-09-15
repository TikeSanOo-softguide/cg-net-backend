import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import {
    ArrowRightIcon,
    Calendar1Icon,
    FileTextIcon,
    ImageIcon,
    ImageOffIcon,
    MapPinIcon,
    NavigationIcon,
    PhoneIcon,
    RouteIcon,
    RouteOffIcon,
    Router,
    UserIcon,
    WifiIcon,
    WifiOffIcon,
} from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import { formActionBarClass, formActionSubmitClass } from '@/components/FormActionBar';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { cn, formatDate } from '@/lib/utils';
import { CopyValueButton } from '@/components/CopyValueButton';
import { CHANGE_PLAN_STATUS } from '@/lib/CommonNameConst';

type FailureReport = {
    id: number;
    label?: string | null;
    status: string;
    customer_name: string;
    customer_phone: string;
    account_number: string;
    account_customer: string;
    failure_type: string;
    contact_name: string;
    contact_phone: string;
    description: string;
    created_at: string | null;
    admin_name: string | null;
    photos: {
        id: number;
        image_url: string;
        label: string | null;
    }[];
    customer_packages: {
        id: number;
        package_id: number;
        expiry_date: string | null;
        start_date: string | null;
        package: {
            id: number;
            price: string;
            network: {
                id: number;
                name_en: string;
                name_zh: string;
                name_my: string;
            } | null;
            speed: {
                id: number;
                mbps: number;
            } | null;
            term: {
                id: number;
                months: number;
            } | null;
        } | null;
    }[];
};

export function FailureReportDetailDialog({
    open,
    onOpenChange,
    request,
    statuses,
    canUpdate,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    request: FailureReport | null;
    statuses: string[];
    canUpdate: boolean;
}) {
    const { t, locale } = useTranslation();
    const nextStatus =
        request?.status === CHANGE_PLAN_STATUS.UNDER_REVIEW
            ? CHANGE_PLAN_STATUS.APPROVED
            : CHANGE_PLAN_STATUS.UNDER_REVIEW;
    const { data, setData, patch } = useForm({ status: nextStatus });
    const nextButton = request?.status === CHANGE_PLAN_STATUS.UNDER_REVIEW ? 'approve' : 'review';
    useEffect(() => {
        if (request) {
            setData('status', nextStatus);
        }
    }, [request?.id, request?.status, statuses]);

    if (!request) {
        return null;
    }

    const handleStatusUpdate = () => {
        if (!request || !data.status) return;

        patch(`/service-requests/failures/${request.id}/status`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('relocation_requests.card_title')}
            description={t('menu.relocation_requests_description')}
            icon={NavigationIcon}
            size="2xl"
        >
            {request ? (
                <div className="mt-1 space-y-4 overflow-y-auto p-2">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5 space-y-1">
                            <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                {t('change_plan.contact')}
                            </p>
                            <p className="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                                <UserIcon className="size-3.5 text-muted-foreground/70" />
                                {request.customer_name}
                            </p>
                            <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                <PhoneIcon className="size-3" />
                                <span className={request.status === 'cancelled' ? 'select-none' : ''}>
                                    {request.customer_phone}
                                </span>
                                {request.status !== 'cancelled' && (
                                    <CopyValueButton
                                        value={request.customer_phone}
                                        label={t('relocation_requests.copy_phone')}
                                    />
                                )}
                            </p>
                        </div>

                        <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5 space-y-1">
                            <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                {t('change_plan.broadband_account')}
                            </p>
                            <p className="flex items-center gap-1.5 font-mono text-sm font-semibold text-foreground">
                                <WifiIcon className="size-3.5 text-muted-foreground/70" />
                                {request.account_number}
                            </p>
                            <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                <UserIcon className="size-3" />
                                <span className="font-mono">{request.account_customer}</span>
                                <span className={`font-mono ${request.status === 'cancelled' ? 'select-none' : ''}`}>
                                    ({request.contact_phone})
                                </span>
                                {request.status !== 'cancelled' && (
                                    <CopyValueButton
                                        value={request.contact_phone}
                                        label={t('relocation_requests.copy_phone')}
                                    />
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="rounded-xl border border-border/60 bg-muted/20 p-4">
                            <p className="mb-3 flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                {t('failure_report.request_info')}
                            </p>

                            <div className="space-y-2.5">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <WifiOffIcon className="size-3.5" />
                                        {t('failure_report.failure_type')}
                                    </span>

                                    <span className="text-sm font-semibold text-foreground">
                                        {t(`failure_report.${request.failure_type}`)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <Calendar1Icon className="size-3.5" />
                                        {t('requests.requested_date')}
                                    </span>

                                    <span
                                        className={`text-sm font-medium text-foreground ${
                                            request.status === 'cancelled' ? 'select-none' : ''
                                        }`}
                                    >
                                        {request.created_at}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div className="rounded-xl border border-border/60 bg-muted/20 p-4">
                            <p className="mb-3 flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                {t('failure_report.current_package')}
                            </p>

                            {request.customer_packages.map((customerPackage) => {
                                const isExpired = customerPackage.expiry_date
                                    ? new Date(customerPackage.expiry_date) < new Date()
                                    : false;

                                return (
                                    <div key={customerPackage.id} className="...">
                                        <div className="flex items-center justify-between gap-3 mt-3">
                                            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <Router className="size-4 shrink-0 text-muted-foreground" />
                                                {t('customers.package')}
                                            </span>
                                            <div className="flex flex-wrap items-center gap-x-1.5 text-sm font-semibold text-foreground">
                                                <span>
                                                    {customerPackage.package?.network?.[`name_${locale}`] ?? '—'}
                                                </span>
                                                <span className="text-muted-foreground">·</span>
                                                <span>
                                                    {customerPackage.package?.speed?.mbps
                                                        ? `${customerPackage.package.speed.mbps} Mbps`
                                                        : '—'}
                                                </span>
                                                <span className="text-muted-foreground">·</span>
                                                <span>
                                                    {customerPackage.package?.term?.months
                                                        ? `${customerPackage.package.term.months} ${
                                                              customerPackage.package.term.months === 1
                                                                  ? t('failure_report.month')
                                                                  : t('packages.months')
                                                          }`
                                                        : '—'}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex items-center justify-between gap-3 mt-3">
                                            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <Calendar1Icon className="size-3.5" />
                                                {t('failure_report.expiry_date')}
                                            </span>

                                            <span
                                                className={`text-sm font-medium ${
                                                    isExpired
                                                        ? 'text-destructive font-semibold'
                                                        : request.status === 'cancelled'
                                                          ? 'select-none text-foreground'
                                                          : 'text-foreground'
                                                }`}
                                            >
                                                {formatDate(customerPackage.expiry_date)}
                                            </span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                    <div className="rounded-xl border border-border/60 bg-muted/25 p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <div className="flex size-7 items-center justify-center rounded-lg bg-primary/10">
                                <ImageIcon className="size-3.5 text-primary" />
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-foreground">{t('requests.images')}</p>
                            </div>
                        </div>

                        {request.photos?.length ? (
                            <div className="flex gap-3 overflow-x-auto pb-1 scrollbar-thin">
                                {request.photos.map((photo) => (
                                    <div
                                        key={photo.id}
                                        className="group relative shrink-0 overflow-hidden rounded-lg border border-border/50 bg-background w-[250px] sm:w-[318px]"
                                    >
                                        <img
                                            src={photo.image_url}
                                            alt=""
                                            className="h-[160px] sm:h-[200px] w-full object-cover transition-transform duration-200 group-hover:scale-105"
                                        />
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="flex h-24 items-center justify-center rounded-lg border border-dashed border-border/50 bg-background/40">
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <ImageOffIcon className="size-4" />
                                    <span>{t('requests.no_images') ?? 'No images available'}</span>
                                </div>
                            </div>
                        )}
                    </div>
                    <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5">
                        <p className="mb-2 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <FileTextIcon className="size-3.5 text-muted-foreground/70" />
                            {t('cms.description')}
                        </p>

                        <div className="max-h-48 overflow-y-auto rounded-lg border border-border/40 bg-background/60 px-3 py-2.5 pr-2">
                            <p className="whitespace-pre-wrap text-sm leading-6 text-foreground">
                                {request.description || (
                                    <span className="text-xs italic text-muted-foreground">
                                        No additional description provided.
                                    </span>
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 flex flex-col gap-2 rounded-xl border border-border/60 bg-muted/10 p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2">
                            <span className="text-xs text-muted-foreground">{t('requests.current_status')}:</span>
                            <StatusBadge status={request.status} />
                        </div>

                        {canUpdate && request?.status !== 'cancelled' && (
                            <Button
                                type="button"
                                size="sm"
                                onClick={handleStatusUpdate}
                                className="h-9 px-4 text-xs font-semibold"
                            >
                                {t(`status.${nextButton}`)}
                            </Button>
                        )}
                    </div>
                </div>
            ) : null}
        </FormDialog>
    );
}
