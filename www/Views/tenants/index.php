<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Tenants</h1>
    <div class="flex items-center space-x-3">
        <?php if (can('tenants.edit') || can('tenants.restore')): ?>
            <a href="?show_archived=<?= $showArchived ? '0' : '1' ?>" class="text-sm <?= $showArchived ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700' ?> px-3 py-1.5 rounded-lg border transition">
                <?= $showArchived ? 'Showing archived' : 'Show archived' ?>
            </a>
        <?php endif; ?>
        <a href="/tenants/create" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 text-sm font-medium">Add Tenant</a>
    </div>
</div>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if (empty($tenants)): ?>
        <div class="p-6 text-center text-gray-500">No tenants found.</div>
    <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Phone</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Property</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($tenants as $tenant): ?>
                    <tr class="hover:bg-gray-50 <?= $tenant['archived_at'] ? 'opacity-60' : '' ?>">
                        <td class="px-6 py-4">
                            <a href="/tenants/<?= $tenant['id'] ?>" class="text-blue-600 hover:underline font-medium"><?= h($tenant['name']) ?></a>
                            <?php if ($tenant['is_main_tenant']): ?>
                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded ml-1">Main</span>
                            <?php endif; ?>
                            <?php if ($tenant['archived_at']): ?>
                                <span class="text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded ml-1">Archived</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= h($tenant['email']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= h($tenant['phone'] ?? '') ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><a href="/properties/<?= $tenant['property_id'] ?>" class="text-blue-600 hover:underline"><?= h($tenant['property_name']) ?></a></td>
                        <td class="px-6 py-4">
                            <?php if ($tenant['moved_out_at']): ?>
                                <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">Moved Out</span>
                            <?php else: ?>
                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 space-x-2">
                            <?php if (!$tenant['archived_at']): ?>
                                <a href="/tenants/<?= $tenant['id'] ?>/edit" class="text-blue-600 hover:underline text-sm">Edit</a>
                                <?php if (can('tenants.edit')): ?>
                                    <form method="POST" action="/tenants/<?= $tenant['id'] ?>/move-out" class="inline" onsubmit="return confirm('WARNING: This will archive this tenant and is not reversible. They will be removed from the property and their account disabled. Continue?')">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                        <button type="submit" class="text-orange-600 hover:underline text-sm">Archive</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (can('tenants.delete')): ?>
                                    <form method="POST" action="/tenants/<?= $tenant['id'] ?>/delete" class="inline" onsubmit="return confirm('WARNING: This will permanently delete this tenant and all associated data. This action cannot be undone. Continue?')">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                        <button type="submit" class="text-red-600 hover:underline text-sm">Delete</button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif (can('tenants.restore')): ?>
                                <form method="POST" action="/tenants/<?= $tenant['id'] ?>/restore" class="inline">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <button type="submit" class="text-green-600 hover:underline text-sm">Restore</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
