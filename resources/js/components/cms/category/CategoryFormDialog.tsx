import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { FolderTreeIcon, SquarePenIcon } from 'lucide-react';

import { CategoryForm, type CategoryFormValues } from '@/components/cms/category/CategoryForm';
import { FormDialog } from '@/components/FormDialog';
import { cmsModalVisit } from '@/lib/cms-modal';
import { useTranslation } from '@/hooks/useTranslation';

export type CategoryItem = {
    id: number;
    name: string;
    slug: string;
};

type CategoryFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: CategoryItem | null;
};

function emptyCategoryForm(): CategoryFormValues {
    return {
        name: '',
        slug: '',
    };
}

export function CategoryFormDialog({ open, onOpenChange, item }: CategoryFormDialogProps) {
    const { t } = useTranslation();
    const isEdit = item !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('cms.edit_category') : t('cms.create_category')}
            description={isEdit ? t('cms.edit_category_description') : t('cms.create_category_description')}
            icon={isEdit ? SquarePenIcon : FolderTreeIcon}
        >
            {open ? (
                <CategoryFormDialogBody
                    key={item ? `edit-${item.id}` : 'create'}
                    item={item}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function CategoryFormDialogBody({ item, onClose }: { item: CategoryItem | null; onClose: () => void }) {
    const isEdit = item !== null;
    const form = useForm<CategoryFormValues>(
        item
            ? {
                  name: item.name,
                  slug: item.slug,
              }
            : emptyCategoryForm(),
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            ...cmsModalVisit,
            onSuccess: onClose,
        };

        if (isEdit && item) {
            form.put(`/cms/categories/${item.id}`, options);

            return;
        }

        form.post('/cms/categories', options);
    };

    return (
        <CategoryForm
            form={form}
            onSubmit={submit}
            onCancel={onClose}
            mode={isEdit ? 'edit' : 'create'}
        />
    );
}
