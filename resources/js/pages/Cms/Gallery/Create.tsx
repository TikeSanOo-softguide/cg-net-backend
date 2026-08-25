import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { GalleryForm, type GalleryFormValues } from '@/components/cms/GalleryForm';
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
                <PageHeader eyebrow={t('menu.cms_gallery')} title={t('cms.create_gallery')} />
                <GalleryForm form={form} onSubmit={submit} cancelHref="/cms/gallery" />
            </FormPage>
        </>
    );
}
