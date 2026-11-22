<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-slate-100"><?= __('Guest Chats') ?></h1>
    <p class="text-gray-500 mt-1 dark:text-slate-400"><?= __('Conversations started by visitors on the public listings page.') ?></p>
    <?php if (can('guest_chats.archive') || can('guest_chats.restore')): ?>
        <a href="/guest-chats?show_archived=<?= ($showArchived ?? false) ? '0' : '1' ?>"
           class="inline-block mt-3 text-sm text-blue-600 hover:underline dark:text-blue-400"
           data-testid="guest-chat-archived-toggle">
            <?= ($showArchived ?? false) ? __('Showing archived') : __('Show archived') ?>
        </a>
    <?php endif; ?>
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
                                <span class="font-semibold text-gray-800 truncate dark:text-slate-100" data-testid="guest-chat-group-email"><?= h($group['guest_email']) ?></span>
                                <?php if ((int) $group['unread'] > 0): ?>
                                    <span class="bg-blue-600 text-white text-xs rounded-full px-2 py-0.5" data-testid="guest-chat-group-unread"><?= (int) $group['unread'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-xs text-gray-400 dark:text-slate-500"><?= h(display_time($group['last_activity'])) ?></div>
                    </div>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-slate-700">
                    <?php foreach ($group['chats'] as $chat): ?>
                        <div class="flex items-stretch" data-testid="guest-chat-row-wrapper">
                            <a href="/guest-chats/<?= (int) $chat['id'] ?>" data-testid="guest-chat-row"
                               class="flex flex-1 items-center justify-between gap-4 px-5 py-4 hover:bg-gray-50 dark:hover:bg-slate-700">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-800 truncate dark:text-slate-100"><?= h($chat['property_name']) ?></span>
                                        <?php if (!empty($chat['archived_at'])): ?>
                                            <span class="bg-red-100 text-red-700 text-xs rounded-full px-2 py-0.5" data-testid="guest-chat-archived-badge"><?= __('Archived') ?></span>
                                        <?php endif; ?>
                                        <?php if ((int) $chat['unread'] > 0): ?>
                                            <span class="bg-blue-600 text-white text-xs rounded-full px-2 py-0.5"><?= (int) $chat['unread'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm text-gray-500 truncate dark:text-slate-400" data-testid="guest-chat-row-name"><?= h($chat['guest_name']) ?></div>
                                    <?php if (!empty($chat['last_body'])): ?>
                                        <div class="text-xs text-gray-400 truncate mt-1 dark:text-slate-500"><?= h($chat['last_body']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-400 flex-shrink-0 dark:text-slate-500"><?= h(display_time($chat['last_activity'])) ?></div>
                            </a>
                            <div class="flex items-center gap-2 pr-4" data-testid="guest-chat-row-actions">
                                <?php if (empty($chat['archived_at']) && can('guest_chats.archive')): ?>
                                    <form method="POST" action="/guest-chats/<?= (int) $chat['id'] ?>/archive"
                                          onsubmit="return confirm('<?= h(__('Archive this conversation? The guest will no longer be able to access it.')) ?>')">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                        <button type="submit" data-testid="guest-chat-list-archive" class="text-xs border border-orange-500 text-orange-600 px-2 py-1 rounded hover:bg-orange-50 dark:hover:bg-slate-700"><?= __('Archive') ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!empty($chat['archived_at']) && can('guest_chats.restore')): ?>
                                    <form method="POST" action="/guest-chats/<?= (int) $chat['id'] ?>/restore">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                        <button type="submit" data-testid="guest-chat-list-restore" class="text-xs border border-orange-500 text-orange-600 px-2 py-1 rounded hover:bg-orange-50 dark:hover:bg-slate-700"><?= __('Restore') ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if (can('guest_chats.delete')): ?>
                                    <form method="POST" action="/guest-chats/<?= (int) $chat['id'] ?>/delete"
                                          onsubmit="return confirm('<?= h(__('Permanently delete this conversation and all its messages? This cannot be undone.')) ?>')">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                        <button type="submit" data-testid="guest-chat-list-delete" class="text-xs bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700"><?= __('Delete') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
