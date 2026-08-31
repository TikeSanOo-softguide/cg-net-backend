<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'network_id',
        'speed_id',
        'term_id',
        'price',
        'image_url',
        'installation_fee',
        'includes_free_iptv',
        'is_active',
        'sort_order',
        'recommended',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'installation_fee' => 'decimal:2',
        'includes_free_iptv' => 'boolean',
        'is_active' => 'boolean',
        'recommended' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class);
    }

    public function speed(): BelongsTo
    {
        return $this->belongsTo(Speed::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'addon_package')->withTimestamps();
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
