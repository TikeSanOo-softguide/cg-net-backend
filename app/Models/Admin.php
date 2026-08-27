<?php

namespace App\Models;

use App\Enums\AdminStatus;
use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['username', 'password', 'status'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected string $guard_name = 'web';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => AdminStatus::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->username;
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'agent_id');
    }
}
