<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\Property;
use App\Http\Requests\StoreLeaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeaseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->isStaff()) {
            $companyIds = $user->companies->pluck('id');
            $leases = Lease::whereIn('property_id', 
                Property::whereIn('company_id', $companyIds)->pluck('id')
            )->with(['property.company', 'documents'])->latest()->get();
        } else {
            $propertyIds = $user->properties->pluck('id');
            $leases = Lease::whereIn('property_id', $propertyIds)
                ->with(['property', 'documents'])->latest()->get();
        }
        return view('leases.index', compact('leases'));
    }

    public function create(Request $request)
    {
        $companyIds = $request->user()->companies->pluck('id');
        $properties = Property::whereIn('company_id', $companyIds)->get();
        return view('leases.create', compact('properties'));
    }

    public function store(StoreLeaseRequest $request)
    {
        $lease = Lease::create([
            'property_id' => $request->property_id,
            'title' => $request->title,
            'description' => $request->description,
            'uploaded_by' => $request->user()->id,
        ]);

        foreach ($request->file('documents', []) as $file) {
            $path = $file->store('leases/' . $lease->id, 'local');
            $lease->documents()->create([
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return redirect()->route('leases.show', $lease)->with('success', 'Lease uploaded successfully.');
    }

    public function show(Request $request, Lease $lease)
    {
        $user = $request->user();
        if ($user->isStaff() && !$user->companies->contains($lease->property->company_id)) {
            abort(403);
        }
        if ($user->isTenant() && !$lease->property->tenants->contains($user)) {
            abort(403);
        }
        $lease->load(['property.company', 'documents', 'uploader']);
        return view('leases.show', compact('lease'));
    }

    public function destroy(Request $request, Lease $lease)
    {
        if (!$request->user()->companies->contains($lease->property->company_id)) {
            abort(403);
        }
        $lease->delete();
        return redirect()->route('leases.index')->with('success', 'Lease archived successfully.');
    }
}
