<?php

namespace App\Events;

use App\Enums\LedgerHealthScanType;
use Illuminate\Foundation\Events\Dispatchable;

class LedgerHealthScanFinished
{
    use Dispatchable;

    public function __construct(
        public readonly int $snapshotId,
        public readonly LedgerHealthScanType $type,
        public readonly bool $completed,
        public readonly bool $hasDiscrepancies,
    ) {}
}
