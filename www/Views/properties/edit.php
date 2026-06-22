<h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Property</h1>
<div class="bg-white rounded-lg shadow p-6 max-w-2xl mb-6">
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
</div>

<!-- Photos -->
<div class="bg-white rounded-lg shadow p-6 max-w-2xl mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Property Photos</h2>

    <form id="photo-upload-form" class="mb-6" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <label class="block text-sm font-medium text-gray-700 mb-1">Add Photo</label>
        <div class="flex items-center space-x-3">
            <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium flex-shrink-0">Upload</button>
        </div>
        <p id="upload-error" class="text-sm text-red-600 mt-1 hidden"></p>
    </form>

    <?php if (empty($photos)): ?>
        <p class="text-sm text-gray-500">No photos uploaded yet.</p>
    <?php else: ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <?php foreach ($photos as $photo): ?>
                <div class="relative group border rounded-lg overflow-hidden <?= $photo['is_main'] ? 'ring-2 ring-blue-500' : '' ?>">
                    <img src="/properties/<?= $property['id'] ?>/photos/<?= $photo['id'] ?>" alt="<?= h($photo['original_name']) ?>" class="w-full h-32 object-cover">
                    <?php if ($photo['is_main']): ?>
                        <span class="absolute top-1 left-1 bg-blue-600 text-white text-xs px-2 py-0.5 rounded">Main</span>
                    <?php endif; ?>
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition flex items-center justify-center space-x-2 opacity-0 group-hover:opacity-100">
                        <a href="/properties/<?= $property['id'] ?>/photos/<?= $photo['id'] ?>/download" class="bg-white text-gray-800 p-1.5 rounded-full hover:bg-gray-100" title="Download">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </a>
                        <?php if (!$photo['is_main']): ?>
                            <form method="POST" action="/properties/<?= $property['id'] ?>/photos/<?= $photo['id'] ?>/main" class="inline">
                                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                <button type="submit" class="bg-blue-600 text-white text-xs px-3 py-1.5 rounded hover:bg-blue-700">Set as Main</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="/properties/<?= $property['id'] ?>/photos/<?= $photo['id'] ?>/delete" class="inline" onsubmit="return confirm('Delete this photo?')">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <button type="submit" class="bg-red-600 text-white text-xs px-3 py-1.5 rounded hover:bg-red-700">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (can('properties.archive')): ?>
    <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
        <div class="mt-0 pt-0 border-t-0">
            <h3 class="text-lg font-medium text-red-600 mb-2">Danger Zone</h3>
            <form method="POST" action="/properties/<?= $property['id'] ?>/delete" onsubmit="return confirm('WARNING: This will permanently delete this property and all its associated data. This is NOT reversible. Continue?')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm">Delete Property</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
document.getElementById('photo-upload-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const errorEl = document.getElementById('upload-error');
    errorEl.classList.add('hidden');

    const formData = new FormData(form);

    fetch('/properties/<?= $property['id'] ?>/photos', {
        method: 'POST',
        body: formData,
        headers: {
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            errorEl.textContent = data.error;
            errorEl.classList.remove('hidden');
        } else {
            window.location.reload();
        }
    })
    .catch(err => {
        errorEl.textContent = 'Upload failed.';
        errorEl.classList.remove('hidden');
    });
});
</script>
