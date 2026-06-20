<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Property;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->isStaff()) {
            $companyIds = $user->companies->pluck('id');
            $properties = Property::whereIn('company_id', $companyIds)
                ->with('company')
                ->withCount(['tenants', 'tickets'])
                ->latest()
                ->get();
        } else {
            $properties = $user->properties()->with('company')->get();
        }
        return view('properties.index', compact('properties'));
    }

    public function create(Request $request)
    {
        $companies = $request->user()->companies;
        return view('properties.create', compact('companies'));
    }

    public function store(StorePropertyRequest $request)
    {
        $property = Property::create($request->validated());
        return redirect()->route('properties.show', $property)->with('success', 'Property created successfully.');
    }

    public function show(Request $request, Property $property)
    {
        $user = $request->user();
        if ($user->isTenant() && !$user->properties->contains($property)) {
            abort(403);
        }
        if ($user->isStaff() && !$user->companies->contains($property->company_id)) {
            abort(403);
        }
        $property->load(['company', 'tenants', 'leases.documents', 'tickets' => function ($q) {
            $q->latest()->limit(10);
        }]);
        return view('properties.show', compact('property'));
    }

    public function edit(Request $request, Property $property)
    {
        if (!$request->user()->companies->contains($property->company_id)) {
            abort(403);
        }
        $companies = $request->user()->companies;
        return view('properties.edit', compact('property', 'companies'));
    }

    public function update(UpdatePropertyRequest $request, Property $property)
    {
        $property->update($request->validated());
        return redirect()->route('properties.show', $property)->with('success', 'Property updated successfully.');
    }

    public function destroy(Request $request, Property $property)
    {
        if (!$request->user()->companies->contains($property->company_id)) {
            abort(403);
        }
        $property->delete();
        return redirect()->route('properties.index')->with('success', 'Property archived successfully.');
    }
}
