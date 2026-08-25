import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { ContactForm, type ContactFormValues } from '@/components/cms/ContactForm';
import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

export default function ContactCreate() {
    const { t } = useTranslation();
    const form = useForm<ContactFormValues>({ contact_point: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/cms/contacts');
    };

    return (
        <>
            <Head title={t('cms.create_contact')} />
            <FormPage>
                <PageHeader eyebrow={t('menu.cms_contacts')} title={t('cms.create_contact')} />
                <ContactForm form={form} onSubmit={submit} cancelHref="/cms/contacts" />
            </FormPage>
        </>
    );
}
