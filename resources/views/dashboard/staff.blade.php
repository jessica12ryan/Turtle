<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Companies</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900">{{ $companies->count() }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Properties</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900">{{ $properties }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Active Tenants</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900">{{ $activeTenants }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Open Tickets</div>
                    <div class="mt-1 text-3xl font-semibold {{ $openTickets > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $openTickets }}</div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Tickets</h3>
                    @if($recentTickets->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Property</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($recentTickets as $ticket)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <a href="{{ route('tickets.show', $ticket) }}" class="text-teal-600 hover:text-teal-900">{{ $ticket->subject }}</a>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $ticket->property->name }}</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($ticket->status === 'open') bg-yellow-100 text-yellow-800
                                            @elseif($ticket->status === 'in_progress') bg-blue-100 text-blue-800
                                            @elseif($ticket->status === 'resolved') bg-green-100 text-green-800
                                            @else bg-gray-100 text-gray-800 @endif
                                        ">
                                            {{ str_replace('_', ' ', ucfirst($ticket->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($ticket->priority === 'emergency') bg-red-100 text-red-800
                                            @elseif($ticket->priority === 'high') bg-orange-100 text-orange-800
                                            @else bg-gray-100 text-gray-800 @endif
                                        ">{{ ucfirst($ticket->priority) }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $ticket->created_at->diffForHumans() }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-gray-500">No recent tickets.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
