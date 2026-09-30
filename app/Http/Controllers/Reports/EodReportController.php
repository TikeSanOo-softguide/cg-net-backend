<?php

namespace App\Http\Controllers\Reports;

use App\Enums\LedgerTransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EodReportController extends Controller
{
    public function index(Request $request): Response
    {
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();

        // Only customer-liability lines (wallet_id set) count toward wallet money movement for EOD.
        $entriesForDate = LedgerEntry::query()
            ->whereNotNull('wallet_id')
            ->whereDate('created_at', $date);

        $credits = (clone $entriesForDate)->sum('credit');
        $debits = (clone $entriesForDate)->sum('debit');

        $transactionStatuses = LedgerTransaction::query()
            ->whereDate('created_at', $date)
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total);

        $breakdown = LedgerEntry::query()
            ->join('ledger_transactions', 'ledger_transactions.id', '=', 'ledger_entries.ledger_transaction_id')
            ->whereNotNull('ledger_entries.wallet_id')
            ->whereDate('ledger_entries.created_at', $date)
            ->select('ledger_transactions.type as transaction_type')
            ->selectRaw('COUNT(*) as entries')
            ->selectRaw('SUM(ledger_entries.credit) as credits')
            ->selectRaw('SUM(ledger_entries.debit) as debits')
            ->groupBy('ledger_transactions.type')
            ->orderBy('ledger_transactions.type')
            ->get()
            ->map(
                fn ($row): array => [
                    'type' => $row->transaction_type,
                    'entries' => (int) $row->entries,
                    'credits' => (int) $row->credits,
                    'debits' => (int) $row->debits,
                ],
            )
            ->values();

        $recentEntries = LedgerEntry::query()
            ->with([
                'wallet.user:id,name',
                'ledgerTransaction:id,transaction_no,type',
            ])
            ->whereNotNull('wallet_id')
            ->whereDate('created_at', $date)
            ->latest('created_at')
            ->latest('id')
            ->limit(100)
            ->get([
                'id',
                'ledger_transaction_id',
                'wallet_id',
                'debit',
                'credit',
                'balance_after',
                'created_at',
            ])
            ->map(
                fn (LedgerEntry $entry): array => [
                    'id' => $entry->id,
                    'transaction_no' => $entry->ledgerTransaction?->transaction_no,
                    'customer_id' => $entry->wallet?->user?->id,
                    'customer' => $entry->wallet?->user?->name,
                    'transaction_type' => $entry->ledgerTransaction?->type?->value,
                    'direction' => $entry->isCredit() ? 'credit' : 'debit',
                    'amount' => $entry->amount(),
                    'balance_after' => (int) $entry->balance_after,
                    'created_at' => $entry->created_at,
                ],
            )
            ->values();

        return Inertia::render('Reports/Eod/Index', [
            'date' => $date,
            'summary' => [
                'entries' => (clone $entriesForDate)->count(),
                'credits' => (int) $credits,
                'debits' => (int) $debits,
                'net' => (int) $credits - (int) $debits,
                'completed_transactions' => $transactionStatuses[LedgerTransactionStatus::Completed->value] ?? 0,
                'pending_transactions' =>
                    ($transactionStatuses[LedgerTransactionStatus::Pending->value] ?? 0) +
                    ($transactionStatuses[LedgerTransactionStatus::Processing->value] ?? 0),
                'failed_transactions' => $transactionStatuses[LedgerTransactionStatus::Failed->value] ?? 0,
            ],
            'breakdown' => $breakdown,
            'recentEntries' => $recentEntries,
        ]);
    }
}
