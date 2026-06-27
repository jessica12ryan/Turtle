<form method="POST" action="/password/change">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <p class="text-gray-600 mb-6 text-sm"><?= __('You are required to change your password before continuing.') ?></p>
    <div class="mb-4">
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1"><?= __('New Password') ?></label>
        <input type="password" name="password" id="password" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required minlength="8">
    </div>
    <div class="mb-6">
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1"><?= __('Confirm Password') ?></label>
        <input type="password" name="password_confirmation" id="password_confirmation" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required minlength="8">
    </div>
    <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 font-medium"><?= __('Change Password') ?></button>
</form>
