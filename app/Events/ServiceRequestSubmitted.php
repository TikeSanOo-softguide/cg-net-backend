<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ServiceRequestSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * $type identifies the source request and must be supported by the notification listener
     * and the admin notification's request model/table mappings.
     */
    public function __construct(
        public readonly string $type,
        public readonly int $requestId,
    ) {}
}
