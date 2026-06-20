<h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Staff Member</h1>
<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST" action="/staff/<?= $staff['id'] ?>/update">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="<?= h($staff['name']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" value="<?= h($staff['email']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
            <p class="text-xs text-gray-500 mt-1">Email cannot be changed.</p>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <input type="text" value="<?= ucfirst(str_replace('_', ' ', $staff['role'])) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
            <p class="text-xs text-gray-500 mt-1">Role cannot be changed. Archive and re-invite if needed.</p>
        </div>
        <?php if (\App\Core\Auth::instance()->user()['role'] === 'landlord'): ?>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Companies</label>
                <div class="space-y-2">
                    <?php foreach ($companies as $c): ?>
                        <label class="flex items-center">
                            <input type="checkbox" name="company_ids[]" value="<?= $c['id'] ?>" <?= in_array($c['id'], $assignedCompanyIds) ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700"><?= h($c['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <hr class="my-6">
        <h3 class="text-lg font-medium text-gray-800 mb-4">Reset Password (optional)</h3>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
            <input type="password" name="password" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" minlength="8">
        </div>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input type="password" name="password_confirmation" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">Update</button>
            <a href="/staff/<?= $staff['id'] ?>" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50">Cancel</a>
        </div>
    </form>
    <?php if (\App\Core\Auth::instance()->user()['role'] === 'landlord'): ?>
        <div class="mt-8 pt-6 border-t">
            <h3 class="text-lg font-medium text-red-600 mb-2">Danger Zone</h3>
            <form method="POST" action="/staff/<?= $staff['id'] ?>/delete" onsubmit="return confirm('Are you sure you want to archive this staff member? They will no longer be able to log in.')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm">Archive Staff Member</button>
            </form>
        </div>
    <?php endif; ?>
</div>
