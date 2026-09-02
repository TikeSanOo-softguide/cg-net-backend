<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[Fillable(['name_en', 'name_zh', 'name_my', 'slug'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [];
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            $category->created_by = Auth::id();
        });

        static::updating(function (Category $category) {
            $category->updated_by = Auth::id();
        });

        static::deleting(function (Category $category) {
            $category->deleted_by = Auth::id();
            $category->saveQuietly();
        });
    }
}
