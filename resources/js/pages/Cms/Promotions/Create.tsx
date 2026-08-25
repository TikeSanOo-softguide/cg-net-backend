import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { PromotionForm, type PromotionFormValues } from '@/components/cms/PromotionForm';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

export default function PromotionCreate() {
    const { t } = useTranslation();
    const form = useForm<PromotionFormValues>({
        title: '',
        description: '',
        start_date: '',
        end_date: '',
        is_active: true,
        image: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/cms/promotions', { forceFormData: true });
    };

    return (
        <>
            <Head title={t('cms.create_promotion')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader eyebrow={t('menu.cms_promotions')} title={t('cms.create_promotion')} />
                <PromotionForm form={form} onSubmit={submit} cancelHref="/cms/promotions" />
            </div>
        </>
    );
}
