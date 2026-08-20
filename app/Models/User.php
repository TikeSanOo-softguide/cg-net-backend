<?php

namespace App\Models;

use App\Enums\LanguagePref;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'phone',
    'name',
    'nrc_number',
    'email',
    'address',
    'language_pref',
    'status',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'language_pref' => LanguagePref::class,
            'status' => UserStatus::class,
        ];
    }

    public function broadbandAccounts(): HasMany
    {
        return $this->hasMany(BroadbandAccount::class);
    }

    public function customerPackages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function redeemedVouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'redeemed_by_user_id');
    }

    public function installationApplications(): HasMany
    {
        return $this->hasMany(InstallationApplication::class);
    }

    public function failureReports(): HasMany
    {
        return $this->hasMany(FailureReport::class);
    }

    public function relocationRequests(): HasMany
    {
        return $this->hasMany(RelocationRequest::class);
    }

    public function changePlanRequests(): HasMany
    {
        return $this->hasMany(ChangePlanRequest::class);
    }

    public function customNotifications(): HasMany
    {
        return $this->hasMany(NotificationCustom::class);
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }
}
