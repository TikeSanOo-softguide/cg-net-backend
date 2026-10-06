<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[Fillable(['title_en', 'title_zh', 'title_my', 'description_en', 'description_zh', 'description_my'])]
class Faq extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Faq $faq) {
            $faq->created_by = Auth::id();
        });

        static::updating(function (Faq $faq) {
            $faq->updated_by = Auth::id();
        });

        static::deleting(function (Faq $faq) {
            $faq->deleted_by = Auth::id();
            $faq->saveQuietly();
        });
    }
}
