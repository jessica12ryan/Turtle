<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800"><?= __('Edit Staff Member') ?></h1>
</div>
<div class="bg-white rounded-lg shadow p-6 max-w-lg">
    <form method="POST" action="/staff/<?= $staff['id'] ?>/update">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Full Name') ?> <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="<?= h($staff['name']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Email') ?></label>
            <input type="email" value="<?= h($staff['email']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
            <p class="text-xs text-gray-500 mt-1"><?= __('Email cannot be changed.') ?></p>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Role') ?></label>
            <p class="text-gray-600"><?= ucfirst(str_replace('_', ' ', $staff['role'])) ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= __('Role cannot be changed. Archive and re-invite if needed.') ?></p>
        </div>
        <div class="mb-6">
            <?php $currentTimezone = $staff['timezone'] ?? ''; require base_path('www/Views/partials/timezone.php'); ?>
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
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium"><?= __('Update') ?></button>
            <a href="/staff/<?= $staff['id'] ?>" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50"><?= __('Cancel') ?></a>
        </div>
    </form>
    <?php if (can('staff.delete')): ?>
        <div class="mt-8 pt-6 border-t">
            <h3 class="text-lg font-medium text-red-600 mb-2"><?= __('Danger Zone') ?></h3>
            <form method="POST" action="/staff/<?= $staff['id'] ?>/hard-delete" onsubmit="return confirm('<?= __('WARNING: This will permanently delete this staff member and all associated records. This is NOT reversible. Continue?') ?>')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm"><?= __('Delete Staff Member') ?></button>
            </form>
        </div>
    <?php endif; ?>
</div>
