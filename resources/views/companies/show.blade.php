<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $company->name }}</h2>
            <div>
                <a href="{{ route('companies.edit', $company) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 mr-2">Edit</a>
                <a href="{{ route('properties.create', ['company_id' => $company->id]) }}" class="inline-flex items-center px-4 py-2 bg-teal-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-700">Add Property</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><dt class="text-sm font-medium text-gray-500">Address</dt><dd class="mt-1 text-sm text-gray-900">{{ $company->address ?? 'N/A' }}</dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">City/Town</dt><dd class="mt-1 text-sm text-gray-900">{{ $company->city ?? 'N/A' }}</dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">Province</dt><dd class="mt-1 text-sm text-gray-900">{{ $company->province ?? 'N/A' }}</dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">Postal Code</dt><dd class="mt-1 text-sm text-gray-900">{{ $company->postal_code ?? 'N/A' }}</dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">Phone</dt><dd class="mt-1 text-sm text-gray-900">{{ $company->phone ?? 'N/A' }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Properties ({{ $company->properties->count() }})</h3>
                    @if($company->properties->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead><tr><th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Name</th><th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">City</th><th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Actions</th></tr></thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($company->properties as $property)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4"><a href="{{ route('properties.show', $property) }}" class="text-teal-600 hover:text-teal-900">{{ $property->name }}</a></td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $property->city ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 text-sm"><a href="{{ route('properties.show', $property) }}" class="text-teal-600 hover:text-teal-900">View</a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-gray-500">No properties yet.</p>
                    @endif
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Staff ({{ $company->users->count() }})</h3>
                    @if($company->users->count() > 0)
                    <ul class="divide-y divide-gray-200">
                        @foreach($company->users as $user)
                        <li class="py-3 flex justify-between">
                            <span class="text-sm text-gray-900">{{ $user->name }} - {{ $user->email }}</span>
                            <span class="text-sm text-gray-500">{{ ucfirst(str_replace('_', ' ', $user->role)) }}</span>
                        </li>
                        @endforeach
                    </ul>
                    @else
                    <p class="text-gray-500">No staff assigned yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
