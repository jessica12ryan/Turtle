<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user');
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_tenant', 'tenant_id')
            ->withPivot(['is_main_tenant', 'assigned_at', 'moved_out_at'])
            ->wherePivotNull('moved_out_at');
    }

    public function managedProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'company_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'tenant_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function propertyTenant()
    {
        return $this->hasOne(\App\Models\PropertyTenant::class, 'tenant_id');
    }

    public function isLandlord(): bool
    {
        return $this->role === 'landlord';
    }

    public function isPropertyManager(): bool
    {
        return $this->role === 'property_manager';
    }

    public function isMaintenance(): bool
    {
        return $this->role === 'maintenance';
    }

    public function isTenant(): bool
    {
        return $this->role === 'tenant';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['landlord', 'property_manager', 'maintenance']);
    }

    public function scopeStaff($query)
    {
        return $query->whereIn('role', ['landlord', 'property_manager', 'maintenance']);
    }

    public function scopeTenants($query)
    {
        return $query->where('role', 'tenant');
    }
}
