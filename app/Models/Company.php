<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'city',
        'province',
        'postal_code',
        'phone',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->whereIn('role', ['landlord', 'property_manager', 'maintenance']);
    }

    public function landlords(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->where('role', 'landlord');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
