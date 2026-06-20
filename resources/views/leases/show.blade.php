<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $lease->title }}</h2>
            @if(Auth::user()->isStaff())
            <form method="POST" action="{{ route('leases.destroy', $lease) }}" onsubmit="return confirm('Archive this lease?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">Archive</button>
            </form>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><dt class="text-sm font-medium text-gray-500">Property</dt><dd class="mt-1 text-sm text-gray-900"><a href="{{ route('properties.show', $lease->property) }}" class="text-teal-600 hover:text-teal-900">{{ $lease->property->name }}</a></dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">Company</dt><dd class="mt-1 text-sm text-gray-900">{{ $lease->property->company->name ?? 'N/A' }}</dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">Description</dt><dd class="mt-1 text-sm text-gray-900">{{ $lease->description ?? 'No description' }}</dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">Uploaded By</dt><dd class="mt-1 text-sm text-gray-900">{{ $lease->uploader->name ?? 'Unknown' }}</dd></div>
                        <div><dt class="text-sm font-medium text-gray-500">Uploaded At</dt><dd class="mt-1 text-sm text-gray-900">{{ $lease->created_at->format('F d, Y h:i A') }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Documents ({{ $lease->documents->count() }})</h3>
                    @if($lease->documents->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">File Name</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($lease->documents as $document)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $document->original_name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $document->mime_type }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $document->size ? round($document->size / 1024, 1) . ' KB' : 'N/A' }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <a href="{{ route('documents.download', $document) }}" class="text-teal-600 hover:text-teal-900 mr-3">Download</a>
                                        @if(Auth::user()->isStaff())
                                        <form method="POST" action="{{ route('documents.destroy', $document) }}" class="inline" onsubmit="return confirm('Delete this document?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-gray-500">No documents attached to this lease.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
