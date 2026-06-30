<h1 class="text-2xl font-bold text-gray-800 mb-6"><?= __('Edit Tenant') ?></h1>
<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST" action="/tenants/<?= $tenant['id'] ?>/update">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Full Name') ?> <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="<?= h($tenant['name']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Email') ?></label>
            <input type="email" value="<?= h($tenant['email']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
            <p class="text-xs text-gray-500 mt-1"><?= __('Email cannot be changed.') ?></p>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Phone Number') ?> <span class="text-red-500">*</span></label>
            <input type="text" name="phone" value="<?= h($tenant['phone'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required placeholder="(555) 555-5555">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Property') ?> <span class="text-red-500">*</span></label>
            <select name="property_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                <?php foreach ($properties as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $p['id'] == ($tenant['property_id'] ?? '') ? 'selected' : '' ?>><?= h($p['name']) ?> (<?= h($p['landlord_name']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Lease Start') ?> <span class="text-red-500">*</span></label>
                <?php if ($tenant['is_main_tenant']): ?>
                    <input type="date" name="lease_start" value="<?= h($tenant['lease_start']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                <?php else: ?>
                    <input type="date" value="<?= h($mainTenant['lease_start'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
                    <p class="text-xs text-gray-400 mt-1"><?= __('Lease dates must be changed on main tenant.') ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Lease End') ?></label>
                <?php if ($tenant['is_main_tenant']): ?>
                    <input type="date" name="lease_end" value="<?= h($tenant['lease_end']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <?php else: ?>
                    <input type="date" value="<?= h($mainTenant['lease_end'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Scheduled Move Out') ?></label>
                <?php if ($tenant['is_main_tenant']): ?>
                    <input type="date" name="move_out_date" value="<?= h($tenant['move_out_date']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <?php else: ?>
                    <input type="date" value="<?= h($mainTenant['move_out_date'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
                <?php endif; ?>
                <p class="text-xs text-gray-400 mt-1"><?= __('Optional — tenant auto-archives on this date.') ?></p>
            </div>
        </div>
        <div class="mb-4" id="lease-type-row" <?= $tenant['is_main_tenant'] ? '' : 'style="display:none"' ?>>
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Lease Type') ?> <span class="text-red-500">*</span></label>
            <?php if ($tenant['is_main_tenant']): ?>
                <select name="lease_type" id="lease-type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <option value=""><?= __('— Select —') ?></option>
                    <option value="fixed_term" <?= ($tenant['lease_type'] ?? '') === 'fixed_term' ? 'selected' : '' ?>><?= __('Fixed Term') ?></option>
                    <option value="year_to_year" <?= ($tenant['lease_type'] ?? '') === 'year_to_year' ? 'selected' : '' ?>><?= __('Year to Year') ?></option>
                    <option value="month_to_month" <?= ($tenant['lease_type'] ?? '') === 'month_to_month' ? 'selected' : '' ?>><?= __('Month to Month') ?></option>
                    <option value="week_to_week" <?= ($tenant['lease_type'] ?? '') === 'week_to_week' ? 'selected' : '' ?>><?= __('Week to Week') ?></option>
                    <option value="other" <?= ($tenant['lease_type'] ?? '') === 'other' ? 'selected' : '' ?>><?= __('Other') ?></option>
                </select>
            <?php else: ?>
                <select disabled class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100">
                    <option value=""><?= h($tenant['lease_type'] ? ucwords(str_replace('_', ' ', $tenant['lease_type'])) : '—') ?></option>
                </select>
            <?php endif; ?>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Language') ?></label>
            <select name="language" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <option value=""><?= __('Use default language') ?></option>
                <?php foreach (languages() as $code => $name): ?>
                    <option value="<?= $code ?>" <?= ($tenant['language'] ?? '') === $code ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-4">
            <?php $currentTimezone = $tenant['timezone'] ?? ''; require base_path('www/Views/partials/timezone.php'); ?>
        </div>
        <hr class="my-6">
        <h3 class="text-lg font-medium text-gray-800 mb-4"><?= __('Change Password (optional)') ?></h3>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('New Password') ?></label>
            <input type="password" name="password" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" minlength="8">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Confirm New Password') ?></label>
            <input type="password" name="password_confirmation" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="mb-6">
            <label class="flex items-start space-x-3">
                <input type="checkbox" name="must_change_password" value="1" class="mt-0.5 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <span class="text-sm text-gray-600"><?= __('Require changing password on next login') ?></span>
            </label>
        </div>
        <div class="flex space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700"><?= __('Update') ?></button>
            <a href="/tenants/<?= $tenant['id'] ?>" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50"><?= __('Cancel') ?></a>
        </div>
    </form>
    <?php if (can('tenants.delete')): ?>
        <div class="mt-8 pt-6 border-t">
            <h3 class="text-lg font-medium text-red-600 mb-2"><?= __('Danger Zone') ?></h3>
            <form method="POST" action="/tenants/<?= $tenant['id'] ?>/delete" onsubmit="return confirm('<?= __('WARNING: This will permanently delete this tenant and all associated records. This is NOT reversible. Continue?') ?>')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm"><?= __('Delete Tenant') ?></button>
            </form>
        </div>
    <?php endif; ?>
</div>
