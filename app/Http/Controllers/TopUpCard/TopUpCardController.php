<?php

namespace App\Http\Controllers\TopUpCard;

use App\Enums\TopUpCardStatus;
use App\Jobs\GenerateTopUpCardsJob;
use App\Http\Controllers\Controller;
use App\Http\Requests\TopUpCard\GenerateTopUpCardsRequest;
use App\Models\Admin;
use App\Models\Agent;
use App\Models\Batch;
use App\Models\TopUpCard;
use App\Models\TopUpCardBatchCode;
use App\Support\GeneratesTopUpCards;
use App\Support\TopUpCardAgents;
use App\Support\TopUpCardGenerationStatus;
use App\Http\Controllers\InOutManagement\CSV\TopUpCard as TopUpCardCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Bus\Batch as QueueBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TopUpCardController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->toString();
        $amount = $request->string('amount')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $sortable = ['serial_no', 'amount', 'status', 'created_at'];
        $partialOnly = $this->inertiaPartialProps($request);
        $tableOnly = $partialOnly !== null && !$this->wantsInertiaProp($partialOnly, 'generated');

        if (!in_array($sort, $sortable, true)) {
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
                'expires_at',
                'redeemed_at',
                'redeemed_by',
                'batch_id',
                'wallet_transaction_id',
                'created_at',
            ])
            ->with(['redeemedBy:id,name,phone', 'batch:id,batch_no,status', 'walletTransaction:id,transaction_no'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereLike('serial_no', '%' . $search . '%');
            })
            ->when(
                $status !== '' && in_array($status, array_column(TopUpCardStatus::cases(), 'value'), true),
                function ($query) use ($status): void {
                    $query->where('status', $status);
                },
            )
            ->when($amount !== '' && is_numeric($amount), function ($query) use ($amount): void {
                $query->where('amount', $amount);
            })
            ->when($latestExpiryDate !== null, function ($query) use ($latestExpiryDate): void {
                $query->whereDate('expires_at', $latestExpiryDate);
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn(TopUpCard $card) => $this->payload($card));

        $agents = Agent::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return Inertia::render('TopUpCards/Generate', [
            'cards' => $cards,
            'generated' => $request->session()->get('top_up_card_export_batch', []),
            'presets' => $this->presetAmounts(),
            'amounts' => $this->amountOptions(),
            'agents' => $agents,
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

        if ($partialOnly === null || $this->wantsInertiaProp($partialOnly, 'agents')) {
            $props['agents'] = $this->agentOptions();
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
            ];
        }

        if ($partialOnly === null || $this->wantsInertiaProp($partialOnly, 'presets')) {
            $props['presets'] = $this->presetAmounts();
        }

        if ($partialOnly === null || $this->wantsInertiaProp($partialOnly, 'max_cards')) {
            $props['max_cards'] = (int) config('top_up_cards.max_cards', 100000);
        }

        if ($partialOnly === null || $this->wantsInertiaProp($partialOnly, 'amounts')) {
            $props['amounts'] = $this->amountOptions();
        }

        return Inertia::render('TopUpCards/Generate', $props);
    }

    /**
     * @return list<string>|null
     */
    private function inertiaPartialProps(Request $request): ?array
    {
        $header = $request->header('X-Inertia-Partial-Data');

        if (!is_string($header) || trim($header) === '') {
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

    public function store(GenerateTopUpCardsRequest $request): RedirectResponse
    {
        $userId = (int) $request->user()->getKey();
        $lock = Cache::lock($this->generationGlobalLockKey(), 20);

        if (!$lock->get()) {
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
            $agentIds = array_values(array_unique(array_map('intval', $request->validated('agent_ids', []))));
            $agentCodes = TopUpCardAgents::resolveCodes($agentIds);
            $expiresAt = $request->date('expires_at')->toDateString();
            $chunkSize = max(1, (int) config('top_up_cards.chunk_size', 500));
            $chunks = $this->buildGenerationPlan($agentCodes, $amounts, $chunkSize);

            if ($previousToken !== null) {
                $this->clearGenerationCache($previousToken);
            }

            Cache::forget('top_up_cards.latest_expires_at');
            Cache::forget('top_up_cards.amount_options');

            $totalCards = 0;
            $totalValue = 0;
            $amountBreakdown = [];
            $metadataItems = [];

            foreach ($agentCodes as $agentCode) {
                foreach ($amounts as $tier) {
                    $amount = (int) $tier['value'];
                    $quantity = (int) $tier['quantity'];

                    $metadataItems[] = [
                        'agent_cd' => (string) $agentCode,
                        'amount' => $amount,
                        'quantity' => $quantity,
                    ];
                }
            }

            foreach ($amounts as $tier) {
                $amount = (int) $tier['value'];
                $quantity = (int) $tier['quantity'];
                $issuedCards = $quantity * count($agentCodes);
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
                'top_up_card_generation:' . $token,
                [
                    'status' => 'processing',
                    'total_cards' => $totalCards,
                    'total_value' => (string) $totalValue,
                    'total_chunks' => count($chunks),
                    'expires_at' => $expiresAt,
                    'amounts' => $amountBreakdown,
                    'agent_codes' => $agentCodes,
                    'user_id' => $userId,
                ],
                now()->addDay(),
            );
            Cache::put($this->generationGlobalActiveKey(), $token, now()->addDay());

            $cardBatch = GeneratesTopUpCards::createGenerationBatch(
                $expiresAt,
                $totalCards,
                $totalValue,
                [
                    'items' => $metadataItems,
                ],
                $token,
            );

            $jobs = [];

            foreach ($chunks as $index => $chunk) {
                $jobs[] = new GenerateTopUpCardsJob(
                    $chunk['amounts'],
                    $expiresAt,
                    $userId,
                    $token,
                    $productionDate->toIso8601String(),
                    $chunk['agent_code'],
                    $index,
                    count($chunks),
                    (int) $cardBatch->id,
                );
            }

            $queueBatch = Bus::batch($jobs)
                ->name('top-up-cards:' . $token)
                ->onConnection('redis')
                ->onQueue('top-up-cards')
                ->allowFailures()
                ->then(function (QueueBatch $batch) use ($token): void {
                    Cache::put(
                        'top_up_card_generation:' . $token . ':batch',
                        [
                            'status' => 'completed',
                            'batch_id' => $batch->id,
                        ],
                        now()->addDay(),
                    );
                })
                ->catch(function (QueueBatch $batch) use ($token): void {
                    Cache::put(
                        'top_up_card_generation:' . $token . ':batch',
                        [
                            'status' => 'failed',
                            'batch_id' => $batch->id,
                        ],
                        now()->addDay(),
                    );
                })
                ->finally(function (QueueBatch $batch) use ($token): void {
                    $status = $batch->hasFailures() ? 'failed' : 'completed';
                    $activeKey = 'top_up_card_generation:active';

                    Cache::put(
                        'top_up_card_generation:' . $token . ':batch',
                        [
                            'status' => $status,
                            'batch_id' => $batch->id,
                            'processed_jobs' => $batch->processedJobs(),
                            'total_jobs' => $batch->totalJobs,
                        ],
                        now()->addDay(),
                    );

                    $generation = Cache::get('top_up_card_generation:' . $token);

                    if (is_array($generation)) {
                        $generation['status'] = $status;
                        Cache::put('top_up_card_generation:' . $token, $generation, now()->addDay());
                    }

                    if (Cache::get($activeKey) === $token) {
                        Cache::forget($activeKey);
                    }

                    self::logGenerationFinished($token, $batch, $batch->hasFailures() ? 'failed' : 'succeeded');
                })
                ->dispatch();

            Cache::put(
                'top_up_card_generation:' . $token,
                [
                    'status' => 'processing',
                    'total_cards' => $totalCards,
                    'total_value' => (string) $totalValue,
                    'total_chunks' => count($chunks),
                    'expires_at' => $expiresAt,
                    'amounts' => $amountBreakdown,
                    'agent_codes' => $agentCodes,
                    'batch_id' => $queueBatch->id,
                    'top_up_batch_id' => $cardBatch->id,
                    'top_up_batch_no' => $cardBatch->batch_no,
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
     * @param  list<string>  $agentCodes
     * @param  list<array{value: int|string, quantity: int}>  $amounts
     * @return list<array{agent_code: string, amounts: list<array{value: int|string, quantity: int}>}>
     */
    private function buildGenerationPlan(array $agentCodes, array $amounts, int $chunkSize): array
    {
        $plan = [];

        foreach ($agentCodes as $agentCode) {
            foreach ($amounts as $tier) {
                $remaining = (int) $tier['quantity'];

                while ($remaining > 0) {
                    $quantity = min($remaining, $chunkSize);
                    $plan[] = [
                        'agent_code' => (string) $agentCode,
                        'amounts' => [
                            [
                                'value' => $tier['value'],
                                'quantity' => $quantity,
                            ],
                        ],
                    ];
                    $remaining -= $quantity;
                }
            }
        }

        return $plan;
    }

    public function generationStatus(Request $request): JsonResponse
    {
        $status = TopUpCardGenerationStatus::forToken($this->generationToken($request));

        return response()->json(
            $status ?? [
                'token' => null,
                'status' => null,
                'total_cards' => 0,
            ],
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $token = $this->generationToken($request);
        $generation = $this->generationFromSession($request);

        abort_if($token === null || ($generation['status'] ?? null) !== 'completed', 404);

        $batch = $this->fullGenerationCards($token);

        abort_if($batch === [], 404);

        return TopUpCardCsv::export($batch, 'top-up-cards-' . now()->format('Ymd-His') . '.csv');
    }

    /**
     * @return array{status?: string, cards?: list<array<string, mixed>>, message?: string, completed_chunks?: int, total_chunks?: int}
     */
    private function generationFromSession(Request $request): array
    {
        $token = $this->generationToken($request);

        if (!is_string($token) || $token === '') {
            return [];
        }

        $generation = Cache::get('top_up_card_generation:' . $token);

        if (!is_array($generation)) {
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

        $batchMeta = Cache::get('top_up_card_generation:' . $token . ':batch');
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
            $needProgress = !$batchCompleted;

            if (!$needPreview && !$needProgress) {
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
            : ($batchCompleted || ($completedChunks === $totalChunks && $totalChunks > 0)
                ? 'completed'
                : 'processing');

        $generation['status'] = $status;
        $generation['completed_chunks'] = $batchCompleted ? $totalChunks : $completedChunks;
        $generation['cards'] = $preview;

        if ($status === 'completed' || $status === 'failed') {
            Cache::put('top_up_card_generation:' . $token, $generation, now()->addDay());
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
     * @return list<array{id: int, name: string}>
     */
    private function agentOptions(): array
    {
        /** @var list<array{id: int, name: string}>|null $cached */
        $cached = Cache::get('top_up_cards.agent_options');

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $agents = Agent::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(
                fn(Agent $agent): array => [
                    'id' => (int) $agent->id,
                    'name' => (string) $agent->name,
                ],
            )
            ->all();

        // Do not cache an empty list — agents may be added right after the first visit.
        if ($agents !== []) {
            Cache::put('top_up_cards.agent_options', $agents, now()->addMinutes(5));
        } else {
            Cache::forget('top_up_cards.agent_options');
        }

        return $agents;
    }

    private function generationToken(Request $request): ?string
    {
        $token = $request->session()->get('top_up_card_generation_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function generationIsInProgress(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $batch = Cache::get('top_up_card_generation:' . $token . ':batch');

        if (in_array($batch['status'] ?? null, ['completed', 'failed'], true)) {
            return false;
        }

        $generation = Cache::get('top_up_card_generation:' . $token);

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
        $generation = Cache::get('top_up_card_generation:' . $token);

        if (!is_array($generation)) {
            return;
        }

        $cardBatchId = (int) ($generation['top_up_batch_id'] ?? 0);
        $cardBatch = $cardBatchId > 0 ? Batch::query()->find($cardBatchId) : null;

        if ($cardBatch === null) {
            return;
        }

        $userId = (int) ($generation['user_id'] ?? 0);
        $causer = $userId > 0 ? Admin::query()->find($userId) : null;
        $metadata = is_array($cardBatch->metadata) ? $cardBatch->metadata : [];
        $items = is_array($metadata['items'] ?? null) ? $metadata['items'] : [];

        activity('top_up_cards')
            ->causedBy($causer)
            ->performedOn($cardBatch)
            ->event($status)
            ->withProperties([
                'status' => $status,
                'batch_id' => $cardBatch->id,
                'batch_no' => $cardBatch->batch_no,
                'total_value' => (int) $cardBatch->total_value,
                'quantity' => (int) $cardBatch->quantity,
                'expires_at' => $cardBatch->expires_at?->toDateString(),
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
        $generation = Cache::get('top_up_card_generation:' . $token);

        if (!is_array($generation)) {
            return [];
        }

        $cards = [];
        $totalChunks = (int) ($generation['total_chunks'] ?? 0);

        for ($index = 0; $index < $totalChunks; $index++) {
            $chunk = Cache::get($this->chunkCacheKey($token, $index), []);

            if (($chunk['status'] ?? null) !== 'completed') {
                continue;
            }

            $cards = array_merge($cards, $chunk['cards'] ?? []);
        }

        return $cards;
    }

    private function clearGenerationCache(string $token): void
    {
        $generation = Cache::get('top_up_card_generation:' . $token);
        $totalChunks = (int) ($generation['total_chunks'] ?? 0);

        for ($index = 0; $index < $totalChunks; $index++) {
            Cache::forget($this->chunkCacheKey($token, $index));
        }

        Cache::forget('top_up_card_generation:' . $token);
        Cache::forget('top_up_card_generation:' . $token . ':batch');

        if (Cache::get($this->generationGlobalActiveKey()) === $token) {
            Cache::forget($this->generationGlobalActiveKey());
        }
    }

    private function chunkCacheKey(string $token, int $index): string
    {
        return 'top_up_card_generation:' . $token . ':chunk:' . $index;
    }

    public function void(Request $request, TopUpCard $topUpCard): RedirectResponse
    {
        if ($topUpCard->status !== TopUpCardStatus::Pending && $topUpCard->status !== TopUpCardStatus::Active) {
            return back()->with('error', 'top_up_cards.cannot_void');
        }

        $topUpCard->update(['status' => TopUpCardStatus::Blocked]);

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

        if (!in_array($sort, $sortable, true)) {
            $sort = 'redeemed_at';
        }

        $cards = TopUpCard::query()
            ->with('redeemedBy:id,name,phone')
            ->where('status', TopUpCardStatus::Used)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereLike('serial_no', '%' . $search . '%')
                        ->orWhereHas('redeemedBy', function ($query) use ($search): void {
                            $query->whereLike('name', '%' . $search . '%')->orWhereLike('phone', '%' . $search . '%');
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
            ->through(fn(TopUpCard $card) => $this->payload($card));

        $recent = TopUpCard::query()
            ->with('redeemedBy:id,name,phone')
            ->where('status', TopUpCardStatus::Used)
            ->latest('redeemed_at')
            ->limit(8)
            ->get()
            ->map(fn(TopUpCard $card) => $this->payload($card))
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

    public function cardHistory(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $amount = $request->string('amount')->toString();
        $batch = $request->string('batch')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $sort = $request->string('sort')->toString();

        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $sortable = ['serial_no', 'amount', 'status', 'created_at'];

        if (!in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $validStatuses = array_column(TopUpCardStatus::cases(), 'value');

        $batches = Batch::query()
            ->select(['id', 'batch_no'])
            ->orderByDesc('id')
            ->get();

        $cards = TopUpCard::query()
            ->with(['redeemedBy:id,name,phone', 'batch:id,batch_no,status', 'walletTransaction:id,transaction_no'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereLike('serial_no', "%{$search}%");
            })
            ->when(in_array($status, $validStatuses, true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($amount !== '' && is_numeric($amount), function ($query) use ($amount): void {
                $query->where('amount', $amount);
            })
            ->when($batch !== '', function ($query) use ($batch): void {
                if (ctype_digit($batch)) {
                    $query->where('batch_id', (int) $batch);
                } else {
                    $query->whereHas('batch', function ($q) use ($batch) {
                        $q->where('batch_no', $batch);
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
            ->paginate(15)
            ->withQueryString()
            ->through(fn(TopUpCard $card) => $this->payload($card));

        return Inertia::render('TopUpCards/CardHistory', [
            'cards' => $cards,
            'generated' => $request->session()->get('top_up_card_export_batch', []),
            'presets' => $this->presetAmounts(),
            'amounts' => $this->amountOptions(),
            'batches' => $batches,
            'stats' => $this->stats(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'amount' => $amount,
                'batch' => $batch,
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
            'expires_at' => $card->expires_at?->toDateString(),
            'redeemed_at' => $card->redeemed_at?->toIso8601String(),
            'redeemed_by_id' => $card->redeemed_by,
            'redeemed_by' => $card->redeemedBy?->name,
            'redeemed_by_phone' => $card->redeemedBy?->phone,
            'batch_no' => $card->batch?->batch_no,
            'batch_status' => $card->batch?->status,
            'transaction_id' => $card->walletTransaction?->id,
            'transaction_no' => $card->walletTransaction?->transaction_no,
        ];
    }

    /**
     * @return array{total: int, value: string, month: int, customers: int}
     */
    private function historyStats(): array
    {
        $redeemed = TopUpCard::query()->where('status', TopUpCardStatus::Used);

        return [
            'total' => $redeemed->clone()->count(),
            'value' => (string) (int) $redeemed->clone()->sum('amount'),
            'month' => $redeemed->clone()->where('redeemed_at', '>=', now()->startOfMonth())->count(),
            'customers' => (int) $redeemed
                ->clone()
                ->whereNotNull('redeemed_by')
                ->selectRaw('count(distinct redeemed_by) as aggregate')
                ->value('aggregate'),
        ];
    }

    private function presetAmounts(): Collection
    {
        return TopUpCardBatchCode::query()
            ->orderBy('amount')
            ->pluck('amount')
            ->map(fn($amount): int => (int) $amount)
            ->unique()
            ->values();
    }

    /**
     * @return list<string>
     */
    private function amountOptions(): array
    {
        return $this->presetAmounts()
            ->map(fn(int $amount): string => (string) $amount)
            ->all();
    }

    private function stats(): array
    {
        return [
            'total' => TopUpCard::query()->count(),
            'pending' => TopUpCard::query()->where('status', TopUpCardStatus::Pending)->count(),
            'active' => TopUpCard::query()->where('status', TopUpCardStatus::Active)->count(),
            'used' => TopUpCard::query()->where('status', TopUpCardStatus::Used)->count(),
            'expired' => TopUpCard::query()->where('status', TopUpCardStatus::Expired)->count(),
            'blocked' => TopUpCard::query()->where('status', TopUpCardStatus::Blocked)->count(),
        ];
    }
}
