<h1 class="text-2xl font-bold text-gray-800 mb-6"><?= __('Sign In') ?></h1>
<form method="POST" action="/login">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <div class="mb-4">
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1"><?= __('Email') ?></label>
        <input type="email" name="email" id="email" value="<?= old('email') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required autofocus>
    </div>
    <div class="mb-6">
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1"><?= __('Password') ?></label>
        <input type="password" name="password" id="password" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
    </div>
    <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 font-medium"><?= __('Sign In') ?></button>
    <div class="mt-4 text-center text-sm space-y-2">
        <div><a href="/forgot-password" class="text-blue-600 hover:underline"><?= __('Forgot your password?') ?></a></div>
        <div><a href="/onboarding" class="text-blue-600 hover:underline"><?= __('Tenant Onboarding') ?></a></div>
        <div><a href="/applications/create" class="text-blue-600 hover:underline font-medium"><?= __('Application for Tenancy') ?></a></div>
    </div>
</form>
