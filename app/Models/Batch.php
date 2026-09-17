<?php

namespace App\Models;

use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'batch_no',
    'amount',
    'quantity',
    'status',
    'expires_at',
])]
class Batch extends Model
{
    /** @use HasFactory<BatchFactory> */
    use HasFactory;

    protected $table = 'batches';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'quantity' => 'integer',
            'expires_at' => 'date',
        ];
    }

    public function topUpCards(): HasMany
    {
        return $this->hasMany(TopUpCard::class);
    }
}
