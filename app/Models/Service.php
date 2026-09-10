<?php

namespace App\Models;

use App\Enums\ServiceStatus;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[
    Fillable([
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
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ServiceStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Service $service) {
            $service->created_by = Auth::id();
        });

        static::updating(function (Service $service) {
            $service->updated_by = Auth::id();
        });

        static::deleting(function (Service $service) {
            $service->deleted_by = Auth::id();
            $service->saveQuietly();
        });
    }
}
