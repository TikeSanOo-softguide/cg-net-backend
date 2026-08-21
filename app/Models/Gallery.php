<?php

namespace App\Models;

use App\Enums\LanguagePref;
use Database\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'image_path',
    'label',
    'lang',
])]
class Gallery extends Model
{
    /** @use HasFactory<GalleryFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'gallery';

    protected function casts(): array
    {
        return [
            'lang' => LanguagePref::class,
        ];
    }
}
