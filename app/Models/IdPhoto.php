<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[
    Fillable([
        'installation_application_id',
        'image_url'
    ]),
]
class IdPhoto extends Model
{
    use HasFactory;

    public function installation(): BelongsTo
    {
        return $this->belongsTo(InstallationApplication::class, 'installation_application_id');
    }
}
