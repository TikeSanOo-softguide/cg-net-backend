<?php

namespace App\Services\Auth;

use App\Models\User;
use Carbon\CarbonInterface;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Issues customer API tokens and keeps their expiry on a sliding window:
 * a token dies only after `auth_api.token.idle_ttl_days` without any use.
 */
final class ApiTokenService
{
    public function issue(User $user): NewAccessToken
    {
        return $user->createToken(
            (string) config('auth_api.token.name', 'flutter'),
            config('auth_api.scopes', ['user:read']),
            $this->nextExpiry(),
        );
    }

    /** Called for every successfully authenticated bearer token. */
    public function slide(PersonalAccessToken $token): void
    {
        if ($token->tokenable_type !== (new User())->getMorphClass()) {
            return;
        }

        $nextExpiry = $this->nextExpiry();

        // Skip the write when the stored expiry is already (nearly) right. This
        // also means a changed idle_ttl_days is picked up on the next touch.
        if ($token->expires_at !== null && abs($token->expires_at->getTimestamp() - $nextExpiry->getTimestamp()) < $this->touchIntervalSeconds()) {
            return;
        }

        $token->forceFill(['expires_at' => $nextExpiry])->save();
    }

    private function nextExpiry(): CarbonInterface
    {
        return now()->addDays(max(1, (int) config('auth_api.token.idle_ttl_days', 180)));
    }

    private function touchIntervalSeconds(): int
    {
        return max(0, (int) config('auth_api.token.touch_interval_minutes', 60)) * 60;
    }
}
