<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Tenants</h1>
    <a href="/tenants/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">Add Tenant</a>
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
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <a href="/tenants/<?= $tenant['id'] ?>" class="text-blue-600 hover:underline font-medium"><?= h($tenant['name']) ?></a>
                            <?php if ($tenant['is_main_tenant']): ?>
                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded ml-1">Main</span>
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
                            <a href="/tenants/<?= $tenant['id'] ?>/edit" class="text-blue-600 hover:underline text-sm">Edit</a>
                            <?php if (in_array(\App\Core\Auth::instance()->user()['role'], ['admin', 'landlord', 'property_manager'])): ?>
                                <form method="POST" action="/tenants/<?= $tenant['id'] ?>/move-out" class="inline" onsubmit="return confirm('Archive this tenant? They will be removed from the property and their account disabled.')">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <button type="submit" class="text-red-600 hover:underline text-sm">Archive</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
