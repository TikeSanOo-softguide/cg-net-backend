<?php

namespace App\Models;

use App\Enums\NewsStatus;
use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
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
])]
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

}
