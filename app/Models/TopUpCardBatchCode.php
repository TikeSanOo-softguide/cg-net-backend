<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['amount', 'batch_code'])]
class TopUpCardBatchCode extends Model
{
    protected $table = 'top_up_card_batch_codes';

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }
}
