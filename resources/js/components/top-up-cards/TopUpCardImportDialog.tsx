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
    const [dragging, setDragging] = useState(false);
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
        setDragging(false);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    }, [open]);

    const handleSelectedFile = (selectedFile: File | null) => {
        if (checking) {
            return;
        }

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

            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }

            return;
        }

        setFile(selectedFile);
    };

    const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        handleSelectedFile(event.target.files?.[0] ?? null);
    };

    const handleBrowse = () => {
        if (checking) {
            return;
        }

        fileInputRef.current?.click();
    };

    const handleDragOver = (event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        event.stopPropagation();

        if (!checking) {
            setDragging(true);
        }
    };

    const handleDragEnter = (event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        event.stopPropagation();

        if (!checking) {
            setDragging(true);
        }
    };

    const handleDragLeave = (event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        event.stopPropagation();

        setDragging(false);
    };

    const handleDrop = (event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        event.stopPropagation();

        setDragging(false);

        if (checking) {
            return;
        }

        const droppedFile = event.dataTransfer.files?.[0] ?? null;

        handleSelectedFile(droppedFile);
    };

    const handleDropZoneKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
        if (checking) {
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            handleBrowse();
        }
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
        setError(null);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }

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
                        <label className="text-sm font-medium text-foreground">
                            {t('top_up_cards.import_csv.csv_file')}
                        </label>
                        <div className="relative">
                            <Input
                                ref={fileInputRef}
                                id="top-up-card-csv"
                                type="file"
                                accept=".csv,text/csv"
                                onChange={handleFileChange}
                                aria-invalid={!!error}
                                className="sr-only"
                            />

                            <div
                                role="button"
                                tabIndex={checking ? -1 : 0}
                                aria-disabled={checking}
                                onClick={handleBrowse}
                                onKeyDown={handleDropZoneKeyDown}
                                onDragOver={handleDragOver}
                                onDragEnter={handleDragEnter}
                                onDragLeave={handleDragLeave}
                                onDrop={handleDrop}
                                className={[
                                    'flex min-h-32 w-full flex-col items-center justify-center',
                                    'rounded-[10px] border-2 border-dashed px-6 py-6 text-center',
                                    'transition-all duration-200',

                                    checking ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',

                                    error
                                        ? 'border-danger bg-danger/5'
                                        : dragging
                                          ? 'border-primary bg-primary/15 shadow-[0_10px_28px_rgb(23_50_54/0.10)]'
                                          : 'border-primary/35 bg-[linear-gradient(180deg,hsl(var(--primary)/0.08),hsl(var(--primary)/0.02))] hover:border-primary hover:bg-primary/10 hover:shadow-[0_10px_28px_rgb(23_50_54/0.08)]',
                                ].join(' ')}
                            >
                                <span
                                    className={[
                                        'flex size-10 items-center justify-center rounded-full',
                                        'bg-primary/12 text-primary',
                                        'shadow-[0_0_0_6px_hsl(var(--primary)/0.06)]',
                                        'transition-transform duration-200',

                                        dragging ? 'scale-110' : '',
                                    ].join(' ')}
                                >
                                    <FileUp className="size-5" strokeWidth={1.6} />
                                </span>
                                <p className="mt-2.5 text-[12px] font-medium leading-5 text-foreground">
                                    {dragging
                                        ? 'Drop your CSV file here'
                                        : file
                                          ? file.name
                                          : 'Drag & drop your CSV file here'}
                                </p>
                                <p className="mt-0.5 text-[10px] leading-4 text-muted-foreground">CSV files only</p>
                                <span
                                    className={[
                                        'mt-2.5 inline-flex h-7 items-center justify-center',
                                        'rounded-[4px] bg-primary px-2.5',
                                        'text-[11px] font-medium text-primary-foreground shadow-sm',
                                    ].join(' ')}
                                >
                                    Browse CSV
                                </span>
                            </div>
                        </div>

                        {error ? <p className="text-sm text-danger">{error}</p> : null}
                    </div>

                    {preview ? (
                        <section className="min-h-0 space-y-2">
                            <p className="flex items-center gap-2 text-sm font-medium text-foreground">
                                <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                    <CheckIcon className="size-3.5" strokeWidth={2} />
                                </span>

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
