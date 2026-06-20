<h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Tenant</h1>
<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST" action="/tenants/<?= $tenant['id'] ?>/update">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="<?= h($tenant['name']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" value="<?= h($tenant['email']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" disabled>
            <p class="text-xs text-gray-500 mt-1">Email cannot be changed.</p>
        </div>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Property</label>
            <select name="property_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <?php foreach ($properties as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $p['id'] == ($tenant['property_id'] ?? '') ? 'selected' : '' ?>><?= h($p['name']) ?> (<?= h($p['company_name']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">Update</button>
            <a href="/tenants/<?= $tenant['id'] ?>" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50">Cancel</a>
        </div>
    </form>
</div>
