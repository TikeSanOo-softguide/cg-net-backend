<?php

namespace App\Models;

use App\Enums\BannerType;
use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[
    Fillable([
        'image_url_en',
        'image_url_zh',
        'image_url_my',
        'sort_order',
        'is_active',
        'type',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
        'deleted_by',
    ]),
]
class Banner extends Model
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Banner $banner) {
            $banner->created_by = Auth::id();
        });

        static::updating(function (Banner $banner) {
            $banner->updated_by = Auth::id();
        });

        static::deleting(function (Banner $banner) {
            $banner->deleted_by = Auth::id();
            $banner->saveQuietly();
        });
    }
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'type' => BannerType::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
