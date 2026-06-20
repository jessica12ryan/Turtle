<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Company $company): bool
    {
        return $user->isStaff() && $user->companies->contains($company);
    }

    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    public function update(User $user, Company $company): bool
    {
        return $user->isLandlord() && $user->companies->contains($company);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->isLandlord() && $user->companies->contains($company);
    }
}
