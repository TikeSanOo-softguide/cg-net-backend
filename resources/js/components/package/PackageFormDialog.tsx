import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { PackagePlusIcon, PackageSearchIcon } from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import {
    emptyPackageForm,
    PackageForm,
    type PackageFormValues,
    type PackageOption,
} from '@/components/package/PackageForm';
import type { PackageDetailMember } from '@/components/package/PackageDetailDialog';
import { useTranslation } from '@/hooks/useTranslation';

export type PackageFormMember = PackageDetailMember;

type PackageFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    package: PackageFormMember | null;
    networks: PackageOption[];
    speeds: PackageOption[];
    terms: PackageOption[];
};

const modalVisit = {
    headers: { 'X-Modal': '1' },
    preserveScroll: true,
};

export function PackageFormDialog({
    open,
    onOpenChange,
    package: packageItem,
    networks,
    speeds,
    terms,
}: PackageFormDialogProps) {
    const { t } = useTranslation();

    const isEdit = packageItem !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('packages.edit') : t('packages.create')}
            description={isEdit ? t('packages.edit_description') : t('packages.create_description')}
            icon={isEdit ? PackageSearchIcon : PackagePlusIcon}
        >
            {open ? (
                <PackageFormDialogBody
                    key={packageItem ? `edit-${packageItem.id}` : 'create'}
                    package={packageItem}
                    networks={networks}
                    speeds={speeds}
                    terms={terms}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function PackageFormDialogBody({
    package: packageItem,
    networks,
    speeds,
    terms,
    onClose,
}: {
    package: PackageFormMember | null;
    networks: PackageOption[];
    speeds: PackageOption[];
    terms: PackageOption[];
    onClose: () => void;
}) {
    const isEdit = packageItem !== null;

    const form = useForm<PackageFormValues>(
        packageItem
            ? {
                  network_id: packageItem.network_id,
                  speed_id: packageItem.speed_id,
                  term_id: packageItem.term_id,
                  price: packageItem.price,
                  image_url: null,
                  installation_fee: packageItem.installation_fee,
                  includes_free_iptv: packageItem.includes_free_iptv,
                  is_active: packageItem.is_active,
                  sort_order: packageItem.sort_order,
                  recommended: packageItem.recommended,
              }
            : emptyPackageForm(),
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            ...modalVisit,
            forceFormData: true,
            onSuccess: onClose,
        };

        if (isEdit && packageItem) {
            form.transform((data) => ({
                ...data,
                _method: 'PUT',
            }));
            form.post(`/packages/${packageItem.id}`, options);

            return;
        }

        form.post('/packages', options);
    };

    return (
        <PackageForm
            form={form}
            networks={networks}
            speeds={speeds}
            terms={terms}
            onSubmit={submit}
            onCancel={onClose}
            mode={isEdit ? 'edit' : 'create'}
            existingImageUrl={packageItem?.image_url ?? null}
        />
    );
}
