import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { AlignLeftIcon, CalendarClockIcon, CalendarIcon, CircleDotIcon, TypeIcon } from 'lucide-react';

import { FormField } from '@/components/ui/form-field';
import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { CmsImageField } from '@/components/cms/shared/CmsImageField';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';

export type PromotionFormValues = {
    title: string;
    description: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    image: File | null;
};

type PromotionFormProps = {
    form: InertiaFormProps<PromotionFormValues>;
    onSubmit: (event: FormEvent) => void;
    cancelHref?: string;
    onCancel?: () => void;
    variant?: 'page' | 'modal';
    mode?: 'create' | 'edit';
    imageUrl?: string | null;
};

export function PromotionForm({ form, onSubmit, cancelHref, onCancel, variant = 'page', mode = 'create', imageUrl }: PromotionFormProps) {
    const { t } = useTranslation();

    return (
        <CmsFormShell onSubmit={onSubmit} cancelHref={cancelHref} onCancel={onCancel} processing={form.processing} variant={variant} mode={mode}>
            <FormField label={t('cms.title')} htmlFor="title" error={form.errors.title} icon={TypeIcon} className="sm:col-span-2">
                <Input id="title" value={form.data.title} required onChange={(event) => form.setData('title', event.target.value)} />
            </FormField>
            <FormField label={t('cms.description')} htmlFor="description" error={form.errors.description} icon={AlignLeftIcon} className="sm:col-span-2">
                <Textarea id="description" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
            </FormField>
            <FormField label={t('cms.start_date')} htmlFor="start_date" error={form.errors.start_date} icon={CalendarIcon}>
                <Input id="start_date" type="date" value={form.data.start_date} onChange={(event) => form.setData('start_date', event.target.value)} />
            </FormField>
            <FormField label={t('cms.end_date')} htmlFor="end_date" error={form.errors.end_date} icon={CalendarClockIcon}>
                <Input id="end_date" type="date" value={form.data.end_date} onChange={(event) => form.setData('end_date', event.target.value)} />
            </FormField>
            <FormField label={t('common.status')} htmlFor="is_active" error={form.errors.is_active} icon={CircleDotIcon}>
                <Select value={form.data.is_active ? '1' : '0'} onValueChange={(value) => form.setData('is_active', value === '1')}>
                    <SelectTrigger id="is_active" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="1">{t('status.active')}</SelectItem>
                        <SelectItem value="0">{t('status.inactive')}</SelectItem>
                    </SelectContent>
                </Select>
            </FormField>
            <CmsImageField error={form.errors.image} currentUrl={imageUrl} required={!imageUrl} onChange={(file) => form.setData('image', file)} />
        </CmsFormShell>
    );
}
