import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { MegaphoneIcon, SquarePenIcon } from 'lucide-react';

import { AnnouncementForm, type AnnouncementFormValues } from '@/components/notification/announcement/AnnouncementForm';
import { FormDialog } from '@/components/FormDialog';
import { cmsModalVisit } from '@/lib/cms-modal';
import { useTranslation } from '@/hooks/useTranslation';

export type AnnouncementItem = {
    id: number;
    content_en: string;
    content_zh: string;
    content_my: string;
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
    created_at: string | null;
    updated_at: string | null;
};

type AnnouncementFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: AnnouncementItem | null;
};

function emptyAnnouncementForm(): AnnouncementFormValues {
    return {
        content_en: '',
        content_zh: '',
        content_my: '',
        start_date: '',
        end_date: '',
        is_active: true,
    };
}

export function AnnouncementFormDialog({ open, onOpenChange, item }: AnnouncementFormDialogProps) {
    const { t } = useTranslation();
    const isEdit = item !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('notification.announcement.edit') : t('notification.announcement.create')}
            description={
                isEdit
                    ? t('notification.announcement.edit_description')
                    : t('notification.announcement.create_description')
            }
            icon={isEdit ? SquarePenIcon : MegaphoneIcon}
            size="3xl"
        >
            {open ? (
                <AnnouncementFormDialogBody
                    key={item ? `edit-${item.id}` : 'create'}
                    item={item}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function AnnouncementFormDialogBody({ item, onClose }: { item: AnnouncementItem | null; onClose: () => void }) {
    const isEdit = item !== null;
    const form = useForm<AnnouncementFormValues>(
        item
            ? {
                  content_en: item.content_en ?? '',
                  content_zh: item.content_zh ?? '',
                  content_my: item.content_my ?? '',
                  start_date: item.start_date ?? '',
                  end_date: item.end_date ?? '',
                  is_active: item.is_active,
              }
            : emptyAnnouncementForm(),
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
            form.post(`/notifications/announcement/${item.id}`, options);

            return;
        }

        form.post('/notifications/announcement', options);
    };

    return <AnnouncementForm form={form} onSubmit={submit} onCancel={onClose} mode={isEdit ? 'edit' : 'create'} />;
}
