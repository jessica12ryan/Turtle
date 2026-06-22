<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Properties</h1>
    <div class="flex items-center space-x-3">
        <a href="?show_archived=<?= $showArchived ? '0' : '1' ?>" class="text-sm <?= $showArchived ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700' ?> px-3 py-1.5 rounded-lg border transition">
            <?= $showArchived ? 'Showing archived' : 'Show archived' ?>
        </a>
        <?php if (in_array(\App\Core\Auth::instance()->user()['role'], ['admin', 'landlord', 'property_manager'])): ?>
            <a href="/properties/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">Add Property</a>
        <?php endif; ?>
    </div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (empty($properties)): ?>
        <div class="col-span-full text-center py-12 text-gray-500">No properties found.</div>
    <?php else: ?>
        <?php foreach ($properties as $property): ?>
            <a href="/properties/<?= $property['id'] ?>" class="bg-white rounded-lg shadow p-6 hover:shadow-md transition <?= $property['archived_at'] ? 'opacity-60' : '' ?>">
                <h3 class="text-lg font-semibold text-gray-800"><?= h($property['name']) ?></h3>
                <p class="text-sm text-gray-500 mt-1"><?= h($property['landlord_name']) ?></p>
                <p class="text-sm text-gray-500"><?= h($property['city']) ?>, <?= h($property['province']) ?></p>
                <?php if (isset($property['tenants_count'])): ?>
                    <div class="mt-3 flex space-x-4 text-sm text-gray-600">
                        <span><?= $property['tenants_count'] ?> tenants</span>
                        <span><?= $property['tickets_count'] ?> tickets</span>
                    </div>
                <?php endif; ?>
                <?php if ($property['archived_at']): ?>
                    <span class="mt-2 inline-block text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded">Archived</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
