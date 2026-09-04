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
import { useTranslation } from '@/hooks/useTranslation';
import { CHANGE_PLAN_STATUS } from '@/lib/CommonNameConst';
import { cn, formatDate } from '@/lib/utils';

export type RelationItem = {
    id: number;
    name_en: string;
    name_my: string;
    name_zh: string;
};

export type Package = {
    id: number;
    price: string | number;
    network?: RelationItem;
    speed?: { id: number; mbps: number };
    term?: { id: number; months: number };
};

export type ChangePlanRequestItem = {
    id: number;
    preferred_date: string | null;
    contact_name: string;
    contact_phone: string;
    note: string | null;
    status: string;
    user: { id: number; name: string; phone: string };
    broadband_account: { id: number; account_number: string };
    current_package: Package;
    new_package: Package;
    admin: { id: number; username: string } | null;
};

type ChangePlanDetailDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    request: ChangePlanRequestItem | null;
    statuses?: string[];
};

export function packageName(pkg: Package): string {
    const parts = [
        pkg.network?.name_en,
        pkg.speed ? `${pkg.speed.mbps} Mbps` : null,
        pkg.term ? `${pkg.term.months} months` : null,
    ].filter(Boolean);

    return parts.length > 0 ? parts.join(' - ') : `Package #${pkg.id}`;
}

export function ChangePlanDetailDialog({
    open,
    onOpenChange,
    request,
    statuses = [CHANGE_PLAN_STATUS.UNDER_REVIEW, CHANGE_PLAN_STATUS.APPROVED],
}: ChangePlanDetailDialogProps) {
    const { t } = useTranslation();
    const { data, setData, patch, processing } = useForm({
        status:
            request?.status === CHANGE_PLAN_STATUS.UNDER_REVIEW
                ? CHANGE_PLAN_STATUS.APPROVED
                : CHANGE_PLAN_STATUS.UNDER_REVIEW,
    });

    useEffect(() => {
        if (request) {
            setData(
                'status',
                request.status === CHANGE_PLAN_STATUS.UNDER_REVIEW
                    ? CHANGE_PLAN_STATUS.APPROVED
                    : CHANGE_PLAN_STATUS.UNDER_REVIEW,
            );
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
        request?.status === CHANGE_PLAN_STATUS.UNDER_REVIEW &&
        request?.preferred_date &&
        new Date(request.preferred_date) < new Date();

    const nextStatus =
        request?.status === CHANGE_PLAN_STATUS.UNDER_REVIEW
            ? CHANGE_PLAN_STATUS.APPROVED
            : CHANGE_PLAN_STATUS.UNDER_REVIEW;

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
                /* Added max-h-[80vh] and overflow-y-auto to allow vertical scrolling when text is long */
                <div className="mt-1 space-y-4 overflow-y-auto p-2">
                    {/* Overdue Alert Banner */}
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

                    {/* Customer & Account Details Grid */}
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
                                {request.contact_phone}
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
                                <span className="font-mono">
                                    {request.user.name} ({request.user.phone})
                                </span>
                            </p>
                        </div>
                    </div>

                    {/* Migration Path Highlight Card */}
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
                            {/* Current Package */}
                            <div className="space-y-1">
                                <span className="text-[11px] font-medium text-muted-foreground">
                                    {t('change_plan.current_plan')}
                                </span>
                                <p className="text-xs font-semibold text-foreground leading-snug">
                                    {packageName(request.current_package)}
                                </p>
                                <p className="text-xs font-mono font-medium text-muted-foreground">
                                    ${request.current_package.price}
                                </p>
                            </div>

                            {/* Transition Indicator */}
                            <div className="flex size-8 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <ArrowRightIcon className="size-4" />
                            </div>

                            {/* Requested Package */}
                            <div className="space-y-1 text-right">
                                <span className="text-[11px] font-bold text-primary">
                                    {t('change_plan.requested_plan')}
                                </span>
                                <p className="text-xs font-bold text-foreground leading-snug text-primary">
                                    {packageName(request.new_package)}
                                </p>
                                <p className="text-xs font-mono font-semibold text-primary">
                                    ${request.new_package.price}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Customer Note Card */}
                    <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5">
                        <p className="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <FileTextIcon className="size-3.5 text-muted-foreground/70" />
                            {t('change_plan.note')}
                        </p>
                        {/* Added max-h-48 and overflow-y-auto specifically for extra long note text */}
                        <div className="max-h-48 overflow-y-auto pr-1">
                            <p className="whitespace-pre-wrap text-xs text-foreground/80 leading-relaxed italic">
                                {request.note ? `"${request.note}"` : t('change_plan.no_note')}
                            </p>
                        </div>
                    </div>

                    {/* Scalable Dynamic Status Footer */}
                    <div className="mt-4 flex flex-col gap-2 rounded-xl border border-border/60 bg-muted/10 p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2">
                            <span className="text-xs text-muted-foreground">{t('change_plan.current_status')}:</span>
                            <StatusBadge status={request.status} />
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                onClick={handleStatusUpdate}
                                className="h-9 px-4 text-xs font-semibold"
                            >
                                {t(`status.${nextStatus}`)}
                            </Button>
                        </div>
                    </div>
                </div>
            ) : null}
        </FormDialog>
    );
}
