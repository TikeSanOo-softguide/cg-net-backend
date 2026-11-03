<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Support\GeneratesTopUpCards;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
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
        public readonly string $officeCode,
        public readonly int $chunkIndex,
        public readonly int $totalChunks,
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

        if ($cards !== []) {
            $this->markChunkCompleted($cached, $cards);

            return;
        }

        Admin::query()->findOrFail($this->actorId);

        $productionDate = Carbon::parse($this->productionDate);
        $quantity = $this->cardCount();
        $amount = (string) ($this->amounts[0]['value'] ?? 0);
        $batchCode = GeneratesTopUpCards::batchCodeForAmount($amount);

        // Keep reserved ranges across retries so generated serials cannot overlap.
        if (!array_key_exists('starting_counter', $cached)) {
            $cached['starting_counter'] = GeneratesTopUpCards::allocateSerialRange(
                $quantity,
                $productionDate,
                $this->officeCode,
                $batchCode,
            );
            $cached['status'] = 'processing';
            Cache::put($this->cacheKey(), $cached, now()->addDay());
        }

        $startingCounter = (int) $cached['starting_counter'];
        if ($cards === []) {
            $materialized = GeneratesTopUpCards::materialize(
                $this->amounts,
                $this->expiresAt,
                $startingCounter,
                $productionDate,
                $this->officeCode,
            );

            $cards = $materialized['cards'];
            $cached['cards'] = $cards;
            $cached['status'] = 'completed';
            Cache::put($this->cacheKey(), $cached, now()->addDay());
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
