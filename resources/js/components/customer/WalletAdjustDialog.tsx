import { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { SlidersHorizontalIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormDialog } from '@/components/FormDialog';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlStateClass } from '@/lib/form-control';
import { TOP_UP_CARD_CURRENCY } from '@/lib/top-up-cards';
import { cn } from '@/lib/utils';

export type WalletAdjustDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    customerId: number;
    customerName?: string | null;
    relatedTransaction?: {
        id: number;
        transaction_no: string;
    } | null;
};

type AdjustForm = {
    direction: 'credit' | 'debit';
    amount: string;
    note: string;
    related_transaction_id: number | null;
};

type AdjustBagErrors = Partial<Record<keyof AdjustForm | 'wallet', string>>;

export function WalletAdjustDialog({
    open,
    onOpenChange,
    customerId,
    customerName,
    relatedTransaction = null,
}: WalletAdjustDialogProps) {
    const { t } = useTranslation();
    const form = useForm<AdjustForm>({
        direction: 'credit',
        amount: '',
        note: '',
        related_transaction_id: relatedTransaction?.id ?? null,
    });
    const bagErrors = form.errors as AdjustBagErrors;

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            direction: 'credit',
            amount: '',
            note: '',
            related_transaction_id: relatedTransaction?.id ?? null,
        });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset only when dialog opens / related txn changes
    }, [open, relatedTransaction?.id]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            direction: data.direction,
            amount: Number(data.amount),
            note: data.note.trim(),
            related_transaction_id: data.related_transaction_id,
        }));

        form.post(`/customers/${customerId}/wallet/adjust`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('customers.wallet_adjust.title')}
            description={
                relatedTransaction
                    ? `${t('customers.wallet_adjust.description_related')} ${relatedTransaction.transaction_no}`
                    : customerName
                      ? `${t('customers.wallet_adjust.description')} (${customerName})`
                      : t('customers.wallet_adjust.description')
            }
            icon={SlidersHorizontalIcon}
            size="md"
        >
            <form className="flex min-h-0 flex-1 flex-col" onSubmit={submit}>
                <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-5">
                    {relatedTransaction ? (
                        <div className="rounded-[8px] border border-border/70 bg-muted/30 px-3 py-2.5 text-[13px]">
                            <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                {t('customers.wallet_adjust.related_transaction')}
                            </p>
                            <p className="mt-0.5 font-mono font-medium">{relatedTransaction.transaction_no}</p>
                        </div>
                    ) : null}

                    <FormField
                        label={t('customers.wallet_adjust.direction')}
                        htmlFor="wallet-adjust-direction"
                        error={form.errors.direction}
                        required
                    >
                        <Select
                            value={form.data.direction}
                            onValueChange={(value) =>
                                form.setData('direction', value === 'debit' ? 'debit' : 'credit')
                            }
                        >
                            <SelectTrigger
                                id="wallet-adjust-direction"
                                className={cn(formControlStateClass(form.errors.direction ? 'error' : 'idle'))}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="credit">{t('customers.wallet_adjust.credit')}</SelectItem>
                                <SelectItem value="debit">{t('customers.wallet_adjust.debit')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        label={`${t('customers.wallet_adjust.amount')} (${TOP_UP_CARD_CURRENCY})`}
                        htmlFor="wallet-adjust-amount"
                        error={form.errors.amount}
                        required
                    >
                        <Input
                            id="wallet-adjust-amount"
                            type="number"
                            min={1}
                            step={1}
                            inputMode="numeric"
                            value={form.data.amount}
                            onChange={(event) => form.setData('amount', event.target.value)}
                            className={cn(formControlStateClass(form.errors.amount ? 'error' : 'idle'))}
                        />
                    </FormField>

                    <FormField
                        label={t('customers.wallet_adjust.note')}
                        htmlFor="wallet-adjust-note"
                        error={form.errors.note}
                        required
                    >
                        <Textarea
                            id="wallet-adjust-note"
                            rows={4}
                            value={form.data.note}
                            onChange={(event) => form.setData('note', event.target.value)}
                            className={cn(formControlStateClass(form.errors.note ? 'error' : 'idle'))}
                            placeholder={t('customers.wallet_adjust.note_placeholder')}
                        />
                    </FormField>

                    {bagErrors.wallet || bagErrors.related_transaction_id ? (
                        <p className="text-[12px] text-danger">
                            {bagErrors.wallet ?? bagErrors.related_transaction_id}
                        </p>
                    ) : null}
                </div>

                <FormActionBar
                    mode="create"
                    processing={form.processing}
                    onCancel={() => onOpenChange(false)}
                    submitLabel={t('customers.wallet_adjust.submit')}
                />
            </form>
        </FormDialog>
    );
}
