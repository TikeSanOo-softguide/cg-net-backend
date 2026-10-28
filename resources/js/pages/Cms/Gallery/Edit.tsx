import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { GalleryForm, type GalleryFormValues } from '@/components/cms/GalleryForm';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    item: { id: number; label_en: string | null; label_zh: string | null; label_my: string | null; image_url: string | null };
};

export default function GalleryEdit({ item }: Props) {
    const { t } = useTranslation();
    const form = useForm<GalleryFormValues>({
        label_en: item.label_en ?? '',
        label_zh: item.label_zh ?? '',
        label_my: item.label_my ?? '',
        image: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: 'put' }));
        form.post(`/cms/gallery/${item.id}`, { forceFormData: true });
    };

    return (
        <>
            <Head title={t('cms.edit_gallery')} />
            <FormPage>
                <PageHeader eyebrow={t('menu.cms_gallery')} title={t('cms.edit_gallery')} />
                <GalleryForm form={form} onSubmit={submit} cancelHref="/cms/gallery" imageUrl={item.image_url} />
            </FormPage>
        </>
    );
}
