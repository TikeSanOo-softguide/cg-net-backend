import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { NewsForm, type NewsFormValues, type NewsOption } from '@/components/cms/NewsForm';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    categories: NewsOption[];
    tags: NewsOption[];
};

export default function NewsCreate({ categories, tags }: Props) {
    const { t } = useTranslation();
    const form = useForm<NewsFormValues>({
        category_id: categories[0] ? String(categories[0].id) : '',
        title: '',
        slug: '',
        content: '',
        status: 'draft',
        lang: 'en',
        image: null,
        tag_ids: [],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/cms/news', { forceFormData: true });
    };

    return (
        <>
            <Head title={t('cms.create_news')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader eyebrow={t('menu.cms_news')} title={t('cms.create_news')} />
                <NewsForm form={form} onSubmit={submit} cancelHref="/cms/news" categories={categories} tags={tags} />
            </div>
        </>
    );
}
