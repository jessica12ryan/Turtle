<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            <?= h($staff['name']) ?>
            <?php if ($staff['archived_at']): ?>
                <span class="text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded ml-1 align-middle"><?= __('Archived') ?></span>
            <?php endif; ?>
        </h1>
        <p class="text-gray-500"><?= h($staff['email']) ?></p>
    </div>
    <div class="flex space-x-3">
        <?php if ($staff['archived_at']): ?>
            <?php if (can('staff.restore')): ?>
                <form method="POST" action="/staff/<?= $staff['id'] ?>/restore" class="inline">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <button type="submit" data-tooltip="<?= __('Restore this item') ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm"><?= __('Restore') ?></button>
                </form>
            <?php endif; ?>
            <?php if (can('staff.delete')): ?>
                <form method="POST" action="/staff/<?= $staff['id'] ?>/hard-delete" class="inline" data-confirm="<?= __('WARNING: This will permanently delete this staff member. This action cannot be undone. Continue?') ?>">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <button type="submit" data-tooltip="<?= __('Delete this item permanently') ?>" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm"><?= __('Delete') ?></button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <?php if (can('staff.edit')): ?>
                <a href="/staff/<?= $staff['id'] ?>/edit" data-tooltip="<?= __('Edit this item') ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm"><?= __('Edit') ?></a>
            <?php endif; ?>
            <?php if (can('staff.archive')): ?>
                <form method="POST" action="/staff/<?= $staff['id'] ?>/delete" class="inline" data-confirm="<?= __('Archive this staff member? They will no longer be able to log in.') ?>">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <button type="submit" data-tooltip="<?= __('Archive this item') ?>" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 text-sm"><?= __('Archive') ?></button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Details</h2>
        <dl class="space-y-3">
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500"><?= __('Role') ?></dt>
                <dd class="text-sm font-medium"><?= h(ucwords(str_replace('_', ' ', $staff['role']))) ?></dd>
            </div>
            <?php if (!empty($staff['secondary_roles'])): ?>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500"><?= __('Secondary Roles') ?></dt>
                <dd class="text-sm">
                    <?php foreach (explode(',', $staff['secondary_roles']) as $sr): ?>
                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded"><?= h(ucfirst(str_replace('_', ' ', $sr))) ?></span>
                    <?php endforeach; ?>
                </dd>
            </div>
            <?php endif; ?>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Email</dt>
                <dd class="text-sm text-gray-600"><?= h($staff['email']) ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Joined</dt>
                <dd class="text-sm text-gray-600"><?= display_time($staff['created_at'], 'M j, Y') ?></dd>
            </div>
        </dl>
    </div>
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-gray-800">Assigned Tickets</h2>
        </div>
        <div class="p-6">
            <?php if (empty($assignedTickets)): ?>
                <p class="text-gray-500 text-sm">No assigned tickets.</p>
            <?php else: ?>
                <ul class="divide-y">
                    <?php foreach ($assignedTickets as $t): ?>
                        <li class="py-3">
                            <a href="/tickets/<?= $t['id'] ?>" class="text-blue-600 hover:underline text-sm"><?= h($t['subject']) ?></a>
                            <span class="text-xs text-gray-500 block"><?= h($t['property_name']) ?> — <?= h(ucfirst(str_replace('_', ' ', $t['status']))) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
