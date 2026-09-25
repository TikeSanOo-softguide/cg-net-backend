import { SmartphoneIcon } from 'lucide-react';
import { FormDialog } from '@/components/FormDialog';
import { AppVersionForm, type AppVersionFormData } from './AppVersionForm';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    version: AppVersionFormData | null;
};

export function AppVersionFormDialog({ open, onOpenChange, version }: Props) {
    const { t } = useTranslation();
    const isEdit = Boolean(version?.id);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('settings.app_version.edit') : t('settings.app_version.create')}
            description={
                isEdit ? t('settings.app_version.edit_description') : t('settings.app_version.create_description')
            }
            icon={SmartphoneIcon}
            size="xl"
        >
            {open ? (
                <AppVersionForm
                    key={version ? `edit-${version.id}` : 'create'}
                    initialData={version}
                    onSuccess={() => onOpenChange(false)}
                    onCancel={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}
