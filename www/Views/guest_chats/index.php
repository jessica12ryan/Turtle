<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-slate-100"><?= __('Guest Chats') ?></h1>
    <p class="text-gray-500 mt-1 dark:text-slate-400"><?= __('Conversations started by visitors on the public listings page.') ?></p>
</div>

<?php if (empty($groups)): ?>
    <div class="bg-white rounded-lg shadow p-6 dark:bg-slate-800">
        <p class="text-sm text-gray-500 dark:text-slate-400"><?= __('No conversations found.') ?></p>
    </div>
<?php else: ?>
    <div class="space-y-6">
        <?php foreach ($groups as $group): ?>
            <section class="bg-white rounded-lg shadow overflow-hidden dark:bg-slate-800" data-testid="guest-chat-group">
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 dark:bg-slate-700/40 dark:border-slate-700">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-800 truncate dark:text-slate-100" data-testid="guest-chat-group-name"><?= h($group['guest_name']) ?></span>
                                <?php if ((int) $group['unread'] > 0): ?>
                                    <span class="bg-blue-600 text-white text-xs rounded-full px-2 py-0.5" data-testid="guest-chat-group-unread"><?= (int) $group['unread'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-500 truncate dark:text-slate-400" data-testid="guest-chat-group-email"><?= h($group['guest_email']) ?></div>
                        </div>
                        <div class="text-xs text-gray-400 dark:text-slate-500"><?= h(display_time($group['last_activity'])) ?></div>
                    </div>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-slate-700">
                    <?php foreach ($group['chats'] as $chat): ?>
                        <a href="/guest-chats/<?= (int) $chat['id'] ?>" data-testid="guest-chat-row"
                           class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-gray-50 dark:hover:bg-slate-700">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-800 truncate dark:text-slate-100"><?= h($chat['property_name']) ?></span>
                                    <?php if ((int) $chat['unread'] > 0): ?>
                                        <span class="bg-blue-600 text-white text-xs rounded-full px-2 py-0.5"><?= (int) $chat['unread'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($chat['last_body'])): ?>
                                    <div class="text-xs text-gray-400 truncate mt-1 dark:text-slate-500"><?= h($chat['last_body']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-400 flex-shrink-0 dark:text-slate-500"><?= h(display_time($chat['last_activity'])) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
