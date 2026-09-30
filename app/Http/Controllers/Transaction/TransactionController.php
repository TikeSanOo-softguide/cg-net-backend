<?php

namespace App\Http\Controllers\Transaction;

use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletActorType;
use App\Exports\TransactionExport;
use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use App\Support\ReturnTo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class TransactionController extends Controller
{
    public function index(Request $request, TransactionService $transactions): Response
    {
        $filters = $transactions->filters($request);

        ReturnTo::captureReferer($request, 'transactions.index');

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions->paginate($filters),
            'filters' => $filters,
            'filterOptions' => [
                'actor_types' => array_column(WalletActorType::cases(), 'value'),
                'types' => array_column(LedgerTransactionType::cases(), 'value'),
                'statuses' => array_column(LedgerTransactionStatus::cases(), 'value'),
            ],
            'scope' => $filters['customer_id'] ? 'customer' : 'global',
            ...ReturnTo::prop('transactions.index'),
        ]);
    }

    public function export(Request $request, TransactionService $transactions)
    {
        return Excel::download(
            new TransactionExport($transactions->query($transactions->filters($request))),
            'transactions-'.now()->format('Ymd-His').'.xlsx',
        );
    }
}
