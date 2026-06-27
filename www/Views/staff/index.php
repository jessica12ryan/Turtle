<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800"><?= __('Staff') ?></h1>
    <div class="flex items-center space-x-3">
        <?php if (can('staff.archive') || can('staff.restore')): ?>
            <a href="?show_archived=<?= $showArchived ? '0' : '1' ?>" class="text-sm <?= $showArchived ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700' ?> px-3 py-1.5 rounded-lg border transition">
                <?= $showArchived ? __('Showing archived') : __('Show archived') ?>
            </a>
        <?php endif; ?>
        <?php if (can('staff.create')): ?>
            <a href="/staff/create" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 text-sm font-medium"><?= __('Add Staff') ?></a>
        <?php endif; ?>
    </div>
</div>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if (empty($staff)): ?>
        <div class="p-6 text-center text-gray-500"><?= __('No staff found.') ?></div>
    <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase"><?= __('Name') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase"><?= __('Email') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase"><?= __('Role') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($staff as $s): ?>
                    <tr class="hover:bg-gray-50 <?= $s['archived_at'] ? 'opacity-60' : '' ?>">
                        <td class="px-6 py-4">
                            <a href="/staff/<?= $s['id'] ?>" class="text-blue-600 hover:underline font-medium"><?= h($s['name']) ?></a>
                            <?php if ($s['archived_at']): ?>
                                <span class="text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded ml-1"><?= __('Archived') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= h($s['email']) ?></td>
                        <td class="px-6 py-4 text-sm">
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded"><?= ucfirst(str_replace('_', ' ', $s['role'])) ?></span>
                        </td>
                        <td class="px-6 py-4 space-x-2">
                            <?php if (!$s['archived_at']): ?>
                                <?php if (can('staff.edit')): ?>
                                    <a href="/staff/<?= $s['id'] ?>/edit" class="text-blue-600 hover:underline text-sm"><?= __('Edit') ?></a>
                                <?php endif; ?>
                                <?php if (can('staff.archive')): ?>
                                    <form method="POST" action="/staff/<?= $s['id'] ?>/delete" class="inline" onsubmit="return confirm('<?= __('WARNING: This will archive this staff member and is not reversible. They will no longer be able to log in. Continue?') ?>')">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                        <button type="submit" class="text-orange-600 hover:underline text-sm"><?= __('Archive') ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if (can('staff.delete')): ?>
                                    <form method="POST" action="/staff/<?= $s['id'] ?>/hard-delete" class="inline" onsubmit="return confirm('<?= __('WARNING: This will permanently delete this staff member. This action cannot be undone. Continue?') ?>')">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                        <button type="submit" class="text-red-600 hover:underline text-sm"><?= __('Delete') ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif (can('staff.restore')): ?>
                                <form method="POST" action="/staff/<?= $s['id'] ?>/restore" class="inline">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <button type="submit" class="text-green-600 hover:underline text-sm"><?= __('Restore') ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
