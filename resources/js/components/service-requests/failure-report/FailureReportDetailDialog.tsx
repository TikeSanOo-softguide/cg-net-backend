import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import {
    Calendar1Icon,
    FileTextIcon,
    ImageIcon,
    ImageOffIcon,
    NavigationIcon,
    PhoneIcon,
    UserIcon,
    WifiOffIcon,
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

type FailureReport = {
    id: number;
    ticket_number: string | null;
    label?: string | null;
    status: string;
    customer_name: string;
    customer_phone: string;
    broadband_account_number: string;
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
    const { t } = useTranslation();
    const nextStatus =
        request?.status === REQUEST_STATUS.UNDER_REVIEW
            ? REQUEST_STATUS.APPROVED
            : REQUEST_STATUS.UNDER_REVIEW;
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

    const ticket = request.ticket_number?.trim() ?? '';

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
                                <span className="inline-flex items-center gap-1 rounded-full bg-background px-2.5 py-1 font-medium text-foreground">
                                    <WifiOffIcon className="size-3.5" />
                                    {t(`failure_report.${request.failure_type}`)}
                                </span>
                                <span className="inline-flex items-center gap-1">
                                    <Calendar1Icon className="size-3.5" />
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
                                { label: t('customers.name'), value: request.customer_name },
                                {
                                    label: t('requests.phone'),
                                    value: formatPhoneInternational(request.customer_phone),
                                    copy: request.customer_phone,
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

                    <div className="rounded-xl border border-border/60 bg-muted/25 p-4">
                        <div className="mb-3">
                            <ServiceRequestSectionHeading icon={ImageIcon} title={t('requests.images')} />
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
                        <div className="mb-2">
                            <ServiceRequestSectionHeading icon={FileTextIcon} title={t('cms.description')} />
                        </div>

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
