<h1 class="text-2xl font-bold text-gray-800 mb-2">Welcome to Turtle</h1>
<p class="text-gray-500 mb-6">Let's get started. Create your admin account to set up the portal.</p>

<form method="POST" action="/setup">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <div class="mb-4">
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Your Name</label>
        <input type="text" name="name" id="name" value="" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required autofocus>
    </div>
    <div class="mb-4">
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
        <input type="email" name="email" id="email" value="" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
    </div>
    <div class="mb-4">
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <input type="password" name="password" id="password" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:ring-blue-500" required minlength="8">
    </div>
    <div class="mb-6">
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
        <input type="password" name="password_confirmation" id="password_confirmation" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required minlength="8">
    </div>
    <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <p class="text-sm text-blue-800">
            <strong>Administrator Account</strong> — This account will have full system access, including the ability to manage all properties, tenants, leases, tickets, and staff. You can invite additional landlords, property managers, and staff after setup.
        </p>
    </div>
    <div class="mb-6">
        <label class="flex items-start space-x-3">
            <input type="checkbox" name="load_sample_data" value="1" checked class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
            <span class="text-sm text-gray-600">
                <strong class="text-gray-800">Load sample data</strong><br>
                Creates demo properties, tenants, and sample tickets so you can explore the portal immediately.
            </span>
        </label>
    </div>
    <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 font-medium">Create Administrator Account</button>
</form>
