<div class="bg-white rounded-lg shadow" data-testid="guest-chat-history">
    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-gray-800"><?= __('Guest Chat History') ?></h2>
        <form method="GET" action="/settings" class="flex items-center space-x-2">
            <input type="hidden" name="tab" value="guest_chats">
            <input type="text" name="q" value="<?= h($guestChatSearch ?? '') ?>" placeholder="<?= __('Search guest messages') ?>"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="bg-blue-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-700"><?= __('Search') ?></button>
        </form>
    </div>

    <div class="flex min-h-[24rem]">
        <div class="w-1/3 border-r border-gray-200 overflow-y-auto max-h-[32rem]">
            <?php if (empty($guestChatConversations)): ?>
                <p class="p-4 text-sm text-gray-500"><?= __('No conversations found.') ?></p>
            <?php else: ?>
                <?php foreach ($guestChatConversations as $conversation): ?>
                    <a href="/settings?tab=guest_chats&conversation=<?= urlencode($conversation['key']) ?><?= !empty($guestChatSearch) ? '&q=' . urlencode($guestChatSearch) : '' ?>"
                       data-testid="guest-chat-conversation"
                       class="block px-4 py-3 border-b border-gray-100 hover:bg-gray-50 <?= $conversation['key'] === ($selectedGuestChat ?? '') ? 'bg-blue-50' : '' ?>">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-800 truncate"><?= h($conversation['name']) ?></span>
                            <span class="text-xs text-gray-400 ml-2 whitespace-nowrap"><?= (int) $conversation['count'] ?></span>
                        </div>
                        <p class="text-xs text-gray-500 truncate mt-1"><?= h($conversation['email']) ?></p>
                        <p class="text-xs text-gray-500 truncate mt-1"><?= h($conversation['last_body']) ?></p>
                        <p class="text-xs text-gray-400 mt-1">
                            <?= h(display_time($conversation['last_at'])) ?>
                            <?php if ($conversation['archived']): ?>
                                <span class="ml-1 text-orange-600"><?= __('Archived') ?></span>
                            <?php endif; ?>
                        </p>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="w-2/3 flex flex-col min-h-0 max-h-[32rem]">
            <?php if (empty($guestChatThread)): ?>
                <div class="flex-1 flex items-center justify-center p-6 text-sm text-gray-500">
                    <?= __('Select a conversation to view its messages.') ?>
                </div>
            <?php else: ?>
                <div class="flex-1 overflow-y-auto p-4 space-y-3" data-testid="guest-chat-thread">
                    <?php foreach ($guestChatThread as $message): ?>
                        <div class="flex <?= $message['from_staff'] ? 'justify-end' : 'justify-start' ?>">
                            <div class="max-w-[80%] rounded-lg px-4 py-2 <?= $message['from_staff'] ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-800 dark:bg-slate-700 dark:text-slate-100' ?>">
                                <div class="text-[11px] mb-1 opacity-80"><?= h($message['sender_name']) ?></div>
                                <div class="whitespace-pre-wrap break-words text-sm"><?= h($message['body']) ?></div>
                                <div class="text-[10px] mt-1 opacity-70"><?= h(display_time($message['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
