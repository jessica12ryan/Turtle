<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Staff</h1>
    <a href="/staff/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">Add Staff</a>
</div>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if (empty($staff)): ?>
        <div class="p-6 text-center text-gray-500">No staff found.</div>
    <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($staff as $s): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <a href="/staff/<?= $s['id'] ?>" class="text-blue-600 hover:underline font-medium"><?= h($s['name']) ?></a>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= h($s['email']) ?></td>
                        <td class="px-6 py-4 text-sm">
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded"><?= ucfirst(str_replace('_', ' ', $s['role'])) ?></span>
                        </td>
                        <td class="px-6 py-4 space-x-2">
                            <a href="/staff/<?= $s['id'] ?>/edit" class="text-blue-600 hover:underline text-sm">Edit</a>
                            <?php if (in_array(\App\Core\Auth::instance()->user()['role'], ['admin', 'landlord'])): ?>
                                <form method="POST" action="/staff/<?= $s['id'] ?>/delete" class="inline" onsubmit="return confirm('Archive this staff member? They will no longer be able to log in.')">
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
