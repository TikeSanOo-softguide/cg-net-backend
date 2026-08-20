<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Database\Factories\InstallationApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'region_id',
    'id_type',
    'id_number',
    'plan_id',
    'photo_path',
    'latitude',
    'longitude',
    'address',
    'note',
    'status',
])]
class InstallationApplication extends Model
{
    /** @use HasFactory<InstallationApplicationFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => ReviewStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'plan_id');
    }
}
