<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatFlowStep extends Model
{
    protected $fillable = [
        'name',
        'is_start',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_start' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(
            ChatFlowStepTranslation::class,
            'step_id'
        );
    }

    public function options(): HasMany
    {
        return $this->hasMany(
            ChatFlowOption::class,
            'step_id'
        )->orderBy('sort_order');
    }

    public function incomingOptions(): HasMany
    {
        return $this->hasMany(
            ChatFlowOption::class,
            'next_step_id'
        );
    }
}
