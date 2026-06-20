<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Companies</h1>
    <a href="/companies/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">New Company</a>
</div>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if (empty($companies)): ?>
        <div class="p-6 text-center text-gray-500">No companies found.</div>
    <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Location</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Properties</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($companies as $company): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4"><a href="/companies/<?= $company['id'] ?>" class="text-blue-600 hover:underline font-medium"><?= h($company['name']) ?></a></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= h($company['city']) ?>, <?= h($company['province']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= $company['properties_count'] ?></td>
                        <td class="px-6 py-4">
                            <a href="/companies/<?= $company['id'] ?>/edit" class="text-blue-600 hover:underline text-sm">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
