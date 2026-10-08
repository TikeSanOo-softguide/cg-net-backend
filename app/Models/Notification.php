<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_id',
    'category',
    'is_read',
    'sent_at',
    'action_type',
    'action_id',
    'templateable_type',
    'templateable_id',
    'template_data',
])]
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'is_read' => 'boolean',
            'sent_at' => 'datetime',
            'template_data' => 'array',
        ];
    }

    public function templateable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
