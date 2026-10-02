<?php

namespace App\Http\Controllers\Transaction;

use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletActorType;
use App\Exports\TransactionExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Transaction\TransactionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class TransactionController extends Controller
{
    public function index(Request $request, TransactionService $transactions): Response
    {
        $filters = $transactions->filters($request);

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions->paginate($filters),
            'filters' => $filters,
            'filterOptions' => [
                'actor_types' => array_column(WalletActorType::cases(), 'value'),
                'types' => array_column(LedgerTransactionType::cases(), 'value'),
                'statuses' => array_column(LedgerTransactionStatus::cases(), 'value'),
            ],
            'scope' => $filters['customer_id'] ? 'customer' : 'global',
        ]);
    }

    public function export(Request $request, TransactionService $transactions)
    {
        return $this->download($transactions, $transactions->filters($request));
    }

    public function exportCustomer(Request $request, User $customer, TransactionService $transactions)
    {
        $filters = $transactions->filters($request);
        $filters['customer_id'] = $customer->id;

        return $this->download($transactions, $filters, 'customer-transactions.xlsx');
    }

    private function download(TransactionService $transactions, array $filters, ?string $filename = null)
    {
        return Excel::download(
            new TransactionExport($transactions->query($filters)),
            $filename ?? 'transactions-' . now()->format('Ymd-His') . '.xlsx',
        );
    }
}
