import { useEffect, useRef, useState, type ChangeEvent } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { CircleAlertIcon, UserRoundIcon, XIcon } from 'lucide-react';

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
import { FormDialog } from '@/components/FormDialog';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { Spinner } from '@/components/ui/spinner';
import { TopUpCardOfficeAssignmentForm } from '@/components/top-up-cards/TopUpCardOfficeAssignmentForm';
import { TopUpCardOfficeCardTable } from '@/components/top-up-cards/TopUpCardOfficeCardTable';
import type { TopUpCardRow } from '@/lib/top-up-cards';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';

type OfficeRow = { id: number; name: string; cd: number; address: string; top_up_cards_count: number };
type BatchRow = { id: number; batch_no: string; expires_at: string | null; available_cards_count: number; available_points: number[] };
type ImportProgress = {
    status: 'processing' | 'completed' | 'failed' | null;
    message: string | null;
    total_cards: number;
    completed_cards: number;
    completed_chunks: number;
    total_chunks: number;
};

type Props = {
    offices: OfficeRow[];
    cards: Paginated<TopUpCardRow>;
    batches: BatchRow[];
    points: number[];
    importProgress: ImportProgress | null;
    filters: { search: string; card_search: string; batch: string; office: string; status: string; amount: string };
};

type PageProps = {
    flash?: {
        import_error?: { key: string; replace?: Record<string, string | number> } | null;
        import_error_token?: string | null;
    };
};

type ImportError = {
    key: string;
    replace?: Record<string, string | number>;
};

function visitOfficeAssign(filters: Props['filters']) {
    router.get('/top-up-cards/office-assign', {
        search: filters.search || undefined,
        card_search: filters.card_search || undefined,
        batch: filters.batch,
        office: filters.office || undefined,
        status: filters.status,
        amount: filters.amount,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

export default function OfficeAssignPage({ offices, cards, batches, points, importProgress, filters }: Props) {
    const { t } = useTranslation();
    const { flash } = usePage<PageProps>().props;
    const [assigning, setAssigning] = useState(false);
    const [importError, setImportError] = useState<ImportError | null>(null);
    const [batchFilter, setBatchFilter] = useState(filters.batch);
    const [officeFilter, setOfficeFilter] = useState(filters.office);
    const [statusFilter, setStatusFilter] = useState(filters.status);
    const [pointFilter, setPointFilter] = useState(filters.amount);
    const [cardSearch, setCardSearch] = useState(filters.card_search);
    const debounce = useRef<number>(0);
    const importInput = useRef<HTMLInputElement>(null);
    const pollInFlight = useRef(false);
    const importErrorMessage = importError
        ? t(importError.key).replace(/:([a-z_]+)/g, (_, key: string) => String(importError.replace?.[key] ?? `:${key}`))
        : null;

    useEffect(() => {
        setCardSearch(filters.card_search);
        setBatchFilter(filters.batch);
        setOfficeFilter(filters.office);
        setStatusFilter(filters.status);
        setPointFilter(filters.amount);
    }, [filters.card_search, filters.batch, filters.office, filters.status, filters.amount]);

    useEffect(() => {
        if (flash?.import_error) {
            setImportError(flash.import_error);
        }
    }, [flash?.import_error_token]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

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
                only: ['cards', 'batches', 'points', 'importProgress'],
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

    const refreshCards = (nextBatch: string, nextOffice: string, nextStatus: string, nextPoint: string, nextSearch = cardSearch, debounceSearch = false) => {
        setCardSearch(nextSearch);
        setBatchFilter(nextBatch);
        setOfficeFilter(nextOffice);
        setStatusFilter(nextStatus);
        setPointFilter(nextPoint);
        window.clearTimeout(debounce.current);
        if (debounceSearch) {
            debounce.current = window.setTimeout(() => visitOfficeAssign({ ...filters, card_search: nextSearch, batch: nextBatch, office: nextOffice, status: nextStatus, amount: nextPoint }), 300);
            return;
        }
        visitOfficeAssign({ ...filters, card_search: nextSearch, batch: nextBatch, office: nextOffice, status: nextStatus, amount: nextPoint });
    };

    const importCards = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) return;

        router.post('/top-up-cards/offices/import', { file, return: 'office-assign' }, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => setImportError(null),
            onFinish: () => {
                if (importInput.current) importInput.current.value = '';
            },
        });
    };

    return (
        <>
            <Head title={t('top_up_cards.office.assign_cards')} />
            <PageContent>
                <PageHeader />
                <input ref={importInput} type="file" accept=".csv,text/csv" className="hidden" onChange={importCards} />
                {importProgress?.status === 'processing' ? (
                    <div
                        role="status"
                        className="mb-3 ml-auto flex w-fit max-w-full flex-wrap items-center justify-end gap-x-2 gap-y-1 border-b border-border px-1 py-2 text-right text-[14px] text-foreground"
                    >
                        <Spinner size="xs" />
                        <span className="font-semibold">{t('top_up_cards.importing_csv')}</span>
                        {importProgress.total_cards > 0 ? (
                            <>
                                <span className="font-mono font-semibold tabular-nums text-foreground">
                                    {importProgress.completed_cards.toLocaleString()}/{importProgress.total_cards.toLocaleString()} cards
                                </span>
                                <span className="text-[12px] font-medium text-foreground/80">
                                    {importProgress.completed_chunks}/{importProgress.total_chunks} chunks
                                </span>
                            </>
                        ) : null}
                    </div>
                ) : null}
                <TopUpCardOfficeCardTable
                    cards={cards.data}
                    pagination={cards}
                    offices={offices}
                    batches={batches}
                    points={points}
                    search={cardSearch}
                    batchFilter={batchFilter}
                    officeFilter={officeFilter}
                    statusFilter={statusFilter}
                    pointFilter={pointFilter}
                    onSearchChange={(value) => refreshCards(batchFilter, officeFilter, statusFilter, pointFilter, value, true)}
                    onBatchChange={(value) => refreshCards(value === 'all' ? '' : value, officeFilter, statusFilter, pointFilter)}
                    onOfficeChange={(value) => refreshCards(batchFilter, value === 'all' ? '' : value, statusFilter, pointFilter)}
                    onStatusChange={(value) => refreshCards(batchFilter, officeFilter, value === 'all' ? '' : value, pointFilter)}
                    onPointChange={(value) => refreshCards(batchFilter, officeFilter, statusFilter, value === 'all' ? '' : value)}
                    onAssign={() => setAssigning(true)}
                    onImport={() => importInput.current?.click()}
                />
            </PageContent>
            <FormDialog open={assigning} onOpenChange={setAssigning} title={t('top_up_cards.office.assign_cards')} description={t('top_up_cards.office.description')} icon={UserRoundIcon}>
                {assigning ? <TopUpCardOfficeAssignmentForm offices={offices} batches={batches} batchId={batchFilter} onClose={() => setAssigning(false)} onSuccess={() => setAssigning(false)} /> : null}
            </FormDialog>
            <Dialog open={importError !== null} onOpenChange={(open) => { if (!open) setImportError(null); }}>
                <DialogContent className="w-[min(100%-2rem,520px)]">
                    <DialogHeader className="text-left">
                        <DialogTitle className="flex items-center gap-2 text-danger">
                            <CircleAlertIcon className="size-5" />
                            {t('top_up_cards.import_errors.title')}
                        </DialogTitle>
                        <DialogDescription className="whitespace-pre-wrap text-left text-foreground">
                            {importErrorMessage}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="destructive">
                                <XIcon className="size-4" />
                                Close
                            </Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
