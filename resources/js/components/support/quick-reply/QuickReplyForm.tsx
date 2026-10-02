import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { FileTextIcon, FolderTreeIcon, TypeIcon } from 'lucide-react';

import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { FormControl } from '@/components/ui/form-control';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlStateClass } from '@/lib/form-control';
import { validateQuickReply, validateQuickReplyField } from '@/lib/quick-reply-validation';
import { cn } from '@/lib/utils';

export type QuickReplyCategoryOption = {
    value: string;
    label_key: string;
};

export type QuickReplyFormValues = {
    keyword: string;
    category: string;
    response_en: string;
    response_my: string;
    response_zh: string;
};

type QuickReplyFormProps = {
    form: InertiaFormProps<QuickReplyFormValues>;
    categories: QuickReplyCategoryOption[];
    existingKeywords: string[];
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    mode?: 'create' | 'edit';
};

const responseFields = [
    { field: 'response_en', labelKey: 'support.quick_replies.response_en' },
    { field: 'response_my', labelKey: 'support.quick_replies.response_my' },
    { field: 'response_zh', labelKey: 'support.quick_replies.response_zh' },
] as const;

export function QuickReplyForm({
    form,
    categories,
    existingKeywords,
    onSubmit,
    onCancel,
    mode = 'create',
}: QuickReplyFormProps) {
    const { t } = useTranslation();
    const categoryValues = categories.map((category) => category.value);
    const [touched, setTouched] = useState<Record<keyof QuickReplyFormValues, boolean>>({
        keyword: false,
        category: false,
        response_en: false,
        response_my: false,
        response_zh: false,
    });
    const [submitted, setSubmitted] = useState(false);

    const markTouched = (field: keyof QuickReplyFormValues) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const setField = <K extends keyof QuickReplyFormValues>(field: K, value: QuickReplyFormValues[K]) => {
        form.setData(field, value as never);
        form.clearErrors(field);
    };

    const fieldState = (field: keyof QuickReplyFormValues): 'idle' | 'error' | 'success' => {
        if (!touched[field] && !submitted) {
            return 'idle';
        }

        return form.errors[field] || validateQuickReplyField(field, form.data, categoryValues, t, existingKeywords)
            ? 'error'
            : 'success';
    };

    const fieldError = (field: keyof QuickReplyFormValues): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }

        return form.errors[field] || validateQuickReplyField(field, form.data, categoryValues, t, existingKeywords);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSubmitted(true);
        setTouched({
            keyword: true,
            category: true,
            response_en: true,
            response_my: true,
            response_zh: true,
        });

        const errors = validateQuickReply(form.data, categoryValues, t, existingKeywords);

        if (Object.keys(errors).length > 0) {
            form.setError(errors);

            return;
        }

        form.clearErrors();
        onSubmit(event);
    };

    return (
        <CmsFormShell onSubmit={submit} onCancel={onCancel} processing={form.processing} mode={mode}>
            <FormField
                label={t('support.quick_replies.keyword')}
                htmlFor="keyword"
                error={fieldError('keyword')}
                required
                icon={TypeIcon}
            >
                <Input
                    id="keyword"
                    value={form.data.keyword}
                    aria-invalid={fieldState('keyword') === 'error'}
                    className={formControlStateClass(fieldState('keyword'))}
                    onBlur={() => markTouched('keyword')}
                    onChange={(event) => setField('keyword', event.target.value)}
                />
            </FormField>
            <FormField
                label={t('support.quick_replies.category')}
                htmlFor="category"
                error={fieldError('category')}
                required
                icon={FolderTreeIcon}
            >
                <FormControl icon={FolderTreeIcon}>
                    <Select
                        value={form.data.category || undefined}
                        onValueChange={(value) => {
                            setField('category', value);
                            markTouched('category');
                        }}
                    >
                        <SelectTrigger
                            id="category"
                            className={cn('w-full', formControlStateClass(fieldState('category')))}
                            aria-invalid={fieldState('category') === 'error'}
                        >
                            <SelectValue placeholder={t('support.quick_replies.category')} />
                        </SelectTrigger>
                        <SelectContent>
                            {categories.map((category) => (
                                <SelectItem key={category.value} value={category.value}>
                                    {t(category.label_key)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormControl>
            </FormField>
            {responseFields.map(({ field, labelKey }) => (
                <div key={field} className="sm:col-span-2">
                    <FormField
                        label={t(labelKey)}
                        htmlFor={field}
                        error={fieldError(field)}
                        required
                        icon={FileTextIcon}
                    >
                        <Textarea
                            id={field}
                            value={form.data[field]}
                            rows={4}
                            aria-invalid={fieldState(field) === 'error'}
                            className={cn('min-h-24', formControlStateClass(fieldState(field)))}
                            onBlur={() => markTouched(field)}
                            onChange={(event) => setField(field, event.target.value)}
                        />
                    </FormField>
                </div>
            ))}
        </CmsFormShell>
    );
}
