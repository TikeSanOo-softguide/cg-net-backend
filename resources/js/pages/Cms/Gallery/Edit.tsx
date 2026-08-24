import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { GalleryForm, type GalleryFormValues } from '@/components/cms/GalleryForm';
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
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader eyebrow={t('menu.cms_gallery')} title={t('cms.edit_gallery')} />
                <GalleryForm form={form} onSubmit={submit} cancelHref="/cms/gallery" imageUrl={item.image_url} />
            </div>
        </>
    );
}
