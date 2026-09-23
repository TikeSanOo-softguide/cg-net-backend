<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatFlowOption extends Model
{
    protected $fillable = [
        'option_en',
        'option_my',
        'option_zh',
        'step_id',
        'action',
        'next_step_id',
        'url',
        'reply_text_en',
        'reply_text_my',
        'reply_text_zh',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(
            ChatFlowStep::class,
            'step_id'
        );
    }

    public function nextStep(): BelongsTo
    {
        return $this->belongsTo(
            ChatFlowStep::class,
            'next_step_id'
        );
    }
}
