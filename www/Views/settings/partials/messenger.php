<div class="bg-white rounded-lg shadow">
    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-gray-800"><?= __('Chat History') ?></h2>
        <form method="GET" action="/settings" class="flex items-center space-x-2">
            <input type="hidden" name="tab" value="messenger">
            <input type="text" name="q" value="<?= h($messageSearch ?? '') ?>" placeholder="<?= __('Search messages') ?>"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="bg-blue-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-700"><?= __('Search') ?></button>
        </form>
    </div>
    <div class="p-6 overflow-x-auto">
        <?php if (empty($messages)): ?>
            <p class="text-sm text-gray-500"><?= __('No messages found.') ?></p>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-3 py-2 text-xs font-medium text-gray-500 uppercase"><?= __('Date') ?></th>
                        <th class="text-left px-3 py-2 text-xs font-medium text-gray-500 uppercase"><?= __('From') ?></th>
                        <th class="text-left px-3 py-2 text-xs font-medium text-gray-500 uppercase"><?= __('To') ?></th>
                        <th class="text-left px-3 py-2 text-xs font-medium text-gray-500 uppercase"><?= __('Message') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($messages as $message): ?>
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-500"><?= display_time($message['created_at']) ?></td>
                            <td class="px-3 py-2 font-medium text-gray-800"><?= h($message['sender_name']) ?></td>
                            <td class="px-3 py-2 text-gray-700"><?= h($message['recipient_name']) ?></td>
                            <td class="px-3 py-2 text-gray-700 whitespace-pre-wrap break-words"><?= h($message['body']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
