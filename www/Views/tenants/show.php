<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= h($tenant['name']) ?></h1>
        <p class="text-gray-500"><?= h($tenant['email']) ?></p>
    </div>
    <div class="flex space-x-3">
        <?php if (can('tenants.edit')): ?>
            <a href="/tenants/<?= $tenant['id'] ?>/edit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm"><?= __('Edit') ?></a>
        <?php endif; ?>
        <?php if (can('tenants.edit')): ?>
            <form method="POST" action="/tenants/<?= $tenant['id'] ?>/move-out" class="inline" onsubmit="return confirm('<?= __('Archive this tenant? They will be removed from the property and their account disabled.') ?>')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 text-sm"><?= __('Archive') ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Details') ?></h2>
        <dl class="space-y-3">
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500"><?= __('Property') ?></dt>
                <dd class="text-sm font-medium"><a href="/properties/<?= $tenant['property_id'] ?>" class="text-blue-600 hover:underline"><?= h($tenant['property_name']) ?></a></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500"><?= __('Phone') ?></dt>
                <dd class="text-sm text-gray-600"><?= h($tenant['phone'] ?? __('N/A')) ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500"><?= __('Role') ?></dt>
                <dd class="text-sm font-medium"><?= $tenant['is_main_tenant'] ? __('Main Tenant') : __('Additional Tenant') ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500"><?= __('Assigned') ?></dt>
                <dd class="text-sm text-gray-600"><?= $tenant['assigned_at'] ?></dd>
            </div>
            <?php if ($tenant['moved_out_at']): ?>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500"><?= __('Moved Out') ?></dt>
                    <dd class="text-sm text-red-600 font-medium"><?= $tenant['moved_out_at'] ?></dd>
                </div>
            <?php endif; ?>
        </dl>
    </div>
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-gray-800"><?= __('Tickets') ?></h2>
        </div>
        <div class="p-6">
            <?php if (empty($tickets)): ?>
                <p class="text-gray-500 text-sm"><?= __('No tickets.') ?></p>
            <?php else: ?>
                <ul class="divide-y">
                    <?php foreach ($tickets as $t): ?>
                        <li class="py-3">
                            <a href="/tickets/<?= $t['id'] ?>" class="text-blue-600 hover:underline text-sm"><?= h($t['subject']) ?></a>
                            <span class="text-xs text-gray-500 block"><?= h($t['property_name']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
