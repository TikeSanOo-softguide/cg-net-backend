import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { NewsForm, type NewsFormValues, type NewsOption } from '@/components/cms/NewsForm';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    categories: NewsOption[];
};

export default function NewsCreate({ categories }: Props) {
    const { t } = useTranslation();
    const form = useForm<NewsFormValues>({
        category_id: categories[0] ? String(categories[0].id) : '',
        title: '',
        slug: '',
        content: '',
        status: 'draft',
        image: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/cms/news', { forceFormData: true });
    };

    return (
        <>
            <Head title={t('cms.create_news')} />
            <FormPage>
                <PageHeader eyebrow={t('menu.cms_news')} title={t('cms.create_news')} />
                <NewsForm form={form} onSubmit={submit} cancelHref="/cms/news" categories={categories} />
            </FormPage>
        </>
    );
}
