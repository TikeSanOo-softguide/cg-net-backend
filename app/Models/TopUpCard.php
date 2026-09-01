<?php

namespace App\Models;

use App\Enums\TopUpCardStatus;
use Database\Factories\TopUpCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'serial_no',
    'pin',
    'amount',
    'expires_at',
    'redeemed_at',
    'redeemed_by',
    'status',
])]
#[Hidden(['pin'])]
class TopUpCard extends Model
{
    /** @use HasFactory<TopUpCardFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'top_up_card';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'date',
            'redeemed_at' => 'datetime',
            'status' => TopUpCardStatus::class,
        ];
    }

    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }
}
