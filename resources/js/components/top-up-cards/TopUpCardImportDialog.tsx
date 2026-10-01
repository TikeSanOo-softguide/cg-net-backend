import { useEffect, useRef, useState } from 'react';
import { CheckIcon, FileUp, LoaderCircleIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FormDialog } from '@/components/FormDialog';
import { FormActionBar, formActionSubmitClass } from '@/components/FormActionBar';
import { useTranslation } from '@/hooks/useTranslation';

export type TopUpCardCsvPreview = {
    rows: {
        office: string;
        count_50: number;
        count_100: number;
        count_250: number;
        count_500: number;
        total_points: number;
        expires_at: string;
    }[];
    total_rows: number;
    total_summaries: number;
};

export type TopUpCardCsvValidationError = {
    key: string;
    replace?: Record<string, string | number>;
};

type TopUpCardImportDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCheck: (file: File) => Promise<TopUpCardCsvPreview>;
    onImport: (file: File) => void;
};

export function TopUpCardImportDialog({ open, onOpenChange, onCheck, onImport }: TopUpCardImportDialogProps) {
    const { t } = useTranslation();

    const [file, setFile] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [checking, setChecking] = useState(false);
    const [checkedFile, setCheckedFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<TopUpCardCsvPreview | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const checkRequestId = useRef(0);
    const translatedError = (validationError: TopUpCardCsvValidationError) =>
        t(validationError.key).replace(/:([a-z_]+)/g, (_, key: string) =>
            String(validationError.replace?.[key] ?? `:${key}`),
        );

    useEffect(() => {
        if (open) {
            return;
        }

        checkRequestId.current++;
        setFile(null);
        setError(null);
        setChecking(false);
        setCheckedFile(null);
        setPreview(null);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    }, [open]);

    const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFile = event.target.files?.[0] ?? null;

        checkRequestId.current++;
        setError(null);
        setFile(null);
        setChecking(false);
        setCheckedFile(null);
        setPreview(null);

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

    const handleCheck = async () => {
        if (!file) {
            setError(t('top_up_cards.validation.import_csv_required'));
            return;
        }

        setError(null);
        setChecking(true);
        const requestId = ++checkRequestId.current;

        try {
            const checkedPreview = await onCheck(file);
            if (checkRequestId.current !== requestId) {
                return;
            }
            setPreview(checkedPreview);
            setCheckedFile(file);
        } catch (validationError) {
            if (checkRequestId.current !== requestId) {
                return;
            }
            setPreview(null);
            setCheckedFile(null);
            const detail = validationError as TopUpCardCsvValidationError;
            setError(detail?.key ? translatedError(detail) : t('csv.import_errors.read_failed'));
        } finally {
            if (checkRequestId.current === requestId) {
                setChecking(false);
            }
        }
    };

    const handleImport = () => {
        if (!file || checkedFile !== file) {
            return;
        }

        onImport(file);
        setFile(null);
        setCheckedFile(null);
        setPreview(null);
        onOpenChange(false);
    };

    const handleCancel = () => {
        onOpenChange(false);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('top_up_cards.import_csv.label')}
            description={t('top_up_cards.import_csv.description')}
            icon={FileUp}
            size="2xl"
        >
            <form
                className="flex min-h-0 flex-1 flex-col"
                onSubmit={(event) => {
                    event.preventDefault();
                    handleImport();
                }}
            >
                <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-5">
                    <div className="flex flex-col gap-2">
                        <Input
                            ref={fileInputRef}
                            type="file"
                            accept=".csv,text/csv"
                            onChange={handleFileChange}
                            aria-invalid={!!error}
                        />

                        {file ? <p className="text-sm text-muted-foreground">{file.name}</p> : null}

                        {error ? <p className="text-sm text-danger">{error}</p> : null}
                    </div>

                    {preview ? (
                        <section className="min-h-0 space-y-2">
                            <p className="text-sm font-medium text-foreground">
                                {t('top_up_cards.import_csv.checked').replace(
                                    ':count',
                                    preview.total_rows.toLocaleString(),
                                )}
                            </p>
                            <div className="max-h-64 overflow-auto rounded-[6px] border border-border bg-surface">
                                <table className="w-full min-w-[760px] text-left text-sm">
                                    <thead className="sticky top-0 bg-muted text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">{t('top_up_cards.office.name')}</th>
                                            {[50, 100, 250, 500].map((amount) => (
                                                <th key={amount} className="px-3 py-2 text-right font-medium">
                                                    {amount} Points
                                                </th>
                                            ))}
                                            <th className="px-3 py-2 text-right font-medium">
                                                {t('top_up_cards.total_points')}
                                            </th>
                                            <th className="px-3 py-2 font-medium">{t('top_up_cards.expires_at')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {preview.rows.map((row, index) => (
                                            <tr
                                                key={`${row.office}-${row.expires_at}-${index}`}
                                                className="border-t border-border/70"
                                            >
                                                <td className="px-3 py-2">{row.office}</td>
                                                {[row.count_50, row.count_100, row.count_250, row.count_500].map(
                                                    (count, countIndex) => (
                                                        <td
                                                            key={countIndex}
                                                            className="px-3 py-2 text-right tabular-nums"
                                                        >
                                                            {count.toLocaleString()}
                                                        </td>
                                                    ),
                                                )}
                                                <td className="px-3 py-2 text-right font-medium tabular-nums">
                                                    {row.total_points.toLocaleString()}
                                                </td>
                                                <td className="px-3 py-2">{row.expires_at}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {preview.total_summaries > preview.rows.length ? (
                                <p className="text-xs text-muted-foreground">
                                    {t('top_up_cards.import_csv.preview_limit')
                                        .replace(':shown', preview.rows.length.toLocaleString())
                                        .replace(':total', preview.total_summaries.toLocaleString())}
                                </p>
                            ) : null}
                        </section>
                    ) : null}
                </div>

                <FormActionBar mode="create" processing={checking} onCancel={handleCancel}>
                    {!preview ? (
                        <Button
                            type="button"
                            className={formActionSubmitClass}
                            variant="outline"
                            disabled={!file || checking}
                            onClick={handleCheck}
                        >
                            {checking ? (
                                <LoaderCircleIcon className="size-3.5 animate-spin" />
                            ) : (
                                <CheckIcon className="size-3.5" />
                            )}
                            {checking ? t('top_up_cards.import_csv.checking') : t('top_up_cards.import_csv.check')}
                        </Button>
                    ) : null}
                    {preview ? (
                        <Button
                            type="submit"
                            className={formActionSubmitClass}
                            variant="primary"
                            disabled={!file || checkedFile !== file || checking}
                        >
                            {t('top_up_cards.import_csv.import')}
                        </Button>
                    ) : null}
                </FormActionBar>
            </form>
        </FormDialog>
    );
}
