<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Database\Factories\ChangePlanRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[
    Fillable([
        'user_id',
        'broadband_account_number',
        'service_type',
        'ticket_number',
        'current_package_detail',
        'new_package_detail',
        'preferred_date',
        'contact_name',
        'contact_phone',
        'note',
        'status',
        'admin_id',
    ]),
]
class ChangePlanRequest extends Model
{
    /** @use HasFactory<ChangePlanRequestFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'status' => RequestStatus::class,
            'current_package_detail' => 'array',
            'new_package_detail' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function packageDetailRules(string $key): array
    {
        return [
            $key => ['required', 'array'],
            "{$key}.package_id" => ['nullable', 'integer'],
            "{$key}.price" => ['required', 'numeric', 'min:0'],
            "{$key}.network_name" => ['nullable', 'array'],
            "{$key}.network_name.en" => ['nullable', 'string', 'max:255'],
            "{$key}.network_name.my" => ['nullable', 'string', 'max:255'],
            "{$key}.network_name.zh" => ['nullable', 'string', 'max:255'],
            "{$key}.speed" => ['nullable', 'array'],
            "{$key}.speed.mbps" => ['nullable', 'numeric', 'min:0'],
            "{$key}.term" => ['nullable', 'array'],
            "{$key}.term.months" => ['nullable', 'integer', 'min:1'],
            "{$key}.term.cycle" => ['nullable'],
            "{$key}.term.cycle_unit" => ['nullable', 'string', 'max:32'],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
