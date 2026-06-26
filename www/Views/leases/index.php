<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Leases &amp; Documents</h1>
    <div class="flex items-center space-x-3">
        <?php if (can('leases.archive') || can('leases.restore')): ?>
            <a href="?show_archived=<?= $showArchived ? '0' : '1' ?>" class="text-sm <?= $showArchived ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700' ?> px-3 py-1.5 rounded-lg border transition">
                <?= $showArchived ? 'Showing archived' : 'Show archived' ?>
            </a>
        <?php endif; ?>
        <?php if (can('leases.create')): ?>
            <a href="/leases/create" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 text-sm font-medium">Upload Document</a>
        <?php endif; ?>
    </div>
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
                    <tr class="hover:bg-gray-50 <?= $lease['archived_at'] ? 'opacity-60' : '' ?>">
                        <td class="px-6 py-4">
                            <a href="/leases/<?= $lease['id'] ?>" class="text-blue-600 hover:underline font-medium"><?= h($lease['title']) ?></a>
                            <?php if ($lease['archived_at']): ?>
                                <span class="text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded ml-1">Archived</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= h($lease['property_name']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= count($lease['documents']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= display_time($lease['created_at'], 'M j, Y') ?></td>
                        <td class="px-6 py-4 space-x-2">
                            <?php if (!$lease['archived_at'] && can('leases.archive')): ?>
                                <form method="POST" action="/leases/<?= $lease['id'] ?>/delete" class="inline" onsubmit="return confirm('WARNING: This will archive this lease and is not reversible. Continue?')">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <button type="submit" class="text-orange-600 hover:underline text-sm">Archive</button>
                                </form>
                                <?php if (can('leases.delete')): ?>
                                    <form method="POST" action="/leases/<?= $lease['id'] ?>/hard-delete" class="inline" onsubmit="return confirm('WARNING: This will permanently delete this lease and all associated documents. This action cannot be undone. Continue?')">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                        <button type="submit" class="text-red-600 hover:underline text-sm">Delete</button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif ($lease['archived_at'] && can('leases.restore')): ?>
                                <form method="POST" action="/leases/<?= $lease['id'] ?>/restore" class="inline">
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
