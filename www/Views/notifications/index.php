<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Notifications</h1>
    <form method="POST" action="/notifications/read-all">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <button type="submit" class="text-sm text-blue-600 hover:underline">Mark all as read</button>
    </form>
</div>
<div class="bg-white rounded-lg shadow">
    <?php if (empty($notifications)): ?>
        <div class="p-6 text-center text-gray-500">No notifications.</div>
    <?php else: ?>
        <ul class="divide-y">
            <?php foreach ($notifications as $n): ?>
                <li class="px-6 py-4 <?= $n['read_at'] ? '' : 'bg-blue-50' ?>">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-gray-800"><?= h($n['type']) ?></p>
                            <p class="text-sm text-gray-600 mt-1"><?= h($n['data']) ?></p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs text-gray-500"><?= display_time($n['created_at']) ?></span>
                            <?php if (!$n['read_at']): ?>
                                <form method="POST" action="/notifications/<?= $n['id'] ?>/read">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <button type="submit" class="text-xs text-blue-600 hover:underline">Mark read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
