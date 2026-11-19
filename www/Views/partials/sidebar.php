<?php
$currentUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$user = \App\Core\Auth::instance()->user();
$logoUrl = site_logo();
$isDefaultLogo = $logoUrl === '/assets/logo.svg';

function navActive(string $prefix, string $currentUri): string
{
    return str_starts_with($currentUri, $prefix)
        ? 'bg-blue-50 text-blue-700 dark:bg-slate-800 dark:text-blue-300'
        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-slate-800 dark:hover:text-white';
}
?>
<!-- Mobile backdrop -->
<div x-show="sidebarOpen"
     @click="sidebarOpen = false"
     class="fixed inset-0 z-30 bg-black/40 md:hidden"
     x-cloak></div>

<aside class="fixed inset-y-0 left-0 z-40 flex flex-col bg-white border-r border-gray-200 shadow-sm transition-all duration-200 dark:bg-slate-900 dark:border-slate-700 transform -translate-x-full md:translate-x-0"
       :class="[sidebarCollapsed ? 'md:w-16' : 'md:w-64', sidebarOpen ? 'translate-x-0' : '-translate-x-full']">
    <div class="flex items-center justify-between h-16 px-3 border-b border-gray-200 dark:border-slate-700">
        <a href="/home" class="flex items-center space-x-2 min-w-0" data-tooltip="<?= __('Home') ?>">
            <img src="<?= h($logoUrl) ?>" alt="<?= h(site_name()) ?>" class="h-8 flex-shrink-0<?= $isDefaultLogo ? ' logo-default' : '' ?>">
        </a>
        <button type="button" @click="sidebarCollapsed = !sidebarCollapsed"
                class="hidden md:flex p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-slate-800"
                :aria-label="sidebarCollapsed ? '<?= __('Expand sidebar') ?>' : '<?= __('Collapse sidebar') ?>'"
                :data-tooltip="sidebarCollapsed ? '<?= __('Expand sidebar') ?>' : '<?= __('Collapse sidebar') ?>'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
        </button>
        <button type="button" @click="sidebarOpen = false"
                class="md:hidden p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-slate-800"
                aria-label="<?= __('Close navigation') ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto py-3 space-y-1 px-2" aria-label="<?= __('Main navigation') ?>">
        <?php
        $items = [];
        $items[] = ['/home', __('Home'), 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'];

        if (can('properties.access')) {
            $items[] = ['/properties', __('Properties'), 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'];
        }
        if (can('rents.access') && $user['role'] !== 'tenant') {
            $items[] = ['/rent', __('Rent'), 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'];
        }
        if (can('tenants.access') && $user['role'] !== 'tenant') {
            $items[] = ['/tenants', __('Tenants'), 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'];
        }
        if (can('leases.access') && $user['role'] !== 'tenant') {
            $items[] = ['/leases', __('Documents'), 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'];
        }
        if (can('tickets.access')) {
            $items[] = ['/tickets', __('Tickets'), 'M11 5a8 8 0 100 16 8 8 0 000-16zm0 0l3-3m-3 3l-3-3m3 3v6m0 0l3 3m-3-3l-3 3'];
        }
        if (can('staff.access') && $user['role'] !== 'tenant') {
            $items[] = ['/staff', __('Staff'), 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'];
        }
        if (can('resources.access')) {
            $items[] = ['/resources', __('Resources'), 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1'];
        }
        if (can('calendar.access') && $user['role'] !== 'tenant') {
            $items[] = ['/calendar', __('Calendar'), 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'];
        }
        if (can('applications.view')) {
            $items[] = ['/applications', __('Applications'), 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'];
        }

        foreach ($items as [$href, $label, $icon]) {
            ?>
            <a href="<?= $href ?>"
               data-tooltip="<?= h($label) ?>"
               @click="sidebarOpen = false"
               class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition <?= navActive($href, $currentUri) ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/></svg>
                <span class="truncate" :class="sidebarCollapsed ? 'md:hidden' : ''"><?= h($label) ?></span>
            </a>
            <?php
        }
        ?>
    </nav>

    <div class="px-2 py-3 border-t border-gray-200 dark:border-slate-700">
        <a href="/profile" data-tooltip="<?= __('Profile') ?>" @click="sidebarOpen = false"
           class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-slate-800">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            <span class="truncate" :class="sidebarCollapsed ? 'md:hidden' : ''"><?= __('Profile') ?></span>
        </a>
    </div>
</aside>
