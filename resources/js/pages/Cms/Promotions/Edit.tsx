import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { PromotionForm, type PromotionFormValues } from '@/components/cms/PromotionForm';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    item: {
        id: number;
        title_en: string;
        title_zh: string;
        title_my: string;
        description_en: string | null;
        description_zh: string | null;
        description_my: string | null;
        start_date: string | null;
        end_date: string | null;
        is_active: boolean;
        image_url: string | null;
    };
};

export default function PromotionEdit({ item }: Props) {
    const { t } = useTranslation();
    const form = useForm<PromotionFormValues>({
        title_en: item.title_en,
        title_zh: item.title_zh,
        title_my: item.title_my,
        description_en: item.description_en ?? '',
        description_zh: item.description_zh ?? '',
        description_my: item.description_my ?? '',
        start_date: item.start_date ?? '',
        end_date: item.end_date ?? '',
        is_active: item.is_active,
        image: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: 'put' }));
        form.post(`/cms/promotions/${item.id}`, { forceFormData: true });
    };

    return (
        <>
            <Head title={t('cms.edit_promotion')} />
            <FormPage>
                <PageHeader eyebrow={t('menu.cms_promotions')} title={t('cms.edit_promotion')} />
                <PromotionForm form={form} onSubmit={submit} cancelHref="/cms/promotions" imageUrl={item.image_url} />
            </FormPage>
        </>
    );
}
