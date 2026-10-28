import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { BannerForm, type BannerFormValues } from '@/components/cms/BannerForm';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    item: {
        id: number;
        sort_order: number;
        start_date: string | null;
        end_date: string | null;
        is_active: boolean;
        image_url_en: string | null;
        image_url_zh: string | null;
        image_url_my: string | null;
    };
};

export default function BannerEdit({ item }: Props) {
    const { t } = useTranslation();
    const form = useForm<BannerFormValues>({
        sort_order: item.sort_order,
        start_date: item.start_date ?? '',
        end_date: item.end_date ?? '',
        is_active: item.is_active,
        image_en: null,
        image_zh: null,
        image_my: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: 'put' }));
        form.post(`/cms/banners/${item.id}`, { forceFormData: true });
    };

    return (
        <>
            <Head title={t('cms.edit_banner')} />
            <FormPage>
                <PageHeader eyebrow={t('menu.cms_banners')} title={t('cms.edit_banner')} />
                <BannerForm
                    form={form}
                    onSubmit={submit}
                    cancelHref="/cms/banners"
                    imageUrlEn={item.image_url_en}
                    imageUrlZh={item.image_url_zh}
                    imageUrlMy={item.image_url_my}
                />
            </FormPage>
        </>
    );
}
