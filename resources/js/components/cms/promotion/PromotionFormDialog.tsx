import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { MegaphoneIcon, SquarePenIcon } from 'lucide-react';

import { PromotionForm, type PromotionFormValues } from '@/components/cms/promotion/PromotionForm';
import { FormDialog } from '@/components/FormDialog';
import { cmsModalVisit } from '@/lib/cms-modal';
import { useTranslation } from '@/hooks/useTranslation';

export type PromotionItem = {
    id: number;
    title: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
    image_url: string | null;
};

type PromotionFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: PromotionItem | null;
};

function emptyPromotionForm(): PromotionFormValues {
    return {
        title: '',
        description: '',
        start_date: '',
        end_date: '',
        is_active: true,
        image: null,
    };
}

export function PromotionFormDialog({ open, onOpenChange, item }: PromotionFormDialogProps) {
    const { t } = useTranslation();
    const isEdit = item !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('cms.edit_promotion') : t('cms.create_promotion')}
            description={isEdit ? t('cms.edit_promotion_description') : t('cms.create_promotion_description')}
            icon={isEdit ? SquarePenIcon : MegaphoneIcon}
        >
            {open ? (
                <PromotionFormDialogBody
                    key={item ? `edit-${item.id}` : 'create'}
                    item={item}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function PromotionFormDialogBody({ item, onClose }: { item: PromotionItem | null; onClose: () => void }) {
    const isEdit = item !== null;
    const form = useForm<PromotionFormValues>(
        item
            ? {
                  title: item.title,
                  description: item.description ?? '',
                  start_date: item.start_date ?? '',
                  end_date: item.end_date ?? '',
                  is_active: item.is_active,
                  image: null,
              }
            : emptyPromotionForm(),
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            ...cmsModalVisit,
            forceFormData: true,
            onSuccess: onClose,
        };

        if (isEdit && item) {
            form.transform((data) => ({ ...data, _method: 'put' })).post(`/cms/promotions/${item.id}`, options);

            return;
        }

        form.post('/cms/promotions', options);
    };

    return (
        <PromotionForm
            form={form}
            onSubmit={submit}
            onCancel={onClose}
            mode={isEdit ? 'edit' : 'create'}
            imageUrl={item?.image_url}
        />
    );
}
