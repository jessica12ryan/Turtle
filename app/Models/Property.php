<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'address',
        'city',
        'province',
        'postal_code',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_tenant', 'property_id', 'tenant_id')
            ->withPivot(['is_main_tenant', 'assigned_at', 'moved_out_at'])
            ->wherePivotNull('moved_out_at');
    }

    public function allTenants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_tenant', 'property_id', 'tenant_id')
            ->withPivot(['is_main_tenant', 'assigned_at', 'moved_out_at']);
    }

    public function mainTenant(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_tenant', 'property_id', 'tenant_id')
            ->withPivot(['is_main_tenant', 'assigned_at', 'moved_out_at'])
            ->wherePivot('is_main_tenant', true);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
