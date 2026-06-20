<form method="POST" action="/forgot-password">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <p class="text-gray-600 mb-6 text-sm">Enter your email address and we'll send you a link to reset your password.</p>
    <div class="mb-4">
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" name="email" id="email" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required autofocus>
    </div>
    <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 font-medium">Send Reset Link</button>
    <div class="mt-4 text-center text-sm">
        <a href="/login" class="text-blue-600 hover:underline">Back to login</a>
    </div>
</form>
