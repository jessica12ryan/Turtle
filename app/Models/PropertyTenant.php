<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyTenant extends Pivot
{
    protected $table = 'property_tenant';

    protected $fillable = [
        'property_id',
        'tenant_id',
        'is_main_tenant',
        'assigned_at',
        'moved_out_at',
    ];

    protected function casts(): array
    {
        return [
            'is_main_tenant' => 'boolean',
            'assigned_at' => 'datetime',
            'moved_out_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('moved_out_at');
    }

    public function scopeMovedOut($query)
    {
        return $query->whereNotNull('moved_out_at');
    }
}
