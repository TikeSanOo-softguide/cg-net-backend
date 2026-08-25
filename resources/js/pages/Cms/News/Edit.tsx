import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { NewsForm, type NewsFormValues, type NewsOption } from '@/components/cms/NewsForm';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    categories: NewsOption[];
    item: {
        id: number;
        category_id: number;
        title: string;
        slug: string;
        content: string;
        status: string;
        image_url: string | null;
    };
};

export default function NewsEdit({ categories, item }: Props) {
    const { t } = useTranslation();
    const form = useForm<NewsFormValues>({
        category_id: String(item.category_id),
        title: item.title,
        slug: item.slug,
        content: item.content,
        status: item.status,
        image: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/cms/news/${item.id}`, { forceFormData: true });
    };

    return (
        <>
            <Head title={t('cms.edit_news')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader eyebrow={t('menu.cms_news')} title={t('cms.edit_news')} />
                <NewsForm form={form} onSubmit={submit} cancelHref="/cms/news" categories={categories} imageUrl={item.image_url} />
            </div>
        </>
    );
}
