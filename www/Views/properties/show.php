<?php $hasMainPhoto = false;
$mainPhotoId = null;
foreach ($photos as $ph) { if ($ph['is_main']) { $hasMainPhoto = true; $mainPhotoId = $ph['id']; break; } } ?>

<div class="flex justify-between items-center mb-6">
    <div class="flex items-center space-x-4">
        <?php if ($hasMainPhoto): ?>
            <img src="/properties/<?= $property['id'] ?>/photos/<?= $mainPhotoId ?>" alt="Main photo" class="w-16 h-16 rounded-lg object-cover flex-shrink-0">
        <?php endif; ?>
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= h($property['name']) ?></h1>
            <p class="text-gray-500"><?= h($property['landlord_name']) ?> — <?= h($property['address']) ?>, <?= h($property['city']) ?>, <?= h($property['province']) ?><?= ($property['country'] ?? 'CA') !== 'CA' ? ', ' . h($property['country']) : '' ?></p>
        </div>
    </div>
    <div class="flex space-x-3">
        <?php if (can('properties.edit')): ?>
            <a href="/properties/<?= $property['id'] ?>/edit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">Edit</a>
        <?php endif; ?>
        <?php if (can('leases.create')): ?>
            <a href="/leases/create?property_id=<?= $property['id'] ?>" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 text-sm">Upload Document</a>
        <?php endif; ?>
        <?php if (can('tickets.create')): ?>
            <a href="/tickets/create?property_id=<?= $property['id'] ?>" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 text-sm">New Ticket</a>
        <?php endif; ?>
        <?php if (can('properties.archive')): ?>
            <form method="POST" action="/properties/<?= $property['id'] ?>/delete" class="inline" onsubmit="return confirm('WARNING: This will archive this property and all its associated tenants, leases, and tickets. This is not reversible. Continue?')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 text-sm">Archive</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">Tenants</h2>
                <?php if (can('tenants.create')): ?>
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
                                    <a href="/tenants/<?= $t['id'] ?>" class="text-blue-600 hover:underline font-medium"><?= h($t['name']) ?></a>
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
                <h2 class="text-lg font-semibold text-gray-800">Leases &amp; Documents</h2>
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
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center space-x-2">
                    <a href="/properties/<?= $property['id'] ?>/photos/<?= $ph['id'] ?>/download" class="bg-white text-gray-800 p-2 rounded-full hover:bg-gray-100" title="Download">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
