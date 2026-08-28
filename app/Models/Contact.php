<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[Fillable([
    'contact_point',
    'created_by',
    'updated_by',
    'deleted_by',
])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Contact $contact) {
            $contact->created_by = Auth::id();
        });

        static::updating(function (Contact $contact) {
            $contact->updated_by = Auth::id();
        });

        static::deleting(function (Contact $contact) {
            $contact->deleted_by = Auth::id();
            $contact->saveQuietly();
        });
    }
}
