import { useState, type FormEvent } from 'react';
import { router } from '@inertiajs/react';

import { FormActionBar } from '@/components/FormActionBar';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDate } from '@/lib/utils';

type OfficeRow = { id: number; name: string; address: string; top_up_cards_count: number };
type BatchRow = { id: number; batch_no: string; expires_at: string | null; available_cards_count: number; available_points: number[] };

type TopUpCardOfficeAssignmentFormProps = {
    offices: OfficeRow[];
    batches: BatchRow[];
    batchId: string;
    onClose: () => void;
    onSuccess: () => void;
};

export function TopUpCardOfficeAssignmentForm({
    offices,
    batches,
    batchId,
    onClose,
    onSuccess,
}: TopUpCardOfficeAssignmentFormProps) {
    const { t } = useTranslation();
    const [selectedPoint, setSelectedPoint] = useState('');
    const [selectedOffice, setSelectedOffice] = useState('');
    const selectedBatch = batches.find((batch) => String(batch.id) === batchId);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.patch('/top-up-cards/assign-office', {
            batch_id: Number(batchId),
            amount: Number(selectedPoint),
            office_id: selectedOffice && selectedOffice !== 'none' ? Number(selectedOffice) : null,
        }, {
            preserveScroll: true,
            onSuccess,
        });
    };

    return (
        <form className="flex min-h-0 flex-1 flex-col" onSubmit={submit}>
            <div className="grid grid-cols-1 gap-4 px-5 py-5 sm:grid-cols-2">
                <FormField label={t('top_up_cards.batch_no')} htmlFor="assign-batch">
                    <div id="assign-batch" className="flex h-9 items-center rounded-md border border-input bg-muted px-3 text-sm">
                        {selectedBatch ? `#${selectedBatch.id} ${selectedBatch.batch_no}` : '—'}
                    </div>
                </FormField>
                <FormField label={t('top_up_cards.expires_at')} htmlFor="assign-expires-at">
                    <div id="assign-expires-at" className="flex h-9 items-center rounded-md border border-input bg-muted px-3 text-sm">
                        {formatDate(selectedBatch?.expires_at) ?? '—'}
                    </div>
                </FormField>
                <div>
                    <FormField label={t('top_up_cards.point')} htmlFor="assign-point">
                        <Select value={selectedPoint} onValueChange={setSelectedPoint} disabled={!selectedBatch}>
                            <SelectTrigger id="assign-point"><SelectValue placeholder={t('top_up_cards.point')} /></SelectTrigger>
                            <SelectContent>
                                {selectedBatch?.available_points.map((point) => (
                                    <SelectItem key={point} value={String(point)}>{point}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>
                <div>
                    <FormField label={t('top_up_cards.office.name')} htmlFor="assign-office">
                        <Select value={selectedOffice} onValueChange={setSelectedOffice}>
                            <SelectTrigger id="assign-office"><SelectValue placeholder={t('top_up_cards.office.select')} /></SelectTrigger>
                            <SelectContent>
                                {offices.map((office) => <SelectItem key={office.id} value={String(office.id)}>{office.name}</SelectItem>)}
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>
            </div>
            <FormActionBar onCancel={onClose}>
                <Button type="submit" size="sm" variant="primary" disabled={!selectedBatch || !selectedPoint || !selectedOffice}>
                    {t('top_up_cards.office.assign')}
                </Button>
            </FormActionBar>
        </form>
    );
}
