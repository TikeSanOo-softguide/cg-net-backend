<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Support\GeneratesTopUpCards;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class GenerateTopUpCardsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * @param  list<array{value: int|string, quantity: int}>  $amounts
     */
    public function __construct(
        public readonly array $amounts,
        public readonly string $expiresAt,
        public readonly int $actorId,
        public readonly string $token,
        public readonly string $productionDate,
        public readonly string $agentCode,
        public readonly int $chunkIndex,
        public readonly int $totalChunks,
        public readonly int $cardBatchId,
    ) {
        $this->timeout = (int) config('horizon.top_up_cards.timeout', 300);
        $this->onQueue('top-up-cards');
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $cached = Cache::get($this->cacheKey());
        $cached = is_array($cached) ? $cached : [];

        if (($cached['status'] ?? null) === 'completed') {
            return;
        }

        $cards = is_array($cached['cards'] ?? null) ? $cached['cards'] : [];

        if ($cards !== [] && GeneratesTopUpCards::allSerialsExist($cards)) {
            $this->markChunkCompleted($cached, $cards);

            return;
        }

        Admin::query()->findOrFail($this->actorId);

        $productionDate = Carbon::parse($this->productionDate);
        $quantity = $this->cardCount();
        $amount = (string) ($this->amounts[0]['value'] ?? 0);
        $batchCode = GeneratesTopUpCards::batchCodeForAmount($amount);

        // Commit the serial reservation before insert so a rolled-back insert does not
        // release the range while this chunk still owns starting_counter in cache.
        if (!array_key_exists('starting_counter', $cached)) {
            $cached['starting_counter'] = GeneratesTopUpCards::allocateSerialRange(
                $quantity,
                $productionDate,
                $this->agentCode,
                $batchCode,
            );
            $cached['status'] = 'processing';
            Cache::put($this->cacheKey(), $cached, now()->addDay());
        }

        $startingCounter = (int) $cached['starting_counter'];
        $cached['batch_id'] = $this->cardBatchId;

        if ($cards === []) {
            $materialized = GeneratesTopUpCards::materialize(
                $this->amounts,
                $this->expiresAt,
                $startingCounter,
                $productionDate,
                $this->agentCode,
                $this->cardBatchId,
            );

            $cards = $materialized['cards'];
            $cached['cards'] = $cards;
            $cached['batch_id'] = $this->cardBatchId;
            $cached['status'] = 'inserting';
            // Persist plaintext PINs before insert so a crash after commit remains recoverable.
            Cache::put($this->cacheKey(), $cached, now()->addDay());
        }

        if (GeneratesTopUpCards::allSerialsExist($cards)) {
            $this->markChunkCompleted($cached, $cards);

            return;
        }

        $batchId = (int) ($cached['batch_id'] ?? $this->cardBatchId);

        if ($batchId < 1) {
            throw new RuntimeException('Cached top-up card chunk is missing batch_id.');
        }

        $rows = GeneratesTopUpCards::rowsFromCards($cards, $batchId);

        try {
            DB::transaction(function () use ($rows): void {
                GeneratesTopUpCards::insertRows($rows);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $message = $exception->getMessage();

            if (str_contains($message, 'serial_no') && GeneratesTopUpCards::allSerialsExist($cards)) {
                $this->markChunkCompleted($cached, $cards);

                return;
            }

            if (str_contains($message, 'pin')) {
                $materialized = GeneratesTopUpCards::materialize(
                    $this->amounts,
                    $this->expiresAt,
                    $startingCounter,
                    $productionDate,
                    $this->agentCode,
                    $batchId,
                );

                $cards = $materialized['cards'];
                $cached['cards'] = $cards;
                $cached['status'] = 'inserting';
                Cache::put($this->cacheKey(), $cached, now()->addDay());

                if (GeneratesTopUpCards::allSerialsExist($cards)) {
                    $this->markChunkCompleted($cached, $cards);

                    return;
                }

                DB::transaction(function () use ($materialized): void {
                    GeneratesTopUpCards::insertRows($materialized['rows']);
                });

                $this->markChunkCompleted($cached, $cards);

                return;
            }

            if (str_contains($message, 'serial_no')) {
                throw new RuntimeException(
                    'Serial range overlap detected for this job; please re-dispatch with a non-overlapping range.',
                    0,
                    $exception,
                );
            }

            throw $exception;
        }

        $this->markChunkCompleted($cached, $cards);
    }

    public function failed(Throwable $exception): void
    {
        $cached = Cache::get($this->cacheKey());
        $cached = is_array($cached) ? $cached : [];

        Cache::put(
            $this->cacheKey(),
            [
                ...$cached,
                'status' => 'failed',
                'message' => 'Top-up card generation failed.',
            ],
            now()->addDay(),
        );
    }

    /**
     * @param  array<string, mixed>  $cached
     * @param  list<array<string, mixed>>  $cards
     */
    private function markChunkCompleted(array $cached, array $cards): void
    {
        Cache::put(
            $this->cacheKey(),
            [
                ...$cached,
                'status' => 'completed',
                'cards' => $cards,
            ],
            now()->addDay(),
        );
    }

    private function cacheKey(): string
    {
        return 'top_up_card_generation:' . $this->token . ':chunk:' . $this->chunkIndex;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return array_values(
            array_filter([
                'top-up-cards',
                'generation:' . $this->token,
                $this->batchId !== null ? 'batch:' . $this->batchId : null,
                'chunk:' . $this->chunkIndex,
                'cards:' . $this->cardCount(),
            ]),
        );
    }

    private function cardCount(): int
    {
        return array_sum(array_map(fn(array $tier): int => (int) $tier['quantity'], $this->amounts));
    }
}
