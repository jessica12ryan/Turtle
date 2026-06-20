<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= h($staff['name']) ?></h1>
        <p class="text-gray-500"><?= h($staff['email']) ?></p>
    </div>
    <div class="flex space-x-3">
        <a href="/staff/<?= $staff['id'] ?>/edit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">Edit</a>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Details</h2>
        <dl class="space-y-3">
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Role</dt>
                <dd class="text-sm font-medium"><?= ucfirst(str_replace('_', ' ', $staff['role'])) ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Email</dt>
                <dd class="text-sm text-gray-600"><?= h($staff['email']) ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Joined</dt>
                <dd class="text-sm text-gray-600"><?= date('M j, Y', strtotime($staff['created_at'])) ?></dd>
            </div>
        </dl>
        <div class="mt-6">
            <h3 class="text-sm font-medium text-gray-700 mb-2">Companies</h3>
            <?php if (empty($companies)): ?>
                <p class="text-sm text-gray-500">Not assigned to any company.</p>
            <?php else: ?>
                <ul class="space-y-1">
                    <?php foreach ($companies as $c): ?>
                        <li class="text-sm text-blue-600"><a href="/companies/<?= $c['id'] ?>" class="hover:underline"><?= h($c['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
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
                            <span class="text-xs text-gray-500 block"><?= h($t['property_name']) ?> — <?= ucfirst($t['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
