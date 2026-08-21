import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { NameSlugForm, type NameSlugFormValues } from '@/components/cms/NameSlugForm';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    item: { id: number; name: string; slug: string; lang: string };
};

export default function CategoryEdit({ item }: Props) {
    const { t } = useTranslation();
    const form = useForm<NameSlugFormValues>({ name: item.name, slug: item.slug, lang: item.lang });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/cms/categories/${item.id}`);
    };

    return (
        <>
            <Head title={t('cms.edit_category')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader eyebrow={t('menu.cms_categories')} title={t('cms.edit_category')} />
                <NameSlugForm form={form} onSubmit={submit} cancelHref="/cms/categories" />
            </div>
        </>
    );
}
