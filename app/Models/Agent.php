<?php

namespace App\Models;

use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'cd', 'address'])]

class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'agents';

    protected function casts(): array
    {
        return [
            'cd' => 'integer',
        ];
    }

    public function topUpCards(): HasMany
    {
        return $this->hasMany(TopUpCard::class);
    }
}
