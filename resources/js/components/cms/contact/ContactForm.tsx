import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { ContactIcon } from 'lucide-react';

import { FormField } from '@/components/ui/form-field';
import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

export type ContactFormValues = {
    contact_point: string;
};

type ContactFormProps = {
    form: InertiaFormProps<ContactFormValues>;
    onSubmit: (event: FormEvent) => void;
    cancelHref?: string;
    onCancel?: () => void;
    variant?: 'page' | 'modal';
    mode?: 'create' | 'edit';
};

export function ContactForm({ form, onSubmit, cancelHref, onCancel, variant = 'page', mode = 'create' }: ContactFormProps) {
    const { t } = useTranslation();

    return (
        <CmsFormShell onSubmit={onSubmit} cancelHref={cancelHref} onCancel={onCancel} processing={form.processing} variant={variant} mode={mode}>
            <FormField label={t('cms.contact_point')} htmlFor="contact_point" error={form.errors.contact_point} icon={ContactIcon} className="sm:col-span-2">
                <Input id="contact_point" value={form.data.contact_point} required onChange={(event) => form.setData('contact_point', event.target.value)} />
            </FormField>
        </CmsFormShell>
    );
}
