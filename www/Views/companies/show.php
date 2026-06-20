<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= h($company['name']) ?></h1>
        <p class="text-gray-500"><?= h($company['address']) ?>, <?= h($company['city']) ?>, <?= h($company['province']) ?> <?= h($company['postal_code']) ?></p>
    </div>
    <div class="flex space-x-3">
        <a href="/companies/<?= $company['id'] ?>/edit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">Edit</a>
        <a href="/properties/create?company_id=<?= $company['id'] ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm">Add Property</a>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-gray-800">Properties</h2>
        </div>
        <div class="p-6">
            <?php if (empty($properties)): ?>
                <p class="text-gray-500 text-sm">No properties yet.</p>
            <?php else: ?>
                <ul class="divide-y">
                    <?php foreach ($properties as $p): ?>
                        <li class="py-3"><a href="/properties/<?= $p['id'] ?>" class="text-blue-600 hover:underline"><?= h($p['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-gray-800">Users</h2>
        </div>
        <div class="p-6">
            <?php if (empty($users)): ?>
                <p class="text-gray-500 text-sm">No users assigned.</p>
            <?php else: ?>
                <ul class="divide-y">
                    <?php foreach ($users as $u): ?>
                        <li class="py-3 flex justify-between items-center">
                            <span><?= h($u['name']) ?></span>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded"><?= ucfirst(str_replace('_', ' ', $u['role'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
