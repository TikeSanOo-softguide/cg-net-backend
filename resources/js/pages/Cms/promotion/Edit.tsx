import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { PromotionForm, type PromotionFormValues } from '@/components/cms/promotion/PromotionForm';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    item: {
        id: number;
        title: string;
        description: string | null;
        start_date: string | null;
        end_date: string | null;
        is_active: boolean;
        image_url: string | null;
    };
};

export default function PromotionEdit({ item }: Props) {
    const { t } = useTranslation();
    const form = useForm<PromotionFormValues>({
        title: item.title,
        description: item.description ?? '',
        start_date: item.start_date ?? '',
        end_date: item.end_date ?? '',
        is_active: item.is_active,
        image: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/cms/promotions/${item.id}`, { forceFormData: true });
    };

    return (
        <>
            <Head title={t('cms.edit_promotion')} />
            <FormPage>
                <PageHeader title={t('cms.edit_promotion')} description={t('cms.edit_promotion_description')} />
                <PromotionForm form={form} onSubmit={submit} cancelHref="/cms/promotions" mode="edit" imageUrl={item.image_url} />
            </FormPage>
        </>
    );
}
