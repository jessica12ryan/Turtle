<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Notifications</h2>
            @if(Auth::user()->unreadNotifications->count() > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">Mark All as Read</button>
            </form>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($notifications->count() > 0)
                    <ul class="divide-y divide-gray-200">
                        @foreach($notifications as $notification)
                        <li class="py-3 {{ $notification->read_at ? '' : 'bg-blue-50 -mx-6 px-6' }}">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm {{ $notification->read_at ? 'text-gray-600' : 'text-gray-900 font-medium' }}">{{ $notification->data['message'] ?? 'Notification' }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                                </div>
                                @if(!$notification->read_at)
                                <a href="{{ route('notifications.read', $notification->id) }}" class="text-xs text-teal-600 hover:text-teal-900">Mark as Read</a>
                                @endif
                            </div>
                        </li>
                        @endforeach
                    </ul>
                    <div class="mt-4">
                        {{ $notifications->links() }}
                    </div>
                    @else
                    <p class="text-gray-500">No notifications.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
