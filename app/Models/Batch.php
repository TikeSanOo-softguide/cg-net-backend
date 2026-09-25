<?php

namespace App\Models;

use App\Enums\BatchStatus;
use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'batch_no',
    'total_value',
    'quantity',
    'status',
    'expires_at',
    'metadata',
])]
class Batch extends Model
{
    /** @use HasFactory<BatchFactory> */
    use HasFactory;

    protected $table = 'batches';

    protected function casts(): array
    {
        return [
            'total_value' => 'integer',
            'quantity' => 'integer',
            'expires_at' => 'date',
            'status' => BatchStatus::class,
            'metadata' => 'array',
        ];
    }

    public function topUpCards(): HasMany
    {
        return $this->hasMany(TopUpCard::class);
    }
}
