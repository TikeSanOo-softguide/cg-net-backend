import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { ImagesIcon, SquarePenIcon } from 'lucide-react';

import { GalleryForm, type GalleryFormValues } from '@/components/cms/gallery/GalleryForm';
import { FormDialog } from '@/components/FormDialog';
import { cmsModalVisit } from '@/lib/cms-modal';
import { useTranslation } from '@/hooks/useTranslation';

export type GalleryItem = {
    id: number;
    label_en: string | null;
    label_my: string | null;
    label_zh: string | null;
    image_url: string | null;
};

type GalleryFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: GalleryItem | null;
};

export function GalleryFormDialog({ open, onOpenChange, item }: GalleryFormDialogProps) {
    const { t } = useTranslation();
    const isEdit = item !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('cms.edit_gallery') : t('cms.create_gallery')}
            description={isEdit ? t('cms.edit_gallery_description') : t('cms.create_gallery_description')}
            icon={isEdit ? SquarePenIcon : ImagesIcon}
        >
            {open ? (
                <GalleryFormDialogBody
                    key={item ? `edit-${item.id}` : 'create'}
                    item={item}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function GalleryFormDialogBody({ item, onClose }: { item: GalleryItem | null; onClose: () => void }) {
    const isEdit = item !== null;
    const form = useForm<GalleryFormValues>(
        item
            ? { label_en: item.label_en, label_my: item.label_my, label_zh: item.label_zh, image: null }
            : { label_en: '', label_my: '', label_zh: '', image: null },
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
            form.post(`/cms/gallery/${item.id}`, options);
            return;
        }

        form.post('/cms/gallery', options);
    };

    return (
        <GalleryForm
            form={form}
            onSubmit={submit}
            onCancel={onClose}
            mode={isEdit ? 'edit' : 'create'}
            imageUrl={item?.image_url}
        />
    );
}
