import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { NameSlugForm, type NameSlugFormValues } from '@/components/cms/NameSlugForm';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

export default function TagCreate() {
    const { t } = useTranslation();
    const form = useForm<NameSlugFormValues>({ name: '', slug: '', lang: 'en' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/cms/tags');
    };

    return (
        <>
            <Head title={t('cms.create_tag')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader eyebrow={t('menu.cms_tags')} title={t('cms.create_tag')} />
                <NameSlugForm form={form} onSubmit={submit} cancelHref="/cms/tags" />
            </div>
        </>
    );
}
