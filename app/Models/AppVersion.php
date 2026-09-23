<?php

namespace App\Models;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[
    Fillable([
        'platform',
        'version',
        'minimum_version',
        'download_url',
        'release_notes_en',
        'release_notes_zh',
        'release_notes_mm',
        'force_update',
        'status',
    ]),
]
class AppVersion extends Model
{
    protected $casts = [
        'force_update' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }
}
