<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Properties</h2>
            @if(Auth::user()->isStaff())
            <a href="{{ route('properties.create') }}" class="inline-flex items-center px-4 py-2 bg-teal-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-700">Add Property</a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($properties->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($properties as $property)
                        <div class="border rounded-lg p-4 hover:shadow-md transition">
                            <h3 class="font-medium text-lg"><a href="{{ route('properties.show', $property) }}" class="text-teal-600 hover:text-teal-900">{{ $property->name }}</a></h3>
                            <p class="text-sm text-gray-500">{{ $property->company->name ?? '' }}</p>
                            <p class="text-sm text-gray-500">{{ $property->address }}, {{ $property->city }}</p>
                            @if(Auth::user()->isStaff())
                            <div class="mt-2 flex space-x-2 text-xs text-gray-500">
                                <span>{{ $property->tenants_count ?? 0 }} tenants</span>
                                <span>{{ $property->tickets_count ?? 0 }} tickets</span>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-gray-500">No properties found.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
