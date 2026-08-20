<?php

namespace App\Models;

use App\Enums\ConnectionStatus;
use Database\Factories\CpeDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'broadband_account_id',
    'cpe_identifier',
    'ssid',
    'wifi_password',
    'connection_status',
])]
#[Hidden(['wifi_password'])]
class CpeDevice extends Model
{
    /** @use HasFactory<CpeDeviceFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'wifi_password' => 'encrypted',
            'connection_status' => ConnectionStatus::class,
        ];
    }

    public function broadbandAccount(): BelongsTo
    {
        return $this->belongsTo(BroadbandAccount::class);
    }
}
