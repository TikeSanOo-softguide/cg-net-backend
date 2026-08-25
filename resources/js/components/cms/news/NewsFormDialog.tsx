import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { NewspaperIcon, SquarePenIcon } from 'lucide-react';

import { NewsForm, type NewsFormValues, type NewsOption } from '@/components/cms/news/NewsForm';
import { FormDialog } from '@/components/FormDialog';
import { cmsModalVisit } from '@/lib/cms-modal';
import { useTranslation } from '@/hooks/useTranslation';

export type NewsItem = {
    id: number;
    category_id: number | null;
    title: string;
    slug: string;
    content: string;
    status: string;
    image_url: string | null;
};

type NewsFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: NewsItem | null;
    categories: NewsOption[];
};

function emptyNewsForm(): NewsFormValues {
    return {
        category_id: '',
        title: '',
        slug: '',
        content: '',
        status: 'draft',
        image: null,
    };
}

export function NewsFormDialog({ open, onOpenChange, item, categories }: NewsFormDialogProps) {
    const { t } = useTranslation();
    const isEdit = item !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('cms.edit_news') : t('cms.create_news')}
            description={isEdit ? t('cms.edit_news_description') : t('cms.create_news_description')}
            icon={isEdit ? SquarePenIcon : NewspaperIcon}
        >
            {open ? (
                <NewsFormDialogBody
                    key={item ? `edit-${item.id}` : 'create'}
                    item={item}
                    categories={categories}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function NewsFormDialogBody({
    item,
    categories,
    onClose,
}: {
    item: NewsItem | null;
    categories: NewsOption[];
    onClose: () => void;
}) {
    const isEdit = item !== null;
    const form = useForm<NewsFormValues>(
        item
            ? {
                  category_id: item.category_id ? String(item.category_id) : '',
                  title: item.title,
                  slug: item.slug,
                  content: item.content,
                  status: item.status,
                  image: null,
              }
            : emptyNewsForm(),
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            ...cmsModalVisit,
            forceFormData: true,
            onSuccess: onClose,
        };

        if (isEdit && item) {
            form.transform((data) => ({ ...data, _method: 'put' })).post(`/cms/news/${item.id}`, options);

            return;
        }

        form.post('/cms/news', options);
    };

    return (
        <NewsForm
            form={form}
            onSubmit={submit}
            onCancel={onClose}
            mode={isEdit ? 'edit' : 'create'}
            categories={categories}
            imageUrl={item?.image_url}
        />
    );
}
