<?php

namespace App\Models;

use App\Enums\LanguagePref;
use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title',
    'image_path',
    'link_url',
    'sort_order',
    'is_active',
    'lang',
    'starts_at',
    'ends_at',
])]
class Banner extends Model
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'lang' => LanguagePref::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
