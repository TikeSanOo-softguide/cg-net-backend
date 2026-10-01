<?php

namespace App\Http\Controllers\TopUpCard;

use App\Enums\TopUpCardStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ImportGeneratedTopUpCardsJob;
use App\Http\Requests\TopUpCard\StoreOfficeRequest;
use App\Http\Requests\TopUpCard\UpdateOfficeRequest;
use App\Models\Office;
use App\Models\Batch;
use App\Support\CsvImportException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
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
        $batch = $request->has('batch')
            ? $request->string('batch')->toString()
            : (string) $request->session()->get('top_up_card_office_batch', '');
        $status = $request->string('status')->toString();
        $cardFilters = function ($query) use ($status): void {
            $query
                ->where('status', '!=', TopUpCardStatus::Pending)
                ->when(
                    in_array($status, array_column(TopUpCardStatus::cases(), 'value'), true),
                    fn($query) => $query->where('status', $status),
                );
        };

        $batchPage = Batch::query()
            ->whereHas('topUpCards', $cardFilters)
            ->when($batch !== '' && is_numeric($batch), fn($query) => $query->whereKey((int) $batch))
            ->withCount([
                'topUpCards as assigned_cards_count' => fn($query) => $query
                    ->where('status', '!=', TopUpCardStatus::Pending)
                    ->whereNotNull('office_id'),
            ])
            ->latest('id')
            ->paginate(100)
            ->withQueryString()
            ->through(
                fn(Batch $batch) => [
                    'id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                    'total_value' => $batch->total_value,
                    'quantity' => $batch->quantity,
                    'status' => $batch->status->value,
                    'expires_at' => $batch->expires_at?->toDateString(),
                    'assigned_cards_count' => $batch->assigned_cards_count,
                ],
            );

        $batches = Batch::query()
            ->select(['id', 'batch_no', 'expires_at'])
            ->whereHas('topUpCards', fn($query) => $query->where('status', '!=', TopUpCardStatus::Pending))
            ->latest('id')
            ->get()
            ->map(
                fn(Batch $batch) => [
                    'id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                ],
            );

        return Inertia::render('TopUpCards/OfficeAssign', [
            'batchPage' => $batchPage,
            'batches' => $batches,
            'importProgress' => $this->importProgress($request),
            'filters' => [
                'batch' => $batch,
                'status' => $status,
            ],
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv', 'mimes:csv', 'max:30000'],
        ]);

        $lock = Cache::lock('top_up_card_generation:lock', 20);

        if (!$lock->get()) {
            return back()->with('error', 'top_up_cards.generation_in_progress');
        }

        $path = null;
        $token = null;

        try {
            $activeToken = Cache::get('top_up_card_generation:active');
            $activeGeneration = is_string($activeToken) ? Cache::get('top_up_card_generation:' . $activeToken) : null;
            $activeBatch = is_string($activeToken)
                ? Cache::get('top_up_card_generation:' . $activeToken . ':batch')
                : null;

            if (
                is_array($activeGeneration) &&
                ($activeGeneration['status'] ?? null) === 'processing' &&
                !in_array($activeBatch['status'] ?? null, ['completed', 'failed'], true)
            ) {
                return back()->with('error', 'top_up_cards.generation_in_progress');
            }

            $path = $request->file('file')->store('imports/top-up-cards', 'local');

            if (!is_string($path)) {
                return back()->with('import_error', [
                    'key' => 'csv.import_errors.read_failed',
                    'replace' => [],
                ]);
            }

            $token = Str::random(64);
            $userId = (int) $request->user()->getKey();

            Cache::put(
                'top_up_card_generation:' . $token,
                [
                    'status' => 'processing',
                    'total_cards' => 0,
                    'completed_cards' => 0,
                    'total_chunks' => 0,
                    'completed_chunks' => 0,
                    'amounts' => [],
                    'office_codes' => [],
                    'user_id' => $userId,
                    'source' => 'csv_import',
                ],
                now()->addDay(),
            );
            Cache::put('top_up_card_generation:' . $token . ':batch', ['status' => 'processing'], now()->addDay());
            Cache::put('top_up_card_generation:active', $token, now()->addDay());
            $request->session()->put('top_up_card_generation_token', $token);
            $request->session()->put('top_up_card_office_batch', '');

            ImportGeneratedTopUpCardsJob::dispatch($path, $token, $userId)
                ->onConnection('redis')
                ->onQueue('top-up-cards');

            return redirect()->route('top-up-cards.office-assign')->with('info', 'top_up_cards.generation_started');
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

    public function validateImport(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv', 'mimes:csv', 'max:30000'],
        ]);

        $path = $request->file('file')->store('imports/top-up-cards/validation', 'local');

        if (!is_string($path)) {
            return response()->json(
                [
                    'error' => ['key' => 'csv.import_errors.read_failed', 'replace' => []],
                ],
                422,
            );
        }

        try {
            return response()->json(ImportGeneratedTopUpCardsJob::previewCsv($path));
        } catch (CsvImportException $exception) {
            return response()->json(
                [
                    'error' => [
                        'key' => $exception->translationKey,
                        'replace' => $exception->replace,
                    ],
                ],
                422,
            );
        } catch (\RuntimeException) {
            return response()->json(
                [
                    'error' => ['key' => 'csv.import_errors.read_failed', 'replace' => []],
                ],
                422,
            );
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * @return array{status: string|null, message: string|null, message_replace: array<string, string|int>, total_cards: int, completed_cards: int, completed_chunks: int, total_chunks: int}|null
     */
    private function importProgress(Request $request): ?array
    {
        $token = $request->session()->get('top_up_card_generation_token');

        if (!is_string($token) || $token === '') {
            return null;
        }

        $generation = Cache::get('top_up_card_generation:' . $token);

        if (!is_array($generation) || ($generation['source'] ?? null) !== 'csv_import') {
            return null;
        }

        $batch = Cache::get('top_up_card_generation:' . $token . ':batch');

        return [
            'status' => is_string($batch['status'] ?? null) ? $batch['status'] : $generation['status'] ?? null,
            'message' => is_string($generation['message'] ?? null) ? $generation['message'] : null,
            'message_replace' => is_array($generation['message_replace'] ?? null) ? $generation['message_replace'] : [],
            'total_cards' => (int) ($generation['total_cards'] ?? 0),
            'completed_cards' => (int) ($generation['completed_cards'] ?? 0),
            'completed_chunks' => (int) ($generation['completed_chunks'] ?? 0),
            'total_chunks' => (int) ($generation['total_chunks'] ?? 0),
        ];
    }

    public function store(StoreOfficeRequest $request): RedirectResponse
    {
        $office = Office::query()->create($request->validated());
        activity('top-up-cards')
            ->causedBy($request->user())
            ->performedOn($office)
            ->event('created')
            ->log('office_created');

        return back()->with('success', 'top_up_cards.office.created');
    }

    public function update(UpdateOfficeRequest $request, Office $office): RedirectResponse
    {
        $office->update($request->validated());
        Cache::forget('top_up_cards.office_options');

        return back()->with('success', 'top_up_cards.office.updated');
    }

    public function destroy(Office $office): RedirectResponse
    {
        $office->delete();
        Cache::forget('top_up_cards.office_options');

        return back()->with('success', 'top_up_cards.office.deleted');
    }
}
