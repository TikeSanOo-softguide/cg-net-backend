<?php

namespace App\Models;

use App\Enums\UserLogEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLog extends Model
{
    use HasFactory;
    protected $table = 'user_log';
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'event', 'ip_address', 'user_agent', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function latestAuthenticationEventFor(User $user): ?self
    {
        return static::query()
            ->where('user_id', $user->getKey())
            ->whereIn('event', [
                UserLogEvent::LoginSuccess->value,
                UserLogEvent::RegisterComplete->value,
                UserLogEvent::Logout->value,
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public function deviceId(): ?string
    {
        if ($this->event === UserLogEvent::Logout->value || !is_array($this->metadata)) {
            return null;
        }

        $deviceId = $this->metadata['device_id'] ?? null;

        return is_string($deviceId) && trim($deviceId) !== '' ? $deviceId : null;
    }

}
