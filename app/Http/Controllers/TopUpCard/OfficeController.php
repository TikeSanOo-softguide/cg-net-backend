<?php

namespace App\Http\Controllers\TopUpCard;

use App\Enums\TopUpCardStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ImportGeneratedTopUpCardsJob;
use App\Http\Requests\TopUpCard\AssignOfficeRequest;
use App\Http\Requests\TopUpCard\AssignCardsToOfficeRequest;
use App\Http\Requests\TopUpCard\StoreOfficeRequest;
use App\Http\Requests\TopUpCard\UpdateOfficeRequest;
use App\Models\Office;
use App\Models\Batch;
use App\Models\TopUpCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OfficeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $officeQuery = Office::query()
            ->withCount('topUpCards')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereLike('name', '%' . $search . '%')->orWhereLike('address', '%' . $search . '%');
            })
            ->orderBy('name');
        $offices = $officeQuery->paginate(15)->withQueryString();
        $officeCds = Office::query()->pluck('cd')->map(fn($cd): string => (string) $cd)->values();
        return Inertia::render('TopUpCards/Office', [
            'offices' => $offices,
            'officeCds' => $officeCds,
            'filters' => ['search' => $search],
        ]);
    }

    public function officeAssign(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $cardSearch = trim((string) $request->string('card_search'));
        $batch = $request->has('batch')
            ? $request->string('batch')->toString()
            : (string) $request->session()->get('top_up_card_office_batch', '');
        $officeFilter = $request->string('office')->toString();
        $status = $request->string('status')->toString();
        $amount = $request->string('amount')->toString();
        $offices = Office::query()
            ->withCount('topUpCards')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereLike('name', '%' . $search . '%')->orWhereLike('address', '%' . $search . '%');
            })
            ->orderBy('name')
            ->get();

        $cardQuery = TopUpCard::query()
            ->select('top_up_card.*')
            ->with('office:id,name')
            ->leftJoin('batches', 'batches.id', '=', 'top_up_card.batch_id')
            ->where('top_up_card.status', '!=', TopUpCardStatus::Pending)
            ->when($cardSearch !== '', fn($query) => $query->whereLike('serial_no', '%' . $cardSearch . '%'))
            ->when($batch !== '', fn($query) => $query->where('batch_id', (int) $batch))
            ->when($officeFilter === 'unassigned', fn($query) => $query->whereNull('office_id'))
            ->when($officeFilter !== '' && $officeFilter !== 'unassigned', fn($query) => $query->where('office_id', (int) $officeFilter))
            ->when($amount !== '' && is_numeric($amount), fn($query) => $query->where('top_up_card.amount', $amount))
            ->when(
                in_array($status, array_column(TopUpCardStatus::cases(), 'value'), true),
                fn($query) => $query->where('top_up_card.status', $status),
            )
            ->orderBy('batches.batch_no')
            ->orderBy('top_up_card.serial_no');
        $cards = $cardQuery
            ->paginate(100)
            ->withQueryString()
            ->through(fn(TopUpCard $card) => $this->cardPayload($card));

        $batches = Batch::query()
            ->select(['id', 'batch_no', 'expires_at'])
            ->whereHas('topUpCards', fn($query) => $query->where('status', '!=', TopUpCardStatus::Pending))
            ->withCount([
                'topUpCards as available_cards_count' => fn($query) => $query
                    ->where('status', TopUpCardStatus::Active)
                    ->whereNull('office_id')
                    ->whereNull('redeemed_at'),
            ])
            ->with([
                'topUpCards' => fn($query) => $query
                    ->select(['batch_id', 'amount'])
                    ->where('status', TopUpCardStatus::Active)
                    ->whereNull('office_id')
                    ->whereNull('redeemed_at')
                    ->distinct(),
            ])
            ->latest('id')
            ->get()
            ->map(
                fn(Batch $batch) => [
                    'id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                    'expires_at' => $batch->expires_at?->toDateString(),
                    'available_cards_count' => $batch->available_cards_count,
                    'available_points' => $batch->topUpCards
                        ->pluck('amount')
                        ->map(fn($amount) => (float) $amount)
                        ->unique()
                        ->sort()
                        ->values(),
                ],
            );

        $points = TopUpCard::query()
            ->where('status', '!=', TopUpCardStatus::Pending)
            ->distinct()
            ->orderBy('amount')
            ->pluck('amount')
            ->map(fn($point) => (float) $point)
            ->values();

        return Inertia::render('TopUpCards/OfficeAssign', [
            'offices' => $offices,
            'cards' => $cards,
            'batches' => $batches,
            'points' => $points,
            'importProgress' => $this->importProgress($request),
            'filters' => [
                'search' => $search,
                'card_search' => $cardSearch,
                'batch' => $batch,
                'office' => $officeFilter,
                'status' => $status,
                'amount' => $amount,
            ],
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:30000'],
        ]);

        $lock = Cache::lock('top_up_card_generation:lock', 20);

        if (! $lock->get()) {
            return back()->with('error', 'top_up_cards.generation_in_progress');
        }

        $path = null;
        $token = null;

        try {
            $activeToken = Cache::get('top_up_card_generation:active');
            $activeGeneration = is_string($activeToken)
                ? Cache::get('top_up_card_generation:' . $activeToken)
                : null;
            $activeBatch = is_string($activeToken)
                ? Cache::get('top_up_card_generation:' . $activeToken . ':batch')
                : null;

            if (
                is_array($activeGeneration) &&
                ($activeGeneration['status'] ?? null) === 'processing' &&
                ! in_array($activeBatch['status'] ?? null, ['completed', 'failed'], true)
            ) {
                return back()->with('error', 'top_up_cards.generation_in_progress');
            }

            $path = $request->file('file')->store('imports/top-up-cards', 'local');

            if (! is_string($path)) {
                return back()->with('import_error', [
                    'key' => 'csv.import_errors.read_failed',
                    'replace' => [],
                ]);
            }

            $token = Str::random(64);
            $userId = (int) $request->user()->getKey();

            Cache::put('top_up_card_generation:' . $token, [
                'status' => 'processing',
                'total_cards' => 0,
                'completed_cards' => 0,
                'total_chunks' => 0,
                'completed_chunks' => 0,
                'amounts' => [],
                'office_codes' => [],
                'user_id' => $userId,
                'source' => 'csv_import',
            ], now()->addDay());
            Cache::put('top_up_card_generation:' . $token . ':batch', ['status' => 'processing'], now()->addDay());
            Cache::put('top_up_card_generation:active', $token, now()->addDay());
            $request->session()->put('top_up_card_generation_token', $token);
            $request->session()->put('top_up_card_office_batch', '');

            ImportGeneratedTopUpCardsJob::dispatch($path, $token, $userId)
                ->onConnection('redis')
                ->onQueue('top-up-cards');

            return redirect()->route('top-up-cards.office-assign')->with('success', 'top_up_cards.generation_started');
        } catch (\Throwable $exception) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }

            if (is_string($token)) {
                Cache::forget('top_up_card_generation:' . $token);
                Cache::forget('top_up_card_generation:' . $token . ':batch');
                if (Cache::get('top_up_card_generation:active') === $token) {
                    Cache::forget('top_up_card_generation:active');
                }
                $request->session()->forget('top_up_card_generation_token');
            }

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{status: string|null, message: string|null, total_cards: int, completed_cards: int, completed_chunks: int, total_chunks: int}|null
     */
    private function importProgress(Request $request): ?array
    {
        $token = $request->session()->get('top_up_card_generation_token');

        if (! is_string($token) || $token === '') {
            return null;
        }

        $generation = Cache::get('top_up_card_generation:' . $token);

        if (! is_array($generation) || ($generation['source'] ?? null) !== 'csv_import') {
            return null;
        }

        $batch = Cache::get('top_up_card_generation:' . $token . ':batch');

        return [
            'status' => is_string($batch['status'] ?? null) ? $batch['status'] : ($generation['status'] ?? null),
            'message' => is_string($generation['message'] ?? null) ? $generation['message'] : null,
            'total_cards' => (int) ($generation['total_cards'] ?? 0),
            'completed_cards' => (int) ($generation['completed_cards'] ?? 0),
            'completed_chunks' => (int) ($generation['completed_chunks'] ?? 0),
            'total_chunks' => (int) ($generation['total_chunks'] ?? 0),
        ];
    }

    public function store(StoreOfficeRequest $request): RedirectResponse
    {
        $office = Office::query()->create($request->validated());
        activity('top-up-cards')->causedBy($request->user())->performedOn($office)->event('created')->log('office_created');

        return back()->with('success', 'Office created successfully.');
    }

    public function update(UpdateOfficeRequest $request, Office $office): RedirectResponse
    {
        $office->update($request->validated());
        Cache::forget('top_up_cards.office_options');

        return back()->with('success', 'Office updated successfully.');
    }

    public function destroy(Office $office): RedirectResponse
    {
        $office->delete();
        Cache::forget('top_up_cards.office_options');

        return back()->with('success', 'Office deleted successfully.');
    }

    public function assign(AssignOfficeRequest $request, TopUpCard $topUpCard): RedirectResponse
    {
        $data = $request->validated();

        if ($topUpCard->redeemed_at !== null || $topUpCard->status === TopUpCardStatus::Used) {
            return back()->with('error', 'A redeemed card cannot be reassigned.');
        }

        $attributes = [
            'office_id' => $data['office_id'],
            'status' => $data['office_id'] === null ? TopUpCardStatus::Pending : TopUpCardStatus::Active,
        ];

        $topUpCard->update($attributes);

        return back()->with('success', 'Top-up card assigned successfully.');
    }

    public function assignOffice(AssignCardsToOfficeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $attributes = [
            'office_id' => $data['office_id'],
            'status' => $data['office_id'] === null ? TopUpCardStatus::Pending : TopUpCardStatus::Active,
        ];

        TopUpCard::query()
            ->where('batch_id', $data['batch_id'])
            ->where('amount', $data['amount'])
            ->whereNull('redeemed_at')
            ->where('status', TopUpCardStatus::Active)
            ->whereNull('office_id')
            ->update($attributes);

        return back()->with('success', 'Top-up cards assigned successfully.');
    }

    private function cardPayload(TopUpCard $card): array
    {
        return [
            'id' => $card->id,
            'serial_no' => $card->serial_no,
            'amount' => $card->amount,
            'status' => $card->status->value,
            'expires_at' => $card->expires_at?->toDateString(),
            'office_id' => $card->office_id,
            'office' => $card->office?->name,
            'batch_no' => $card->batch?->batch_no,
        ];
    }
}