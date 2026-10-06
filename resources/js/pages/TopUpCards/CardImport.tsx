import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { CircleAlertIcon, XIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { Spinner } from '@/components/ui/spinner';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';
import { TopUpCardImportTable } from '@/components/top-up-cards/TopUpCardImportTable';
import type { TopUpCardCsvPreview } from '@/components/top-up-cards/TopUpCardImportDialog';

type BatchRow = {
    id: number;
    batch_no: string;
};

type BatchTableRow = {
    id: number;
    batch_no: string;
    total_value: number;
    quantity: number;
    status: string;
    expires_at: string | null;
    assigned_cards_count: number;
};

type ImportProgress = {
    status: 'processing' | 'completed' | 'failed' | null;
    message: string | null;
    message_replace?: Record<string, string | number>;
    total_cards: number;
    completed_cards: number;
    completed_chunks: number;
    total_chunks: number;
};

type Props = {
    batchPage: Paginated<BatchTableRow>;
    batches: BatchRow[];
    importProgress: ImportProgress | null;
    filters: {
        batch: string;
        status: string;
    };
};

type PageProps = {
    flash?: {
        import_error?: {
            key: string;
            replace?: Record<string, string | number>;
        } | null;
        import_error_token?: string | null;
    };
};

type ImportError = {
    key: string;
    replace?: Record<string, string | number>;
};

function visitCardImport(filters: Props['filters']) {
    router.get(
        '/top-up-cards/card-import',
        {
            batch: filters.batch,
            status: filters.status,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

export default function CardImportPage({ batchPage, batches, importProgress, filters }: Props) {
    const { t } = useTranslation();
    const { flash } = usePage<PageProps>().props;
    const [importError, setImportError] = useState<ImportError | null>(null);
    const [batchFilter, setBatchFilter] = useState(filters.batch);
    const [statusFilter, setStatusFilter] = useState(filters.status);
    const pollInFlight = useRef(false);
    const previousImportStatus = useRef(importProgress?.status);
    const importErrorMessage = importError
        ? t(importError.key).replace(/:([a-z_]+)/g, (_, key: string) => String(importError.replace?.[key] ?? `:${key}`))
        : null;

    useEffect(() => {
        setBatchFilter(filters.batch);
        setStatusFilter(filters.status);
    }, [filters.batch, filters.status]);

    useEffect(() => {
        if (flash?.import_error) {
            setImportError(flash.import_error);
        }
    }, [flash?.import_error_token]);

    useEffect(() => {
        const failedAfterProcessing =
            previousImportStatus.current === 'processing' && importProgress?.status === 'failed';
        previousImportStatus.current = importProgress?.status;

        if (failedAfterProcessing && importProgress.message) {
            setImportError({
                key: importProgress.message,
                replace: importProgress.message_replace,
            });
        }
    }, [importProgress?.status, importProgress?.message]);

    useEffect(() => {
        if (importProgress?.status !== 'processing') {
            return;
        }

        const reloadImport = () => {
            if (pollInFlight.current || document.hidden) {
                return;
            }

            pollInFlight.current = true;
            router.reload({
                only: ['batchPage', 'batches', 'importProgress'],
                preserveUrl: true,
                onFinish: () => {
                    pollInFlight.current = false;
                },
            });
        };

        reloadImport();
        const interval = window.setInterval(reloadImport, 1500);

        return () => {
            window.clearInterval(interval);
            pollInFlight.current = false;
        };
    }, [importProgress?.status]);

    const refreshCards = (nextBatch: string, nextStatus: string) => {
        setBatchFilter(nextBatch);
        setStatusFilter(nextStatus);
        visitCardImport({
            batch: nextBatch,
            status: nextStatus,
        });
    };

    const importCards = (file: File) => {
        router.post(
            '/top-up-cards/cards/import',
            {
                file,
                return: 'card-import',
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => {
                    setImportError(null);
                },
            },
        );
    };

    const checkImport = async (file: File): Promise<TopUpCardCsvPreview> => {
        const formData = new FormData();
        formData.append('file', file);
        const xsrfCookie = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='));
        const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie.slice('XSRF-TOKEN='.length)) : '';
        const response = await fetch('/top-up-cards/offices/validate-import', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
            },
            body: formData,
        });
        const payload = await response.json().catch(() => null);

        if (!response.ok) {
            throw payload?.error ?? { key: 'csv.import_errors.read_failed' };
        }

        return payload as TopUpCardCsvPreview;
    };

    return (
        <>
            <Head title={t('top_up_cards.office.assign_cards')} />
            <PageContent>
                <PageHeader />
                {importProgress?.status === 'processing' ? (
                    <div
                        role="status"
                        className="mb-3 ml-auto flex w-fit max-w-full flex-wrap items-center justify-end gap-x-2 gap-y-1 border-b border-border px-1 py-2 text-right text-[14px] text-foreground"
                    >
                        <Spinner size="xs" />
                        <span className="font-semibold">{t('top_up_cards.import_csv.importing_csv')}</span>
                        {importProgress.total_cards > 0 ? (
                            <>
                                <span className="font-mono font-semibold tabular-nums text-foreground">
                                    {importProgress.completed_cards.toLocaleString()}/
                                    {importProgress.total_cards.toLocaleString()} cards
                                </span>
                                <span className="text-[12px] font-medium text-foreground/80">
                                    {importProgress.completed_chunks}/{importProgress.total_chunks} chunks
                                </span>
                            </>
                        ) : null}
                    </div>
                ) : null}
                <TopUpCardImportTable
                    batchRows={batchPage.data}
                    pagination={batchPage}
                    batches={batches}
                    batchFilter={batchFilter}
                    statusFilter={statusFilter}
                    importProcessing={importProgress?.status === 'processing'}
                    onBatchChange={(value) => refreshCards(value === 'all' ? '' : value, statusFilter)}
                    onStatusChange={(value) => refreshCards(batchFilter, value === 'all' ? '' : value)}
                    onCheckImport={checkImport}
                    onImport={importCards}
                />
            </PageContent>
            <Dialog
                open={importError !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setImportError(null);
                    }
                }}
            >
                <DialogContent className="w-[min(100%-2rem,520px)]">
                    <DialogHeader className="text-left">
                        <DialogTitle className="flex items-center gap-2 text-danger">
                            <CircleAlertIcon className="size-5" />
                            {t('csv.import_errors.title')}
                        </DialogTitle>
                        <DialogDescription className="whitespace-pre-wrap text-left text-foreground">
                            {importErrorMessage}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="destructive">
                                {t('common.close')}
                            </Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
