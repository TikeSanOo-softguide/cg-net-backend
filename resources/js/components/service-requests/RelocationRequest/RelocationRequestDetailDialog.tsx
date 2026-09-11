import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { ArrowRightIcon, FileTextIcon, MapPinIcon, NavigationIcon, PhoneIcon, UserIcon, WifiIcon } from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import { formActionBarClass, formActionSubmitClass } from '@/components/FormActionBar';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import { CopyValueButton } from '@/components/CopyValueButton';
import { CHANGE_PLAN_STATUS } from '@/lib/CommonNameConst';

type RelocationRequest = {
    id: number;
    status: string;
    phone: string;
    details?: string | null;
    current_address: string;
    new_address: string;
    user: {
        name: string;
        phone: string;
    };
    broadband_account: {
        account_number: string;
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

        patch(`/service-requests/relocations/${request.id}/status`, {
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
            size="xl"
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
                                {request.user.name}
                            </p>
                            <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                <PhoneIcon className="size-3" />
                                <span className={request.status === 'cancelled' ? 'select-none' : ''}>
                                    {request.phone}
                                </span>
                                {request.status !== 'cancelled' && (
                                    <CopyValueButton
                                        value={request.phone}
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
                                {request.broadband_account.account_number}
                            </p>
                            <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                <UserIcon className="size-3" />
                                <span className="font-mono">{request.user.name}</span>
                                <span className={`font-mono ${request.status === 'cancelled' ? 'select-none' : ''}`}>
                                    ({request.user?.phone})
                                </span>
                                {request.status !== 'cancelled' && (
                                    <CopyValueButton
                                        value={request.user.phone}
                                        label={t('relocation_requests.copy_phone')}
                                    />
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-border/60 p-4">
                        <div className="mb-4 flex items-center justify-between">
                            <div>
                                <p className="mt-0.5 text-sm font-semibold">
                                    {t('relocation_requests.service_transfer')}
                                </p>
                            </div>

                            <div className="flex size-8 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <NavigationIcon className="size-4" />
                            </div>
                        </div>

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
                        <p className="mb-2 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <FileTextIcon className="size-3.5 text-muted-foreground/70" />
                            {t('relocation_requests.detail')}
                        </p>

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
