import {
    CircleDotIcon,
    DollarSignIcon,
    ImageIcon,
    NetworkIcon,
    PackageIcon,
    PercentIcon,
    RouterIcon,
    SquarePenIcon,
    StarIcon,
    XIcon,
    ZapIcon,
} from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import { formActionBarClass, formActionButtonClass } from '@/components/FormActionBar';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';

export type PackageDetailMember = {
    id: number;

    network_id: number;
    network: {
        id: number;
        name_en: string;
        name_zh: string;
        name_my: string;
    } | null;

    speed_id: number;
    speed: {
        id: number;
        mbps: number;
    } | null;

    term_id: number;
    term: {
        id: number;
        months: number;
    } | null;

    price: string;
    image_url?: string | null;
    installation_fee: string;

    includes_free_iptv: boolean;
    is_active: boolean;
    sort_order: number;
    recommended: boolean;

    created_at?: string | null;
};

type PackageDetailDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    package: PackageDetailMember | null;
    onEdit?: (packageItem: PackageDetailMember) => void;
};

export function PackageDetailDialog({
    open,
    onOpenChange,
    package: packageItem,
    onEdit,
}: PackageDetailDialogProps) {
    const { t } = useTranslation();
    const can = useCan();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('package.details')}
            description={t('package.details_description')}
            icon={PackageIcon}
        >
            {open && packageItem ? (
                <div className="flex min-h-0 flex-1 flex-col">
                    <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <FormField
                                label={t('packages.network')}
                                htmlFor="view-network"
                                icon={NetworkIcon}
                            >
                                <Input
                                    id="view-network"
                                    value={packageItem.network?.name_en ?? '—'}
                                    readOnly
                                />
                            </FormField>

                            <FormField
                                label={t('packages.speed')}
                                htmlFor="view-speed"
                                icon={ZapIcon}
                            >
                                <Input
                                    id="view-speed"
                                    value={packageItem.speed?.mbps ?? '—'}
                                    readOnly
                                />
                            </FormField>

                            <FormField
                                label={t('packages.term')}
                                htmlFor="view-term"
                                icon={RouterIcon}
                            >
                                <Input
                                    id="view-term"
                                    value={packageItem.term?.months ?? '—'}
                                    readOnly
                                />
                            </FormField>

                            <FormField
                                label={t('packages.price')}
                                htmlFor="view-price"
                                icon={DollarSignIcon}
                            >
                                <Input
                                    id="view-price"
                                    value={packageItem.price}
                                    readOnly
                                />
                            </FormField>

                            <FormField
                                label={t('packages.installation_fee')}
                                htmlFor="view-installation-fee"
                                icon={DollarSignIcon}
                            >
                                <Input
                                    id="view-installation-fee"
                                    value={packageItem.installation_fee}
                                    readOnly
                                />
                            </FormField>

                            <FormField
                                label={t('cms.sort_order')}
                                htmlFor="view-sort-order"
                                icon={PercentIcon}
                            >
                                <Input
                                    id="view-sort-order"
                                    value={String(packageItem.sort_order)}
                                    readOnly
                                />
                            </FormField>

                            <FormField
                                label={t('common.status')}
                                htmlFor="view-status"
                                icon={CircleDotIcon}
                            >
                                <div
                                    id="view-status"
                                    className="flex h-10 items-center"
                                >
                                    <StatusBadge
                                        status={
                                            packageItem.is_active
                                                ? 'active'
                                                : 'inactive'
                                        }
                                    />
                                </div>
                            </FormField>

                            <FormField
                                label={t('packages.recommended')}
                                htmlFor="view-recommended"
                                icon={StarIcon}
                            >
                                <Input
                                    id="view-recommended"
                                    value={
                                        packageItem.recommended
                                            ? t('common.yes')
                                            : t('common.no')
                                    }
                                    readOnly
                                />
                            </FormField>

                            <FormField
                                label={t('package.free_iptv')}
                                htmlFor="view-free-iptv"
                                icon={PackageIcon}
                            >
                                <Input
                                    id="view-free-iptv"
                                    value={
                                        packageItem.includes_free_iptv
                                            ? t('common.yes')
                                            : t('common.no')
                                    }
                                    readOnly
                                />
                            </FormField>

                            <FormField
                                label={t('cms.image')}
                                htmlFor="view-image"
                                icon={ImageIcon}
                                className="sm:col-span-2"
                            >
                                <Input
                                    id="view-image"
                                    value={packageItem.image_url ?? '—'}
                                    readOnly
                                />
                            </FormField>

                            <FormField
                                label={t('customers.joined')}
                                htmlFor="view-created-at"
                                icon={PackageIcon}
                                className="sm:col-span-2"
                            >
                                <Input
                                    id="view-created-at"
                                    value={packageItem.created_at ?? '—'}
                                    readOnly
                                />
                            </FormField>
                        </div>
                    </div>

                    <div className={formActionBarClass}>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className={formActionButtonClass}
                            onClick={() => onOpenChange(false)}
                        >
                            <XIcon className="size-3.5" strokeWidth={1.85} />
                            {t('common.close')}
                        </Button>

                        {can('packages.update') && onEdit ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                className={formActionButtonClass}
                                onClick={() => onEdit(packageItem)}
                            >
                                <SquarePenIcon
                                    className="size-3.5"
                                    strokeWidth={1.85}
                                />
                                {t('common.edit')}
                            </Button>
                        ) : null}
                    </div>
                </div>
            ) : null}
        </FormDialog>
    );
}
