<?php

namespace App\Models;

use App\Models\ChatFlowOption;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'option_id',
    'language',
    'reply_text',
])]
class ChatFlowOptionReply extends Model
{
    public function option(): BelongsTo
    {
        return $this->belongsTo(ChatFlowOption::class, 'option_id');
    }
}
