<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-white"><?= __('Notifications') ?></h1>
    <div class="flex items-center space-x-3">
        <a href="/notifications?show_archived=<?= $showArchived ? '0' : '1' ?>" data-tooltip="<?= __('Show or hide read notifications') ?>" class="text-sm <?= $showArchived ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white' ?> px-3 py-1.5 rounded-lg border dark:border-slate-600 transition">
            <?= $showArchived ? __('Showing read') : __('Show read') ?>
        </a>
        <form method="POST" action="/notifications/read-all">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <button type="submit" class="text-sm text-blue-600 dark:text-blue-400 hover:underline" data-tooltip="<?= __('Mark all as read') ?>"><?= __('Mark all as read') ?></button>
        </form>
    </div>
</div>
<div class="bg-white dark:bg-slate-900 rounded-lg shadow">
    <?php if (empty($notifications)): ?>
        <div class="p-6 text-center text-gray-500 dark:text-gray-400"><?= __('No notifications.') ?></div>
    <?php else: ?>
        <ul class="divide-y divide-gray-200 dark:divide-slate-700">
            <?php foreach ($notifications as $n): ?>
                <?php $labels = notificationEventLabels(); ?>
                <li class="px-6 py-4 <?= $n['read_at'] ? '' : 'bg-blue-50 dark:bg-blue-950/30' ?>">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-gray-800 dark:text-white"><?= h($labels[$n['type']] ?? ($n['type'] === 'email' ? __('Email') : $n['type'])) ?></p>
                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 whitespace-pre-wrap"><?= h($n['data']) ?></p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400"><?= display_time($n['created_at']) ?></span>
                            <?php if (!$n['read_at']): ?>
                                <form method="POST" action="/notifications/<?= $n['id'] ?>/read">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <button type="submit" class="text-xs text-blue-600 dark:text-blue-400 hover:underline" data-tooltip="<?= __('Mark read') ?>"><?= __('Mark read') ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
