<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Properties</h1>
    <div class="flex items-center space-x-3">
        <?php if (can('properties.archive') || can('properties.restore')): ?>
            <a href="?show_archived=<?= $showArchived ? '0' : '1' ?>" class="text-sm <?= $showArchived ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700' ?> px-3 py-1.5 rounded-lg border transition">
                <?= $showArchived ? 'Showing archived' : 'Show archived' ?>
            </a>
        <?php endif; ?>
        <?php if (can('properties.create')): ?>
            <a href="/properties/create" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 text-sm font-medium">Add Property</a>
        <?php endif; ?>
    </div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (empty($properties)): ?>
        <div class="col-span-full text-center py-12 text-gray-500">No properties found.</div>
    <?php else: ?>
        <?php foreach ($properties as $property): ?>
            <div class="bg-white rounded-lg shadow hover:shadow-md transition overflow-hidden <?= $property['archived_at'] ? 'opacity-60' : '' ?>">
                <a href="/properties/<?= $property['id'] ?>">
                    <?php if ($property['main_photo']): ?>
                        <div class="h-40 bg-gray-100 overflow-hidden">
                            <img src="/properties/<?= $property['id'] ?>/photos/<?= $property['main_photo']['id'] ?>" alt="<?= h($property['main_photo']['original_name']) ?>" class="w-full h-full object-cover">
                        </div>
                    <?php else: ?>
                        <div class="h-40 bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center">
                            <svg class="w-12 h-12 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    <?php endif; ?>
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-gray-800"><?= h($property['name']) ?></h3>
                        <p class="text-sm text-gray-500 mt-1"><?= h($property['landlord_name']) ?></p>
                        <p class="text-sm text-gray-500"><?= h($property['city']) ?>, <?= h($property['province']) ?><?= ($property['country'] ?? 'CA') !== 'CA' ? ', ' . h($property['country']) : '' ?></p>
                        <?php if (isset($property['tenants_count'])): ?>
                            <div class="mt-2 flex space-x-4 text-sm text-gray-600">
                                <span><?= $property['tenants_count'] ?> tenants</span>
                                <span><?= $property['tickets_count'] ?> tickets</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="px-4 pb-4 flex items-center space-x-2">
                    <?php if ($property['archived_at']): ?>
                        <span class="text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded">Archived</span>
                        <?php if (can('properties.restore')): ?>
                            <form method="POST" action="/properties/<?= $property['id'] ?>/restore" class="inline">
                                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                <button type="submit" class="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded hover:bg-green-200">Restore</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
