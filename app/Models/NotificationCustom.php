<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use Database\Factories\NotificationCustomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'title',
    'body',
    'category',
    'is_read',
    'sent_at',
])]
class NotificationCustom extends Model
{
    /** @use HasFactory<NotificationCustomFactory> */
    use HasFactory;

    protected $table = 'notifications_custom';

    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'is_read' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
