import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    ArrowRightIcon,
    CalendarIcon,
    FileTextIcon,
    PackageIcon,
    PhoneIcon,
    UserIcon,
    WifiIcon,
} from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import {
    ServiceRequestInfoCard,
    ServiceRequestSectionHeading,
} from '@/components/service-requests/ServiceRequestInfoCard';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { REQUEST_STATUS } from '@/lib/CommonNameConst';
import { cn, formatDate, todayInTimezone } from '@/lib/utils';
import { CopyValueButton } from '@/components/CopyValueButton';
import { formatPhoneInternational } from '@/lib/phone';

export type PackageDetail = {
    package_id?: number | null;
    price?: string | number | null;
    network_name?: {
        en?: string | null;
        my?: string | null;
        zh?: string | null;
    } | null;
    speed?: { mbps?: number | string | null } | null;
    term?: { months?: number | string | null } | null;
};

export type ChangePlanRequestItem = {
    id: number;
    created_at: string;
    preferred_date: string | null;
    contact_name: string;
    contact_phone: string;
    note: string | null;
    status: string;
    service_type: string | null;
    ticket_number: string | null;
    user: { id: number; name: string; phone: string };
    broadband_account_number: string | null;
    current_package_detail: PackageDetail | null;
    new_package_detail: PackageDetail | null;
    admin: { id: number; username: string } | null;
};

type ChangePlanDetailDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    request: ChangePlanRequestItem | null;
};

export function packageName(pkg: PackageDetail | null | undefined): string {
    if (!pkg) {
        return '—';
    }

    const parts = [
        pkg.network_name?.en,
        pkg.speed?.mbps != null && `${pkg.speed.mbps}` !== '' ? `${pkg.speed.mbps} Mbps` : null,
        pkg.term?.months != null && `${pkg.term.months}` !== '' ? `${pkg.term.months} months` : null,
    ].filter(Boolean);

    return parts.length > 0 ? parts.join(' - ') : '—';
}

export function ChangePlanDetailDialog({
    open,
    onOpenChange,
    request,
}: ChangePlanDetailDialogProps) {
    const { t } = useTranslation();
    const can = useCan();
    const canUpdate = can('service-requests.update');

    const nextStatus =
        request?.status === REQUEST_STATUS.UNDER_REVIEW
            ? REQUEST_STATUS.APPROVED
            : REQUEST_STATUS.UNDER_REVIEW;

    const { data, setData, patch } = useForm({ status: nextStatus });

    useEffect(() => {
        if (request) {
            setData('status', nextStatus);
        }
    }, [request?.id, request?.status]);

    const handleStatusUpdate = () => {
        if (!request || !data.status) return;

        patch(`/service-requests/change-plan/${request.id}/status`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const isExpired =
        request?.status === REQUEST_STATUS.UNDER_REVIEW &&
        request?.preferred_date &&
        request.preferred_date < todayInTimezone();

    const nextButton = request?.status === REQUEST_STATUS.UNDER_REVIEW ? 'approve' : 'review';
    const ticket = request?.ticket_number?.trim() || '';
    // Prefer persisted service_type; fall back to account prefix for older rows.
    const accountPrefixType = request?.broadband_account_number?.startsWith('FTTH')
        ? 'FTTH'
        : request?.broadband_account_number?.startsWith('CG')
          ? 'CG'
          : '';
    const resolvedServiceType = request?.service_type?.trim() || accountPrefixType;
    const serviceLabel =
        resolvedServiceType === 'CG'
            ? t('change_plan.cg_router')
            : resolvedServiceType || '—';

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('change_plan.request_details')}
            description={t('change_plan.request_details_description')}
            icon={WifiIcon}
            size="lg"
        >
            {request ? (
                <div className="mt-1 space-y-4 overflow-y-auto p-2">
                    {isExpired && (
                        <div className="flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-xs text-amber-700 dark:text-amber-400">
                            <AlertTriangleIcon className="mt-0.5 size-4 shrink-0" />
                            <div>
                                <span className="font-semibold">{t('change_plan.overdue')}</span>
                                <p className="mt-0.5 font-medium text-foreground">
                                    {t('change_plan.preferred_date')}: {formatDate(request.preferred_date)}{' '}
                                    {t('change_plan.urgent_processing')}
                                </p>
                            </div>
                        </div>
                    )}

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
                                    {serviceLabel}
                                </span>
                                <span className="inline-flex items-center gap-1">
                                    <CalendarIcon className="size-3.5" />
                                    <span className="font-medium text-foreground">
                                        {formatDate(request.created_at)}
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <ServiceRequestInfoCard
                            icon={UserIcon}
                            title={t('broadband_application.app_user')}
                            fields={[
                                { label: t('customers.name'), value: request.user.name },
                                {
                                    label: t('requests.phone'),
                                    value: formatPhoneInternational(request.user.phone),
                                    copy: request.user.phone,
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

                    <div className="rounded-2xl border border-border/60 p-4">
                        <div className="mb-4">
                            <ServiceRequestSectionHeading
                                icon={WifiIcon}
                                title={t('change_plan.migration')}
                            />
                        </div>
                        <p className="mb-4 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <CalendarIcon className="size-3.5" />
                            <span>{t('change_plan.preferred_date')}:</span>
                            <span
                                className={cn(
                                    'font-medium text-foreground',
                                    isExpired && 'font-semibold text-amber-600 dark:text-amber-400',
                                )}
                            >
                                {formatDate(request.preferred_date)}
                            </span>
                        </p>
                        <div className="grid gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                            <div className="flex h-full min-w-0 flex-col rounded-xl border border-border/60 bg-muted/20 p-3.5">
                                <p className="mb-1 text-[10px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
                                    {t('change_plan.current_plan')}
                                </p>
                                <div className="flex min-w-0 items-start gap-1.5 text-sm font-medium leading-5 text-foreground">
                                    <PackageIcon className="mt-0.5 size-3.5 shrink-0" />
                                    <span className="min-w-0 break-words">
                                        {packageName(request.current_package_detail)}
                                    </span>
                                </div>
                                <p className="mt-2 text-xs font-mono font-medium text-muted-foreground">
                                    {pointsLabel(request.current_package_detail?.price, t('billing_report.points'))}
                                </p>
                            </div>

                            <div className="hidden sm:flex">
                                <div className="flex size-9 items-center justify-center rounded-full border border-primary/20 bg-primary/5 text-primary">
                                    <ArrowRightIcon className="size-4" />
                                </div>
                            </div>

                            <div className="flex h-full min-w-0 flex-col rounded-xl border border-primary/20 bg-primary/5 p-3.5">
                                <p className="mb-1 text-[10px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
                                    {t('change_plan.new_plan')}
                                </p>
                                <div className="flex min-w-0 items-start gap-1.5 text-sm font-semibold leading-5 text-primary">
                                    <PackageIcon className="mt-0.5 size-3.5 shrink-0" />
                                    <span className="min-w-0 break-words">
                                        {packageName(request.new_package_detail)}
                                    </span>
                                </div>
                                <p className="mt-2 text-xs font-mono font-semibold text-primary">
                                    {pointsLabel(request.new_package_detail?.price, t('billing_report.points'))}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5">
                        <div className="mb-1.5">
                            <ServiceRequestSectionHeading icon={FileTextIcon} title={t('change_plan.note')} />
                        </div>
                        <div className="max-h-48 overflow-y-auto pr-1">
                            <p className="whitespace-pre-wrap text-xs text-foreground/80 leading-relaxed italic">
                                {request.note ? `"${request.note}"` : t('change_plan.no_note')}
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 flex flex-col gap-2 rounded-xl border border-border/60 bg-muted/10 p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2">
                            <span className="text-xs text-muted-foreground">{t('change_plan.current_status')}:</span>
                            <StatusBadge status={request.status} />
                        </div>

                        <div className="flex items-center gap-2">
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
                </div>
            ) : null}
        </FormDialog>
    );
}

function pointsLabel(value: number | string | null | undefined, unit: string): string {
    if (value == null || value === '') {
        return '—';
    }

    if (typeof value !== 'number' && typeof value !== 'string') {
        return '—';
    }

    return `${String(value)} ${unit}`;
}
