import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { Link2Icon, TypeIcon } from 'lucide-react';

import { FormField } from '@/components/ui/form-field';
import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

export type NameSlugFormValues = {
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

type NameSlugFormProps = {
    form: InertiaFormProps<NameSlugFormValues>;
    onSubmit: (event: FormEvent) => void;
    cancelHref?: string;
    onCancel?: () => void;
    variant?: 'page' | 'modal';
    mode?: 'create' | 'edit';
};

export function NameSlugForm({ form, onSubmit, cancelHref, onCancel, variant = 'page', mode = 'create' }: NameSlugFormProps) {
    const { t } = useTranslation();
    const [slugTouched, setSlugTouched] = useState(form.data.slug !== '');

    return (
        <CmsFormShell onSubmit={onSubmit} cancelHref={cancelHref} onCancel={onCancel} processing={form.processing} variant={variant} mode={mode}>
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
