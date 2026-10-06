<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'cd', 'address'])]
class Office extends Model
{
    use SoftDeletes;

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