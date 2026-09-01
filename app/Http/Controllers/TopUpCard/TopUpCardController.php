<?php

namespace App\Http\Controllers\TopUpCard;

use App\Enums\TopUpCardStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\TopUpCard\GenerateTopUpCardsRequest;
use App\Models\TopUpCard;
use App\Support\GeneratesTopUpCards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TopUpCardController extends Controller
{
    /**
     * @var list<int>
     */
    public const Presets = [1000, 3000, 5000, 10000, 20000, 50000];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->toString();
        $amount = $request->string('amount')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $sortable = ['serial_no', 'amount', 'status', 'expires_at', 'redeemed_at', 'created_at'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $cards = TopUpCard::query()
            ->with('redeemedBy:id,name,phone')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('serial_no', 'like', '%'.$search.'%');
            })
            ->when($status !== '' && in_array($status, array_column(TopUpCardStatus::cases(), 'value'), true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($amount !== '' && is_numeric($amount), function ($query) use ($amount): void {
                $query->where('amount', $amount);
            })
            ->when($from !== '', function ($query) use ($from): void {
                $query->whereDate('expires_at', '>=', $from);
            })
            ->when($to !== '', function ($query) use ($to): void {
                $query->whereDate('expires_at', '<=', $to);
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (TopUpCard $card) => $this->payload($card));

        return Inertia::render('TopUpCards/Generate', [
            'cards' => $cards,
            'generated' => $request->session()->get('top_up_card_export_batch', []),
            'presets' => self::Presets,
            'amounts' => $this->amountOptions(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'amount' => $amount,
                'from' => $from,
                'to' => $to,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function store(GenerateTopUpCardsRequest $request): RedirectResponse
    {
        $created = GeneratesTopUpCards::run(
            $request->validated('amounts'),
            $request->date('expires_at')->toDateString(),
            $request->user(),
        );

        $request->session()->put('top_up_card_export_batch', $created);

        return redirect()
            ->route('top-up-cards.batch')
            ->with('success', 'top_up_cards.generated')
            ->with('deleted_count', count($created));
    }

    public function export(Request $request): StreamedResponse
    {
        $batch = $request->session()->get('top_up_card_export_batch', []);

        abort_if($batch === [], 404);

        $filename = 'top-up-cards-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($batch): void {
            $stream = fopen('php://output', 'w');

            if ($stream === false) {
                return;
            }

            fputcsv($stream, ['serial_no', 'pin', 'amount', 'expires_at', 'status']);

            foreach ($batch as $card) {
                fputcsv($stream, [
                    $card['serial_no'] ?? '',
                    $card['pin'] ?? '',
                    $card['amount'] ?? '',
                    $card['expires_at'] ?? '',
                    $card['status'] ?? '',
                ]);
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function void(Request $request, TopUpCard $topUpCard): RedirectResponse
    {
        if ($topUpCard->status !== TopUpCardStatus::Valid) {
            return back()->with('error', 'top_up_cards.cannot_void');
        }

        $topUpCard->update(['status' => TopUpCardStatus::Invalid]);

        activity('top-up-cards')
            ->causedBy($request->user())
            ->performedOn($topUpCard)
            ->event('voided')
            ->log('top_up_card_voided');

        return back()->with('success', 'top_up_cards.voided');
    }

    public function history(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $amount = $request->string('amount')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $sortable = ['serial_no', 'amount', 'redeemed_at'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'redeemed_at';
        }

        $cards = TopUpCard::query()
            ->with('redeemedBy:id,name,phone')
            ->where('status', TopUpCardStatus::Redeemed)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('serial_no', 'like', '%'.$search.'%')
                        ->orWhereHas('redeemedBy', function ($query) use ($search): void {
                            $query->where('name', 'like', '%'.$search.'%')
                                ->orWhere('phone', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($amount !== '' && is_numeric($amount), function ($query) use ($amount): void {
                $query->where('amount', $amount);
            })
            ->when($from !== '', function ($query) use ($from): void {
                $query->whereDate('redeemed_at', '>=', $from);
            })
            ->when($to !== '', function ($query) use ($to): void {
                $query->whereDate('redeemed_at', '<=', $to);
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (TopUpCard $card) => $this->payload($card));

        $recent = TopUpCard::query()
            ->with('redeemedBy:id,name,phone')
            ->where('status', TopUpCardStatus::Redeemed)
            ->latest('redeemed_at')
            ->limit(8)
            ->get()
            ->map(fn (TopUpCard $card) => $this->payload($card))
            ->values()
            ->all();

        return Inertia::render('TopUpCards/History', [
            'cards' => $cards,
            'recent' => $recent,
            'amounts' => $this->amountOptions(),
            'stats' => $this->historyStats(),
            'filters' => [
                'search' => $search,
                'amount' => $amount,
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

        if ($status === TopUpCardStatus::Valid && $card->expires_at?->copy()->endOfDay()->isPast()) {
            $status = TopUpCardStatus::Invalid;
        }

        return [
            'id' => $card->id,
            'serial_no' => $card->serial_no,
            'amount' => $card->amount,
            'status' => $status->value,
            'expires_at' => $card->expires_at?->toDateString(),
            'redeemed_at' => $card->redeemed_at?->toIso8601String(),
            'redeemed_by_id' => $card->redeemed_by,
            'redeemed_by' => $card->redeemedBy?->name,
            'redeemed_by_phone' => $card->redeemedBy?->phone,
        ];
    }

    /**
     * @return array{total: int, value: string, month: int, customers: int}
     */
    private function historyStats(): array
    {
        $redeemed = TopUpCard::query()->where('status', TopUpCardStatus::Redeemed);

        return [
            'total' => $redeemed->clone()->count(),
            'value' => number_format((float) $redeemed->clone()->sum('amount'), 2, '.', ''),
            'month' => $redeemed->clone()->where('redeemed_at', '>=', now()->startOfMonth())->count(),
            'customers' => (int) $redeemed->clone()
                ->whereNotNull('redeemed_by')
                ->selectRaw('count(distinct redeemed_by) as aggregate')
                ->value('aggregate'),
        ];
    }

    /**
     * @return list<string>
     */
    private function amountOptions(): array
    {
        $stored = TopUpCard::query()
            ->select('amount')
            ->distinct()
            ->orderBy('amount')
            ->pluck('amount')
            ->map(fn ($amount): string => (string) $amount);

        return Collection::make(self::Presets)
            ->map(fn (int $amount): string => number_format($amount, 2, '.', ''))
            ->merge($stored)
            ->map(fn (string $amount): string => number_format((float) $amount, 2, '.', ''))
            ->unique()
            ->values()
            ->all();
    }
}
