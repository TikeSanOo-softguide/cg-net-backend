<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class FinancialSummaryFinished
{
    use Dispatchable;

    public function __construct(public readonly int $snapshotId, public readonly bool $completed) {}
}
