<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['requested_by', 'status', 'results', 'error', 'checked_at'])]
class LedgerHealthSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'results' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
