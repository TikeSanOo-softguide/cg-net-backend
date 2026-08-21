import { LanguagesIcon } from 'lucide-react';

import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CmsField } from '@/components/cms/CmsField';
import { useTranslation } from '@/hooks/useTranslation';

type CmsLanguageFieldProps = {
    value: string;
    error?: string;
    onChange: (value: string) => void;
};

export function CmsLanguageField({ value, error, onChange }: CmsLanguageFieldProps) {
    const { t } = useTranslation();

    return (
        <CmsField label={t('common.language')} htmlFor="lang" error={error} icon={LanguagesIcon}>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id="lang" className="w-full" aria-invalid={Boolean(error)}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="en">{t('language.en')}</SelectItem>
                    <SelectItem value="my">{t('language.my')}</SelectItem>
                    <SelectItem value="zh">{t('language.zh')}</SelectItem>
                </SelectContent>
            </Select>
        </CmsField>
    );
}
