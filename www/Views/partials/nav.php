<?php
$user = \App\Core\Auth::instance()->user();
$currentUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$unread = \App\Core\Database::fetch("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_at IS NULL", [$user['id']])['count'] ?? 0;
$mgmtRoles = ['admin', 'landlord', 'property_manager'];

function navActive(string $prefix, string $currentUri): string {
    return str_starts_with($currentUri, $prefix) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900';
}
?>
<nav class="bg-white shadow-md">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16">
            <div class="flex items-center space-x-8">
                <a href="/home"><img src="/assets/logo.svg" alt="Turtle" class="h-8"></a>
                <div class="hidden md:flex space-x-4">
                    <a href="/home" class="px-3 py-2 rounded-md text-sm font-medium <?= navActive('/home', $currentUri) ?>">Home</a>
                    <a href="/properties" class="px-3 py-2 rounded-md text-sm font-medium <?= navActive('/properties', $currentUri) ?>">Properties</a>
                    <?php if (in_array($user['role'], $mgmtRoles)): ?>
                        <a href="/tenants" class="px-3 py-2 rounded-md text-sm font-medium <?= navActive('/tenants', $currentUri) ?>">Tenants</a>
                    <?php endif; ?>
                    <a href="/leases" class="px-3 py-2 rounded-md text-sm font-medium <?= navActive('/leases', $currentUri) ?>">Leases</a>
                    <a href="/tickets" class="px-3 py-2 rounded-md text-sm font-medium <?= navActive('/tickets', $currentUri) ?>">Tickets</a>
                    <?php if (in_array($user['role'], $mgmtRoles)): ?>
                        <a href="/staff" class="px-3 py-2 rounded-md text-sm font-medium <?= navActive('/staff', $currentUri) ?>">Staff</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <a href="/notifications" class="relative text-gray-600 hover:text-gray-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <?php if ($unread > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?= $unread ?></span>
                    <?php endif; ?>
                </a>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900">
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-medium"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                        <span class="hidden md:block text-sm"><?= h($user['name']) ?></span>
                    </button>
                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border z-50">
                        <a href="/profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-t-lg">Profile</a>
                        <?php if ($user['role'] === 'admin'): ?>
                            <a href="/settings" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                        <?php endif; ?>
                        <form method="POST" action="/logout">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-b-lg">Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
