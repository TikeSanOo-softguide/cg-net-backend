import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { ContactIcon } from 'lucide-react';

import { CmsField } from '@/components/cms/CmsField';
import { CmsFormShell } from '@/components/cms/CmsFormShell';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

export type ContactFormValues = {
    contact_point: string;
};

type ContactFormProps = {
    form: InertiaFormProps<ContactFormValues>;
    onSubmit: (event: FormEvent) => void;
    cancelHref: string;
};

export function ContactForm({ form, onSubmit, cancelHref }: ContactFormProps) {
    const { t } = useTranslation();

    return (
        <CmsFormShell onSubmit={onSubmit} cancelHref={cancelHref} processing={form.processing}>
            <CmsField label={t('cms.contact_point')} htmlFor="contact_point" error={form.errors.contact_point} icon={ContactIcon} className="sm:col-span-2">
                <Input id="contact_point" value={form.data.contact_point} required onChange={(event) => form.setData('contact_point', event.target.value)} />
            </CmsField>
        </CmsFormShell>
    );
}
