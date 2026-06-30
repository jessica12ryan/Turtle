<div class="flex gap-6">
    <!-- Sidebar -->
    <div class="w-56 flex-shrink-0">
        <div class="bg-white rounded-lg shadow divide-y">
            <a href="/settings?tab=general" class="flex items-center space-x-3 px-4 py-3 text-sm font-medium <?= $tab === 'general' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' ?> rounded-t-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span><?= __('General') ?></span>
            </a>
            <a href="/settings?tab=applications" class="flex items-center space-x-3 px-4 py-3 text-sm font-medium <?= $tab === 'applications' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span><?= __('Applications') ?></span>
            </a>
            <a href="/settings?tab=permissions" class="flex items-center space-x-3 px-4 py-3 text-sm font-medium <?= $tab === 'permissions' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span><?= __('Permissions') ?></span>
            </a>
            <a href="/settings?tab=logging" class="flex items-center space-x-3 px-4 py-3 text-sm font-medium <?= $tab === 'logging' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <span><?= __('Logging') ?></span>
            </a>
            <a href="/settings?tab=backup" class="flex items-center space-x-3 px-4 py-3 text-sm font-medium <?= $tab === 'backup' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L5 8m4-4v12"/></svg>
                <span><?= __('Backup & Restore') ?></span>
            </a>
            <a href="/settings?tab=updates" class="flex items-center space-x-3 px-4 py-3 text-sm font-medium <?= $tab === 'updates' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span><?= __('Updates') ?></span>
            </a>
            <a href="/settings?tab=reset" class="flex items-center space-x-3 px-4 py-3 text-sm font-medium <?= $tab === 'reset' ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' ?> rounded-b-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span><?= __('Reset') ?></span>
            </a>
        </div>
    </div>

    <!-- Content -->
    <div class="flex-1 min-w-0">
        <?php if ($tab === 'general'): ?>
            <?php require base_path('www/Views/settings/partials/general.php'); ?>
        <?php elseif ($tab === 'updates'): ?>
            <?php require base_path('www/Views/settings/partials/updates.php'); ?>
        <?php elseif ($tab === 'applications'): ?>
            <?php require base_path('www/Views/settings/partials/applications.php'); ?>
        <?php elseif ($tab === 'permissions'): ?>
            <?php require base_path('www/Views/settings/partials/permissions.php'); ?>
        <?php elseif ($tab === 'backup'): ?>
            <?php require base_path('www/Views/settings/partials/backup.php'); ?>
        <?php elseif ($tab === 'logging'): ?>
            <?php require base_path('www/Views/settings/partials/logging.php'); ?>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-red-600 mb-4"><?= __('Reset Data') ?></h2>
                <p class="text-sm text-gray-600 mb-6"><?= __('Select the data you want to reset. This action cannot be undone. Your admin account will remain active.') ?></p>

                <form method="POST" action="/settings/reset" x-data="{
                    resetAll: false,
                    toggleAll() {
                        this.resetAll = !this.resetAll;
                        $el.querySelectorAll('.reset-checkbox:not(#reset_all)').forEach(cb => cb.checked = this.resetAll);
                    }
                }">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

                    <div class="space-y-3 mb-6">
                        <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" id="reset_all" name="reset_all" value="1" @change="toggleAll()" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-3 font-medium text-gray-800"><?= __('Everything') ?></span>
                        </label>

                        <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer ml-6">
                            <input type="checkbox" name="reset_properties" value="1" class="reset-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-3 text-gray-700"><?= __('Property Data') ?></span>
                        </label>

                        <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer ml-6">
                            <input type="checkbox" name="reset_tenants" value="1" class="reset-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-3 text-gray-700"><?= __('Tenant Data') ?></span>
                        </label>

                        <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer ml-6">
                            <input type="checkbox" name="reset_staff" value="1" class="reset-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-3 text-gray-700"><?= __('Staff Data (leaves your admin account alone)') ?></span>
                        </label>

                        <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer ml-6">
                            <input type="checkbox" name="reset_leases" value="1" class="reset-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-3 text-gray-700"><?= __('Lease Data') ?></span>
                        </label>

                        <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer ml-6">
                            <input type="checkbox" name="reset_tickets" value="1" class="reset-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-3 text-gray-700"><?= __('Ticket Data') ?></span>
                        </label>

                        <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer ml-6">
                            <input type="checkbox" name="reset_resources" value="1" class="reset-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-3 text-gray-700"><?= __('Resource Data') ?></span>
                        </label>
                    </div>

                    <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 font-medium" onclick="return confirm('<?= __('Are you sure you want to reset the selected data? This cannot be undone.') ?>')"><?= __('Reset Selected Data') ?></button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
