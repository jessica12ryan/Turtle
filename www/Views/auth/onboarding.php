<form method="POST" action="/onboarding">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">Tenant Onboarding</h1>
    <p class="text-gray-600 mb-6 text-sm">Enter the email your landlord used to invite you. We'll send your temporary password to get you started.</p>
    <div class="mb-4">
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" name="email" id="email" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required autofocus>
    </div>
    <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 font-medium">Send Welcome Email</button>
    <div class="mt-4 text-center text-sm space-y-2">
        <div><a href="/login" class="text-blue-600 hover:underline">Back to login</a></div>
        <div><a href="/forgot-password" class="text-blue-600 hover:underline">Forgot your password?</a></div>
    </div>
</form>
