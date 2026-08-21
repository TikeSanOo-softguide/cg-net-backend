import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { Link2Icon, TypeIcon } from 'lucide-react';

import { CmsField } from '@/components/cms/CmsField';
import { CmsFormShell } from '@/components/cms/CmsFormShell';
import { CmsLanguageField } from '@/components/cms/CmsLanguageField';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

export type NameSlugFormValues = {
    name: string;
    slug: string;
    lang: string;
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
    cancelHref: string;
};

export function NameSlugForm({ form, onSubmit, cancelHref }: NameSlugFormProps) {
    const { t } = useTranslation();
    const [slugTouched, setSlugTouched] = useState(form.data.slug !== '');

    return (
        <CmsFormShell onSubmit={onSubmit} cancelHref={cancelHref} processing={form.processing}>
            <CmsField label={t('cms.name')} htmlFor="name" error={form.errors.name} icon={TypeIcon} className="sm:col-span-2">
                <Input
                    id="name"
                    value={form.data.name}
                    required
                    onChange={(event) => {
                        form.setData('name', event.target.value);
                        if (! slugTouched) {
                            form.setData('slug', toSlug(event.target.value));
                        }
                    }}
                />
            </CmsField>
            <CmsField label={t('cms.slug')} htmlFor="slug" error={form.errors.slug} icon={Link2Icon}>
                <Input
                    id="slug"
                    value={form.data.slug}
                    required
                    onChange={(event) => {
                        setSlugTouched(true);
                        form.setData('slug', event.target.value);
                    }}
                />
            </CmsField>
            <CmsLanguageField value={form.data.lang} error={form.errors.lang} onChange={(value) => form.setData('lang', value)} />
        </CmsFormShell>
    );
}
