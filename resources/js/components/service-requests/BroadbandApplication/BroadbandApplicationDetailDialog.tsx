import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import {
    BadgeIcon,
    CalendarDaysIcon,
    CircleCheckIcon,
    GaugeIcon,
    HashIcon,
    HouseIcon,
    IdCardIcon,
    ImageOffIcon,
    ImagesIcon,
    LandmarkIcon,
    MapIcon,
    NetworkIcon,
    PackageIcon,
    SmartphoneIcon,
    UserRoundIcon,
    MapPinIcon,
    PhoneIcon,
    UserIcon,
    WifiIcon,
    FileIcon,
    KeyRoundIcon,
} from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { CopyValueButton } from '@/components/CopyValueButton';
import { CHANGE_PLAN_STATUS } from '@/lib/CommonNameConst';

export type BroadbandApplicationDetail = {
    id: number;
    phone: string;
    address: string;
    note: string | null;
    status: string;
    id_type: string;
    id_name: string;
    id_number: string;
    photos: {
        id: number;
        image_url: string;
    }[];
    user: {
        id: number;
        name: string;
        phone: string;
    } | null;
    area: {
        id: number;
        name_en: string;
        name_zh: string;
        name_my: string;
        region?: {
            id: number;
            name_en: string;
            name_zh: string;
            name_my: string;
            state?: {
                id: number;
                name_en: string;
                name_zh: string;
                name_my: string;
            } | null;
        } | null;
    } | null;
    package: {
        id: number;
        price?: string | number;
        network?: {
            id: number;
            name_en: string;
            name_zh: string;
            name_my: string;
        } | null;
        speed?: {
            id: number;
            mbps: number;
        } | null;
        term?: {
            id: number;
            months: number;
        } | null;
    } | null;
    admin: {
        id: number;
        username: string;
    } | null;
};

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

        patch(`/service-requests/installations/${request.id}/status`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('menu.installation_applications')}
            description={t('menu.installation_applications_description')}
            icon={WifiIcon}
            size="lg"
        >
            <div className="mt-1 space-y-4 overflow-y-auto p-2">
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-xl border border-border/60 bg-muted/20 p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <div className="flex size-7 items-center justify-center rounded-lg bg-primary/10">
                                <UserIcon className="size-3.5 text-primary" />
                            </div>

                            <p className="text-xs font-semibold text-foreground">{t('requests.contact_information')}</p>
                        </div>

                        <div className="space-y-2.5">
                            <div className="flex items-center gap-2">
                                <UserIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="text-sm font-semibold text-foreground">
                                    {request.user?.name ?? request.id_name ?? '—'}
                                </span>
                            </div>

                            <div className="flex items-center gap-2">
                                <PhoneIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="text-xs text-muted-foreground">{request.user?.phone ?? '—'}</span>
                                {request.user?.phone && (
                                    <CopyValueButton value={request.user.phone} label="Phone number" />
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                <SmartphoneIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="text-xs text-muted-foreground">{request.phone ?? '—'}</span>
                                {request.phone && <CopyValueButton value={request.phone} label="Phone number" />}
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border border-border/60 bg-muted/20 p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <div className="flex size-7 items-center justify-center rounded-lg bg-primary/10">
                                <MapPinIcon className="size-3.5 text-primary" />
                            </div>
                            <p className="text-xs font-semibold text-foreground">
                                {t('broadband_application.location')}
                            </p>
                        </div>

                        <div className="space-y-2.5">
                            <div className="flex items-center gap-2">
                                <MapPinIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="text-sm font-semibold text-foreground">
                                    {request.area?.[`name_${locale}`] ?? '—'}
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <MapIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="text-xs text-muted-foreground">
                                    {request.area?.region?.[`name_${locale}`] ?? '—'}
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <LandmarkIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="text-xs text-muted-foreground">
                                    {request.area?.region?.state?.[`name_${locale}`] ?? '—'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-xl border border-border/60 bg-muted/20 p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <div className="flex size-7 items-center justify-center rounded-lg bg-primary/10">
                                <PackageIcon className="size-3.5 text-primary" />
                            </div>
                            <p className="text-xs font-semibold text-foreground">{t('menu.packages')}</p>
                        </div>

                        <div className="space-y-2.5">
                            <div className="flex items-center gap-2">
                                <NetworkIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="text-sm font-semibold text-foreground">
                                    {request.package?.network?.[`name_${locale}`] ?? '—'}
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <GaugeIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="text-xs text-muted-foreground">
                                    {request.package?.speed?.mbps ?? '—'} Mbps
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <CalendarDaysIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="text-xs text-muted-foreground">
                                    {request.package?.term?.months ?? '—'} {t('packages.months')}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border border-border/60 bg-muted/20 p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <div className="flex size-7 items-center justify-center rounded-lg bg-primary/10">
                                <IdCardIcon className="size-3.5 text-primary" />
                            </div>

                            <p className="text-xs font-semibold text-foreground">
                                {t('broadband_application.identity')}
                            </p>
                        </div>
                        <div className="space-y-2.5">
                            <div className="flex items-center gap-2">
                                <UserRoundIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="text-sm font-semibold text-foreground">{request.id_name ?? '—'}</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <KeyRoundIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="font-mono text-xs text-muted-foreground">
                                    {request.id_number ?? '—'}
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <BadgeIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="text-xs text-muted-foreground">{request.id_type ?? '—'}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-border/60 bg-muted/20 p-4">
                    <div className="mb-3 flex items-center gap-2">
                        <div className="flex size-7 items-center justify-center rounded-lg bg-primary/10">
                            <ImagesIcon className="size-3.5 text-primary" />
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-foreground">{t('requests.images')}</p>
                        </div>
                    </div>

                    {request.photos?.length ? (
                        <div className="flex gap-3 overflow-x-auto pb-1">
                            {request.photos.map((photo) => (
                                <div
                                    key={photo.id}
                                    className="group relative shrink-0 overflow-hidden rounded-lg border border-border/50 bg-background"
                                >
                                    <img
                                        src={photo.image_url}
                                        alt=""
                                        className="h-[200px] w-[320px] object-cover transition-transform duration-200 group-hover:scale-105"
                                    />
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="flex h-24 items-center justify-center rounded-lg border border-dashed border-border/50 bg-background/40">
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <ImageOffIcon className="size-4" />
                            </div>
                        </div>
                    )}
                </div>

                <div className="rounded-xl border border-border/60 bg-muted/20 p-4">
                    <div className="mb-3 flex items-center gap-2">
                        <div className="flex size-7 items-center justify-center rounded-lg bg-primary/10">
                            <HouseIcon className="size-3.5 text-primary" />
                        </div>
                        <p className="text-xs font-semibold text-foreground">{t('requests.address')}</p>
                    </div>
                    <div className="rounded-lg border border-border/40 bg-background/60 px-3.5 py-3">
                        <div className="flex items-center gap-2">
                            <span className="text-xs text-muted-foreground">{request.address ?? '—'}</span>
                            {request.address && <CopyValueButton value={request.address} label="Address" />}
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-border/60 bg-muted/20 p-4">
                    <div className="mb-3 flex items-center gap-2">
                        <div className="flex size-7 items-center justify-center rounded-lg bg-primary/10">
                            <FileIcon className="size-3.5 text-primary" />
                        </div>
                        <p className="text-xs font-semibold text-foreground">{t('requests.note')}</p>
                    </div>
                    <div className="rounded-lg border border-border/40 bg-background/60 px-3.5 py-3">
                        <p className="whitespace-pre-wrap text-sm leading-6 text-foreground">{request.note || '—'}</p>
                    </div>
                </div>
                <div className="flex flex-col gap-3 rounded-xl border border-border/60 bg-muted/10 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2">
                        <span className="text-xs text-muted-foreground">{t('requests.current_status')}:</span>
                        <StatusBadge status={request.status} />
                    </div>

                    {canUpdate && request.status !== 'cancelled' && (
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
        </FormDialog>
    );
}
