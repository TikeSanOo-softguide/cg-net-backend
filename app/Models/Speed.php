<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Speed extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['mbps'];

    /**
     * The packages that belong to this speed tier.
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }
}
