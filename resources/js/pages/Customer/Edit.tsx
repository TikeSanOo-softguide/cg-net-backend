import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';
import { CustomerForm, type CustomerFormValues } from '@/components/customer/CustomerForm';

type CustomerEditProps = {
    customer: {
        id: number;
        name: string;
        phone: string;
        nrc_number: string;
        email: string | null;
        address: string | null;
        language_pref: string;
        status: string;
    };
};

export default function CustomerEdit({ customer }: CustomerEditProps) {
    const { t } = useTranslation();
    const form = useForm<CustomerFormValues>({
        name: customer.name,
        phone: customer.phone,
        nrc_number: customer.nrc_number,
        email: customer.email ?? '',
        address: customer.address ?? '',
        language_pref: customer.language_pref,
        status: customer.status,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/customers/${customer.id}`);
    };

    return (
        <>
            <Head title={t('customers.edit')} />
            <FormPage>
                <PageHeader title={t('customers.edit')} description={t('customers.edit_description')} />
                <CustomerForm
                    form={form}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    cancelHref={`/customers/${customer.id}`}
                />
            </FormPage>
        </>
    );
}
