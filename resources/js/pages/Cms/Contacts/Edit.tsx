import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { ContactForm, type ContactFormValues } from '@/components/cms/ContactForm';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    item: { id: number; contact_point: string };
};

export default function ContactEdit({ item }: Props) {
    const { t } = useTranslation();
    const form = useForm<ContactFormValues>({ contact_point: item.contact_point });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/cms/contacts/${item.id}`);
    };

    return (
        <>
            <Head title={t('cms.edit_contact')} />
            <FormPage>
                <PageHeader title={t('cms.edit_contact')} />
                <ContactForm form={form} onSubmit={submit} cancelHref="/cms/contacts" />
            </FormPage>
        </>
    );
}
