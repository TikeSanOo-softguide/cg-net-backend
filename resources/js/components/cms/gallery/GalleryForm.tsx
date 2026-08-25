import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { TagIcon } from 'lucide-react';

import { FormField } from '@/components/ui/form-field';
import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { CmsImageField } from '@/components/cms/shared/CmsImageField';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

export type GalleryFormValues = {
    label: string;
    image: File | null;
};

type GalleryFormProps = {
    form: InertiaFormProps<GalleryFormValues>;
    onSubmit: (event: FormEvent) => void;
    cancelHref?: string;
    onCancel?: () => void;
    variant?: 'page' | 'modal';
    mode?: 'create' | 'edit';
    imageUrl?: string | null;
};

export function GalleryForm({ form, onSubmit, cancelHref, onCancel, variant = 'page', mode = 'create', imageUrl }: GalleryFormProps) {
    const { t } = useTranslation();

    return (
        <CmsFormShell onSubmit={onSubmit} cancelHref={cancelHref} onCancel={onCancel} processing={form.processing} variant={variant} mode={mode}>
            <FormField label={t('cms.label')} htmlFor="label" error={form.errors.label} icon={TagIcon} className="sm:col-span-2">
                <Input id="label" value={form.data.label} onChange={(event) => form.setData('label', event.target.value)} />
            </FormField>
            <CmsImageField error={form.errors.image} currentUrl={imageUrl} required={!imageUrl} onChange={(file) => form.setData('image', file)} />
        </CmsFormShell>
    );
}
