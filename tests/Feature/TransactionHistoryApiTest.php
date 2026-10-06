<?php

namespace Tests\Feature;

use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_cursor_paginate_only_their_transaction_history(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $otherUser = User::factory()->create();
        $otherWallet = Wallet::factory()->for($otherUser)->create();

        $ownTransactions = LedgerTransaction::factory()
            ->count(3)
            ->for($wallet)
            ->create();
        $otherTransaction = LedgerTransaction::factory()
            ->for($otherWallet)
            ->create();

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token, 'Bearer')
            ->getJson('/api/transactions?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');

        $firstPageIds = collect($response->json('data'))->pluck('id');
        $this->assertCount(2, $firstPageIds->intersect($ownTransactions->pluck('id')));
        $this->assertNotContains($otherTransaction->id, $firstPageIds);

        parse_str((string) parse_url($response->json('links.next'), PHP_URL_QUERY), $nextPageQuery);
        $this->assertArrayHasKey('cursor', $nextPageQuery);

        $secondPageIds = collect(
            $this->withToken($token, 'Bearer')
                ->getJson('/api/transactions?'.http_build_query($nextPageQuery))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->json('data'),
        )->pluck('id');

        $this->assertCount(3, $firstPageIds->merge($secondPageIds)->unique());
        $this->assertNotContains($otherTransaction->id, $secondPageIds);
    }

    public function test_transaction_history_requires_authentication(): void
    {
        $this->getJson('/api/transactions')->assertUnauthorized();
    }

    public function test_transaction_history_validates_page_size(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/transactions?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
}
