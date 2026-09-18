<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatFlowOption extends Model
{
    protected $fillable = [
        'step_id',
        'action',
        'next_step_id',
        'url',
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

    public function translations(): HasMany
    {
        return $this->hasMany(
            ChatFlowOptionTranslation::class,
            'option_id'
        );
    }

    public function replies(): HasMany
    {
        return $this->hasMany(
            ChatFlowOptionReply::class,
            'option_id'
        );
    }
}
