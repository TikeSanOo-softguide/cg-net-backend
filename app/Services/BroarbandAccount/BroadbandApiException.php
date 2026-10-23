<?php

namespace App\Services\BroarbandAccount;

use RuntimeException;

class BroadbandApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
