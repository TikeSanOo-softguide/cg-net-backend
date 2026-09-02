import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { Link2Icon, TypeIcon } from 'lucide-react';

import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import {
    CATEGORY_NAME_MAX_LENGTH,
    CATEGORY_SLUG_MAX_LENGTH,
    validateCategory,
    validateCategoryField,
} from '@/lib/category-validation';
import { formControlStateClass } from '@/lib/form-control';
import { cn } from '@/lib/utils';

export type CategoryFormValues = {
    name_en: string;
    name_zh: string;
    name_my: string;
    slug: string;
};

function toSlug(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

type CategoryFormProps = {
    form: InertiaFormProps<CategoryFormValues>;
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    mode?: 'create' | 'edit';
};

export function CategoryForm({ form, onSubmit, onCancel, mode = 'create' }: CategoryFormProps) {
    const { t } = useTranslation();
    const [slugTouched, setSlugTouched] = useState(form.data.slug !== '');
    const [touched, setTouched] = useState<Record<keyof CategoryFormValues, boolean>>({
        name_en: false,
        name_zh: false,
        name_my: false,
        slug: false,
    });
    const [submitted, setSubmitted] = useState(false);

    const markTouched = (field: keyof CategoryFormValues) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const setField = <K extends keyof CategoryFormValues>(field: K, value: CategoryFormValues[K]) => {
        form.setData(field, value as never);
        form.clearErrors(field);
    };

    const fieldState = (field: keyof CategoryFormValues): 'idle' | 'error' | 'success' => {
        if (!touched[field] && !submitted) {
            return 'idle';
        }

        return form.errors[field] || validateCategoryField(field, form.data, t) ? 'error' : 'success';
    };

    const fieldError = (field: keyof CategoryFormValues): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }

        return form.errors[field] || validateCategoryField(field, form.data, t);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSubmitted(true);
        setTouched({ name_en: true, name_zh: true, name_my: true, slug: true });

        const errors = validateCategory(form.data, t);

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
                label={t('cms.category.name_en')}
                htmlFor="name_en"
                error={fieldError('name_en')}
                required
                icon={TypeIcon}
                className="sm:col-span-2"
            >
                <Input
                    id="name_en"
                    value={form.data.name_en}
                    maxLength={CATEGORY_NAME_MAX_LENGTH}
                    aria-invalid={fieldState('name_en') === 'error'}
                    className={formControlStateClass(fieldState('name_en'))}
                    onBlur={() => markTouched('name_en')}
                    onChange={(event) => {
                        setField('name_en', event.target.value);
                        if (!slugTouched) {
                            setField('slug', toSlug(event.target.value));
                        }
                    }}
                />
            </FormField>
            <FormField
                label={t('cms.category.name_zh')}
                htmlFor="name_zh"
                error={fieldError('name_zh')}
                required
                icon={TypeIcon}
                className="sm:col-span-2"
            >
                <Input
                    id="name_zh"
                    value={form.data.name_zh}
                    maxLength={CATEGORY_NAME_MAX_LENGTH}
                    aria-invalid={fieldState('name_zh') === 'error'}
                    className={formControlStateClass(fieldState('name_zh'))}
                    onBlur={() => markTouched('name_zh')}
                    onChange={(event) => {
                        setField('name_zh', event.target.value);
                    }}
                />
            </FormField>
            <FormField
                label={t('cms.category.name_my')}
                htmlFor="name_my"
                error={fieldError('name_my')}
                required
                icon={TypeIcon}
                className="sm:col-span-2"
            >
                <Input
                    id="name_my"
                    value={form.data.name_my}
                    maxLength={CATEGORY_NAME_MAX_LENGTH}
                    aria-invalid={fieldState('name_my') === 'error'}
                    className={formControlStateClass(fieldState('name_my'))}
                    onBlur={() => markTouched('name_my')}
                    onChange={(event) => {
                        setField('name_my', event.target.value);
                    }}
                />
            </FormField>
            <FormField
                label={t('cms.slug')}
                htmlFor="slug"
                error={fieldError('slug')}
                required
                icon={Link2Icon}
                className="sm:col-span-2"
            >
                <Input
                    id="slug"
                    value={form.data.slug}
                    maxLength={CATEGORY_SLUG_MAX_LENGTH}
                    aria-invalid={fieldState('slug') === 'error'}
                    className={cn('w-full', formControlStateClass(fieldState('slug')))}
                    onBlur={() => markTouched('slug')}
                    onChange={(event) => {
                        setSlugTouched(true);
                        setField('slug', event.target.value);
                    }}
                />
            </FormField>
        </CmsFormShell>
    );
}
