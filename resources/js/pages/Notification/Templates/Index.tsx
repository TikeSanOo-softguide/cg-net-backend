import { Head, useForm } from '@inertiajs/react';
import { FileTextIcon, PencilIcon, SaveIcon, XIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';

import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { Accordion } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';

type NotificationTemplateType =
    'bill_alert' | 'ftth_bill_payment_processing' | 'ftth_bill_payment_completed' | 'ftth_bill_payment_refunded';

type NotificationTemplate = {
    id: number;
    type: NotificationTemplateType;
    title_en: string;
    title_my: string;
    title_zh: string;
    description_en: string;
    description_my: string;
    description_zh: string;
};

type TemplateFormData = Pick<
    NotificationTemplate,
    'title_en' | 'title_my' | 'title_zh' | 'description_en' | 'description_my' | 'description_zh'
>;
type TemplateField = keyof TemplateFormData;
type TouchedFields = Record<TemplateField, boolean>;

const templateFields: TemplateField[] = [
    'title_en',
    'title_my',
    'title_zh',
    'description_en',
    'description_my',
    'description_zh',
];

type Props = {
    templates: NotificationTemplate[];
};

export default function NotificationTemplatesIndex({ templates }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.notification_templates')} />
            <PageContent>
                <PageHeader />
                <Card className="gap-0 overflow-hidden border-0 py-0 shadow-[0_4px_16px_rgb(23_50_54/0.06)] dark:shadow-[0_4px_16px_rgb(0_0_0/0.22)]">
                    {templates.map((template) => (
                        <NotificationTemplateEditor key={template.id} template={template} />
                    ))}
                </Card>
            </PageContent>
        </>
    );
}

function NotificationTemplateEditor({ template }: { template: NotificationTemplate }) {
    const { t } = useTranslation();
    const canUpdate = useCan()('notifications.update');
    const [isEditing, setIsEditing] = useState(false);
    const [touched, setTouched] = useState<TouchedFields>({
        title_en: false,
        title_my: false,
        title_zh: false,
        description_en: false,
        description_my: false,
        description_zh: false,
    });
    const [submitted, setSubmitted] = useState(false);
    const form = useForm<TemplateFormData>({
        title_en: template.title_en,
        title_my: template.title_my,
        title_zh: template.title_zh,
        description_en: template.description_en,
        description_my: template.description_my,
        description_zh: template.description_zh,
    });

    const validateField = (field: TemplateField): string | undefined => {
        const value = form.data[field];

        if (!value.trim()) {
            return t(`notification.template.validation.${field}_required`);
        }

        if (field.startsWith('title_') && Array.from(value).length > 120) {
            return t('notification.template.validation.title_max');
        }

        if (field.startsWith('description_') && Array.from(value).length > 5000) {
            return t('notification.template.validation.description_max');
        }

        return undefined;
    };

    const fieldError = (field: TemplateField): string | undefined => {
        if (!touched[field] && !submitted) {
            return form.errors[field];
        }

        return form.errors[field] || validateField(field);
    };

    const markTouched = (field: TemplateField) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const setField = (field: TemplateField, value: string) => {
        form.setData(field, value);
        form.clearErrors(field);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSubmitted(true);
        setTouched({
            title_en: true,
            title_my: true,
            title_zh: true,
            description_en: true,
            description_my: true,
            description_zh: true,
        });

        const errors: Partial<Record<TemplateField, string>> = {};
        templateFields.forEach((field) => {
            const message = validateField(field);
            if (message) {
                errors[field] = message;
            }
        });

        if (Object.keys(errors).length > 0) {
            form.setError(errors);
            return;
        }

        form.clearErrors();
        form.put(`/notifications/templates/${template.id}`, {
            onSuccess: () => {
                form.setDefaults(form.data);
                setIsEditing(false);
            },
        });
    };

    const reset = () => {
        form.reset();
        form.clearErrors();
        setSubmitted(false);
        setTouched({
            title_en: false,
            title_my: false,
            title_zh: false,
            description_en: false,
            description_my: false,
            description_zh: false,
        });
        setIsEditing(false);
    };

    return (
        <form onSubmit={submit}>
            <Accordion
                title={t(`notification.template.types.${template.type}`)}
                description={t('notification.template.description')}
            >
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    {(['en', 'my', 'zh'] as const).map((locale) => (
                        <div key={locale} className="space-y-4">
                            {isEditing ? (
                                <>
                                    <FormField
                                        label={t(`notification.template.title_${locale}`)}
                                        htmlFor={`title_${template.id}_${locale}`}
                                        error={fieldError(`title_${locale}`)}
                                        required
                                        icon={FileTextIcon}
                                    >
                                        <Input
                                            id={`title_${template.id}_${locale}`}
                                            maxLength={120}
                                            aria-invalid={Boolean(fieldError(`title_${locale}`))}
                                            value={form.data[`title_${locale}`]}
                                            onBlur={() => markTouched(`title_${locale}`)}
                                            onChange={(event) => setField(`title_${locale}`, event.target.value)}
                                        />
                                    </FormField>
                                    <FormField
                                        label={t(`notification.template.description_${locale}`)}
                                        htmlFor={`description_${template.id}_${locale}`}
                                        error={fieldError(`description_${locale}`)}
                                        required
                                        icon={FileTextIcon}
                                    >
                                        <Textarea
                                            id={`description_${template.id}_${locale}`}
                                            className="min-h-20"
                                            maxLength={5000}
                                            aria-invalid={Boolean(fieldError(`description_${locale}`))}
                                            value={form.data[`description_${locale}`]}
                                            onBlur={() => markTouched(`description_${locale}`)}
                                            onChange={(event) => setField(`description_${locale}`, event.target.value)}
                                        />
                                    </FormField>
                                </>
                            ) : (
                                <>
                                    <div>
                                        <p className="mt-1 text-sm font-medium">{form.data[`title_${locale}`]}</p>
                                    </div>
                                    <div>
                                        <p className="mt-1 whitespace-pre-wrap text-sm">
                                            {form.data[`description_${locale}`]}
                                        </p>
                                    </div>
                                </>
                            )}
                        </div>
                    ))}
                </div>

                {isEditing ? (
                    <p className="text-xs text-muted-foreground">
                        {t(`notification.template.placeholders.${template.type}`)}
                    </p>
                ) : null}
                {Object.keys(form.errors).length > 0 ? (
                    <p role="alert" className="text-sm text-danger">
                        {t('notification.template.validation_error')}
                    </p>
                ) : null}
                {canUpdate ? (
                    <div className="flex justify-end border-t border-border/70 pt-4">
                        {isEditing ? (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={form.processing}
                                    onClick={reset}
                                >
                                    <XIcon className="size-4" />
                                    {t('common.cancel')}
                                </Button>
                                <Button type="submit" size="sm" className="ml-2" disabled={form.processing}>
                                    <SaveIcon className="size-4" />
                                    {form.processing ? t('common.please_wait') : t('common.save')}
                                </Button>
                            </>
                        ) : (
                            <Button type="button" size="sm" onClick={() => setIsEditing(true)}>
                                <PencilIcon className="size-4" />
                                {t('common.edit')}
                            </Button>
                        )}
                    </div>
                ) : null}
            </Accordion>
        </form>
    );
}
