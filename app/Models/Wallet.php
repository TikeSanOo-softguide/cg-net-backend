<?php

namespace App\Models;

use App\Enums\WalletStatus;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
#[Fillable(['user_id', 'balance', 'status', 'version', 'created_by', 'updated_by', 'deleted_by'])]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'wallets';

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'version' => 'integer',
            'status' => WalletStatus::class,
        ];
    }

    /**
     * The owner of the wallet.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The user/admin who created the wallet.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The admin or user who last updated/modified the wallet.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The admin or user who soft-deleted the wallet.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
