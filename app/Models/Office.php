<?php

namespace App\Models;

use Database\Factories\OfficeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name_en',
    'name_mm',
    'name_zh',
    'cd',
    'address_en',
    'address_mm',
    'address_zh',
    'phone_num1',
    'phone_num2',
    'phone_num3',
    'telegram_link',
    'wechat_link',
    'viber_link',
])]
class Office extends Model
{
    /** @use HasFactory<OfficeFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'offices';

    protected function casts(): array
    {
        return ['cd' => 'string'];
    }

    public function localizedName(?string $locale = null): string
    {
        return match ($locale ?? app()->getLocale()) {
            'my' => $this->name_mm,
            'zh' => $this->name_zh,
            default => $this->name_en,
        };
    }

    public function localizedAddress(?string $locale = null): string
    {
        return match ($locale ?? app()->getLocale()) {
            'my' => $this->address_mm,
            'zh' => $this->address_zh,
            default => $this->address_en,
        };
    }

    public function topUpCards(): HasMany
    {
        return $this->hasMany(TopUpCard::class, 'office_id');
    }
}
