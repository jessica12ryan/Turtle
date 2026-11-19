<h1 class="text-2xl font-bold text-gray-800 mb-6"><?= __('Profile') ?></h1>
<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST" action="/profile" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('Profile Picture') ?></label>
            <div class="flex items-center space-x-4">
                <?php $avatarUrl = avatar_url($user); ?>
                <?php if ($avatarUrl): ?>
                    <img src="<?= h($avatarUrl) ?>" alt="<?= h($user['name']) ?>" class="w-16 h-16 rounded-full object-cover border border-gray-200">
                <?php else: ?>
                    <div class="w-16 h-16 rounded-full bg-blue-600 text-white flex items-center justify-center text-xl font-medium"><?= h(initials_for($user['name'])) ?></div>
                <?php endif; ?>
                <div class="flex-1">
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" class="block text-sm text-gray-600">
                    <p class="text-xs text-gray-500 mt-1"><?= __('JPG, PNG, GIF, or WebP. Maximum 2 MB.') ?></p>
                    <?php if ($avatarUrl): ?>
                        <label class="flex items-center mt-2 text-sm text-gray-700">
                            <input type="checkbox" name="remove_avatar" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2"><?= __('Remove profile picture') ?></span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Name') ?> <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="<?= h($user['name']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Email') ?></label>
            <input type="email" value="<?= h($user['email']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
            <p class="text-xs text-gray-500 mt-1"><?= __('Email cannot be changed.') ?></p>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Role') ?></label>
            <input type="text" value="<?= h(ucwords(str_replace('_', ' ', $user['role']))) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
        </div>
        <?php if ($user['role'] !== 'tenant' && !empty($user['secondary_roles'])): ?>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Secondary Roles') ?></label>
            <input type="text" value="<?= h(implode(', ', array_map(function($r) { return ucwords(str_replace('_', ' ', $r)); }, explode(',', $user['secondary_roles'])))) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
        </div>
        <?php endif; ?>
        <hr class="my-6">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Language') ?></label>
            <select name="language" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <option value=""><?= __('Use default language') ?></option>
                <?php foreach (languages() as $code => $name): ?>
                    <option value="<?= $code ?>" <?= ($user['language'] ?? '') === $code ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-6">
            <?php $currentTimezone = $user['timezone'] ?? ''; require base_path('www/Views/partials/timezone.php'); ?>
        </div>
        <hr class="my-6">
        <div class="mb-6">
            <?php $currentTheme = $user['theme'] ?? 'system'; require base_path('www/Views/partials/theme.php'); ?>
        </div>
        <hr class="my-6">
        <h3 class="text-lg font-medium text-gray-800 mb-4"><?= __('Change Password (optional)') ?></h3>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('New Password') ?></label>
            <input type="password" name="password" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" minlength="8">
        </div>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Confirm Password') ?></label>
            <input type="password" name="password_confirmation" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700"><?= __('Update Profile') ?></button>
            <a href="/home" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50"><?= __('Cancel') ?></a>
        </div>
    </form>
</div>
