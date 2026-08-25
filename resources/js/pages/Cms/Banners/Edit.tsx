import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { BannerForm, type BannerFormValues } from '@/components/cms/BannerForm';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    item: {
        id: number;
        title: string;
        link_url: string | null;
        sort_order: number;
        start_date: string | null;
        end_date: string | null;
        is_active: boolean;
        image_url: string | null;
    };
};

export default function BannerEdit({ item }: Props) {
    const { t } = useTranslation();
    const form = useForm<BannerFormValues>({
        title: item.title,
        link_url: item.link_url ?? '',
        sort_order: item.sort_order,
        start_date: item.start_date ?? '',
        end_date: item.end_date ?? '',
        is_active: item.is_active,
        image: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/cms/banners/${item.id}`, { forceFormData: true });
    };

    return (
        <>
            <Head title={t('cms.edit_banner')} />
            <FormPage>
                <PageHeader title={t('cms.edit_banner')} />
                <BannerForm form={form} onSubmit={submit} cancelHref="/cms/banners" imageUrl={item.image_url} />
            </FormPage>
        </>
    );
}
