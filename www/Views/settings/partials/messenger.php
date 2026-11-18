<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-4"><?= __('Messenger Settings') ?></h2>
    <form method="POST" action="/settings/messenger">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <label class="flex items-center space-x-3">
            <input type="checkbox" name="messaging_enabled" value="1" <?= ($messagingEnabled ?? true) ? 'checked' : '' ?>
                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span class="text-sm font-medium text-gray-700"><?= __('Allow Messaging') ?></span>
        </label>
        <p class="text-xs text-gray-500 ml-7 mt-1"><?= __('Tenants cannot message other tenants.') ?></p>
        <div class="mt-4 ml-7">
            <button type="submit" class="bg-blue-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-700"><?= __('Save') ?></button>
        </div>
    </form>
</div>

<div class="bg-white rounded-lg shadow mt-6">
    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-gray-800"><?= __('Chat History') ?></h2>
        <form method="GET" action="/settings" class="flex items-center space-x-2">
            <input type="hidden" name="tab" value="messenger">
            <input type="text" name="q" value="<?= h($messageSearch ?? '') ?>" placeholder="<?= __('Search messages') ?>"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="bg-blue-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-700"><?= __('Search') ?></button>
        </form>
    </div>

    <div class="flex min-h-[24rem]">
        <div class="w-1/3 border-r border-gray-200 overflow-y-auto max-h-[32rem]">
            <?php if (empty($conversations)): ?>
                <p class="p-4 text-sm text-gray-500"><?= __('No conversations found.') ?></p>
            <?php else: ?>
                <?php foreach ($conversations as $conversation): ?>
                    <a href="/settings?tab=messenger&conversation=<?= urlencode($conversation['key']) ?><?= !empty($messageSearch) ? '&q=' . urlencode($messageSearch) : '' ?>"
                       class="block px-4 py-3 border-b border-gray-100 hover:bg-gray-50 <?= $conversation['key'] === ($selectedConversation ?? '') ? 'bg-blue-50' : '' ?>">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-800 truncate"><?= h($conversation['name']) ?></span>
                            <span class="text-xs text-gray-400 ml-2 whitespace-nowrap"><?= (int)$conversation['count'] ?></span>
                        </div>
                        <p class="text-xs text-gray-500 truncate mt-1"><?= h($conversation['last_body']) ?></p>
                        <p class="text-xs text-gray-400 mt-1"><?= display_time($conversation['last_at']) ?></p>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="w-2/3 flex flex-col min-h-0 max-h-[32rem]">
            <?php if (empty($threadMessages)): ?>
                <div class="flex-1 flex items-center justify-center p-6 text-sm text-gray-500">
                    <?= __('Select a conversation to view its messages.') ?>
                </div>
            <?php else: ?>
                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    <?php foreach ($threadMessages as $message): ?>
                        <div class="border border-gray-200 rounded-lg p-3">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-blue-700"><?= h($message['sender_name']) ?> → <?= h($message['recipient_name']) ?></span>
                                <span class="text-xs text-gray-400"><?= display_time($message['created_at']) ?></span>
                            </div>
                            <p class="text-sm text-gray-700 whitespace-pre-wrap break-words"><?= h($message['body']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
