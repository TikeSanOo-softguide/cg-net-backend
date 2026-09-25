<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'keyword',
    'response_en',
    'response_my',
    'response_zh',
])]
class QuickReply extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'quick_replies';

    protected function casts(): array
    {
        return [];
    }
}
