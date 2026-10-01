<?php

namespace App\Models;

use App\Enums\LedgerHealthScanType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[
    Fillable([
        'requested_by',
        'type',
        'window_start',
        'window_end',
        'status',
        'results',
        'error',
        'checked_at',
    ]),
]
class LedgerHealthSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'type' => LedgerHealthScanType::class,
            'results' => 'array',
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }
}
