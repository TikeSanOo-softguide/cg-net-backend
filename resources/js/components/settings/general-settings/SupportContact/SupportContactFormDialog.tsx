import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { PhoneIcon, PlusIcon, SquarePenIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormDialog } from '@/components/FormDialog';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

export type SupportContactItem = {
    id: number;
    phone: string;
};

type FormValues = {
    phone: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: SupportContactItem | null;
};

function getInitialValues(item: SupportContactItem | null): FormValues {
    return {
        phone: item?.phone ?? '',
    };
}

export function SupportContactFormDialog({ open, onOpenChange, item }: Props) {
    const { t } = useTranslation();

    const isEdit = item !== null;

    const close = () => {
        onOpenChange(false);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={`${isEdit ? t('common.edit') : t('common.create')} ${t(
                'settings.general_settings.support_contact',
            )}`}
            description={t('settings.general_settings.contact_form_description')}
            icon={isEdit ? SquarePenIcon : PhoneIcon}
            size="md"
        >
            {open ? <SupportContactFormBody key={item?.id ?? 'create'} item={item} onClose={close} /> : null}
        </FormDialog>
    );
}

function SupportContactFormBody({ item, onClose }: { item: SupportContactItem | null; onClose: () => void }) {
    const { t } = useTranslation();

    const isEdit = item !== null;

    const form = useForm<FormValues>(getInitialValues(item));

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const route = '/settings/general/support-contacts';

        const options = {
            headers: {
                'X-Modal': '1',
            },
            preserveScroll: true,
            onSuccess: onClose,
        };

        if (isEdit && item) {
            form.put(`${route}/${item.id}`, options);
            return;
        }

        form.post(route, options);
    };

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                <FormField
                    label={t('settings.general_settings.phone')}
                    htmlFor="support-contact-phone"
                    error={form.errors.phone}
                    required
                >
                    <Input
                        id="support-contact-phone"
                        type="tel"
                        maxLength={20}
                        value={form.data.phone}
                        onChange={(event) => form.setData('phone', event.target.value)}
                        aria-invalid={Boolean(form.errors.phone)}
                        required
                    />
                </FormField>
            </div>

            <FormActionBar mode={isEdit ? 'edit' : 'create'} onCancel={onClose} processing={form.processing} />
        </form>
    );
}
