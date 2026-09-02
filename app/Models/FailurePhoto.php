<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FailurePhoto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'failure_photos';

    protected $fillable = [
        'failure_report_id',
        'image_url',
        'label',
    ];

    public function failureReport(): BelongsTo
    {
        return $this->belongsTo(FailureReport::class);
    }
}
