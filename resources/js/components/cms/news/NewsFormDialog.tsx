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
    title_en: string;
    title_zh: string;
    title_my: string;
    description_en: string;
    description_zh: string;
    description_my: string;
    slug: string;
    status: string;
    image_url: string | null;
    created_at: string | null;
    updated_at: string | null;
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
        title_en: '',
        title_zh: '',
        title_my: '',
        description_en: '',
        description_zh: '',
        description_my: '',
        slug: '',
        status: 'published',
        image: null,
        image_url: '',
    };
}

export function NewsFormDialog({ open, onOpenChange, item, categories }: NewsFormDialogProps) {
    const { t } = useTranslation();
    const isEdit = item !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('cms.news.edit') : t('cms.news.create')}
            description={isEdit ? t('cms.news.edit_description') : t('cms.news.create_description')}
            icon={isEdit ? SquarePenIcon : NewspaperIcon}
            size="3xl"
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
                  title_en: item.title_en,
                  title_zh: item.title_zh,
                  title_my: item.title_my,
                  description_en: item.description_en,
                  description_zh: item.description_zh,
                  description_my: item.description_my,
                  slug: item.slug,
                  status: item.status,
                  image: null,
                  image_url: item.image_url,
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
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(`/cms/news/${item.id}`, options);
            return;
        }

        form.post('/cms/news', options);
    };

    const onImageClear = () => {
        if (item) {
            item.image_url = null;
        }
    };

    return (
        <NewsForm
            form={form}
            onSubmit={submit}
            onCancel={onClose}
            onImageClear={onImageClear}
            mode={isEdit ? 'edit' : 'create'}
            categories={categories}
            imageUrl={item?.image_url}
        />
    );
}
