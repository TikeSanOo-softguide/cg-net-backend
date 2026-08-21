import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { TagIcon } from 'lucide-react';

import { CmsField } from '@/components/cms/CmsField';
import { CmsFormShell } from '@/components/cms/CmsFormShell';
import { CmsImageField } from '@/components/cms/CmsImageField';
import { CmsLanguageField } from '@/components/cms/CmsLanguageField';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

export type GalleryFormValues = {
    label: string;
    lang: string;
    image: File | null;
};

type GalleryFormProps = {
    form: InertiaFormProps<GalleryFormValues>;
    onSubmit: (event: FormEvent) => void;
    cancelHref: string;
    imageUrl?: string | null;
};

export function GalleryForm({ form, onSubmit, cancelHref, imageUrl }: GalleryFormProps) {
    const { t } = useTranslation();

    return (
        <CmsFormShell onSubmit={onSubmit} cancelHref={cancelHref} processing={form.processing}>
            <CmsField label={t('cms.label')} htmlFor="label" error={form.errors.label} icon={TagIcon} className="sm:col-span-2">
                <Input id="label" value={form.data.label} onChange={(event) => form.setData('label', event.target.value)} />
            </CmsField>
            <CmsLanguageField value={form.data.lang} error={form.errors.lang} onChange={(value) => form.setData('lang', value)} />
            <CmsImageField error={form.errors.image} currentUrl={imageUrl} required={! imageUrl} onChange={(file) => form.setData('image', file)} />
        </CmsFormShell>
    );
}
