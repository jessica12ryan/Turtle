<h1 class="text-2xl font-bold text-gray-800 mb-2">Welcome to Turtle</h1>
<p class="text-gray-500 mb-6">Let's get started. Create your admin account to set up the portal.</p>

<form method="POST" action="/setup" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

    <div class="mb-4">
        <label for="site_name" class="block text-sm font-medium text-gray-700 mb-1">Site Name</label>
        <input type="text" name="site_name" id="site_name" value="Turtle" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        <p class="text-xs text-gray-400 mt-1">Used in page titles and branding.</p>
    </div>

    <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
        <div class="flex items-center space-x-2 mb-2">
            <input type="radio" name="logo_default" value="1" checked class="text-blue-600 focus:ring-blue-500">
            <span class="text-sm text-gray-700">Use default logo</span>
        </div>
        <div class="flex items-center space-x-2 mb-2">
            <input type="radio" name="logo_default" value="0" class="text-blue-600 focus:ring-blue-500">
            <span class="text-sm text-gray-700">Upload custom logo</span>
        </div>
        <input type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/svg+xml" class="block text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
        <p class="text-xs text-gray-400 mt-1">Recommended: 200x50px PNG, JPEG, GIF, or SVG. Max dimensions: 400x100px.</p>
    </div>

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
