import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { UserCogIcon, UserPlusIcon } from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import { CustomerForm, emptyCustomerForm, type CustomerFormValues } from '@/components/customer/CustomerForm';
import { useTranslation } from '@/hooks/useTranslation';

export type CustomerFormMember = {
    id: number;
    name: string;
    phone: string;
    status: string;
};

type CustomerFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    customer: CustomerFormMember | null;
};

const modalVisit = {
    headers: { 'X-Modal': '1' },
    preserveScroll: true,
};

export function CustomerFormDialog({ open, onOpenChange, customer }: CustomerFormDialogProps) {
    const { t } = useTranslation();
    const isEdit = customer !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('customers.edit') : t('customers.create')}
            description={isEdit ? t('customers.edit_description') : t('customers.create_description')}
            icon={isEdit ? UserCogIcon : UserPlusIcon}
        >
            {open ? (
                <CustomerFormDialogBody
                    key={customer ? `edit-${customer.id}` : 'create'}
                    customer={customer}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function CustomerFormDialogBody({
    customer,
    onClose,
}: {
    customer: CustomerFormMember | null;
    onClose: () => void;
}) {
    const isEdit = customer !== null;
    const form = useForm<CustomerFormValues>(
        customer
            ? {
                  name: customer.name,
                  phone: customer.phone,
                  password: '',
                  password_confirmation: '',
                  status: customer.status,
              }
            : emptyCustomerForm(),
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            ...modalVisit,
            onSuccess: onClose,
        };

        if (isEdit && customer) {
            form.put(`/customers/${customer.id}`, options);

            return;
        }

        form.post('/customers', options);
    };

    return (
        <CustomerForm
            form={form}
            onSubmit={submit}
            onCancel={onClose}
            mode={isEdit ? 'edit' : 'create'}
        />
    );
}
