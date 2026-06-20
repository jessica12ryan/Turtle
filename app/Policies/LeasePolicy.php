<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\User;

class LeasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lease $lease): bool
    {
        if ($user->isStaff()) {
            return $user->companies->contains($lease->property->company_id);
        }
        return $lease->property->tenants->contains($user);
    }

    public function create(User $user): bool
    {
        return $user->isLandlord() || $user->isPropertyManager();
    }

    public function delete(User $user, Lease $lease): bool
    {
        return ($user->isLandlord() || $user->isPropertyManager()) && $user->companies->contains($lease->property->company_id);
    }
}
