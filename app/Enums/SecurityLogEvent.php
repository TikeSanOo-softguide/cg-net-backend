<?php

namespace App\Enums;

enum SecurityLogEvent: string
{
    case LoginFailed = 'login_failed';
    case LoginBlocked = 'login_blocked';
    case OtpRequestLimitExceeded = 'otp_request_limit_exceeded';
    case UnauthorizedAccess = 'unauthorized_access';

    /** @return list<string> */
    public static function values(): array
    {
        $values = array_map(static fn(self $event): string => $event->value, self::cases());
        sort($values, SORT_STRING);

        return $values;
    }
}
