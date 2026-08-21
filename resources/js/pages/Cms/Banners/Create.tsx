import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { BannerForm, type BannerFormValues } from '@/components/cms/BannerForm';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

export default function BannerCreate() {
    const { t } = useTranslation();
    const form = useForm<BannerFormValues>({
        title: '',
        link_url: '',
        sort_order: 0,
        start_date: '',
        end_date: '',
        is_active: true,
        lang: 'en',
        image: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/cms/banners', { forceFormData: true });
    };

    return (
        <>
            <Head title={t('cms.create_banner')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader eyebrow={t('menu.cms_banners')} title={t('cms.create_banner')} />
                <BannerForm form={form} onSubmit={submit} cancelHref="/cms/banners" />
            </div>
        </>
    );
}
