<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Leases</h1>
    <?php if (in_array(\App\Core\Auth::instance()->user()['role'], ['admin', 'landlord', 'property_manager'])): ?>
        <a href="/leases/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">Upload Lease</a>
    <?php endif; ?>
</div>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if (empty($leases)): ?>
        <div class="p-6 text-center text-gray-500">No leases found.</div>
    <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Title</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Property</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Documents</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Uploaded</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($leases as $lease): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4"><a href="/leases/<?= $lease['id'] ?>" class="text-blue-600 hover:underline font-medium"><?= h($lease['title']) ?></a></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= h($lease['property_name']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= count($lease['documents']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= date('M j, Y', strtotime($lease['created_at'])) ?></td>
                        <td class="px-6 py-4 space-x-2">
                            <a href="/leases/<?= $lease['id'] ?>" class="text-blue-600 hover:underline text-sm">View</a>
                            <?php if (in_array(\App\Core\Auth::instance()->user()['role'], ['admin', 'landlord', 'property_manager'])): ?>
                                <form method="POST" action="/leases/<?= $lease['id'] ?>/delete" class="inline" onsubmit="return confirm('Archive this lease?')">
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
