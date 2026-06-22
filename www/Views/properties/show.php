<?php $hasMainPhoto = false;
$mainPhotoId = null;
foreach ($photos as $ph) { if ($ph['is_main']) { $hasMainPhoto = true; $mainPhotoId = $ph['id']; break; } } ?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= h($property['name']) ?></h1>
        <p class="text-gray-500"><?= h($property['landlord_name']) ?> — <?= h($property['address']) ?>, <?= h($property['city']) ?>, <?= h($property['province']) ?></p>
    </div>
    <?php $role = \App\Core\Auth::instance()->user()['role']; ?>
    <div class="flex space-x-3">
        <?php if (in_array($role, ['admin', 'landlord'])): ?>
            <a href="/properties/<?= $property['id'] ?>/edit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">Edit</a>
        <?php endif; ?>
        <?php if (in_array($role, ['admin', 'landlord', 'property_manager'])): ?>
            <a href="/leases/create?property_id=<?= $property['id'] ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm">Upload Lease</a>
        <?php endif; ?>
        <?php if (in_array($role, ['admin', 'landlord', 'property_manager', 'tenant'])): ?>
            <a href="/tickets/create?property_id=<?= $property['id'] ?>" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 text-sm">New Ticket</a>
        <?php endif; ?>
        <?php if (in_array($role, ['admin', 'landlord'])): ?>
            <form method="POST" action="/properties/<?= $property['id'] ?>/delete" class="inline" onsubmit="return confirm('WARNING: This will archive this property and all its associated tenants, leases, and tickets. This is not reversible. Continue?')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm">Archive</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">Tenants</h2>
                <?php if (in_array(\App\Core\Auth::instance()->user()['role'], ['admin', 'landlord', 'property_manager'])): ?>
                    <a href="/tenants/create?property_id=<?= $property['id'] ?>" class="text-sm text-blue-600 hover:underline">Add Tenant</a>
                <?php endif; ?>
            </div>
            <div class="p-6">
                <?php if (empty($tenants)): ?>
                    <p class="text-gray-500 text-sm">No tenants assigned.</p>
                <?php else: ?>
                    <ul class="divide-y">
                        <?php foreach ($tenants as $t): ?>
                            <li class="py-3 flex justify-between items-center">
                                <div>
                                    <span class="font-medium"><?= h($t['name']) ?></span>
                                    <?php if ($t['is_main_tenant']): ?>
                                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded ml-2">Main</span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-semibold text-gray-800">Leases</h2>
            </div>
            <div class="p-6">
                <?php if (empty($leases)): ?>
                    <p class="text-gray-500 text-sm">No leases uploaded.</p>
                <?php else: ?>
                    <ul class="divide-y">
                        <?php foreach ($leases as $l): ?>
                            <li class="py-3"><a href="/leases/<?= $l['id'] ?>" class="text-blue-600 hover:underline"><?= h($l['title']) ?></a> <span class="text-sm text-gray-500">(<?= $l['documents_count'] ?> docs)</span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-gray-800">Recent Tickets</h2>
        </div>
        <div class="p-6">
            <?php if (empty($tickets)): ?>
                <p class="text-gray-500 text-sm">No tickets.</p>
            <?php else: ?>
                <ul class="divide-y">
                    <?php foreach ($tickets as $t): ?>
                        <li class="py-3">
                            <a href="/tickets/<?= $t['id'] ?>" class="text-blue-600 hover:underline text-sm"><?= h($t['subject']) ?></a>
                            <span class="text-xs text-gray-500 block"><?= h($t['tenant_name']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($photos)): ?>
<div class="mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-3">Photos</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        <?php foreach ($photos as $ph): ?>
            <div class="relative group border rounded-lg overflow-hidden <?= $ph['is_main'] ? 'ring-2 ring-blue-500' : '' ?>">
                <img src="/properties/<?= $property['id'] ?>/photos/<?= $ph['id'] ?>" alt="<?= h($ph['original_name']) ?>" class="w-full h-32 object-cover">
                <?php if ($ph['is_main']): ?>
                    <span class="absolute top-1 left-1 bg-blue-600 text-white text-xs px-2 py-0.5 rounded">Main</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
