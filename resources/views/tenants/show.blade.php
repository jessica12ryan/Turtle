<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $tenant->name }}</h2>
            @if(Auth::user()->isStaff())
            <a href="{{ route('tenants.edit', $tenant) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Edit</a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><dt class="text-sm font-medium text-gray-500">Name</dt><dd class="mt-1 text-sm text-gray-900">{{ $tenant->name }}</dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">Email</dt><dd class="mt-1 text-sm text-gray-900">{{ $tenant->email }}</dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">Property</dt><dd class="mt-1 text-sm text-gray-900">
                            @if($tenant->propertyTenant && $tenant->propertyTenant->property)
                                <a href="{{ route('properties.show', $tenant->propertyTenant->property) }}" class="text-teal-600 hover:text-teal-900">{{ $tenant->propertyTenant->property->name }}</a>
                            @else
                                Unassigned
                            @endif
                        </dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">Status</dt><dd class="mt-1 text-sm text-gray-900">
                            @if($tenant->propertyTenant && $tenant->propertyTenant->moved_out_at)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Moved Out ({{ $tenant->propertyTenant->moved_out_at->format('M d, Y') }})</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                            @endif
                        </dd></div>
                        @if($tenant->propertyTenant && $tenant->propertyTenant->is_main_tenant)
                        <div><dt class="text-sm font-medium text-gray-500">Type</dt><dd class="mt-1"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-teal-100 text-teal-800">Main Tenant</span></dd></div>
                        @endif
                    </dl>

                    @if(Auth::user()->isStaff() && !($tenant->propertyTenant && $tenant->propertyTenant->moved_out_at))
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <form method="POST" action="{{ route('tenants.move-out', $tenant) }}" class="flex items-center space-x-3">
                            @csrf
                            <label class="text-sm font-medium text-gray-700">Schedule Move-Out Date:</label>
                            <input type="date" name="moved_out_at" class="rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm" />
                            <button type="submit" class="inline-flex items-center px-3 py-1 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700">Schedule Move-Out</button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Tickets</h3>
                    @if($tenant->tickets->count() > 0)
                    <ul class="divide-y divide-gray-200">
                        @foreach($tenant->tickets as $ticket)
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
                    <p class="text-gray-500">No tickets from this tenant.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
