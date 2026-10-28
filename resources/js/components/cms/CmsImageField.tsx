import { ImageIcon } from 'lucide-react';

import { CmsField } from '@/components/cms/CmsField';
import { FormControl } from '@/components/ui/form-control';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

type CmsImageFieldProps = {
    label?: string;
    inputId?: string;
    error?: string;
    currentUrl?: string | null;
    onChange: (file: File | null) => void;
    required?: boolean;
};

export function CmsImageField({ label, inputId = 'image', error, currentUrl, onChange, required = false }: CmsImageFieldProps) {
    const { t } = useTranslation();

    return (
        <CmsField label={label ?? t('cms.image')} htmlFor={inputId} error={error} className="sm:col-span-2">
            {currentUrl ? (
                <img src={currentUrl} alt="" className="mb-2 h-24 w-auto rounded-[6px] border border-border object-cover" />
            ) : null}
            <FormControl icon={ImageIcon}>
                <Input
                    id={inputId}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    required={required && !currentUrl}
                    aria-invalid={Boolean(error)}
                    onChange={(event) => onChange(event.target.files?.[0] ?? null)}
                />
            </FormControl>
        </CmsField>
    );
}
