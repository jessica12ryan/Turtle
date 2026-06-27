<h1 class="text-2xl font-bold text-gray-800 mb-6"><?= __('Reset Password') ?></h1>
<form method="POST" action="/reset-password">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="token" value="<?= h($token) ?>">
    <input type="hidden" name="email" value="<?= h($email) ?>">
    <div class="mb-4">
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1"><?= __('New Password') ?></label>
        <input type="password" name="password" id="password" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required minlength="8">
    </div>
    <div class="mb-6">
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1"><?= __('Confirm Password') ?></label>
        <input type="password" name="password_confirmation" id="password_confirmation" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required minlength="8">
    </div>
    <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 font-medium"><?= __('Reset Password') ?></button>
</form>
