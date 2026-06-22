<h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Property</h1>
<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST" action="/properties/<?= $property['id'] ?>/update">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Landlord <span class="text-red-500">*</span></label>
            <select name="landlord_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                <option value="">Select Landlord</option>
                <?php foreach ($landlords as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= $property['landlord_id'] == $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?> (<?= h($l['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Nickname <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="<?= h($property['name']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Address <span class="text-red-500">*</span></label>
            <input type="text" name="address" value="<?= h($property['address']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">City/Town <span class="text-red-500">*</span></label>
                <input type="text" name="city" value="<?= h($property['city']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Province <span class="text-red-500">*</span></label>
                <select name="province" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                    <option value="">Select Province</option>
                    <?php foreach (provinces() as $code => $name): ?>
                        <option value="<?= $code ?>" <?= $property['province'] === $code ? 'selected' : '' ?>><?= h($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code <span class="text-red-500">*</span></label>
            <input type="text" name="postal_code" value="<?= h($property['postal_code']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 uppercase" required oninput="this.value = this.value.toUpperCase()" placeholder="A1A 1A1">
        </div>
        <div class="flex space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium">Update Property</button>
            <a href="/properties/<?= $property['id'] ?>" class="text-gray-600 px-6 py-2 rounded-lg border hover:bg-gray-50">Cancel</a>
        </div>
    </form>
    <?php if (\App\Core\Auth::instance()->user()['role'] === 'admin'): ?>
        <div class="mt-8 pt-6 border-t">
            <h3 class="text-lg font-medium text-red-600 mb-2">Danger Zone</h3>
            <form method="POST" action="/properties/<?= $property['id'] ?>/delete" onsubmit="return confirm('WARNING: This will permanently delete this property and all its associated data. This is NOT reversible. Continue?')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm">Delete Property</button>
            </form>
        </div>
    <?php endif; ?>
</div>
