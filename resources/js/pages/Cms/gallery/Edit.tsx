import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { GalleryForm, type GalleryFormValues } from '@/components/cms/gallery/GalleryForm';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    item: { id: number; label: string | null; image_url: string | null };
};

export default function GalleryEdit({ item }: Props) {
    const { t } = useTranslation();
    const form = useForm<GalleryFormValues>({ label: item.label ?? '', image: null });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/cms/gallery/${item.id}`, { forceFormData: true });
    };

    return (
        <>
            <Head title={t('cms.edit_gallery')} />
            <FormPage>
                <PageHeader title={t('cms.edit_gallery')} description={t('cms.edit_gallery_description')} />
                <GalleryForm form={form} onSubmit={submit} cancelHref="/cms/gallery" mode="edit" imageUrl={item.image_url} />
            </FormPage>
        </>
    );
}
