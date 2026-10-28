import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { NameSlugForm, type NameSlugFormValues } from '@/components/cms/NameSlugForm';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    item: { id: number; name_en: string; name_zh: string; name_my: string; slug: string };
};

export default function CategoryEdit({ item }: Props) {
    const { t } = useTranslation();
    const form = useForm<NameSlugFormValues>({
        name_en: item.name_en,
        name_zh: item.name_zh,
        name_my: item.name_my,
        slug: item.slug,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/cms/categories/${item.id}`);
    };

    return (
        <>
            <Head title={t('cms.edit_category')} />
            <FormPage>
                <PageHeader eyebrow={t('menu.cms_categories')} title={t('cms.edit_category')} />
                <NameSlugForm form={form} onSubmit={submit} cancelHref="/cms/categories" />
            </FormPage>
        </>
    );
}
