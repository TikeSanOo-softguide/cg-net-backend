import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { NewsForm, type NewsFormValues, type NewsOption } from '@/components/cms/NewsForm';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    categories: NewsOption[];
    tags: NewsOption[];
    item: {
        id: number;
        category_id: number;
        title: string;
        slug: string;
        content: string;
        status: string;
        lang: string;
        image_url: string | null;
        tag_ids: number[];
    };
};

export default function NewsEdit({ categories, tags, item }: Props) {
    const { t } = useTranslation();
    const form = useForm<NewsFormValues>({
        category_id: String(item.category_id),
        title: item.title,
        slug: item.slug,
        content: item.content,
        status: item.status,
        lang: item.lang,
        image: null,
        tag_ids: item.tag_ids,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/cms/news/${item.id}`, { forceFormData: true });
    };

    return (
        <>
            <Head title={t('cms.edit_news')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader eyebrow={t('menu.cms_news')} title={t('cms.edit_news')} />
                <NewsForm form={form} onSubmit={submit} cancelHref="/cms/news" categories={categories} tags={tags} imageUrl={item.image_url} />
            </div>
        </>
    );
}
