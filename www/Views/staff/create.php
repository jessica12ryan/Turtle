<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Invite Staff Member</h1>
</div>
<div class="bg-white rounded-lg shadow p-6 max-w-lg">
    <form method="POST" action="/staff">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="<?= old('name') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
            <input type="email" name="email" value="<?= old('email') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Role <span class="text-red-500">*</span></label>
            <select name="role" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                <option value="">Select Role</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= $role ?>" <?= old('role') === $role ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $role)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-4">
            <?php $currentTimezone = old('timezone'); require base_path('www/Views/partials/timezone.php'); ?>
        </div>
        <div class="mb-6">
            <label class="flex items-center">
                <input type="checkbox" name="send_welcome_email" value="1" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700">Send welcome email with temporary password</span>
            </label>
        </div>
        <div class="flex space-x-3">
            <button type="submit" class="bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700 font-medium">Invite Staff</button>
            <a href="/staff" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50">Cancel</a>
        </div>
    </form>
</div>
