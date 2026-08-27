import {
    CalendarIcon,
    CircleDotIcon,
    HashIcon,
    IdCardIcon,
    LanguagesIcon,
    MailIcon,
    MapPinIcon,
    PhoneIcon,
    SquarePenIcon,
    UserIcon,
    XIcon,
} from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import { StatusBadge } from '@/components/StatusBadge';
import type { CustomerFormMember } from '@/components/customer/CustomerFormDialog';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';

export type CustomerDetailMember = CustomerFormMember & {
    accounts_count?: number;
    created_at?: string | null;
};

type CustomerDetailDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    customer: CustomerDetailMember | null;
    onEdit?: (customer: CustomerDetailMember) => void;
};

export function CustomerDetailDialog({ open, onOpenChange, customer, onEdit }: CustomerDetailDialogProps) {
    const { t } = useTranslation();
    const can = useCan();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={customer?.name ?? t('customers.profile')}
            description={t('menu.customers_list_description')}
            icon={UserIcon}
        >
            {open && customer ? (
                <div className="flex min-h-0 flex-1 flex-col">
                    <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <FormField label={t('customers.name')} htmlFor="view-name" icon={UserIcon} className="sm:col-span-2">
                                <Input id="view-name" value={customer.name} readOnly />
                            </FormField>
                            <FormField label={t('customers.phone')} htmlFor="view-phone" icon={PhoneIcon}>
                                <Input id="view-phone" value={customer.phone} readOnly />
                            </FormField>
                            <FormField label={t('customers.nrc')} htmlFor="view-nrc" icon={IdCardIcon}>
                                <Input id="view-nrc" value={customer.nrc_number} readOnly />
                            </FormField>
                            <FormField label={t('customers.email')} htmlFor="view-email" icon={MailIcon}>
                                <Input id="view-email" value={customer.email ?? '—'} readOnly />
                            </FormField>
                            <FormField label={t('customers.language')} htmlFor="view-language" icon={LanguagesIcon}>
                                <Input id="view-language" value={t(`language.${customer.language_pref}`)} readOnly />
                            </FormField>
                            <FormField label={t('common.status')} htmlFor="view-status" icon={CircleDotIcon}>
                                <div id="view-status" className="flex h-10 items-center">
                                    <StatusBadge status={customer.status} />
                                </div>
                            </FormField>
                            <FormField label={t('customers.accounts')} htmlFor="view-accounts" icon={HashIcon}>
                                <Input id="view-accounts" value={String(customer.accounts_count ?? 0)} readOnly />
                            </FormField>
                            <FormField label={t('customers.joined')} htmlFor="view-joined" icon={CalendarIcon} className="sm:col-span-2">
                                <Input id="view-joined" value={customer.created_at ?? '—'} readOnly />
                            </FormField>
                            <FormField label={t('customers.address')} htmlFor="view-address" icon={MapPinIcon} className="sm:col-span-2">
                                <Textarea id="view-address" value={customer.address ?? '—'} readOnly rows={3} />
                            </FormField>
                        </div>
                    </div>
                    <div className="flex w-full shrink-0 items-center justify-center gap-2 border-t border-border/70 px-4 py-3 sm:px-5 sm:py-4">
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            className="h-8 w-[120px] shrink-0 rounded-[4px]"
                            onClick={() => onOpenChange(false)}
                        >
                            <XIcon className="size-3.5" strokeWidth={1.85} />
                            {t('common.close')}
                        </Button>
                        {can('customers.update') && onEdit ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="primary"
                                className="h-8 w-[120px] shrink-0 rounded-[4px]"
                                onClick={() => onEdit(customer)}
                            >
                                <SquarePenIcon className="size-3.5" strokeWidth={1.85} />
                                {t('common.edit')}
                            </Button>
                        ) : null}
                    </div>
                </div>
            ) : null}
        </FormDialog>
    );
}
