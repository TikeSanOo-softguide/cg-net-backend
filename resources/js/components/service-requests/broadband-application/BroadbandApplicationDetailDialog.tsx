import { useEffect, type ReactNode } from 'react';
import { useForm } from '@inertiajs/react';
import {
    CalendarIcon,
    FileTextIcon,
    HouseIcon,
    MapPinIcon,
    PackageIcon,
    PhoneIcon,
    UserIcon,
    WifiIcon,
} from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { CopyValueButton } from '@/components/CopyValueButton';
import { REQUEST_STATUS } from '@/lib/CommonNameConst';
import { formatPhoneInternational } from '@/lib/phone';
import { cn, formatDate } from '@/lib/utils';

type LocalizedName = {
    name_en: string;
    name_zh: string;
    name_my: string;
};

export type BroadbandApplicationDetail = {
    id: number;
    phone: string;
    address: string;
    note: string | null;
    status: string;
    name: string;
    service_type: string | null;
    ticket_number: string | null;
    created_at: string;
    package_detail: {
        product_id?: string;
        product_name?: string;
        product_line?: string;
        product_speed?: string;
        product_price?: number | string;
        installation_fee?: number | string;
        month_cycle?: number | string;
        month_cycle_unit?: string;
    } | null;
    user: {
        id: number;
        name: string;
        phone: string;
        broadband_account_number: string | null;
    } | null;
    area: {
        id: number;
        name_en: string;
        name_zh: string;
        name_my: string;
        region?: (LocalizedName & {
            id: number;
            state?: (LocalizedName & { id: number }) | null;
        }) | null;
    } | null;
    admin: {
        id: number;
        username: string;
    } | null;
};

function localizedName(item: LocalizedName | null | undefined, locale: string): string {
    if (!item) {
        return '—';
    }

    return (
        item[`name_${locale}` as keyof LocalizedName] ||
        item.name_en ||
        item.name_my ||
        item.name_zh ||
        '—'
    );
}

function Section({
    icon: Icon,
    title,
    children,
    className,
}: {
    icon: typeof UserIcon;
    title: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={cn('rounded-xl border border-border/60 bg-muted/20 p-4', className)}>
            <div className="mb-3 flex items-center gap-2">
                <div className="flex size-7 items-center justify-center rounded-lg bg-primary/10">
                    <Icon className="size-3.5 text-primary" />
                </div>
                <h3 className="text-xs font-semibold text-foreground">{title}</h3>
            </div>
            {children}
        </section>
    );
}

function Field({
    label,
    value,
    copy,
    copyLabel,
    mono = false,
}: {
    label: string;
    value: string;
    copy?: string | null;
    copyLabel?: string;
    mono?: boolean;
}) {
    const shown = value.trim() || '—';

    return (
        <div className="min-w-0">
            <p className="text-[11px] text-muted-foreground">{label}</p>
            <div className="mt-0.5 flex min-w-0 items-center gap-1">
                <p className={cn('min-w-0 text-sm font-medium break-words text-foreground', mono && 'font-mono')}>{shown}</p>
                {copy && copyLabel ? <CopyValueButton value={copy} label={copyLabel} /> : null}
            </div>
        </div>
    );
}

export function BroadbandApplicationDetailDialog({
    open,
    onOpenChange,
    request,
    statuses,
    canUpdate,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    request: BroadbandApplicationDetail | null;
    statuses: string[];
    canUpdate: boolean;
}) {
    const { t, locale } = useTranslation();
    const nextStatus =
        request?.status === REQUEST_STATUS.UNDER_REVIEW ? REQUEST_STATUS.APPROVED : REQUEST_STATUS.UNDER_REVIEW;
    const { data, setData, patch } = useForm({ status: nextStatus });
    const nextButton = request?.status === REQUEST_STATUS.UNDER_REVIEW ? 'approve' : 'review';

    useEffect(() => {
        if (request) {
            setData('status', nextStatus);
        }
    }, [request?.id, request?.status, statuses]);

    if (!request) {
        return null;
    }

    const handleStatusUpdate = () => {
        if (!request || !data.status) {
            return;
        }

        patch(`/service-requests/installations/${request.id}/status`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const detail = request.package_detail;
    const ticket = request.ticket_number?.trim() || '';
    const accountNumber = request.user?.broadband_account_number?.trim() || '';
    const contactPhone = request.phone ? formatPhoneInternational(request.phone) : '';
    const serviceLabel =
        request.service_type === 'CG'
            ? t('broadband_application.cg_router')
            : request.service_type?.trim() || '—';

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('menu.installation_applications')}
            description={t('menu.installation_applications_description')}
            icon={WifiIcon}
            size="lg"
        >
            <div className="mt-1 max-h-[70vh] space-y-3 overflow-y-auto p-1">
                <div className="rounded-xl border border-primary/20 bg-primary/5 p-4">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                {t('broadband_application.ticket_number')}
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
                                    <CopyValueButton value={ticket} label={t('broadband_application.ticket_number')} />
                                ) : null}
                            </div>
                        </div>
                        <StatusBadge status={request.status} />
                    </div>
                    <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <span className="rounded-full bg-background px-2.5 py-1 font-medium text-foreground">
                            {serviceLabel}
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <CalendarIcon className="size-3.5" />
                            {formatDate(request.created_at)}
                        </span>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <Section icon={UserIcon} title={t('broadband_application.app_user')}>
                        <div className="space-y-3">
                            <Field label={t('customers.name')} value={request.user?.name || '—'} />
                            <Field
                                label={t('requests.broadband_account')}
                                value={accountNumber || '—'}
                                copy={accountNumber}
                                copyLabel={t('requests.broadband_account')}
                            />
                        </div>
                    </Section>

                    <Section icon={PhoneIcon} title={t('broadband_application.installation_contact')}>
                        <div className="space-y-3">
                            <Field label={t('customers.name')} value={request.name || '—'} />
                            <Field
                                label={t('requests.phone')}
                                value={contactPhone || '—'}
                                copy={contactPhone}
                                copyLabel={t('requests.copy_phone')}
                            />
                        </div>
                    </Section>
                </div>

                <Section icon={PackageIcon} title={t('broadband_application.package')}>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('broadband_application.package')} value={detail?.product_name?.trim() || '—'} />
                        <Field
                            label={t('broadband_application.network_provider')}
                            value={detail?.product_line?.trim() || '—'}
                        />
                        <Field label={t('broadband_application.speed')} value={speedLabel(detail?.product_speed)} />
                        <Field label={t('broadband_application.billing_period')} value={periodLabel(detail, t)} />
                        <Field
                            label={t('broadband_application.package_price')}
                            value={pointsLabel(detail?.product_price, t('billing_report.points'))}
                        />
                        <Field
                            label={t('broadband_application.installation_fee')}
                            value={pointsLabel(detail?.installation_fee, t('billing_report.points'))}
                        />
                    </div>
                </Section>

                <Section icon={MapPinIcon} title={t('broadband_application.location')}>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Field
                            label={t('broadband_application.state')}
                            value={localizedName(request.area?.region?.state, locale)}
                        />
                        <Field
                            label={t('broadband_application.region')}
                            value={localizedName(request.area?.region, locale)}
                        />
                        <Field label={t('broadband_application.area')} value={localizedName(request.area, locale)} />
                    </div>
                </Section>

                <Section icon={HouseIcon} title={t('requests.address')}>
                    <div className="flex items-start gap-1">
                        <p className="text-sm leading-6 text-foreground">{request.address?.trim() || '—'}</p>
                        {request.address ? (
                            <CopyValueButton value={request.address} label={t('requests.copy_address')} />
                        ) : null}
                    </div>
                </Section>

                {request.note?.trim() ? (
                    <Section icon={FileTextIcon} title={t('requests.note')}>
                        <p className="text-sm leading-6 whitespace-pre-wrap text-foreground">{request.note}</p>
                    </Section>
                ) : null}

                <div className="flex flex-col gap-3 rounded-xl border border-border/60 bg-muted/10 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-xs text-muted-foreground">{t('requests.current_status')}:</span>
                        <StatusBadge status={request.status} />
                        {request.status === 'approved' && request.admin ? (
                            <span className="text-[11px] text-muted-foreground">
                                {t('requests.approved_by')}: {request.admin.username}
                            </span>
                        ) : null}
                    </div>

                    {canUpdate && request.status !== 'cancelled' && (
                        <Button type="button" size="sm" onClick={handleStatusUpdate} className="h-9 px-4 text-xs font-semibold">
                            {t(`status.${nextButton}`)}
                        </Button>
                    )}
                </div>
            </div>
        </FormDialog>
    );
}

function speedLabel(speed?: string): string {
    const value = speed?.trim() ?? '';
    if (!value) {
        return '—';
    }

    if (/mbps/i.test(value)) {
        return value;
    }

    const digits = value.match(/\d+/)?.[0];

    return digits ? `${digits} Mbps` : value;
}

function periodLabel(
    detail: BroadbandApplicationDetail['package_detail'],
    t: (key: string) => string,
): string {
    const cycle = Number(detail?.month_cycle);
    if (!Number.isFinite(cycle) || cycle <= 0) {
        return '—';
    }

    const unit = detail?.month_cycle_unit ?? '';
    const isDay = unit.includes('天') || /day/i.test(unit);
    const label = isDay
        ? t(cycle === 1 ? 'broadband_application.day' : 'broadband_application.days')
        : t(cycle === 1 ? 'broadband_application.month' : 'broadband_application.months');

    return `${cycle} ${label}`;
}

function pointsLabel(value: number | string | null | undefined, unit: string): string {
    if (value == null || value === '') {
        return '—';
    }

    const amount = typeof value === 'number' ? value : Number(value);
    if (!Number.isFinite(amount)) {
        return '—';
    }

    return `${new Intl.NumberFormat().format(amount)} ${unit}`;
}
