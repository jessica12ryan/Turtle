<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if($properties->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                @foreach($properties as $property)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900">{{ $property->name }}</h3>
                    <p class="text-sm text-gray-500">{{ $property->company->name }}</p>
                    <p class="text-sm text-gray-500">{{ $property->address }}, {{ $property->city }}, {{ $property->province }}</p>
                </div>
                @endforeach
            </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">My Recent Tickets</h3>
                        @if($tickets->count() > 0)
                        <ul class="divide-y divide-gray-200">
                            @foreach($tickets as $ticket)
                            <li class="py-3">
                                <a href="{{ route('tickets.show', $ticket) }}" class="text-teal-600 hover:text-teal-900">{{ $ticket->subject }}</a>
                                <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    @if($ticket->status === 'open') bg-yellow-100 text-yellow-800
                                    @elseif($ticket->status === 'in_progress') bg-blue-100 text-blue-800
                                    @elseif($ticket->status === 'resolved') bg-green-100 text-green-800
                                    @else bg-gray-100 text-gray-800 @endif
                                ">{{ str_replace('_', ' ', ucfirst($ticket->status)) }}</span>
                            </li>
                            @endforeach
                        </ul>
                        @else
                        <p class="text-gray-500">No tickets yet.</p>
                        @endif
                        <a href="{{ route('tickets.create') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-teal-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-700">Create Ticket</a>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Leases</h3>
                        @if($leases->count() > 0)
                        <ul class="divide-y divide-gray-200">
                            @foreach($leases as $lease)
                            <li class="py-3">
                                <a href="{{ route('leases.show', $lease) }}" class="text-teal-600 hover:text-teal-900">{{ $lease->title }}</a>
                                <p class="text-xs text-gray-500">{{ $lease->created_at->format('M d, Y') }}</p>
                            </li>
                            @endforeach
                        </ul>
                        @else
                        <p class="text-gray-500">No leases available.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
