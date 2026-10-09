import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { ImageIcon, SquarePenIcon } from 'lucide-react';

import { BannerForm, type BannerFormValues, type BannerType } from '@/components/cms/banner/BannerForm';
import { FormDialog } from '@/components/FormDialog';
import { cmsModalVisit } from '@/lib/cms-modal';
import { useTranslation } from '@/hooks/useTranslation';

export type BannerItem = {
    id: number;
    image_url_en: string | null;
    image_url_zh: string | null;
    image_url_my: string | null;
    type: BannerType;
    sort_order: number;
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
    created_at: string | null;
    updated_at: string | null;
};

type BannerFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: BannerItem | null;
    bannerTypeCounts: Partial<Record<BannerType, number>>;
};

function emptyBannerForm(): BannerFormValues {
    return {
        image_url_en: null,
        image_url_zh: null,
        image_url_my: null,
        type: 'web_background',
        sort_order: 0,
        start_date: '',
        end_date: '',
        is_active: true,
    };
}

export function BannerFormDialog({ open, onOpenChange, item, bannerTypeCounts }: BannerFormDialogProps) {
    const { t } = useTranslation();
    const isEdit = item !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('cms.banner.edit') : t('cms.banner.create')}
            description={isEdit ? t('cms.banner.edit_description') : t('cms.banner.create_description')}
            icon={isEdit ? SquarePenIcon : ImageIcon}
            size="3xl"
        >
            {open ? (
                <BannerFormDialogBody
                    key={item ? `edit-${item.id}` : 'create'}
                    item={item}
                    bannerTypeCounts={bannerTypeCounts}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function BannerFormDialogBody({
    item,
    bannerTypeCounts,
    onClose,
}: {
    item: BannerItem | null;
    bannerTypeCounts: Partial<Record<BannerType, number>>;
    onClose: () => void;
}) {
    const isEdit = item !== null;
    const formatDateForInput = (date: string | null) => {
        if (!date) return '';

        return date.slice(0, 10);
    };

    const form = useForm<BannerFormValues>(
        item
            ? {
                  image_url_en: null,
                  image_url_zh: null,
                  image_url_my: null,
                  type: item.type,
                  sort_order: item.sort_order,
                  start_date: formatDateForInput(item.start_date) ?? '',
                  end_date: formatDateForInput(item.end_date) ?? '',
                  is_active: item.is_active,
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
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(`/cms/banners/${item.id}`, options);

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
            bannerTypeCounts={bannerTypeCounts}
            imageUrls={{
                en: item?.image_url_en,
                zh: item?.image_url_zh,
                my: item?.image_url_my,
            }}
            createdAt={item?.created_at}
            updatedAt={item?.updated_at}
        />
    );
}
