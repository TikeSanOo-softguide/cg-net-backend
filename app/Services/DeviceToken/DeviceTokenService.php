<?php

namespace App\Services\DeviceToken;

use App\Models\DeviceToken;
use App\Models\User;

class DeviceTokenService
{
    public static function register(
        User $user,
        ?string $token,
        ?string $platform = null,
    ): void {
        if ($token === null || trim($token) === '') {
            return;
        }

        DeviceToken::query()->updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'platform' => $platform,
                'last_used_at' => now(),
            ],
        );
    }
}
