<?php $user = \App\Core\Auth::instance()->user(); ?>
<?php
$ticketFiles = array_filter($files ?? [], fn($f) => $f['comment_id'] === null);
$commentFiles = [];
foreach ($files ?? [] as $f) {
    if ($f['comment_id'] !== null) {
        $commentFiles[$f['comment_id']][] = $f;
    }
}
?>
<div class="mb-6">
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= h($ticket['subject']) ?></h1>
            <p class="text-gray-500 mt-1"><?= h($ticket['property_name']) ?> — <?= __('Opened by') ?> <?= h($ticket['tenant_name']) ?> <?= __('on') ?> <?= display_time($ticket['created_at']) ?></p>
        </div>
        <div class="flex items-center space-x-2">
            <span class="px-3 py-1 text-sm rounded-full <?= $ticket['status'] === 'open' ? 'bg-yellow-100 text-yellow-800' : ($ticket['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' : ($ticket['status'] === 'awaiting_parts' ? 'bg-purple-100 text-purple-800' : ($ticket['status'] === 'awaiting_contractor' ? 'bg-indigo-100 text-indigo-800' : ($ticket['status'] === 'closed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800')))) ?>"><?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?></span>
            <span class="px-3 py-1 text-sm rounded-full <?= $ticket['priority'] === 'emergency' ? 'bg-red-100 text-red-800' : ($ticket['priority'] === 'high' ? 'bg-orange-100 text-orange-800' : ($ticket['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')) ?>"><?= ucfirst($ticket['priority']) ?></span>
        </div>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-3"><?= __('Description') ?></h2>
            <p class="text-gray-600 whitespace-pre-wrap"><?= h($ticket['description']) ?></p>
            <?php if (!empty($ticketFiles)): ?>
                <div class="mt-4 pt-4 border-t">
                    <h3 class="text-sm font-medium text-gray-700 mb-2"><?= __('Attachments') ?></h3>
                    <div class="space-y-1">
                        <?php foreach ($ticketFiles as $f): ?>
                            <div class="flex items-center text-sm">
                                <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                <a href="/tickets/<?= $ticket['id'] ?>/files/<?= $f['id'] ?>/download" class="text-blue-600 hover:underline"><?= h($f['original_name']) ?></a>
                                <span class="text-xs text-gray-400 ml-2">(<?= $f['size'] ? round($f['size'] / 1024) . ' KB' : '' ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-semibold text-gray-800"><?= __('Comments') ?> (<?= count(array_filter($comments, fn($c) => $user['role'] !== 'tenant' || !$c['is_internal'])) ?>)</h2>
            </div>
            <div class="p-6 space-y-4">
                <?php if (empty($comments)): ?>
                    <p class="text-gray-500 text-sm"><?= __('No comments yet.') ?></p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <?php if ($comment['is_internal'] && $user['role'] === 'tenant') continue; ?>
                        <?php if ($comment['is_system']): ?>
                            <div class="p-3 border rounded-lg bg-gray-50 border-gray-200">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded"><?= __('System') ?></span>
                                    <span class="text-xs text-gray-400"><?= display_time($comment['created_at']) ?></span>
                                </div>
                                <p class="text-gray-500 text-sm italic"><?= h($comment['body']) ?></p>
                            </div>
                        <?php else: ?>
                            <div class="p-4 border rounded-lg <?= $comment['is_internal'] ? 'bg-yellow-50 border-yellow-200' : '' ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <span class="font-medium text-sm"><?= h($comment['user_name']) ?></span>
                                        <span class="text-xs bg-gray-100 px-2 py-0.5 rounded ml-1"><?= ucfirst(str_replace('_', ' ', $comment['user_role'])) ?></span>
                                        <?php if ($comment['is_internal']): ?>
                                            <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded ml-1"><?= __('Internal') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs text-gray-500"><?= display_time($comment['created_at']) ?></span>
                                </div>
                                <p class="text-gray-700 text-sm whitespace-pre-wrap"><?= h($comment['body']) ?></p>
                                <?php if (!empty($commentFiles[$comment['id']])): ?>
                                    <div class="mt-3 pt-3 border-t space-y-1">
                                        <?php foreach ($commentFiles[$comment['id']] as $cf): ?>
                                            <div class="flex items-center text-sm">
                                                <svg class="w-4 h-4 text-gray-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                <a href="/tickets/<?= $ticket['id'] ?>/files/<?= $cf['id'] ?>/download" class="text-blue-600 hover:underline"><?= h($cf['original_name']) ?></a>
                                                <span class="text-xs text-gray-400 ml-2">(<?= $cf['size'] ? round($cf['size'] / 1024) . ' KB' : '' ?>)</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Add Comment') ?></h2>
            <form method="POST" action="/tickets/<?= $ticket['id'] ?>/comment" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <div class="mb-3">
                    <textarea name="body" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required placeholder="<?= __('Type your comment...') ?>"></textarea>
                </div>
                <?php if ($user['role'] !== 'tenant'): ?>
                    <div class="mb-3">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_internal" value="1" class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500">
                            <span class="ml-2 text-sm text-gray-600"><?= __('Internal note (not visible to tenant)') ?></span>
                        </label>
                    </div>
                <?php endif; ?>
                <?php if (can('tickets.upload_photos')): ?>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Attachments') ?></label>
                        <input type="file" name="attachments[]" multiple class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                <?php endif; ?>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm"><?= __('Post Comment') ?></button>
            </form>
        </div>
    </div>
    <div class="space-y-6">
        <?php if (can('tickets.assign')): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Assign') ?></h2>
                <form method="POST" action="/tickets/<?= $ticket['id'] ?>/assign">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <select name="assigned_to" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-3 focus:ring-2 focus:ring-blue-500">
                        <option value=""><?= __('Unassigned') ?></option>
                        <?php foreach ($staffUsers as $su): ?>
                            <option value="<?= $su['id'] ?>" <?= $su['id'] == $ticket['assigned_to'] ? 'selected' : '' ?>><?= h($su['name']) ?> (<?= ucfirst(str_replace('_', ' ', $su['role'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm"><?= __('Update') ?></button>
                </form>
            </div>
        <?php endif; ?>
        <?php if (can('tickets.update_status')): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Status') ?></h2>
                <form method="POST" action="/tickets/<?= $ticket['id'] ?>/status">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-3 focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $s === $ticket['status'] ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm"><?= __('Update Status') ?></button>
                </form>
            </div>
        <?php endif; ?>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-3"><?= __('Details') ?></h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500"><?= __('Category') ?></dt><dd><?= ucfirst(str_replace('_', ' ', $ticket['category'])) ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500"><?= __('Priority') ?></dt><dd><?= ucfirst($ticket['priority']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500"><?= __('Assigned To') ?></dt><dd><?= h($ticket['assignee_name'] ?? __('Unassigned')) ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500"><?= __('Created') ?></dt><dd><?= display_time($ticket['created_at']) ?></dd></div>
            </dl>
        </div>
    </div>
</div>