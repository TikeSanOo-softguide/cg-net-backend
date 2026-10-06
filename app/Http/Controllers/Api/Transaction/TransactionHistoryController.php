<?php

namespace App\Http\Controllers\Api\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionHistoryResource;
use App\Models\LedgerTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionHistoryController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $transactions = LedgerTransaction::query()
            ->whereHas('wallet', fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ->with(['entries' => fn (HasMany $query) => $query->whereNotNull('wallet_id')])
            ->latest('created_at')
            ->latest('id')
            ->cursorPaginate($validated['per_page'] ?? 20, ['*'], 'cursor');

        return TransactionHistoryResource::collection($transactions);
    }
}
