<?php

namespace App\Models;

use App\Enums\NewsStatus;
use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[
    Fillable([
        'category_id',
        'title_en',
        'title_zh',
        'title_my',
        'slug',
        'description_en',
        'description_zh',
        'description_my',
        'image_url',
        'status',
    ]),
]
class News extends Model
{
    /** @use HasFactory<NewsFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => NewsStatus::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected static function booted(): void
    {
        static::creating(function (News $news) {
            $news->created_by = Auth::id();
        });

        static::updating(function (News $news) {
            $news->updated_by = Auth::id();
        });

        static::deleting(function (News $news) {
            $news->deleted_by = Auth::id();
            $news->saveQuietly();
        });
    }
}
