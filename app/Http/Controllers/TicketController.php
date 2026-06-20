<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Models\Property;
use App\Http\Requests\StoreTicketRequest;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Enums\TicketPriority;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketStatusChanged;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Ticket::query();

        if ($user->isTenant()) {
            $query->where('tenant_id', $user->id);
        } elseif ($user->isMaintenance()) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereIn('property_id', 
                      Property::whereIn('company_id', $user->companies->pluck('id'))->pluck('id')
                  );
            });
        } else {
            $query->whereIn('property_id',
                Property::whereIn('company_id', $user->companies->pluck('id'))->pluck('id')
            );
        }

        $tickets = $query->with(['property', 'tenant', 'assignee'])
            ->latest()
            ->paginate(20);

        return view('tickets.index', compact('tickets'));
    }

    public function create(Request $request)
    {
        $properties = $request->user()->properties()->with('company')->get();
        $categories = TicketCategory::cases();
        $priorities = TicketPriority::cases();
        return view('tickets.create', compact('properties', 'categories', 'priorities'));
    }

    public function store(StoreTicketRequest $request)
    {
        $ticket = Ticket::create([
            'property_id' => $request->property_id,
            'tenant_id' => $request->user()->id,
            'subject' => $request->subject,
            'description' => $request->description,
            'category' => $request->category,
            'priority' => $request->priority,
            'status' => 'open',
        ]);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket created successfully.');
    }

    public function show(Request $request, Ticket $ticket)
    {
        $user = $request->user();
        if ($user->isTenant() && $ticket->tenant_id !== $user->id) {
            abort(403);
        }
        if ($user->isStaff() && !$user->companies->contains($ticket->property->company_id)) {
            abort(403);
        }

        $ticket->load(['property.company', 'tenant', 'assignee', 'comments.user']);
        $categories = TicketCategory::cases();
        $statuses = TicketStatus::cases();
        $priorities = TicketPriority::cases();

        $staffUsers = User::staff()
            ->whereHas('companies', function ($q) use ($ticket) {
                $q->where('companies.id', $ticket->property->company_id);
            })
            ->get();

        return view('tickets.show', compact('ticket', 'categories', 'statuses', 'priorities', 'staffUsers'));
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $request->validate(['assigned_to' => 'required|exists:users,id']);
        $ticket->update(['assigned_to' => $request->assigned_to, 'status' => 'in_progress']);
        if ($ticket->assignee) {
            $ticket->assignee->notify(new TicketAssigned($ticket));
        }
        return redirect()->back()->with('success', 'Ticket assigned successfully.');
    }

    public function status(Request $request, Ticket $ticket)
    {
        $request->validate(['status' => 'required|string|in:open,in_progress,resolved,closed']);
        $ticket->update(['status' => $request->status]);
        $ticket->tenant->notify(new TicketStatusChanged($ticket));
        return redirect()->back()->with('success', 'Ticket status updated successfully.');
    }

    public function comment(Request $request, Ticket $ticket)
    {
        $request->validate(['body' => 'required|string']);
        $ticket->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->body,
            'is_internal' => $request->boolean('is_internal', false) && $request->user()->isStaff(),
        ]);
        return redirect()->back()->with('success', 'Comment added successfully.');
    }
}
