import { FormEvent, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { PlusIcon, SquarePenIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormDialog } from '@/components/FormDialog';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { SupportedLocale } from '@/types';

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

type FormFieldName = keyof FormValues;
type TouchedFields = Partial<Record<FormFieldName, boolean>>;

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

function validateField(field: FormFieldName, value: string, t: (key: string) => string): string | undefined {
    if (value.trim() === '') {
        return t('settings.general_settings.validation.required');
    }

    if (field === 'title_en' || field === 'title_zh' || field === 'title_my') {
        if (value.length > 120) {
            return t('settings.general_settings.validation.title_max');
        }
    }

    return undefined;
}

function validateForm(values: FormValues, t: (key: string) => string): Partial<Record<FormFieldName, string>> {
    const errors: Partial<Record<FormFieldName, string>> = {};

    (Object.keys(values) as FormFieldName[]).forEach((field) => {
        const error = validateField(field, values[field], t);

        if (error) {
            errors[field] = error;
        }
    });

    return errors;
}

function FaqFormBody({ item, onClose }: { item: FaqItem | null; onClose: () => void }) {
    const { t } = useTranslation();

    const isEdit = item !== null;

    const form = useForm<FormValues>(getInitialValues(item));

    const [touched, setTouched] = useState<TouchedFields>({});
    const [submitted, setSubmitted] = useState(false);

    const markTouched = (field: FormFieldName) => {
        setTouched((current) => ({
            ...current,
            [field]: true,
        }));
    };

    const setField = (field: FormFieldName, value: string) => {
        form.setData(field, value);
        form.clearErrors(field);

        setTouched((current) => ({
            ...current,
            [field]: true,
        }));
    };

    const fieldError = (field: FormFieldName): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }

        return form.errors[field] || validateField(field, form.data[field], t);
    };

    const fieldState = (field: FormFieldName): 'idle' | 'error' | 'success' => {
        if (!touched[field] && !submitted) {
            return 'idle';
        }

        if (form.errors[field] || validateField(field, form.data[field], t)) {
            return 'error';
        }

        return 'success';
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        setSubmitted(true);

        setTouched({
            title_en: true,
            title_zh: true,
            title_my: true,
            description_en: true,
            description_zh: true,
            description_my: true,
        });

        const errors = validateForm(form.data, t);

        if (Object.keys(errors).length > 0) {
            form.setError(errors);
            return;
        }

        form.clearErrors();

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

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                <section className="rounded-lg border bg-card">
                    <div className="border-b bg-muted/30 px-4 py-3">
                        <h3 className="text-sm font-semibold">{t('settings.general_settings.english')}</h3>
                    </div>

                    <div className="space-y-4 p-4">
                        <FormField
                            label={`${t('settings.general_settings.title')}`}
                            htmlFor="faq-title-en"
                            error={fieldError('title_en')}
                            required
                        >
                            <Input
                                id="faq-title-en"
                                value={form.data.title_en}
                                onBlur={() => markTouched('title_en')}
                                onChange={(event) => setField('title_en', event.target.value)}
                                aria-invalid={fieldState('title_en') === 'error'}
                                className={fieldState('title_en') === 'error' ? 'border-destructive' : ''}
                            />
                        </FormField>

                        <FormField
                            label={`${t('settings.general_settings.description')}`}
                            htmlFor="faq-description-en"
                            error={fieldError('description_en')}
                            required
                        >
                            <Textarea
                                id="faq-description-en"
                                value={form.data.description_en}
                                onBlur={() => markTouched('description_en')}
                                onChange={(event) => setField('description_en', event.target.value)}
                                aria-invalid={fieldState('description_en') === 'error'}
                                className={fieldState('description_en') === 'error' ? 'border-destructive' : ''}
                            />
                        </FormField>
                    </div>
                </section>

                <section className="rounded-lg border bg-card">
                    <div className="border-b bg-muted/30 px-4 py-3">
                        <h3 className="text-sm font-semibold">{t('settings.general_settings.chinese')}</h3>
                    </div>

                    <div className="space-y-4 p-4">
                        <FormField
                            label={`${t('settings.general_settings.title')}`}
                            htmlFor="faq-title-zh"
                            error={fieldError('title_zh')}
                            required
                        >
                            <Input
                                id="faq-title-zh"
                                value={form.data.title_zh}
                                onBlur={() => markTouched('title_zh')}
                                onChange={(event) => setField('title_zh', event.target.value)}
                                aria-invalid={fieldState('title_zh') === 'error'}
                                className={fieldState('title_zh') === 'error' ? 'border-destructive' : ''}
                            />
                        </FormField>

                        <FormField
                            label={`${t('settings.general_settings.description')}`}
                            htmlFor="faq-description-zh"
                            error={fieldError('description_zh')}
                            required
                        >
                            <Textarea
                                id="faq-description-zh"
                                value={form.data.description_zh}
                                onBlur={() => markTouched('description_zh')}
                                onChange={(event) => setField('description_zh', event.target.value)}
                                aria-invalid={fieldState('description_zh') === 'error'}
                                className={fieldState('description_zh') === 'error' ? 'border-destructive' : ''}
                            />
                        </FormField>
                    </div>
                </section>

                <section className="rounded-lg border bg-card">
                    <div className="border-b bg-muted/30 px-4 py-3">
                        <h3 className="text-sm font-semibold">{t('settings.general_settings.myanmar')}</h3>
                    </div>

                    <div className="space-y-4 p-4">
                        <FormField
                            label={`${t('settings.general_settings.title')}`}
                            htmlFor="faq-title-my"
                            error={fieldError('title_my')}
                            required
                        >
                            <Input
                                id="faq-title-my"
                                value={form.data.title_my}
                                onBlur={() => markTouched('title_my')}
                                onChange={(event) => setField('title_my', event.target.value)}
                                aria-invalid={fieldState('title_my') === 'error'}
                                className={fieldState('title_my') === 'error' ? 'border-destructive' : ''}
                            />
                        </FormField>

                        <FormField
                            label={`${t('settings.general_settings.description')}`}
                            htmlFor="faq-description-my"
                            error={fieldError('description_my')}
                            required
                        >
                            <Textarea
                                id="faq-description-my"
                                value={form.data.description_my}
                                onBlur={() => markTouched('description_my')}
                                onChange={(event) => setField('description_my', event.target.value)}
                                aria-invalid={fieldState('description_my') === 'error'}
                                className={fieldState('description_my') === 'error' ? 'border-destructive' : ''}
                            />
                        </FormField>
                    </div>
                </section>
            </div>

            <FormActionBar mode={isEdit ? 'edit' : 'create'} onCancel={onClose} processing={form.processing} />
        </form>
    );
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
