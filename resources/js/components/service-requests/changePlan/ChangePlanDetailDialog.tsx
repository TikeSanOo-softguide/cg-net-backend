import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    ArrowRightIcon,
    CalendarIcon,
    FileTextIcon,
    PhoneIcon,
    UserIcon,
    WifiIcon,
} from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
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
                                <p className="mt-0.5 text-muted-foreground">
                                    {t('change_plan.preferred_date')}: {formatDate(request.preferred_date)}{' '}
                                    {t('change_plan.urgent_processing')}
                                </p>
                            </div>
                        </div>
                    )}

                    <div className="rounded-xl border border-primary/20 bg-primary/5 p-4">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                    {t('change_plan.ticket_number')}
                                </p>
                                <div className="mt-1 flex items-center gap-1.5">
                                    {ticket ? (
                                        <span className="inline-flex max-w-full items-center truncate rounded-md bg-primary/10 px-2 py-0.5 font-mono text-[11px] font-medium text-primary">
                                            {ticket}
                                        </span>
                                    ) : (
                                        <span className="text-sm text-muted-foreground">—</span>
                                    )}
                                    {ticket ? (
                                        <CopyValueButton value={ticket} label={t('change_plan.ticket_number')} />
                                    ) : null}
                                </div>
                            </div>
                            <StatusBadge status={request.status} />
                        </div>
                        <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <span className="rounded-full bg-background px-2.5 py-1 font-medium text-foreground">
                                {serviceLabel}
                            </span>
                        </div>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5 space-y-1">
                            <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                {t('change_plan.contact')}
                            </p>
                            <p className="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                                <UserIcon className="size-3.5 text-muted-foreground/70" />
                                {request.contact_name}
                            </p>
                            <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                <PhoneIcon className="size-3" />
                                <span className={request.status === 'cancelled' ? 'select-none' : ''}>
                                    {formatPhoneInternational(request.contact_phone)}
                                </span>
                                {request.status !== 'cancelled' && (
                                    <CopyValueButton
                                        value={request.contact_phone}
                                        label={t('change_password.copy_contact_phone')}
                                        format="phone"
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
                                {request.broadband_account_number ?? '—'}
                            </p>
                            <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                <UserIcon className="size-3" />
                                <span className="font-mono">{request.user.name}</span>
                                <span className={`font-mono ${request.status === 'cancelled' ? 'select-none' : ''}`}>
                                    ({formatPhoneInternational(request.user?.phone)})
                                </span>
                                {request.status !== 'cancelled' && (
                                    <CopyValueButton
                                        value={request.user?.phone}
                                        label={t('change_password.copy_account_phone')}
                                        format="phone"
                                    />
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="rounded-xl border border-primary/20 bg-primary/5 p-4">
                        <p className="mb-3 text-[11px] font-bold uppercase tracking-wider">
                            {t('change_plan.migration')}
                        </p>
                        <p className="flex items-center gap-1 text-xs text-muted-foreground">
                            <CalendarIcon className="size-3" />
                            <span className={cn(isExpired && 'font-semibold text-amber-600 dark:text-amber-400')}>
                                {t('change_plan.preferred_date')}: {formatDate(request.preferred_date)}
                            </span>
                        </p>
                        <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-3">
                            <div className="space-y-1">
                                <span className="text-[11px] font-medium text-muted-foreground">
                                    {t('change_plan.current_plan')}
                                </span>
                                <p className="text-xs font-semibold text-foreground leading-snug">
                                    {packageName(request.current_package_detail)}
                                </p>
                                <p className="text-xs font-mono font-medium text-muted-foreground">
                                    {pointsLabel(request.current_package_detail?.price, t('billing_report.points'))}
                                </p>
                            </div>

                            <div className="flex size-8 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <ArrowRightIcon className="size-4" />
                            </div>

                            <div className="space-y-1 text-right">
                                <span className="text-[11px] font-bold text-primary">
                                    {t('change_plan.requested_plan')}
                                </span>
                                <p className="text-xs font-bold text-foreground leading-snug text-primary">
                                    {packageName(request.new_package_detail)}
                                </p>
                                <p className="text-xs font-mono font-semibold text-primary">
                                    {pointsLabel(request.new_package_detail?.price, t('billing_report.points'))}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5">
                        <p className="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <FileTextIcon className="size-3.5 text-muted-foreground/70" />
                            {t('change_plan.note')}
                        </p>
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
