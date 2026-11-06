<?php

namespace App\Support;

use App\Enums\MessageCode;
use App\Exceptions\ApiMessageException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class CustomerPassword
{
    public static function verify(User $user, string $password): void
    {
        if (!Hash::check($password, $user->getAuthPassword())) {
            throw new ApiMessageException(
                MessageCode::UserPasswordIncorrect,
                extra: [
                    'errors' => ['password' => [MessageCode::UserPasswordIncorrect->message()]],
                ],
            );
        }
    }
}
