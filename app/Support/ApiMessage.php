<?php

namespace App\Support;

use App\Enums\MessageCode;

final class ApiMessage
{
    /**
     * Stable code + level for frontend localization.
     * A `message` already in $body is kept so existing clients still read the same text.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function attach(MessageCode $code, array $body = []): array
    {
        return array_merge([
            'code' => $code->value,
            'level' => $code->level(),
            'message' => $code->message(),
        ], $body);
    }
}
