<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ticket: {{ $ticket->subject }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Details</h3>
                            <div class="prose max-w-none">
                                <p>{{ nl2br(e($ticket->description)) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Comments</h3>
                            @foreach($ticket->comments as $comment)
                            <div class="border-l-4 {{ $comment->is_internal ? 'border-yellow-400 bg-yellow-50' : 'border-teal-400' }} mb-4 pl-4 py-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="font-medium text-sm text-gray-900">{{ $comment->user->name }}</span>
                                        @if($comment->is_internal)
                                        <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Internal Note</span>
                                        @endif
                                    </div>
                                    <span class="text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-700">{{ nl2br(e($comment->body)) }}</p>
                            </div>
                            @endforeach

                            <form method="POST" action="{{ route('tickets.comment', $ticket) }}" class="mt-6">
                                @csrf
                                <label class="block text-sm font-medium text-gray-700">Add Comment</label>
                                <textarea name="body" rows="3" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"></textarea>
                                @if(Auth::user()->isStaff())
                                <label class="mt-2 inline-flex items-center">
                                    <input type="checkbox" name="is_internal" value="1" class="rounded border-gray-300 text-teal-600 shadow-sm focus:ring-teal-500" />
                                    <span class="ml-2 text-sm text-gray-600">Internal note (not visible to tenant)</span>
                                </label>
                                @endif
                                <div class="mt-3">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-teal-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-700">Post Comment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Status</h3>
                            <div class="mb-4">
                                <span class="w-full px-3 py-2 inline-flex text-sm leading-5 font-semibold rounded-md
                                    @if($ticket->status === 'open') bg-yellow-100 text-yellow-800
                                    @elseif($ticket->status === 'in_progress') bg-blue-100 text-blue-800
                                    @elseif($ticket->status === 'resolved') bg-green-100 text-green-800
                                    @else bg-gray-100 text-gray-800 @endif
                                ">{{ str_replace('_', ' ', ucfirst($ticket->status)) }}</span>
                            </div>

                            @if(Auth::user()->isStaff())
                            <form method="POST" action="{{ route('tickets.status', $ticket) }}" class="mb-4">
                                @csrf
                                <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm">
                                    @foreach($statuses as $status)
                                    <option value="{{ $status->value }}" {{ $ticket->status === $status->value ? 'selected' : '' }}>{{ str_replace('_', ' ', ucfirst($status->value)) }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="mt-2 w-full inline-flex justify-center items-center px-4 py-2 bg-teal-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-700">Update Status</button>
                            </form>

                            <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="mb-4">
                                @csrf
                                <label class="block text-sm font-medium text-gray-700 mb-1">Assign To</label>
                                <select name="assigned_to" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm">
                                    <option value="">Unassigned</option>
                                    @foreach($staffUsers as $staff)
                                    <option value="{{ $staff->id }}" {{ $ticket->assigned_to == $staff->id ? 'selected' : '' }}>{{ $staff->name }} ({{ ucfirst(str_replace('_', ' ', $staff->role)) }})</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="mt-2 w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Assign</button>
                            </form>
                            @endif

                            <div class="space-y-2 text-sm">
                                <div><span class="font-medium text-gray-500">Priority:</span> <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    @if($ticket->priority === 'emergency') bg-red-100 text-red-800
                                    @elseif($ticket->priority === 'high') bg-orange-100 text-orange-800
                                    @else bg-gray-100 text-gray-800 @endif
                                ">{{ ucfirst($ticket->priority) }}</span></div>
                                <div><span class="font-medium text-gray-500">Category:</span> <span class="text-gray-900">{{ str_replace('_', ' ', ucfirst($ticket->category)) }}</span></div>
                                <div><span class="font-medium text-gray-500">Property:</span> <a href="{{ route('properties.show', $ticket->property) }}" class="text-teal-600 hover:text-teal-900">{{ $ticket->property->name }}</a></div>
                                <div><span class="font-medium text-gray-500">Tenant:</span> <span class="text-gray-900">{{ $ticket->tenant->name }}</span></div>
                                <div><span class="font-medium text-gray-500">Assignee:</span> <span class="text-gray-900">{{ $ticket->assignee->name ?? 'Unassigned' }}</span></div>
                                <div><span class="font-medium text-gray-500">Created:</span> <span class="text-gray-900">{{ $ticket->created_at->format('M d, Y h:i A') }}</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
