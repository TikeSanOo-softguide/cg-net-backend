import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { Link2Icon, TypeIcon } from 'lucide-react';

import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

export type CategoryFormValues = {
    name: string;
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

    return (
        <CmsFormShell onSubmit={onSubmit} onCancel={onCancel} processing={form.processing} mode={mode}>
            <FormField label={t('cms.name')} htmlFor="name" error={form.errors.name} icon={TypeIcon} className="sm:col-span-2">
                <Input
                    id="name"
                    value={form.data.name}
                    required
                    onChange={(event) => {
                        form.setData('name', event.target.value);
                        if (!slugTouched) {
                            form.setData('slug', toSlug(event.target.value));
                        }
                    }}
                />
            </FormField>
            <FormField label={t('cms.slug')} htmlFor="slug" error={form.errors.slug} icon={Link2Icon}>
                <Input
                    id="slug"
                    value={form.data.slug}
                    required
                    onChange={(event) => {
                        setSlugTouched(true);
                        form.setData('slug', event.target.value);
                    }}
                />
            </FormField>
        </CmsFormShell>
    );
}
