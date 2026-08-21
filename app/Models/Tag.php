<?php

namespace App\Models;

use App\Enums\LanguagePref;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'slug',
    'lang',
])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'lang' => LanguagePref::class,
        ];
    }

    public function news(): BelongsToMany
    {
        return $this->belongsToMany(News::class, 'news_tags')->withPivot('id');
    }
}
