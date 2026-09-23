<?php

namespace App\Http\Controllers\Transaction;

use App\Enums\WalletActorType;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Exports\TransactionExport;
use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Inertia\Inertia;
use Inertia\Response;

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
                'types' => array_column(WalletTransactionType::cases(), 'value'),
                'statuses' => array_column(WalletTransactionStatus::cases(), 'value'),
            ],
            'scope' => $filters['customer_id'] ? 'customer' : 'global',
        ]);
    }

    public function export(Request $request, TransactionService $transactions)
    {
        return Excel::download(
            new TransactionExport($transactions->query($transactions->filters($request))),
            'transactions-' . now()->format('Ymd-His') . '.xlsx',
        );
    }
}
