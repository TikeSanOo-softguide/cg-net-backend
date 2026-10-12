<?php

namespace App\Models;

use Database\Factories\OfficeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'cd', 'address'])]
class Office extends Model
{
    /** @use HasFactory<OfficeFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'offices';

    protected function casts(): array
    {
        return ['cd' => 'string'];
    }

    public function topUpCards(): HasMany
    {
        return $this->hasMany(TopUpCard::class, 'office_id');
    }
}