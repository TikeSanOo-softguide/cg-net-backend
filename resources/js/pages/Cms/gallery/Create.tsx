import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { GalleryForm, type GalleryFormValues } from '@/components/cms/gallery/GalleryForm';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

export default function GalleryCreate() {
    const { t } = useTranslation();
    const form = useForm<GalleryFormValues>({ label: '', image: null });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/cms/gallery', { forceFormData: true });
    };

    return (
        <>
            <Head title={t('cms.create_gallery')} />
            <FormPage>
                <PageHeader title={t('cms.create_gallery')} description={t('cms.create_gallery_description')} />
                <GalleryForm form={form} onSubmit={submit} cancelHref="/cms/gallery" mode="create" />
            </FormPage>
        </>
    );
}
