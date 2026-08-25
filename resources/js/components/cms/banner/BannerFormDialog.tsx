import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { ImageIcon, SquarePenIcon } from 'lucide-react';

import { BannerForm, type BannerFormValues } from '@/components/cms/banner/BannerForm';
import { FormDialog } from '@/components/FormDialog';
import { cmsModalVisit } from '@/lib/cms-modal';
import { useTranslation } from '@/hooks/useTranslation';

export type BannerItem = {
    id: number;
    title: string;
    link_url: string | null;
    sort_order: number;
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
    image_url: string | null;
};

type BannerFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: BannerItem | null;
};

function emptyBannerForm(): BannerFormValues {
    return {
        title: '',
        link_url: '',
        sort_order: 0,
        start_date: '',
        end_date: '',
        is_active: true,
        image: null,
    };
}

export function BannerFormDialog({ open, onOpenChange, item }: BannerFormDialogProps) {
    const { t } = useTranslation();
    const isEdit = item !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('cms.edit_banner') : t('cms.create_banner')}
            description={isEdit ? t('cms.edit_banner_description') : t('cms.create_banner_description')}
            icon={isEdit ? SquarePenIcon : ImageIcon}
        >
            {open ? (
                <BannerFormDialogBody
                    key={item ? `edit-${item.id}` : 'create'}
                    item={item}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function BannerFormDialogBody({ item, onClose }: { item: BannerItem | null; onClose: () => void }) {
    const isEdit = item !== null;
    const form = useForm<BannerFormValues>(
        item
            ? {
                  title: item.title,
                  link_url: item.link_url ?? '',
                  sort_order: item.sort_order,
                  start_date: item.start_date ?? '',
                  end_date: item.end_date ?? '',
                  is_active: item.is_active,
                  image: null,
              }
            : emptyBannerForm(),
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            ...cmsModalVisit,
            forceFormData: true,
            onSuccess: onClose,
        };

        if (isEdit && item) {
            form.transform((data) => ({ ...data, _method: 'put' })).post(`/cms/banners/${item.id}`, options);

            return;
        }

        form.post('/cms/banners', options);
    };

    return (
        <BannerForm
            form={form}
            onSubmit={submit}
            onCancel={onClose}
            mode={isEdit ? 'edit' : 'create'}
            imageUrl={item?.image_url}
        />
    );
}
