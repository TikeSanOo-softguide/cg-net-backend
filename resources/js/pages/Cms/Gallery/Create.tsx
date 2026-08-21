import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { GalleryForm, type GalleryFormValues } from '@/components/cms/GalleryForm';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

export default function GalleryCreate() {
    const { t } = useTranslation();
    const form = useForm<GalleryFormValues>({ label: '', lang: 'en', image: null });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/cms/gallery', { forceFormData: true });
    };

    return (
        <>
            <Head title={t('cms.create_gallery')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader eyebrow={t('menu.cms_gallery')} title={t('cms.create_gallery')} />
                <GalleryForm form={form} onSubmit={submit} cancelHref="/cms/gallery" />
            </div>
        </>
    );
}
