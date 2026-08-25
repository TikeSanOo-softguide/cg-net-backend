import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { ContactIcon, SquarePenIcon } from 'lucide-react';

import { ContactForm, type ContactFormValues } from '@/components/cms/contact/ContactForm';
import { FormDialog } from '@/components/FormDialog';
import { cmsModalVisit } from '@/lib/cms-modal';
import { useTranslation } from '@/hooks/useTranslation';

export type ContactItem = {
    id: number;
    contact_point: string;
};

type ContactFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: ContactItem | null;
};

export function ContactFormDialog({ open, onOpenChange, item }: ContactFormDialogProps) {
    const { t } = useTranslation();
    const isEdit = item !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('cms.edit_contact') : t('cms.create_contact')}
            description={isEdit ? t('cms.edit_contact_description') : t('cms.create_contact_description')}
            icon={isEdit ? SquarePenIcon : ContactIcon}
        >
            {open ? (
                <ContactFormDialogBody
                    key={item ? `edit-${item.id}` : 'create'}
                    item={item}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function ContactFormDialogBody({ item, onClose }: { item: ContactItem | null; onClose: () => void }) {
    const isEdit = item !== null;
    const form = useForm<ContactFormValues>(
        item
            ? { contact_point: item.contact_point }
            : { contact_point: '' },
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            ...cmsModalVisit,
            onSuccess: onClose,
        };

        if (isEdit && item) {
            form.put(`/cms/contacts/${item.id}`, options);

            return;
        }

        form.post('/cms/contacts', options);
    };

    return <ContactForm form={form} onSubmit={submit} onCancel={onClose} variant="modal" mode={isEdit ? 'edit' : 'create'} />;
}
