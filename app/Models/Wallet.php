<?php

namespace App\Models;

use App\Enums\WalletStatus;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    public function incrementVersion(): void
    {
        $this->version = (int) $this->version + 1;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class);
    }

    public function ledgerAccount(): HasOne
    {
        return $this->hasOne(LedgerAccount::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
