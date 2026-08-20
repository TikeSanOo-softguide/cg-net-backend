<?php

namespace App\Models;

use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'data_gb',
    'price',
    'validity_days',
    'speed_mbps',
    'description',
    'is_active',
])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'data_gb' => 'integer',
            'price' => 'decimal:2',
            'validity_days' => 'integer',
            'speed_mbps' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function broadbandAccounts(): HasMany
    {
        return $this->hasMany(BroadbandAccount::class, 'current_package_id');
    }

    public function customerPackages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function installationApplications(): HasMany
    {
        return $this->hasMany(InstallationApplication::class, 'plan_id');
    }

    public function currentChangePlanRequests(): HasMany
    {
        return $this->hasMany(ChangePlanRequest::class, 'current_package_id');
    }

    public function newChangePlanRequests(): HasMany
    {
        return $this->hasMany(ChangePlanRequest::class, 'new_package_id');
    }
}
