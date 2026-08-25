import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { NameSlugForm, type NameSlugFormValues } from '@/components/cms/shared/NameSlugForm';
import { FormPage } from '@/components/FormPage';
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
            <FormPage>
                <PageHeader title={t('cms.create_category')} description={t('cms.create_category_description')} />
                <NameSlugForm form={form} onSubmit={submit} cancelHref="/cms/categories" mode="create" />
            </FormPage>
        </>
    );
}
