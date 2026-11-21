<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-slate-100"><?= __('Guest Chats') ?></h1>
    <p class="text-gray-500 mt-1 dark:text-slate-400"><?= __('Conversations started by visitors on the public listings page.') ?></p>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden dark:bg-slate-800">
    <?php if (empty($chats)): ?>
        <p class="p-6 text-sm text-gray-500 dark:text-slate-400"><?= __('No conversations found.') ?></p>
    <?php else: ?>
        <div class="divide-y divide-gray-100 dark:divide-slate-700">
            <?php foreach ($chats as $chat): ?>
                <a href="/guest-chats/<?= (int) $chat['id'] ?>" data-testid="guest-chat-row"
                   class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-gray-50 dark:hover:bg-slate-700">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-gray-800 truncate dark:text-slate-100"><?= h($chat['guest_name']) ?></span>
                            <?php if ((int) $chat['unread'] > 0): ?>
                                <span class="bg-blue-600 text-white text-xs rounded-full px-2 py-0.5"><?= (int) $chat['unread'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="text-sm text-gray-500 dark:text-slate-400"><?= h($chat['property_name']) ?></div>
                        <?php if (!empty($chat['last_body'])): ?>
                            <div class="text-xs text-gray-400 truncate mt-1 dark:text-slate-500"><?= h($chat['last_body']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-gray-400 flex-shrink-0 dark:text-slate-500"><?= h(display_time($chat['last_activity'])) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
