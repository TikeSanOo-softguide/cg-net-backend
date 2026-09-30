import { useState } from 'react';
import { FileUp } from 'lucide-react';

import { Input } from '@/components/ui/input';
import { FormDialog } from '@/components/FormDialog';
import { FormActionBar } from '@/components/FormActionBar';
import { useTranslation } from '@/hooks/useTranslation';

type TopUpCardImportDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onImport: (file: File) => void;
};

export function TopUpCardImportDialog({ open, onOpenChange, onImport }: TopUpCardImportDialogProps) {
    const { t } = useTranslation();

    const [file, setFile] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFile = event.target.files?.[0] ?? null;

        setError(null);
        setFile(null);

        if (!selectedFile) {
            return;
        }

        const fileName = selectedFile.name.toLowerCase();
        const isCsv = selectedFile.type === 'text/csv' || fileName.endsWith('.csv');

        if (!isCsv) {
            setError(t('top_up_cards.validation.csv_file_type'));
            event.target.value = '';
            return;
        }

        setFile(selectedFile);
    };

    const handleImport = () => {
        if (!file) {
            setError(t('top_up_cards.validation.import_csv_required'));
            return;
        }

        setError(null);
        setProcessing(true);

        onImport(file);
        setFile(null);
        onOpenChange(false);
        setProcessing(false);
    };

    const handleCancel = () => {
        setFile(null);
        setError(null);
        onOpenChange(false);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('top_up_cards.import_csv.label')}
            description={t('top_up_cards.import_csv.description')}
            icon={FileUp}
            size="md"
        >
            <form
                className="flex min-h-0 flex-1 flex-col"
                onSubmit={(event) => {
                    event.preventDefault();
                    handleImport();
                }}
            >
                <div className="flex flex-1 flex-col gap-4 p-5">
                    <div className="flex flex-col gap-2">
                        <Input type="file" accept=".csv,text/csv" onChange={handleFileChange} aria-invalid={!!error} />

                        {file ? <p className="text-sm text-muted-foreground">{file.name}</p> : null}

                        {error ? <p className="text-sm text-danger">{error}</p> : null}
                    </div>
                </div>

                <FormActionBar
                    mode="create"
                    submitLabel={t('top_up_cards.import_csv.import')}
                    processing={processing}
                    onCancel={handleCancel}
                />
            </form>
        </FormDialog>
    );
}
