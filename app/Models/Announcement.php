<?php

namespace App\Models;

use App\Enums\AnnouncementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'type',
    'title_en',
    'title_zh',
    'title_my',
    'content_en',
    'content_zh',
    'content_my',
    'start_date',
    'end_date',
    'is_active',
    'push_sent_at',
])]
class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_active' => 'boolean',
            'push_sent_at' => 'datetime',
        ];
    }
}
