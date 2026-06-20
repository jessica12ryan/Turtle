<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tickets</h2>
            @if(Auth::user()->isTenant())
            <a href="{{ route('tickets.create') }}" class="inline-flex items-center px-4 py-2 bg-teal-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-700">Create Ticket</a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($tickets->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                                    @if(Auth::user()->isStaff())<th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>@endif
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Property</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Assigned To</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($tickets as $ticket)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <a href="{{ route('tickets.show', $ticket) }}" class="text-teal-600 hover:text-teal-900 font-medium">{{ $ticket->subject }}</a>
                                    </td>
                                    @if(Auth::user()->isStaff())<td class="px-6 py-4 text-sm text-gray-500">{{ $ticket->tenant->name }}</td>@endif
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $ticket->property->name }}</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($ticket->status === 'open') bg-yellow-100 text-yellow-800
                                            @elseif($ticket->status === 'in_progress') bg-blue-100 text-blue-800
                                            @elseif($ticket->status === 'resolved') bg-green-100 text-green-800
                                            @else bg-gray-100 text-gray-800 @endif
                                        ">{{ str_replace('_', ' ', ucfirst($ticket->status)) }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($ticket->priority === 'emergency') bg-red-100 text-red-800
                                            @elseif($ticket->priority === 'high') bg-orange-100 text-orange-800
                                            @else bg-gray-100 text-gray-800 @endif
                                        ">{{ ucfirst($ticket->priority) }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $ticket->assignee->name ?? 'Unassigned' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $ticket->created_at->diffForHumans() }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $tickets->links() }}
                    </div>
                    @else
                    <p class="text-gray-500">No tickets found.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
