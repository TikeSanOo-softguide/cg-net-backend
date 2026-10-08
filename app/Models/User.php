<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use LogicException;

#[Fillable(['phone', 'name', 'password', 'status', 'broadband_account_number', 'lang'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Customer accounts cannot be deleted. Suspend the account instead.');
        });
        static::forceDeleting(function (): never {
            throw new LogicException('Customer accounts cannot be deleted. Suspend the account instead.');
        });

        // API tokens live on a long sliding window, so account changes that should
        // end old sessions must do it here, whichever screen or endpoint made them.
        static::updated(function (User $user): void {
            if (
                $user->wasChanged('status') &&
                in_array($user->status, [UserStatus::Suspended, UserStatus::Deactivated], true)
            ) {
                $user->revokeSessions();

                return;
            }

            // Password changed sign out every other session.
            // The session making the change stays valid.
            if ($user->wasChanged('password')) {
                $user->revokeSessions(keepCurrentToken: true);
            }
        });
    }

    /**
     * Sign the customer out of the app.
     *
     * With $keepCurrentToken and an authenticated API token on this instance
     * (a customer changing their own password), only the other tokens are removed
     * and push registrations are kept. Otherwise (admin action, no current token)
     * every token and device token is removed.
     */
    public function revokeSessions(bool $keepCurrentToken = false): void
    {
        $current = $keepCurrentToken ? $this->currentAccessToken() : null;

        if ($current instanceof PersonalAccessToken) {
            $this->tokens()->whereKeyNot($current->getKey())->delete();

            return;
        }

        $this->tokens()->delete();
        $this->deviceTokens()->delete();
    }

    /**
     * Safety net: whatever writes a phone (admin form, seeder, tinker) it is stored
     * without a leading "+", the canonical format login and OTP look up.
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn(?string $value): ?string => $value === null ? null : ltrim($value, '+'));
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    public function customerPackages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }

    public function packageOrders(): HasMany
    {
        return $this->hasMany(PackageOrder::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function redeemedTopUpCards(): HasMany
    {
        return $this->hasMany(TopUpCard::class, 'redeemed_by');
    }

    public function installationApplications(): HasMany
    {
        return $this->hasMany(InstallationApplication::class);
    }

    public function cpeDevices(): HasMany
    {
        return $this->hasMany(CpeDevice::class);
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

    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * @return list<string>
     */
    public function routeNotificationForFcm(): array
    {
        return $this->deviceTokens()->pluck('token')->all();
    }
}
