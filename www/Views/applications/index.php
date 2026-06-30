<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-black dark:text-white"><?= __('Applications') ?></h1>
    <div class="flex items-center space-x-3">
        <?php if (can('applications.view')): ?>
            <a href="/applications?show_archived=<?= $showArchived ? '0' : '1' ?>" class="text-sm <?= $showArchived ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700' ?> px-3 py-1.5 rounded-lg border transition">
                <?= $showArchived ? __('Showing archived') : __('Show archived') ?>
            </a>
        <?php endif; ?>
    </div>
</div>
<div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
    <?php if (empty($applications)): ?>
        <div class="p-6 text-center text-gray-500 dark:text-gray-400"><?= __('No applications found.') ?></div>
    <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('ID') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Applicant') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Property') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Submitted') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Status') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($applications as $app): ?>
                    <?php $data = json_decode($app['data'], true); ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 <?= $app['archived_at'] ? 'opacity-60' : '' ?>">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-200">#<?= $app['id'] ?></td>
                        <td class="px-6 py-4">
                            <a href="/applications/<?= $app['id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                <?= h($data['primary_applicant']['first_name'] ?? '') ?> <?= h($data['primary_applicant']['last_name'] ?? '') ?>
                            </a>
                            <?php if ($app['archived_at']): ?>
                                <span class="text-xs bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 px-2 py-0.5 rounded ml-1"><?= __('Archived') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"><?= h($app['property_name'] ?? '—') ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"><?= date('M j, Y', strtotime($app['created_at'])) ?></td>
                        <td class="px-6 py-4">
                            <?php $status = $app['status']; ?>
                            <span class="px-2 py-1 text-xs rounded-full <?= $status === 'pending' ? 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200' : ($status === 'reviewed' ? 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200' : ($status === 'accepted' ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : ($status === 'rejected' ? 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200' : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200'))) ?>">
                                <?= ucfirst($status) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 space-x-2 whitespace-nowrap">
                            <a href="/applications/<?= $app['id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline text-sm"><?= __('View') ?></a>
                            <?php if (!$app['archived_at']): ?>
                                <?php if (can('applications.edit')): ?>
                                    <form method="POST" action="/applications/<?= $app['id'] ?>/archive" class="inline" onsubmit="return confirm('<?= __('Archive this application?') ?>')">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                        <button type="submit" class="text-orange-600 hover:underline text-sm"><?= __('Archive') ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif (can('applications.edit')): ?>
                                <form method="POST" action="/applications/<?= $app['id'] ?>/restore" class="inline">
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
