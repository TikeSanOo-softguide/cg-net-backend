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
    WifiIcon,
    WifiOffIcon,
} from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
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
            title={t('menu.failure_reports')}
            description={t('menu.failure_reports_description')}
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
                                    {formatPhoneInternational(request.customer_phone)}
                                </span>
                                {request.status !== 'cancelled' && (
                                    <CopyValueButton
                                        value={request.customer_phone}
                                        label={t('requests.copy_phone')}
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
                                {request.broadband_account_number}
                            </p>
                            <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                <UserIcon className="size-3" />
                                <span className="font-mono">{request.account_customer}</span>
                                <span className={`font-mono ${request.status === 'cancelled' ? 'select-none' : ''}`}>
                                    ({formatPhoneInternational(request.contact_phone)})
                                </span>
                                {request.status !== 'cancelled' && (
                                    <CopyValueButton
                                        value={request.contact_phone}
                                        label={t('requests.copy_phone')}
                                        format="phone"
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
                                    <span className="text-xs text-muted-foreground">
                                        {t('broadband_application.ticket_number')}
                                    </span>
                                    <span className="inline-flex items-center gap-1">
                                        {ticket ? (
                                            <span className="inline-flex max-w-full items-center truncate rounded-md bg-primary/10 px-2 py-0.5 font-mono text-[11px] font-medium text-primary">
                                                {ticket}
                                            </span>
                                        ) : (
                                            <span className="text-sm text-muted-foreground">—</span>
                                        )}
                                        {ticket && (
                                            <CopyValueButton
                                                value={ticket}
                                                label={t('broadband_application.ticket_number')}
                                            />
                                        )}
                                    </span>
                                </div>
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
