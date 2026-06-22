<h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Tenant</h1>
<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST" action="/tenants/<?= $tenant['id'] ?>/update">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="<?= h($tenant['name']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" value="<?= h($tenant['email']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
            <p class="text-xs text-gray-500 mt-1">Email cannot be changed.</p>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
            <input type="text" name="phone" value="<?= h($tenant['phone'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="(555) 555-5555">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Property</label>
            <select name="property_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <?php foreach ($properties as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $p['id'] == ($tenant['property_id'] ?? '') ? 'selected' : '' ?>><?= h($p['name']) ?> (<?= h($p['landlord_name']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Lease Start</label>
                <?php if ($tenant['is_main_tenant']): ?>
                    <input type="date" name="lease_start" value="<?= h($tenant['lease_start']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <?php else: ?>
                    <input type="date" value="<?= h($tenant['lease_start']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
                    <p class="text-xs text-gray-400 mt-1">Lease dates must be changed on main tenant.</p>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Lease End</label>
                <?php if ($tenant['is_main_tenant']): ?>
                    <input type="date" name="lease_end" value="<?= h($tenant['lease_end']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <?php else: ?>
                    <input type="date" value="<?= h($tenant['lease_end']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Scheduled Move Out</label>
                <?php if ($tenant['is_main_tenant']): ?>
                    <input type="date" name="move_out_date" value="<?= h($tenant['move_out_date']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <?php else: ?>
                    <input type="date" value="<?= h($tenant['move_out_date']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
                <?php endif; ?>
                <p class="text-xs text-gray-400 mt-1">Optional — tenant auto-archives on this date.</p>
            </div>
        </div>
        <div class="mb-4">
            <?php $currentTimezone = $tenant['timezone'] ?? ''; require base_path('www/Views/partials/timezone.php'); ?>
        </div>
        <div class="flex space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">Update</button>
            <a href="/tenants/<?= $tenant['id'] ?>" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50">Cancel</a>
        </div>
    </form>
    <?php if (\App\Core\Auth::instance()->user()['role'] === 'admin'): ?>
        <div class="mt-8 pt-6 border-t">
            <h3 class="text-lg font-medium text-red-600 mb-2">Danger Zone</h3>
            <form method="POST" action="/tenants/<?= $tenant['id'] ?>/delete" onsubmit="return confirm('WARNING: This will permanently delete this tenant and all associated records. This is NOT reversible. Continue?')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm">Delete Tenant</button>
            </form>
        </div>
    <?php endif; ?>
</div>
