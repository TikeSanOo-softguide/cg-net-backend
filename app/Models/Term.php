<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Term extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['months'];

    /**
     * The packages that belong to this contract term.
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }
}
