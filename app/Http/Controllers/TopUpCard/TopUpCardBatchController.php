<?php

namespace App\Http\Controllers\TopUpCard;

use App\Enums\ActivityLogEvent;
use App\Enums\ActivityLogName;
use App\Enums\BatchStatus;
use App\Enums\TopUpCardStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\InOutManagement\CSV\TopUpCard as TopUpCardCsv;
use App\Http\Requests\TopUpCard\GenerateTopUpCardsRequest;
use App\Jobs\GenerateTopUpCardsJob;
use App\Jobs\ImportGeneratedTopUpCardsJob;
use App\Models\Admin;
use App\Models\Batch;
use App\Models\Office;
use App\Models\TopUpCard;
use App\Support\CsvImportException;
use App\Support\TopUpCardOffices;
use Illuminate\Bus\Batch as QueueBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TopUpCardBatchController extends Controller
{
    private const AMOUNT_OPTIONS = [50, 100, 150, 500];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->toString();
        $validStatuses = array_column(TopUpCardStatus::cases(), 'value');
        if (! in_array($status, $validStatuses, true)) {
            $status = '';
        }
        $amount = $request->string('amount')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $sortable = ['serial_no', 'amount', 'status', 'created_at'];
        $partialOnly = $this->inertiaPartialProps($request);
        $tableOnly = $partialOnly !== null && ! $this->wantsInertiaProp($partialOnly, 'generated');

        if (! in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $latestExpiryDate = $this->latestExpiryDate();
        $generation = $tableOnly ? [] : $this->generationFromSession($request);

        $cards = TopUpCard::query()
            ->select([
                'id',
                'serial_no',
                'amount',
                'status',
                'activated_at',
                'expires_at',
                'redeemed_at',
                'redeemed_by',
                'batch_id',
                'ledger_transaction_id',
                'created_at',
            ])
            ->with(['redeemedBy:id,name,phone', 'batch:id,batch_no,status', 'ledgerTransaction:id,transaction_no'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereLike('serial_no', '%'.$search.'%');
            })
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($amount !== '' && is_numeric($amount), function ($query) use ($amount): void {
                $query->where('amount', $amount);
            })
            ->when($latestExpiryDate !== null, function ($query) use ($latestExpiryDate): void {
                $query->whereDate('expires_at', $latestExpiryDate);
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (TopUpCard $card) => $this->payload($card));

        $props = [
            'cards' => $cards,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'amount' => $amount,
                'from' => $from,
                'to' => $to,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ];

        if ($partialOnly === null || $this->wantsInertiaProp($partialOnly, 'offices')) {
            $props['offices'] = $this->officeOptions();
        }

        if ($partialOnly === null || $this->wantsInertiaProp($partialOnly, 'generated')) {
            $props['generated'] = $generation['cards'] ?? [];
        }

        if ($partialOnly === null || $this->wantsInertiaProp($partialOnly, 'generation')) {
            $props['generation'] = [
                'status' => $generation['status'] ?? null,
                'message' => $generation['message'] ?? null,
                'total_cards' => (int) ($generation['total_cards'] ?? 0),
                'completed_chunks' => (int) ($generation['completed_chunks'] ?? 0),
                'total_chunks' => (int) ($generation['total_chunks'] ?? 0),
                'source' => $generation['source'] ?? null,
            ];
        }

        if ($partialOnly === null || $this->wantsInertiaProp($partialOnly, 'presets')) {
            $props['presets'] = collect(self::AMOUNT_OPTIONS);
        }

        if ($partialOnly === null || $this->wantsInertiaProp($partialOnly, 'max_cards')) {
            $props['max_cards'] = (int) config('top_up_cards.max_cards', 100000);
        }

        if ($partialOnly === null || $this->wantsInertiaProp($partialOnly, 'amounts')) {
            $props['amounts'] = self::AMOUNT_OPTIONS;
        }

        return Inertia::render('TopUpCards/Generate', $props);
    }

    public function store(GenerateTopUpCardsRequest $request): RedirectResponse
    {
        $userId = (int) $request->user()->getKey();
        $lock = Cache::lock($this->generationGlobalLockKey(), 20);

        if (! $lock->get()) {
            return back()->with('error', 'top_up_cards.generation_in_progress');
        }

        try {
            $previousToken = $this->generationToken($request);
            $activeToken = Cache::get($this->generationGlobalActiveKey());

            if ($this->generationIsInProgress(is_string($activeToken) ? $activeToken : null)) {
                return back()->with('error', 'top_up_cards.generation_in_progress');
            }

            if ($this->generationIsInProgress($previousToken)) {
                return back()->with('error', 'top_up_cards.generation_in_progress');
            }

            $token = Str::random(64);
            $amounts = $request->validated('amounts');
            $officeIds = array_values(array_unique(array_map('intval', $request->validated('office_ids', []))));
            $officeCodes = TopUpCardOffices::resolveCodes($officeIds);
            $expiresAt = $request->date('expires_at')->toDateString();
            $chunkSize = max(1, (int) config('top_up_cards.chunk_size', 500));
            $chunks = $this->buildGenerationPlan($officeCodes, $amounts, $chunkSize);

            if ($previousToken !== null) {
                $this->clearGenerationCache($previousToken);
            }

            Cache::forget('top_up_cards.latest_expires_at');
            Cache::forget('top_up_cards.amount_options');

            $totalCards = 0;
            $totalValue = 0;
            $amountBreakdown = [];
            $metadataItems = [];

            foreach ($officeCodes as $officeCode) {
                foreach ($amounts as $tier) {
                    $metadataItems[] = [
                        'office_cd' => (string) $officeCode,
                        'amount' => (int) $tier['value'],
                        'quantity' => (int) $tier['quantity'],
                    ];
                }
            }

            foreach ($amounts as $tier) {
                $amount = (int) $tier['value'];
                $issuedCards = (int) $tier['quantity'] * count($officeCodes);
                $issuedValue = $amount * $issuedCards;
                $totalCards += $issuedCards;
                $totalValue += $issuedValue;
                $amountBreakdown[] = [
                    'amount' => $amount,
                    'cards' => $issuedCards,
                    'value' => $issuedValue,
                ];
            }

            $productionDate = now();
            Cache::put(
                'top_up_card_generation:'.$token,
                [
                    'status' => 'processing',
                    'total_cards' => $totalCards,
                    'total_value' => (string) $totalValue,
                    'total_chunks' => count($chunks),
                    'expires_at' => $expiresAt,
                    'amounts' => $amountBreakdown,
                    'items' => $metadataItems,
                    'office_codes' => $officeCodes,
                    'user_id' => $userId,
                ],
                now()->addDay(),
            );
            Cache::put($this->generationGlobalActiveKey(), $token, now()->addDay());

            $jobs = [];

            foreach ($chunks as $index => $chunk) {
                $jobs[] = new GenerateTopUpCardsJob(
                    $chunk['amounts'],
                    $expiresAt,
                    $userId,
                    $token,
                    $productionDate->toIso8601String(),
                    $chunk['office_code'],
                    $index,
                    count($chunks),
                );
            }

            $queueBatch = Bus::batch($jobs)
                ->name('top-up-cards:'.$token)
                ->onConnection(app()->runningUnitTests() ? config('queue.default') : 'redis')
                ->onQueue('top-up-cards')
                ->allowFailures()
                ->then(function (QueueBatch $batch) use ($token): void {
                    Cache::put(
                        'top_up_card_generation:'.$token.':batch',
                        ['status' => 'completed', 'batch_id' => $batch->id],
                        now()->addDay(),
                    );
                })
                ->catch(function (QueueBatch $batch) use ($token): void {
                    Cache::put(
                        'top_up_card_generation:'.$token.':batch',
                        ['status' => 'failed', 'batch_id' => $batch->id],
                        now()->addDay(),
                    );
                })
                ->finally(function (QueueBatch $batch) use ($token): void {
                    $status = $batch->hasFailures() ? 'failed' : 'completed';

                    Cache::put(
                        'top_up_card_generation:'.$token.':batch',
                        [
                            'status' => $status,
                            'batch_id' => $batch->id,
                            'processed_jobs' => $batch->processedJobs(),
                            'total_jobs' => $batch->totalJobs,
                        ],
                        now()->addDay(),
                    );

                    $generation = Cache::get('top_up_card_generation:'.$token);

                    if (is_array($generation)) {
                        $generation['status'] = $status;
                        Cache::put('top_up_card_generation:'.$token, $generation, now()->addDay());
                    }

                    if (Cache::get($this->generationGlobalActiveKey()) === $token) {
                        Cache::forget($this->generationGlobalActiveKey());
                    }

                    self::logGenerationFinished($token, $batch, $batch->hasFailures() ? 'failed' : 'succeeded');
                })
                ->dispatch();

            Cache::put(
                'top_up_card_generation:'.$token,
                [
                    'status' => 'processing',
                    'total_cards' => $totalCards,
                    'total_value' => (string) $totalValue,
                    'total_chunks' => count($chunks),
                    'expires_at' => $expiresAt,
                    'amounts' => $amountBreakdown,
                    'office_codes' => $officeCodes,
                    'items' => $metadataItems,
                    'batch_id' => $queueBatch->id,
                    'user_id' => $userId,
                ],
                now()->addDay(),
            );

            $request->session()->put('top_up_card_generation_token', $token);

            return redirect()->route('top-up-cards.batch')->with('success', 'top_up_cards.generation_started');
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<string>  $officeCodes
     * @param  list<array{value: int|string, quantity: int}>  $amounts
     * @return list<array{office_code: string, amounts: list<array{value: int|string, quantity: int}>}>
     */
    private function buildGenerationPlan(array $officeCodes, array $amounts, int $chunkSize): array
    {
        $plan = [];

        foreach ($officeCodes as $officeCode) {
            foreach ($amounts as $tier) {
                $remaining = (int) $tier['quantity'];

                while ($remaining > 0) {
                    $quantity = min($remaining, $chunkSize);
                    $plan[] = [
                        'office_code' => (string) $officeCode,
                        'amounts' => [['value' => $tier['value'], 'quantity' => $quantity]],
                    ];
                    $remaining -= $quantity;
                }
            }
        }

        return $plan;
    }

    public function cardImport(Request $request): Response
    {
        $batch = $request->has('batch')
            ? $request->string('batch')->toString()
            : (string) $request->session()->get('top_up_card_office_batch', '');
        $status = $request->string('status')->toString();
        $batchStatus = BatchStatus::tryFrom($status);

        $batchPage = Batch::query()
            ->whereHas('topUpCards')
            ->when($batchStatus, fn ($query) => $query->where('status', $batchStatus))
            ->when($batch !== '' && is_numeric($batch), fn ($query) => $query->whereKey((int) $batch))
            ->withCount([
                'topUpCards as assigned_cards_count' => fn ($query) => $query->whereNotNull('office_id'),
            ])
            ->latest('id')
            ->paginate(100)
            ->withQueryString()
            ->through(
                fn (Batch $batch) => [
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
            ->whereHas('topUpCards')
            ->latest('id')
            ->get()
            ->map(fn (Batch $batch) => ['id' => $batch->id, 'batch_no' => $batch->batch_no]);

        return Inertia::render('TopUpCards/CardImport', [
            'batchPage' => $batchPage,
            'batches' => $batches,
            'importProgress' => $this->importProgress($request),
            'filters' => ['batch' => $batch, 'status' => $status],
        ]);
    }

    public function voidBatch(Request $request, Batch $batch): RedirectResponse
    {
        $blockedBatch = DB::transaction(function () use ($batch): ?Batch {
            $lockedBatch = Batch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($lockedBatch->status === BatchStatus::Blocked) {
                return null;
            }

            abort_unless($lockedBatch->status === BatchStatus::Active, 409);

            $lockedBatch->topUpCards()->update(['status' => TopUpCardStatus::Blocked]);
            $lockedBatch->update(['status' => BatchStatus::Blocked]);

            return $lockedBatch;
        });

        if ($blockedBatch instanceof Batch) {
            activity(ActivityLogName::TopUpCards->value)
                ->causedBy($request->user())
                ->performedOn($blockedBatch)
                ->event(ActivityLogEvent::Voided->value)
                ->log('top_up_cards_batch_blocked');
        }

        return back()->with('success', 'status.blocked');
    }

    public function activateBatch(Request $request, Batch $batch): RedirectResponse
    {
        $activatedBatch = DB::transaction(function () use ($batch): ?Batch {
            $lockedBatch = Batch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($lockedBatch->status === BatchStatus::Active) {
                return null;
            }

            abort_unless($lockedBatch->status === BatchStatus::Pending, 409);

            $activationDate = now()->startOfDay();
            $activatedAt = $activationDate->toDateString();
            $expiresAt = $activationDate->copy()->addYears(2)->toDateString();
            $activatedCards = $lockedBatch->topUpCards()
                ->where('status', TopUpCardStatus::Pending)
                ->update([
                    'status' => TopUpCardStatus::Active->value,
                    'activated_at' => $activatedAt,
                    'expires_at' => $expiresAt,
                    'updated_at' => now(),
                ]);

            abort_unless($activatedCards > 0, 409);

            $lockedBatch->update(['status' => BatchStatus::Active, 'expires_at' => $expiresAt]);

            return $lockedBatch;
        });

        if ($activatedBatch instanceof Batch) {
            activity(ActivityLogName::TopUpCards->value)
                ->causedBy($request->user())
                ->performedOn($activatedBatch)
                ->event(ActivityLogEvent::StatusChanged->value)
                ->log('top_up_cards_batch_activated');
        }

        return back()->with('success', 'top_up_cards.batch_activated');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv', 'extensions:csv', 'max:30000'],
        ]);

        $lock = Cache::lock($this->generationGlobalLockKey(), 20);

        if (! $lock->get()) {
            return back()->with('error', 'top_up_cards.generation_in_progress');
        }

        $path = null;
        $token = null;

        try {
            $activeToken = Cache::get($this->generationGlobalActiveKey());
            $activeGeneration = is_string($activeToken) ? Cache::get('top_up_card_generation:'.$activeToken) : null;
            $activeBatch = is_string($activeToken)
                ? Cache::get('top_up_card_generation:'.$activeToken.':batch')
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

            Cache::put(
                'top_up_card_generation:'.$token,
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
            Cache::put('top_up_card_generation:'.$token.':batch', ['status' => 'processing'], now()->addDay());
            Cache::put($this->generationGlobalActiveKey(), $token, now()->addDay());
            $request->session()->put('top_up_card_generation_token', $token);
            $request->session()->put('top_up_card_office_batch', '');

            ImportGeneratedTopUpCardsJob::dispatch($path, $token, $userId)
                ->onConnection('redis')
                ->onQueue('top-up-cards');

            return redirect()->route('top-up-cards.card-import');
        } catch (\Throwable $exception) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }

            if (is_string($token)) {
                Cache::forget('top_up_card_generation:'.$token);
                Cache::forget('top_up_card_generation:'.$token.':batch');
                if (Cache::get($this->generationGlobalActiveKey()) === $token) {
                    Cache::forget($this->generationGlobalActiveKey());
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
            'file' => ['required', 'file', 'extensions:csv', 'extensions:csv', 'max:30000'],
        ]);

        $path = $request->file('file')->store('imports/top-up-cards/validation', 'local');

        if (! is_string($path)) {
            return response()->json(['error' => ['key' => 'csv.import_errors.read_failed', 'replace' => []]], 422);
        }

        try {
            return response()->json(ImportGeneratedTopUpCardsJob::previewCsv($path));
        } catch (CsvImportException $exception) {
            return response()->json(
                ['error' => ['key' => $exception->translationKey, 'replace' => $exception->replace]],
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
        $token = $this->generationToken($request);

        if ($token === null) {
            return null;
        }

        $generation = Cache::get('top_up_card_generation:'.$token);

        if (! is_array($generation) || ($generation['source'] ?? null) !== 'csv_import') {
            return null;
        }

        $batch = Cache::get('top_up_card_generation:'.$token.':batch');

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

    public function export(Request $request): StreamedResponse
    {
        $token = $this->generationToken($request);
        $generation = $this->generationFromSession($request);

        abort_if($token === null || ($generation['status'] ?? null) !== 'completed', 404);

        $batch = $this->fullGenerationCards($token);
        abort_if($batch === [], 404);

        return TopUpCardCsv::export($batch, 'top-up-cards-'.now()->format('Ymd-His').'.csv');
    }

    /**
     * @return array{status?: string, cards?: list<array<string, mixed>>, message?: string, completed_chunks?: int, total_chunks?: int}
     */
    private function generationFromSession(Request $request): array
    {
        $token = $this->generationToken($request);

        if ($token === null) {
            return [];
        }

        $generation = Cache::get('top_up_card_generation:'.$token);

        if (! is_array($generation)) {
            return [];
        }

        if (
            ($generation['status'] ?? null) === 'completed' &&
            isset($generation['cards']) &&
            is_array($generation['cards']) &&
            ($generation['cards'] !== [] || (int) ($generation['total_cards'] ?? 0) === 0)
        ) {
            return $generation;
        }

        $batchMeta = Cache::get('top_up_card_generation:'.$token.':batch');
        $previewLimit = max(0, (int) config('top_up_cards.preview_limit', 100));
        $totalChunks = (int) ($generation['total_chunks'] ?? 0);
        $preview = [];
        $completedChunks = 0;
        $failed = ($batchMeta['status'] ?? null) === 'failed';
        $batchCompleted = ($batchMeta['status'] ?? null) === 'completed';

        for ($index = 0; $index < $totalChunks; $index++) {
            if ($failed) {
                break;
            }

            $needPreview = count($preview) < $previewLimit;
            $needProgress = ! $batchCompleted;

            if (! $needPreview && ! $needProgress) {
                break;
            }

            $chunk = Cache::get($this->chunkCacheKey($token, $index), []);
            $chunkStatus = $chunk['status'] ?? null;

            if ($chunkStatus === 'failed') {
                $failed = true;
                break;
            }

            if ($chunkStatus !== 'completed') {
                continue;
            }

            $completedChunks++;
            if ($needPreview) {
                $preview = array_merge(
                    $preview,
                    array_slice($chunk['cards'] ?? [], 0, $previewLimit - count($preview)),
                );
            }
        }

        $status = $failed
            ? 'failed'
            : ($batchCompleted || ($completedChunks === $totalChunks && $totalChunks > 0) ? 'completed' : 'processing');
        $generation['status'] = $status;
        $generation['completed_chunks'] = $batchCompleted ? $totalChunks : $completedChunks;
        $generation['cards'] = $preview;

        if (in_array($status, ['completed', 'failed'], true)) {
            Cache::put('top_up_card_generation:'.$token, $generation, now()->addDay());
        }

        return $generation;
    }

    private function latestExpiryDate(): ?string
    {
        /** @var string|null $cached */
        $cached = Cache::remember('top_up_cards.latest_expires_at', now()->addMinutes(5), function (): ?string {
            $value = TopUpCard::query()->orderByDesc('expires_at')->value('expires_at');

            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            return is_string($value) && $value !== '' ? $value : null;
        });

        return $cached;
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
                ? ['name' => $card->office->localizedName(), 'code' => (string) $card->office->cd]
                : null,
            'transaction_id' => $card->ledgerTransaction?->id,
            'transaction_no' => $card->ledgerTransaction?->transaction_no,
        ];
    }

    private function inertiaPartialProps(Request $request): ?array
    {
        $header = $request->header('X-Inertia-Partial-Data');

        if (! is_string($header) || trim($header) === '') {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $header))));
    }

    /**
     * @param  list<string>  $partialOnly
     */
    private function wantsInertiaProp(array $partialOnly, string $prop): bool
    {
        return in_array($prop, $partialOnly, true);
    }

    private function generationToken(Request $request): ?string
    {
        $token = $request->session()->get('top_up_card_generation_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function generationIsInProgress(?string $token): bool
    {
        if ($token === null) {
            return false;
        }

        $batch = Cache::get('top_up_card_generation:'.$token.':batch');

        if (in_array($batch['status'] ?? null, ['completed', 'failed'], true)) {
            return false;
        }

        $generation = Cache::get('top_up_card_generation:'.$token);

        return is_array($generation) && ($generation['status'] ?? null) === 'processing';
    }

    private function generationGlobalLockKey(): string
    {
        return 'top_up_card_generation:lock';
    }

    private function generationGlobalActiveKey(): string
    {
        return 'top_up_card_generation:active';
    }

    /**
     * @param  'succeeded'|'failed'  $status
     */
    public static function logGenerationFinished(string $token, QueueBatch $batch, string $status): void
    {
        $generation = Cache::get('top_up_card_generation:'.$token);

        if (! is_array($generation)) {
            return;
        }

        $userId = (int) ($generation['user_id'] ?? 0);
        $causer = $userId > 0 ? Admin::query()->find($userId) : null;
        $items = is_array($generation['items'] ?? null) ? $generation['items'] : [];

        activity(ActivityLogName::TopUpCards->value)
            ->causedBy($causer)
            ->event(ActivityLogEvent::from($status)->value)
            ->withProperties([
                'status' => $status,
                'generation_token' => $token,
                'total_value' => (int) ($generation['total_value'] ?? 0),
                'quantity' => (int) ($generation['total_cards'] ?? 0),
                'expires_at' => $generation['expires_at'] ?? null,
                'items' => $items,
                'queue_batch_id' => $batch->id,
                'processed_jobs' => $batch->processedJobs(),
                'failed_jobs' => $batch->failedJobs,
                'total_jobs' => $batch->totalJobs,
            ])
            ->log($status === 'succeeded' ? 'top_up_cards_generation_succeeded' : 'top_up_cards_generation_failed');
    }

    private function fullGenerationCards(string $token): array
    {
        $generation = Cache::get('top_up_card_generation:'.$token);

        if (! is_array($generation)) {
            return [];
        }

        $cards = [];
        $totalChunks = (int) ($generation['total_chunks'] ?? 0);

        for ($index = 0; $index < $totalChunks; $index++) {
            $chunk = Cache::get($this->chunkCacheKey($token, $index), []);

            if (($chunk['status'] ?? null) === 'completed') {
                $cards = array_merge($cards, $chunk['cards'] ?? []);
            }
        }

        return $cards;
    }

    private function clearGenerationCache(string $token): void
    {
        $generation = Cache::get('top_up_card_generation:'.$token);
        $totalChunks = (int) ($generation['total_chunks'] ?? 0);

        for ($index = 0; $index < $totalChunks; $index++) {
            Cache::forget($this->chunkCacheKey($token, $index));
        }

        Cache::forget('top_up_card_generation:'.$token);
        Cache::forget('top_up_card_generation:'.$token.':batch');

        if (Cache::get($this->generationGlobalActiveKey()) === $token) {
            Cache::forget($this->generationGlobalActiveKey());
        }
    }

    private function chunkCacheKey(string $token, int $index): string
    {
        return 'top_up_card_generation:'.$token.':chunk:'.$index;
    }
}
