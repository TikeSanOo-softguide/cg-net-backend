<?php

namespace App\Models;

use App\Enums\PushScheduleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'title_en',
    'title_zh',
    'title_my',
    'scheduled_at',
    'status',
    'sent_at',
    'error_message',
    'created_by',
])]
class PushSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'status' => PushScheduleStatus::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
