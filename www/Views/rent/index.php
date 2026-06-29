<h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-6"><?= __('Rent Dashboard') ?></h1>

<?php if (empty($rentData)): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center">
        <p class="text-gray-500 dark:text-gray-400"><?= __('No properties with rent configured. Set a rent amount on a property to get started.') ?></p>
    </div>
<?php else: ?>
    <!-- Totals -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-blue-600">$<?= number_format($totalExpected, 2) ?></p>
            <p class="text-sm text-gray-500 dark:text-gray-400"><?= __('Total Expected') ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-green-600">$<?= number_format($totalCollected, 2) ?></p>
            <p class="text-sm text-gray-500 dark:text-gray-400"><?= __('Total Collected') ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-<?= $totalCollected >= $totalExpected ? 'green' : 'orange' ?>-600"><?= count($rentData) ?></p>
            <p class="text-sm text-gray-500 dark:text-gray-400"><?= __('Properties') ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-purple-600"><?= count(array_filter($rentData, fn($p) => $p['rent_status'] === 'paid')) ?>/<?= count($rentData) ?></p>
            <p class="text-sm text-gray-500 dark:text-gray-400"><?= __('Paid This Month') ?></p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Property') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Tenants') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Monthly Rent') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Due Day') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('This Month') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Status') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Last Payment') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($rentData as $prop): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4">
                            <a href="/properties/<?= $prop['id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline font-medium"><?= h($prop['name']) ?></a>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"><?= $prop['tenants_count'] ?></td>
                        <td class="px-6 py-4 text-sm font-medium dark:text-gray-200">$<?= number_format($prop['rent_amount'], 2) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"><?= h($prop['rent_due_day'] ?? '—') ?></td>
                        <td class="px-6 py-4 text-sm font-medium dark:text-gray-200">$<?= number_format($prop['paid_amount'], 2) ?></td>
                        <td class="px-6 py-4">
                            <?php if ($prop['rent_status'] === 'paid'): ?>
                                <span class="text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-2 py-1 rounded-full font-medium"><?= __('Paid') ?></span>
                            <?php elseif ($prop['rent_status'] === 'partial'): ?>
                                <span class="text-xs bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 px-2 py-1 rounded-full font-medium"><?= __('Partial') ?></span>
                            <?php else: ?>
                                <span class="text-xs bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 px-2 py-1 rounded-full font-medium"><?= __('Unpaid') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                            <?php if ($prop['last_payment_date']): ?>
                                <?= h($prop['last_payment_date']) ?><br>
                                <span class="text-xs dark:text-gray-400">$<?= number_format($prop['last_payment_amount'], 2) ?></span>
                            <?php else: ?>
                                <span class="text-gray-400 dark:text-gray-500">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <a href="/properties/<?= $prop['id'] ?>/rent" class="text-blue-600 dark:text-blue-400 hover:underline text-sm"><?= __('Manage') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
