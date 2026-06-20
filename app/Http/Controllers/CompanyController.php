<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = $request->user()->companies()->withCount('properties')->get();
        return view('companies.index', compact('companies'));
    }

    public function create()
    {
        return view('companies.create');
    }

    public function store(StoreCompanyRequest $request)
    {
        $company = Company::create($request->validated());
        $request->user()->companies()->attach($company);
        return redirect()->route('companies.show', $company)->with('success', 'Company created successfully.');
    }

    public function show(Request $request, Company $company)
    {
        if (!$request->user()->companies->contains($company) && !$request->user()->isLandlord()) {
            abort(403);
        }
        $company->load(['properties', 'users']);
        return view('companies.show', compact('company'));
    }

    public function edit(Request $request, Company $company)
    {
        if (!$request->user()->companies->contains($company)) {
            abort(403);
        }
        return view('companies.edit', compact('company'));
    }

    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $company->update($request->validated());
        return redirect()->route('companies.show', $company)->with('success', 'Company updated successfully.');
    }

    public function destroy(Request $request, Company $company)
    {
        if (!$request->user()->companies->contains($company)) {
            abort(403);
        }
        $company->delete();
        return redirect()->route('dashboard')->with('success', 'Company archived successfully.');
    }
}
