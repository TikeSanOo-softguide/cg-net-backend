import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';
import { CustomerForm, type CustomerFormValues } from '@/components/customer/CustomerForm';

export default function CustomerCreate() {
    const { t } = useTranslation();
    const form = useForm<CustomerFormValues>({
        name: '',
        phone: '',
        nrc_number: '',
        email: '',
        address: '',
        language_pref: 'my',
        status: 'active',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/customers');
    };

    return (
        <>
            <Head title={t('customers.create')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader
                    eyebrow={t('menu.customers_list')}
                    title={t('customers.create')}
                    description={t('customers.create_description')}
                />
                <CustomerForm
                    form={form}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    cancelHref="/customers"
                />
            </div>
        </>
    );
}
