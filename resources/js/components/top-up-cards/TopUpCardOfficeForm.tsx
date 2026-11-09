import { FormEvent, useState } from 'react';
import { useForm } from '@inertiajs/react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import {
    validateOffice,
    validateOfficeCdUnique,
    validateOfficeField,
    type OfficeFormValues,
} from '@/lib/office-validation';

export type OfficeRow = Omit<
    OfficeFormValues,
    'phone_num2' | 'phone_num3' | 'telegram_link' | 'wechat_link' | 'viber_link'
> & {
    id: number;
    phone_num2: string | null;
    phone_num3: string | null;
    telegram_link: string | null;
    wechat_link: string | null;
    viber_link: string | null;
    top_up_cards_count: number;
};
type TopUpCardOfficeFormProps = {
    item: OfficeRow | null;
    officeCds: string[];
    onClose: () => void;
};

type OfficeField = keyof OfficeFormValues;
type TouchedFields = Record<OfficeField, boolean>;

const officeDetailsFields: OfficeField[] = [
    'name_en',
    'name_mm',
    'name_zh',
    'cd',
    'address_en',
    'address_mm',
    'address_zh',
];

const officeContactFields: OfficeField[] = [
    'phone_num1',
    'phone_num2',
    'phone_num3',
    'telegram_link',
    'wechat_link',
    'viber_link',
];

const emptyValues: OfficeFormValues = {
    name_en: '',
    name_mm: '',
    name_zh: '',
    cd: '',
    address_en: '',
    address_mm: '',
    address_zh: '',
    phone_num1: '',
    phone_num2: '',
    phone_num3: '',
    telegram_link: '',
    wechat_link: '',
    viber_link: '',
};

const untouched: TouchedFields = {
    name_en: false,
    name_mm: false,
    name_zh: false,
    cd: false,
    address_en: false,
    address_mm: false,
    address_zh: false,
    phone_num1: false,
    phone_num2: false,
    phone_num3: false,
    telegram_link: false,
    wechat_link: false,
    viber_link: false,
};

function initialValues(item: OfficeRow | null): OfficeFormValues {
    if (!item) {
        return { ...emptyValues };
    }

    return {
        name_en: item.name_en,
        name_mm: item.name_mm,
        name_zh: item.name_zh,
        cd: item.cd,
        address_en: item.address_en,
        address_mm: item.address_mm,
        address_zh: item.address_zh,
        phone_num1: item.phone_num1,
        phone_num2: item.phone_num2 ?? '',
        phone_num3: item.phone_num3 ?? '',
        telegram_link: item.telegram_link ?? '',
        wechat_link: item.wechat_link ?? '',
        viber_link: item.viber_link ?? '',
    };
}

export function TopUpCardOfficeForm({ item, officeCds, onClose }: TopUpCardOfficeFormProps) {
    const { t } = useTranslation();
    const form = useForm<OfficeFormValues>(initialValues(item));
    const [touched, setTouched] = useState<TouchedFields>(untouched);
    const [submitted, setSubmitted] = useState(false);

    const markTouched = (field: OfficeField) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const fieldError = (field: OfficeField): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }
        if (field === 'cd') {
            return (
                validateOfficeCdUnique(form.data.cd, officeCds, item?.cd, t) ??
                form.errors[field] ??
                validateOfficeField(field, form.data, t)
            );
        }

        return form.errors[field] || validateOfficeField(field, form.data, t);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSubmitted(true);
        setTouched({
            name_en: true,
            name_mm: true,
            name_zh: true,
            cd: true,
            address_en: true,
            address_mm: true,
            address_zh: true,
            phone_num1: true,
            phone_num2: true,
            phone_num3: true,
            telegram_link: true,
            wechat_link: true,
            viber_link: true,
        });

        const errors = validateOffice(form.data, t);
        const cdError = validateOfficeCdUnique(form.data.cd, officeCds, item?.cd, t);
        if (cdError) {
            errors.cd = cdError;
        }
        if (Object.keys(errors).length > 0) {
            form.setError(errors);
            return;
        }
        const options = { preserveScroll: true, onSuccess: onClose };
        item ? form.put(`/top-up-cards/offices/${item.id}`, options) : form.post('/top-up-cards/offices', options);
    };

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <div className="grid grid-cols-1 gap-6 overflow-y-auto px-5 py-5 md:grid-cols-2">
                {[officeDetailsFields, officeContactFields].map((fields, column) => (
                    <div key={column} className="grid content-start gap-4">
                        {fields.map((field) => {
                            const isCode = field === 'cd';
                            const required =
                                field.startsWith('name_') || field.startsWith('address_') || field === 'phone_num1';
                            const error = fieldError(field);

                            return (
                                <FormField
                                    key={field}
                                    label={t(`top_up_cards.office.${field}`)}
                                    htmlFor={`office-${field}`}
                                    required={required || isCode}
                                    error={error}
                                >
                                    <Input
                                        id={`office-${field}`}
                                        type={isCode ? 'text' : field.startsWith('phone_num') ? 'tel' : 'text'}
                                        inputMode={isCode ? 'numeric' : undefined}
                                        maxLength={
                                            isCode
                                                ? 2
                                                : field.startsWith('phone_num')
                                                  ? 16
                                                  : field.endsWith('_link')
                                                    ? 255
                                                    : undefined
                                        }
                                        value={form.data[field]}
                                        aria-invalid={Boolean(error)}
                                        onBlur={() => markTouched(field)}
                                        onChange={(event) => {
                                            markTouched(field);
                                            form.setData(field, event.target.value);
                                            form.clearErrors(field);
                                        }}
                                    />
                                </FormField>
                            );
                        })}
                    </div>
                ))}
            </div>
            <FormActionBar onCancel={onClose} processing={form.processing} mode={item ? 'edit' : 'create'} />
        </form>
    );
}
