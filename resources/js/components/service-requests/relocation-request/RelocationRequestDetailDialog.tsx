import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import {
    ArrowRightIcon,
    CalendarIcon,
    FileTextIcon,
    MapPinIcon,
    NavigationIcon,
    PhoneIcon,
    UserIcon,
} from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import {
    ServiceRequestInfoCard,
    ServiceRequestSectionHeading,
} from '@/components/service-requests/ServiceRequestInfoCard';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { cn, formatDate } from '@/lib/utils';
import { CopyValueButton } from '@/components/CopyValueButton';
import { REQUEST_STATUS } from '@/lib/CommonNameConst';
import { formatPhoneInternational } from '@/lib/phone';

type RelocationRequest = {
    id: number;
    status: string;
    ticket_number: string | null;
    created_at: string;
    preferred_date: string | null;
    contact_name: string;
    contact_phone: string;
    details?: string | null;
    current_address: string;
    new_address: string;
    broadband_account_number: string | null;
    user: {
        name: string;
        phone: string;
    };
};

type AddressCardProps = {
    label: string;
    value: string;
    accent?: boolean;
    showCopy?: boolean;
};

function AddressCard({ label, value, accent = false, showCopy = true }: AddressCardProps) {
    return (
        <div
            className={cn(
                'flex h-full min-w-0 flex-col rounded-xl border p-3.5',
                accent ? 'border-primary/20 bg-primary/5' : 'border-border/60 bg-muted/20',
            )}
        >
            <p className="mb-1 text-[10px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">{label}</p>

            <div
                className={cn(
                    'flex items-start gap-1.5 text-sm font-medium leading-5',
                    accent ? 'text-primary' : 'text-foreground',
                )}
            >
                <MapPinIcon className="mt-0.5 size-3.5 shrink-0" />
                <span className="min-w-0">{value}</span>
                {showCopy && <CopyValueButton value={value} label={label} />}
            </div>
        </div>
    );
}

export function RelocationRequestDetailDialog({
    open,
    onOpenChange,
    request,
    statuses,
    canUpdate,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    request: RelocationRequest | null;
    statuses: string[];
    canUpdate: boolean;
}) {
    const { t } = useTranslation();
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
        if (!request || !data.status) return;

        patch(`/service-requests/relocations/${request.id}/status`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const ticket = request.ticket_number?.trim() || '';
    const serviceType = request.broadband_account_number?.startsWith('FTTH')
        ? 'FTTH'
        : request.broadband_account_number?.startsWith('CG')
          ? t('change_plan.cg_router')
          : '—';

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('relocation_requests.card_title')}
            description={t('menu.relocation_requests_description')}
            icon={NavigationIcon}
            size="xl"
        >
            {request ? (
                <div className="mt-1 space-y-4 overflow-y-auto p-2">
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
                                    copyLabel: t('requests.copy_phone'),
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
                                    copyLabel: t('requests.copy_phone'),
                                    copyFormat: 'phone',
                                    isCancelled: request.status === 'cancelled',
                                },
                            ]}
                        />
                    </div>

                    <div className="rounded-2xl border border-border/60 p-4">
                        <div className="mb-4">
                            <ServiceRequestSectionHeading
                                icon={NavigationIcon}
                                title={t('relocation_requests.service_transfer')}
                            />
                        </div>
                        <p className="mb-4 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <CalendarIcon className="size-3.5" />
                            <span>{t('relocation_requests.preferred_date')}:</span>
                            <span className="font-medium text-foreground">{formatDate(request.preferred_date)}</span>
                        </p>

                        <div className="grid gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                            <AddressCard
                                label={t('relocation_requests.current_location')}
                                value={request.current_address}
                                showCopy={request.status !== 'cancelled'}
                            />
                            <div className="hidden sm:flex">
                                <div className="flex size-9 items-center justify-center rounded-full border border-primary/20 bg-primary/5 text-primary">
                                    <ArrowRightIcon className="size-4" />
                                </div>
                            </div>
                            <AddressCard
                                label={t('relocation_requests.new_location')}
                                value={request.new_address}
                                accent
                                showCopy={request.status !== 'cancelled'}
                            />
                        </div>
                    </div>

                    <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5">
                        <div className="mb-2">
                            <ServiceRequestSectionHeading icon={FileTextIcon} title={t('relocation_requests.detail')} />
                        </div>

                        <div className="max-h-48 overflow-y-auto rounded-lg border border-border/40 bg-background/60 px-3 py-2.5 pr-2">
                            <p className="whitespace-pre-wrap text-sm leading-6 text-foreground">
                                {request.details || (
                                    <span className="text-xs italic text-muted-foreground">
                                        No additional details provided.
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
