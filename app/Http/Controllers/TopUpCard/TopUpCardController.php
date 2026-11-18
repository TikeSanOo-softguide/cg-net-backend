<?php

namespace App\Http\Controllers\TopUpCard;

use App\Enums\ActivityLogEvent;
use App\Enums\ActivityLogName;
use App\Enums\TopUpCardStatus;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Office;
use App\Models\TopUpCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TopUpCardController extends Controller
{
    public function block(Request $request, TopUpCard $topUpCard): RedirectResponse
    {
        if ($topUpCard->status !== TopUpCardStatus::Active) {
            return back()->with('error', 'top_up_cards.cannot_block');
        }

        $topUpCard->update(['status' => TopUpCardStatus::Blocked]);

        activity(ActivityLogName::TopUpCards->value)
            ->causedBy($request->user())
            ->performedOn($topUpCard)
            ->event(ActivityLogEvent::Blocked->value)
            ->log('top_up_card_blocked');

        return back()->with('success', 'top_up_cards.blocked');
    }

    public function cardHistory(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $amount = $request->string('amount')->toString();
        $office = $request->string('office')->toString();
        $batch = $request->string('batch')->toString();
        if ($batch === 'all') {
            $batch = '';
        }
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $sortable = ['serial_no', 'amount', 'status', 'created_at'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $validStatuses = array_column(TopUpCardStatus::cases(), 'value');
        if (! in_array($status, $validStatuses, true)) {
            $status = '';
        }

        $batches = Batch::query()
            ->select(['id', 'batch_no'])
            ->orderByDesc('id')
            ->get();

        if ($batch === '' && ! $request->query->has('batch')) {
            $batch = (string) (
                Batch::query()
                    ->where('metadata->import_status', 'completed')
                    ->orderByDesc('id')
                    ->value('id') ?? ''
            );
        }

        $cards = TopUpCard::query()
            ->with([
                'redeemedBy:id,name,phone',
                'batch:id,batch_no,status',
                'office:id,name_en,name_mm,name_zh,cd',
                'ledgerTransaction:id,transaction_no',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereLike('serial_no', "%{$search}%");
            })
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($amount !== '' && is_numeric($amount), function ($query) use ($amount): void {
                $query->where('amount', $amount);
            })
            ->when(ctype_digit($office), function ($query) use ($office): void {
                $query->where('office_id', (int) $office);
            })
            ->when($batch !== '', function ($query) use ($batch): void {
                if (ctype_digit($batch)) {
                    $query->where('batch_id', (int) $batch);
                } else {
                    $query->whereHas('batch', function ($query) use ($batch): void {
                        $query->where('batch_no', $batch);
                    });
                }
            })
            ->when($from !== '', function ($query) use ($from): void {
                $query->whereDate('created_at', '>=', $from);
            })
            ->when($to !== '', function ($query) use ($to): void {
                $query->whereDate('created_at', '<=', $to);
            })
            ->orderBy($sort, $direction)
            ->paginate(100)
            ->withQueryString()
            ->appends(['batch' => $batch === '' ? 'all' : $batch])
            ->through(fn (TopUpCard $card) => $this->payload($card));

        return Inertia::render('TopUpCards/CardHistory', [
            'cards' => $cards,
            'generated' => $request->session()->get('top_up_card_export_batch', []),
            'amounts' => $this->amountOptions(),
            'offices' => $this->officeOptions(),
            'batches' => $batches,
            'stats' => $this->stats($status, $amount, $batch, $office),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'amount' => $amount,
                'batch' => $batch,
                'office' => $office,
                'from' => $from,
                'to' => $to,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TopUpCard $card): array
    {
        $status = $card->status;

        if ($status === TopUpCardStatus::Active && $card->expires_at?->copy()->endOfDay()->isPast()) {
            $status = TopUpCardStatus::Expired;
        }

        return [
            'id' => $card->id,
            'serial_no' => $card->serial_no,
            'amount' => $card->amount,
            'status' => $status->value,
            'activated_at' => $card->activated_at?->toDateString(),
            'expires_at' => $card->expires_at?->toDateString(),
            'redeemed_at' => $card->redeemed_at?->toIso8601String(),
            'redeemed_by_id' => $card->redeemed_by,
            'redeemed_by' => $card->redeemedBy?->name,
            'redeemed_by_phone' => $card->redeemedBy?->phone,
            'batch_no' => $card->batch?->batch_no,
            'batch_status' => $card->batch?->status,
            'office' => $card->office
                ? [
                    'name' => $card->office->localizedName(),
                    'code' => (string) $card->office->cd,
                ]
                : null,
            'transaction_id' => $card->ledgerTransaction?->id,
            'transaction_no' => $card->ledgerTransaction?->transaction_no,
        ];
    }

    /**
     * @return list<array{id: int, name: string, code: string}>
     */
    private function officeOptions(): array
    {
        return Office::query()
            ->select(['id', 'name_en', 'name_mm', 'name_zh', 'cd'])
            ->orderBy('name_en')
            ->get()
            ->map(
                fn (Office $office): array => [
                    'id' => (int) $office->id,
                    'name' => $office->localizedName(),
                    'code' => (string) $office->cd,
                ],
            )
            ->all();
    }

    /**
     * @return list<int>
     */
    private function amountOptions(): array
    {
        return [50, 100, 150, 500];
    }

    private function stats(string $status, string $amount, string $batch, string $office): array
    {
        $cards = TopUpCard::query()
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($amount !== '' && is_numeric($amount), function ($query) use ($amount): void {
                $query->where('amount', $amount);
            })
            ->when(ctype_digit($office), function ($query) use ($office): void {
                $query->where('office_id', (int) $office);
            })
            ->when($batch !== '', function ($query) use ($batch): void {
                if (ctype_digit($batch)) {
                    $query->where('batch_id', (int) $batch);
                } else {
                    $query->whereHas('batch', function ($query) use ($batch): void {
                        $query->where('batch_no', $batch);
                    });
                }
            });

        return [
            'total' => (clone $cards)->count(),
            'pending' => (clone $cards)->where('status', TopUpCardStatus::Pending)->count(),
            'active' => (clone $cards)->where('status', TopUpCardStatus::Active)->count(),
            'used' => (clone $cards)->where('status', TopUpCardStatus::Used)->count(),
            'expired' => (clone $cards)->where('status', TopUpCardStatus::Expired)->count(),
            'blocked' => (clone $cards)->where('status', TopUpCardStatus::Blocked)->count(),
        ];
    }
}
