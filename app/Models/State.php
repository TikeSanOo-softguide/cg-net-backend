<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name_en', 'name_zh', 'name_my'])]
class State extends Model
{
    use HasFactory, SoftDeletes;

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }
}
