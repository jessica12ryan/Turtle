<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-slate-100"><?= __('Your conversations') ?></h1>
        <p class="text-sm text-gray-500 mt-1 dark:text-slate-400">
            <?= __('Signed in as :name (:email)', [':name' => h($visitor['name']), ':email' => h($visitor['email'])]) ?>
        </p>
        <p class="text-gray-600 mt-3 dark:text-slate-300"><?= __('Choose a property to continue your conversation.') ?></p>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden dark:bg-slate-800">
        <?php if (empty($chats)): ?>
            <p class="p-6 text-sm text-gray-500 dark:text-slate-400"><?= __('No conversations found.') ?></p>
        <?php else: ?>
            <div class="divide-y divide-gray-100 dark:divide-slate-700">
                <?php foreach ($chats as $chat): ?>
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <div class="min-w-0">
                            <h2 class="font-semibold text-gray-800 dark:text-slate-100"><?= h($chat['property_name']) ?></h2>
                            <p class="text-xs text-gray-500 mt-1 dark:text-slate-400">
                                <?= __('Last activity') ?>: <?= h(display_time($chat['last_activity'])) ?>
                            </p>
                        </div>
                        <a href="/listings/conversations/<?= h($hubToken) ?>/chat/<?= (int) $chat['id'] ?>"
                           class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700"
                           data-testid="hub-conversation-link">
                            <?= __('Open conversation') ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <p class="mt-5 text-sm">
        <a href="/listings" class="text-blue-600 hover:underline dark:text-blue-400"><?= __('Back to listings') ?></a>
    </p>
</div>
