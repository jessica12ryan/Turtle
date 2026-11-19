<?php
$user = \App\Core\Auth::instance()->user();
$unread = \App\Core\Database::fetch("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_at IS NULL AND (type IS NULL OR type != 'message')", [$user['id']])['count'] ?? 0;
$logoUrl = site_logo();
$isDefaultLogo = $logoUrl === '/assets/logo.svg';
?>
<nav class="bg-white shadow-md dark:bg-slate-900 dark:border-b dark:border-slate-700 sticky top-0 z-20">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16 items-center">
            <div class="flex items-center space-x-3">
                <button type="button" @click="sidebarOpen = true"
                        class="md:hidden p-2 rounded-md text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-slate-800"
                        aria-label="<?= __('Open navigation') ?>" data-tooltip="<?= __('Open navigation') ?>">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <a href="/home" data-tooltip="<?= __('Home') ?>">
                    <img src="<?= h($logoUrl) ?>" alt="<?= h(site_name()) ?>" class="h-8<?= $isDefaultLogo ? ' logo-default' : '' ?>">
                </a>
            </div>

            <div class="flex items-center space-x-2">
                <?php if (messaging_enabled()): ?>
                    <button type="button" id="messenger-toggle" onclick="window.dispatchEvent(new Event('messenger:toggle'))" class="relative text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white p-1" data-tooltip="<?= __('Messages') ?>" aria-label="<?= __('Messages') ?>">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <span id="message-badge" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 items-center justify-center"></span>
                    </button>
                <?php endif; ?>
                <button type="button" onclick="window.dispatchEvent(new Event('notifications:toggle'))"
                        class="relative text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white p-1"
                        data-tooltip="<?= __('View notifications') ?>" aria-label="<?= __('View notifications') ?>">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <span id="notification-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 items-center justify-center" style="display: <?= $unread > 0 ? 'flex' : 'none' ?>"><?= $unread ?></span>
                </button>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white" data-tooltip="<?= __('Open user menu') ?>">
                        <?php $avatarUrl = avatar_url($user); ?>
                        <?php if ($avatarUrl): ?>
                            <img src="<?= h($avatarUrl) ?>" alt="<?= h($user['name']) ?>" class="w-8 h-8 rounded-full object-cover border border-gray-200">
                        <?php else: ?>
                            <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-medium"><?= h(initials_for($user['name'])) ?></div>
                        <?php endif; ?>
                        <span class="hidden lg:block text-sm"><?= h($user['name']) ?></span>
                    </button>
                    <div x-show="open" x-cloak x-transition @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border z-50 dark:bg-slate-900 dark:border-slate-700">
                        <a href="/profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-t-lg dark:text-gray-200 dark:hover:bg-slate-800" data-tooltip="<?= __('Edit your profile') ?>"><?= __('Profile') ?></a>
                        <?php if (can('ai_assistant.access')): ?>
                            <a href="/ai-assistant" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-slate-800" data-tooltip="<?= __('Open AI assistant') ?>"><?= __('AI Assistant') ?></a>
                        <?php endif; ?>
                        <?php if ($user['role'] === 'admin'): ?>
                            <a href="/settings" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-slate-800" data-tooltip="<?= __('Open settings') ?>"><?= __('Settings') ?></a>
                        <?php endif; ?>
                        <form method="POST" action="/logout">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-b-lg dark:text-gray-200 dark:hover:bg-slate-800" data-tooltip="<?= __('Sign out') ?>"><?= __('Logout') ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
