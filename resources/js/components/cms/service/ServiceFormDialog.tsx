import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { FileStackIcon, SquarePenIcon } from 'lucide-react';

import { ServiceForm, type ServiceFormValues } from '@/components/cms/service/ServiceForm';
import { FormDialog } from '@/components/FormDialog';
import { cmsModalVisit } from '@/lib/cms-modal';
import { useTranslation } from '@/hooks/useTranslation';

export type ServiceItem = {
    id: number;
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

type ServiceFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: ServiceItem | null;
};

function emptyServiceForm(): ServiceFormValues {
    return {
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

export function ServiceFormDialog({ open, onOpenChange, item }: ServiceFormDialogProps) {
    const { t } = useTranslation();
    const isEdit = item !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('cms.service.edit') : t('cms.service.create')}
            description={isEdit ? t('cms.service.edit_description') : t('cms.service.create_description')}
            icon={isEdit ? SquarePenIcon : FileStackIcon}
            size="3xl"
        >
            {open ? (
                <ServiceFormDialogBody
                    key={item ? `edit-${item.id}` : 'create'}
                    item={item}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function ServiceFormDialogBody({ item, onClose }: { item: ServiceItem | null; onClose: () => void }) {
    const isEdit = item !== null;
    const form = useForm<ServiceFormValues>(
        item
            ? {
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
            : emptyServiceForm(),
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
            form.post(`/cms/services/${item.id}`, options);
            return;
        }

        form.post('/cms/services', options);
    };

    const onImageClear = () => {
        if (item) {
            item.image_url = null;
        }
    };

    return (
        <ServiceForm
            form={form}
            onSubmit={submit}
            onCancel={onClose}
            onImageClear={onImageClear}
            mode={isEdit ? 'edit' : 'create'}
            imageUrl={item?.image_url}
        />
    );
}
