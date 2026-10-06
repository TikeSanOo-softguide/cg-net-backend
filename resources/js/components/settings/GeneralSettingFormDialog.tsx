import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { PhoneIcon, PlusIcon, SquarePenIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormDialog } from '@/components/FormDialog';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import type { SupportedLocale } from '@/types';

export type LocalizedSettingItem = {
    id: number;
    title_en: string;
    title_zh: string;
    title_my: string;
    description_en: string;
    description_zh: string;
    description_my: string;
};

export type SupportContactItem = {
    id: number;
    phone: string;
};

export type GeneralSettingKind = 'termsAndConditions' | 'faqs' | 'supportContacts';

type GeneralSettingItem = LocalizedSettingItem | SupportContactItem;

type FormValues = {
    title_en: string;
    title_zh: string;
    title_my: string;
    description_en: string;
    description_zh: string;
    description_my: string;
    phone: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    kind: GeneralSettingKind;
    item: GeneralSettingItem | null;
};

function getInitialValues(item: GeneralSettingItem | null): FormValues {
    if (item && 'phone' in item) {
        return {
            title_en: '',
            title_zh: '',
            title_my: '',
            description_en: '',
            description_zh: '',
            description_my: '',
            phone: item.phone,
        };
    }

    return {
        title_en: item?.title_en ?? '',
        title_zh: item?.title_zh ?? '',
        title_my: item?.title_my ?? '',
        description_en: item?.description_en ?? '',
        description_zh: item?.description_zh ?? '',
        description_my: item?.description_my ?? '',
        phone: '',
    };
}

function getRoute(kind: GeneralSettingKind): string {
    switch (kind) {
        case 'termsAndConditions':
            return '/settings/general/terms-and-conditions';
        case 'faqs':
            return '/settings/general/faqs';
        case 'supportContacts':
            return '/settings/general/support-contacts';
    }
}

export function GeneralSettingFormDialog({ open, onOpenChange, kind, item }: Props) {
    const { t } = useTranslation();
    const isEdit = item !== null;
    const isContact = kind === 'supportContacts';
    const sectionLabel = isContact
        ? t('settings.general_settings.support_contact')
        : kind === 'faqs'
          ? t('settings.general_settings.faq')
          : t('settings.general_settings.terms_and_conditions');
    const close = () => onOpenChange(false);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={`${isEdit ? t('common.edit') : t('common.create')} ${sectionLabel}`}
            description={t(
                isContact
                    ? 'settings.general_settings.contact_form_description'
                    : 'settings.general_settings.content_form_description',
            )}
            icon={isEdit ? SquarePenIcon : isContact ? PhoneIcon : PlusIcon}
            size="xl"
        >
            {open ? (
                <GeneralSettingFormBody
                    key={`${kind}-${item?.id ?? 'create'}`}
                    kind={kind}
                    item={item}
                    onClose={close}
                />
            ) : null}
        </FormDialog>
    );
}

function GeneralSettingFormBody({
    kind,
    item,
    onClose,
}: {
    kind: GeneralSettingKind;
    item: GeneralSettingItem | null;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const isEdit = item !== null;
    const isContact = kind === 'supportContacts';
    const form = useForm<FormValues>(getInitialValues(item));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const route = getRoute(kind);
        const options = {
            headers: { 'X-Modal': '1' },
            preserveScroll: true,
            onSuccess: onClose,
        };

        if (isEdit && item) {
            form.put(`${route}/${item.id}`, options);
            return;
        }

        form.post(route, options);
    };

    const localeNames: Record<SupportedLocale, string> = {
        en: t('settings.general_settings.english'),
        zh: t('settings.general_settings.chinese'),
        my: t('settings.general_settings.burmese'),
    };

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                {isContact ? (
                    <FormField
                        label={t('settings.general_settings.phone')}
                        htmlFor="support-phone"
                        error={form.errors.phone}
                        required
                    >
                        <Input
                            id="support-phone"
                            type="tel"
                            maxLength={20}
                            value={form.data.phone}
                            onChange={(event) => form.setData('phone', event.target.value)}
                            aria-invalid={Boolean(form.errors.phone)}
                            required
                        />
                    </FormField>
                ) : (
                    (['en', 'zh', 'my'] as const).map((locale) => {
                        const titleKey = `title_${locale}` as const;
                        const descriptionKey = `description_${locale}` as const;
                        const localeLabel = localeNames[locale];

                        return (
                            <fieldset key={locale} className="grid gap-3 rounded-lg border p-4 sm:grid-cols-2">
                                <legend className="px-1 text-sm font-semibold">{localeLabel}</legend>
                                <FormField
                                    label={`${t('settings.general_settings.title')} (${localeLabel})`}
                                    htmlFor={titleKey}
                                    error={form.errors[titleKey]}
                                    required
                                >
                                    <Input
                                        id={titleKey}
                                        maxLength={120}
                                        value={form.data[titleKey]}
                                        onChange={(event) => form.setData(titleKey, event.target.value)}
                                        aria-invalid={Boolean(form.errors[titleKey])}
                                        required
                                    />
                                </FormField>
                                <FormField
                                    label={`${t('settings.general_settings.description')} (${localeLabel})`}
                                    htmlFor={descriptionKey}
                                    error={form.errors[descriptionKey]}
                                    required
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        id={descriptionKey}
                                        value={form.data[descriptionKey]}
                                        onChange={(event) => form.setData(descriptionKey, event.target.value)}
                                        aria-invalid={Boolean(form.errors[descriptionKey])}
                                        required
                                    />
                                </FormField>
                            </fieldset>
                        );
                    })
                )}
            </div>
            <FormActionBar mode={isEdit ? 'edit' : 'create'} onCancel={onClose} processing={form.processing} />
        </form>
    );
}
