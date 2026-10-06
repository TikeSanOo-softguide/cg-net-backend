<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[Fillable(['phone'])]
class SupportContact extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (SupportContact $supportContact) {
            $supportContact->created_by = Auth::id();
        });

        static::updating(function (SupportContact $supportContact) {
            $supportContact->updated_by = Auth::id();
        });

        static::deleting(function (SupportContact $supportContact) {
            $supportContact->deleted_by = Auth::id();
            $supportContact->saveQuietly();
        });
    }
}
