<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $property->name }}</h2>
            @if(Auth::user()->isStaff())
            <div>
                <a href="{{ route('properties.edit', $property) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 mr-2">Edit</a>
                <a href="{{ route('leases.create', ['property_id' => $property->id]) }}" class="inline-flex items-center px-4 py-2 bg-teal-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-700">Upload Lease</a>
            </div>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><dt class="text-sm font-medium text-gray-500">Company</dt><dd class="mt-1 text-sm text-gray-900">{{ $property->company->name }}</dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">Address</dt><dd class="mt-1 text-sm text-gray-900">{{ $property->address ?? 'N/A' }}</dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">City/Town</dt><dd class="mt-1 text-sm text-gray-900">{{ $property->city ?? 'N/A' }}</dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">Province</dt><dd class="mt-1 text-sm text-gray-900">{{ $property->province ?? 'N/A' }}</dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">Postal Code</dt><dd class="mt-1 text-sm text-gray-900">{{ $property->postal_code ?? 'N/A' }}</dd></div>
                    </dl>
                </div>
            </div>

            @if(Auth::user()->isStaff())
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Tenants ({{ $property->tenants->count() }})</h3>
                        <a href="{{ route('tenants.create', ['property_id' => $property->id]) }}" class="inline-flex items-center px-3 py-1 bg-teal-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-700">Add Tenant</a>
                    </div>
                    @if($property->tenants->count() > 0)
                    <ul class="divide-y divide-gray-200">
                        @foreach($property->tenants as $tenant)
                        <li class="py-3 flex justify-between items-center">
                            <div>
                                <a href="{{ route('tenants.show', $tenant) }}" class="text-teal-600 hover:text-teal-900">{{ $tenant->name }}</a>
                                <span class="text-sm text-gray-500 ml-2">{{ $tenant->email }}</span>
                                @if($tenant->pivot->is_main_tenant)
                                <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-teal-100 text-teal-800">Main</span>
                                @endif
                            </div>
                        </li>
                        @endforeach
                    </ul>
                    @else
                    <p class="text-gray-500">No tenants assigned.</p>
                    @endif
                </div>
            </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Leases ({{ $property->leases->count() }})</h3>
                    @if($property->leases->count() > 0)
                    <ul class="divide-y divide-gray-200">
                        @foreach($property->leases as $lease)
                        <li class="py-3">
                            <a href="{{ route('leases.show', $lease) }}" class="text-teal-600 hover:text-teal-900">{{ $lease->title }}</a>
                            <span class="text-sm text-gray-500 ml-2">({{ $lease->documents->count() }} documents)</span>
                        </li>
                        @endforeach
                    </ul>
                    @else
                    <p class="text-gray-500">No leases uploaded yet.</p>
                    @endif
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Recent Tickets</h3>
                        @if(Auth::user()->isStaff())
                        <span class="text-sm text-gray-500"><a href="{{ route('tickets.index') }}" class="text-teal-600 hover:text-teal-900">View All</a></span>
                        @endif
                    </div>
                    @if($property->tickets->count() > 0)
                    <ul class="divide-y divide-gray-200">
                        @foreach($property->tickets as $ticket)
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
                    <p class="text-gray-500">No tickets for this property.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
