<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Property;
use App\Models\PropertyTenant;
use App\Http\Requests\InviteTenantRequest;
use App\Notifications\TenantInvited;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyIds = $user->companies->pluck('id');
        $propertyIds = Property::whereIn('company_id', $companyIds)->pluck('id');

        $tenants = User::where('role', 'tenant')
            ->whereHas('propertyTenant', function ($q) use ($propertyIds) {
                $q->whereIn('property_id', $propertyIds);
            })
            ->with(['propertyTenant.property'])
            ->get();

        return view('tenants.index', compact('tenants'));
    }

    public function create(Request $request)
    {
        $companyIds = $request->user()->companies->pluck('id');
        $properties = Property::whereIn('company_id', $companyIds)->get();
        return view('tenants.create', compact('properties'));
    }

    public function store(InviteTenantRequest $request)
    {
        $password = Str::random(12);

        $tenant = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($password),
            'role' => 'tenant',
            'must_change_password' => true,
        ]);

        $existingMain = PropertyTenant::where('property_id', $request->property_id)
            ->where('is_main_tenant', true)
            ->whereNull('moved_out_at')
            ->exists();

        PropertyTenant::create([
            'property_id' => $request->property_id,
            'tenant_id' => $tenant->id,
            'is_main_tenant' => !$existingMain && $request->boolean('is_main_tenant', true),
            'assigned_at' => now(),
        ]);

        $tenant->notify(new TenantInvited($tenant, $password));

        return redirect()->route('tenants.index')->with('success', 'Tenant invited successfully. They will receive an email with their temporary password.');
    }

    public function show(Request $request, User $tenant)
    {
        if ($tenant->role !== 'tenant') abort(404);
        $tenant->load(['propertyTenant.property.company', 'tickets' => function ($q) {
            $q->latest()->limit(10);
        }]);
        return view('tenants.show', compact('tenant'));
    }

    public function edit(Request $request, User $tenant)
    {
        if ($tenant->role !== 'tenant') abort(404);
        $companyIds = $request->user()->companies->pluck('id');
        $properties = Property::whereIn('company_id', $companyIds)->get();
        return view('tenants.edit', compact('tenant', 'properties'));
    }

    public function update(Request $request, User $tenant)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $tenant->update(['name' => $request->name]);
        return redirect()->route('tenants.show', $tenant)->with('success', 'Tenant updated successfully.');
    }

    public function moveOut(Request $request, User $tenant)
    {
        $request->validate([
            'moved_out_at' => 'nullable|date',
        ]);

        $pivot = PropertyTenant::where('tenant_id', $tenant->id)
            ->whereNull('moved_out_at')
            ->firstOrFail();

        $pivot->update([
            'moved_out_at' => $request->moved_out_at ?? now(),
        ]);

        return redirect()->route('tenants.show', $tenant)->with('success', 'Tenant move-out scheduled successfully.');
    }
}
