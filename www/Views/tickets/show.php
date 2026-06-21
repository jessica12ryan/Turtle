<?php
$user = \App\Core\Auth::instance()->user();
$isStaff = in_array($user['role'], ['admin', 'landlord', 'property_manager', 'maintenance']);
$isTenant = $user['role'] === 'tenant';
?>
<div class="mb-6">
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= h($ticket['subject']) ?></h1>
            <p class="text-gray-500 mt-1"><?= h($ticket['property_name']) ?> — Opened by <?= h($ticket['tenant_name']) ?> on <?= date('M j, Y', strtotime($ticket['created_at'])) ?></p>
        </div>
        <div class="flex items-center space-x-2">
            <span class="px-3 py-1 text-sm rounded-full <?= $ticket['status'] === 'open' ? 'bg-yellow-100 text-yellow-800' : ($ticket['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' : ($ticket['status'] === 'resolved' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800')) ?>"><?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?></span>
            <span class="px-3 py-1 text-sm rounded-full <?= $ticket['priority'] === 'emergency' ? 'bg-red-100 text-red-800' : ($ticket['priority'] === 'high' ? 'bg-orange-100 text-orange-800' : ($ticket['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')) ?>"><?= ucfirst($ticket['priority']) ?></span>
        </div>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">Description</h2>
            <p class="text-gray-600 whitespace-pre-wrap"><?= h($ticket['description']) ?></p>
        </div>
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-semibold text-gray-800">Comments (<?= count($comments) ?>)</h2>
            </div>
            <div class="p-6 space-y-4">
                <?php if (empty($comments)): ?>
                    <p class="text-gray-500 text-sm">No comments yet.</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="p-4 border rounded-lg <?= $comment['is_internal'] ? 'bg-yellow-50 border-yellow-200' : '' ?>">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <span class="font-medium text-sm"><?= h($comment['user_name']) ?></span>
                                    <span class="text-xs bg-gray-100 px-2 py-0.5 rounded ml-1"><?= ucfirst(str_replace('_', ' ', $comment['user_role'])) ?></span>
                                    <?php if ($comment['is_internal']): ?>
                                        <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded ml-1">Internal</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-xs text-gray-500"><?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?></span>
                            </div>
                            <p class="text-gray-700 text-sm whitespace-pre-wrap"><?= h($comment['body']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Add Comment</h2>
            <form method="POST" action="/tickets/<?= $ticket['id'] ?>/comment">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <div class="mb-3">
                    <textarea name="body" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required placeholder="Type your comment..."></textarea>
                </div>
                <?php if ($isStaff): ?>
                    <div class="mb-3">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_internal" value="1" class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500">
                            <span class="ml-2 text-sm text-gray-600">Internal note (not visible to tenant)</span>
                        </label>
                    </div>
                <?php endif; ?>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">Post Comment</button>
            </form>
        </div>
    </div>
    <div class="space-y-6">
        <?php if ($isStaff): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Assign</h2>
                <form method="POST" action="/tickets/<?= $ticket['id'] ?>/assign">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <select name="assigned_to" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-3 focus:ring-2 focus:ring-blue-500">
                        <option value="">Unassigned</option>
                        <?php foreach ($staffUsers as $su): ?>
                            <option value="<?= $su['id'] ?>" <?= $su['id'] == $ticket['assigned_to'] ? 'selected' : '' ?>><?= h($su['name']) ?> (<?= ucfirst(str_replace('_', ' ', $su['role'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">Update</button>
                </form>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Status</h2>
                <form method="POST" action="/tickets/<?= $ticket['id'] ?>/status">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-3 focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $s === $ticket['status'] ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">Update Status</button>
                </form>
            </div>
        <?php endif; ?>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">Details</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Category</dt><dd><?= ucfirst(str_replace('_', ' ', $ticket['category'])) ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Priority</dt><dd><?= ucfirst($ticket['priority']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Assigned To</dt><dd><?= h($ticket['assignee_name'] ?? 'Unassigned') ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Created</dt><dd><?= date('M j, Y', strtotime($ticket['created_at'])) ?></dd></div>
            </dl>
        </div>
    </div>
</div>
