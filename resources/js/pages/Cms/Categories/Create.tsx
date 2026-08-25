import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { NameSlugForm, type NameSlugFormValues } from '@/components/cms/NameSlugForm';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

export default function CategoryCreate() {
    const { t } = useTranslation();
    const form = useForm<NameSlugFormValues>({ name: '', slug: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/cms/categories');
    };

    return (
        <>
            <Head title={t('cms.create_category')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader eyebrow={t('menu.cms_categories')} title={t('cms.create_category')} />
                <NameSlugForm form={form} onSubmit={submit} cancelHref="/cms/categories" />
            </div>
        </>
    );
}
