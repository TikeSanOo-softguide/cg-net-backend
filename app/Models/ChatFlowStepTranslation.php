<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'step_id',
    'language',
    'message',
])]
class ChatFlowStepTranslation extends Model
{
    public function step(): BelongsTo
    {
        return $this->belongsTo(ChatFlowStep::class, 'step_id');
    }
}
