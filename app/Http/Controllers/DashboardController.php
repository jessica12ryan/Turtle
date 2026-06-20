<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Property;
use App\Models\Ticket;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isTenant()) {
            $properties = $user->properties()->with('company')->get();
            $tickets = $user->tickets()->with('property')->latest()->take(5)->get();
            $leases = \App\Models\Lease::whereIn('property_id', $properties->pluck('id'))
                ->with('documents')
                ->latest()
                ->take(5)
                ->get();

            return view('dashboard.tenant', compact('properties', 'tickets', 'leases'));
        }

        $companyIds = $user->companies->pluck('id');
        $companies = $user->companies;
        $properties = Property::whereIn('company_id', $companyIds)->count();
        $activeTenants = \App\Models\PropertyTenant::whereIn('property_id', 
            Property::whereIn('company_id', $companyIds)->pluck('id')
        )->whereNull('moved_out_at')->count();
        $openTickets = Ticket::whereIn('property_id', 
            Property::whereIn('company_id', $companyIds)->pluck('id')
        )->open()->count();
        $recentTickets = Ticket::whereIn('property_id',
            Property::whereIn('company_id', $companyIds)->pluck('id')
        )->with(['property', 'tenant'])->latest()->take(5)->get();

        return view('dashboard.staff', compact('companies', 'properties', 'activeTenants', 'openTickets', 'recentTickets'));
    }
}
