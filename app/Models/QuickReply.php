<?php

namespace App\Models;

use App\Enums\QuickReplyCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'keyword',
    'category',
    'response_en',
    'response_my',
    'response_zh',
])]
class QuickReply extends Model
{
    use SoftDeletes;

    protected $table = 'quick_replies';

    protected function casts(): array
    {
        return [
            'category' => QuickReplyCategory::class,
        ];
    }
}
