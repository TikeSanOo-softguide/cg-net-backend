import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { NameSlugForm, type NameSlugFormValues } from '@/components/cms/NameSlugForm';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    item: { id: number; name: string; slug: string; lang: string };
};

export default function TagEdit({ item }: Props) {
    const { t } = useTranslation();
    const form = useForm<NameSlugFormValues>({ name: item.name, slug: item.slug, lang: item.lang });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/cms/tags/${item.id}`);
    };

    return (
        <>
            <Head title={t('cms.edit_tag')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader eyebrow={t('menu.cms_tags')} title={t('cms.edit_tag')} />
                <NameSlugForm form={form} onSubmit={submit} cancelHref="/cms/tags" />
            </div>
        </>
    );
}
