import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { PlusIcon, SquarePenIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormDialog } from '@/components/FormDialog';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import type { SupportedLocale } from '@/types';

export type FaqItem = {
    id: number;
    title_en: string;
    title_zh: string;
    title_my: string;
    description_en: string;
    description_zh: string;
    description_my: string;
};

type FormValues = {
    title_en: string;
    title_zh: string;
    title_my: string;
    description_en: string;
    description_zh: string;
    description_my: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: FaqItem | null;
};

function getInitialValues(item: FaqItem | null): FormValues {
    return {
        title_en: item?.title_en ?? '',
        title_zh: item?.title_zh ?? '',
        title_my: item?.title_my ?? '',
        description_en: item?.description_en ?? '',
        description_zh: item?.description_zh ?? '',
        description_my: item?.description_my ?? '',
    };
}

export function FaqFormDialog({ open, onOpenChange, item }: Props) {
    const { t } = useTranslation();

    const isEdit = item !== null;

    const close = () => {
        onOpenChange(false);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={`${isEdit ? t('common.edit') : t('common.create')} ${t('settings.general_settings.faq')}`}
            description={t('settings.general_settings.content_form_description')}
            icon={isEdit ? SquarePenIcon : PlusIcon}
            size="xl"
        >
            {open ? <FaqFormBody key={item?.id ?? 'create'} item={item} onClose={close} /> : null}
        </FormDialog>
    );
}

function FaqFormBody({ item, onClose }: { item: FaqItem | null; onClose: () => void }) {
    const { t } = useTranslation();

    const isEdit = item !== null;

    const form = useForm<FormValues>(getInitialValues(item));

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const route = '/settings/general/faqs';

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

    const localeNames: Record<SupportedLocale, string> = {
        en: t('settings.general_settings.english'),
        zh: t('settings.general_settings.chinese'),
        my: t('settings.general_settings.burmese'),
    };

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                {(['en', 'zh', 'my'] as const).map((locale) => {
                    const titleKey = `title_${locale}` as const;
                    const descriptionKey = `description_${locale}` as const;

                    const localeLabel = localeNames[locale];

                    return (
                        <fieldset key={locale} className="grid gap-3 rounded-lg border p-4 sm:grid-cols-2">
                            <legend className="px-1 text-sm font-semibold">{localeLabel}</legend>

                            <FormField
                                label={`${t('settings.general_settings.title')} (${localeLabel})`}
                                htmlFor={`faq-${titleKey}`}
                                error={form.errors[titleKey]}
                                required
                            >
                                <Input
                                    id={`faq-${titleKey}`}
                                    maxLength={120}
                                    value={form.data[titleKey]}
                                    onChange={(event) => form.setData(titleKey, event.target.value)}
                                    aria-invalid={Boolean(form.errors[titleKey])}
                                    required
                                />
                            </FormField>

                            <FormField
                                label={`${t('settings.general_settings.description')} (${localeLabel})`}
                                htmlFor={`faq-${descriptionKey}`}
                                error={form.errors[descriptionKey]}
                                required
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id={`faq-${descriptionKey}`}
                                    value={form.data[descriptionKey]}
                                    onChange={(event) => form.setData(descriptionKey, event.target.value)}
                                    aria-invalid={Boolean(form.errors[descriptionKey])}
                                    required
                                />
                            </FormField>
                        </fieldset>
                    );
                })}
            </div>

            <FormActionBar mode={isEdit ? 'edit' : 'create'} onCancel={onClose} processing={form.processing} />
        </form>
    );
}
