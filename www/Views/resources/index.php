<?php $role = \App\Core\Auth::instance()->user()['role']; ?>
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Resources</h1>
    <?php if (in_array($role, ['admin', 'landlord', 'property_manager'])): ?>
        <a href="/resources/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">Add Resource</a>
    <?php endif; ?>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php if (empty($links)): ?>
        <div class="col-span-full text-center py-12 text-gray-500">No resources added yet.</div>
    <?php else: ?>
        <?php foreach ($links as $link): ?>
            <div class="bg-white rounded-lg shadow p-5 hover:shadow-md transition">
                <a href="<?= h($link['url']) ?>" target="_blank" rel="noopener noreferrer" class="text-lg font-semibold text-blue-600 hover:underline flex items-center space-x-2">
                    <span><?= h($link['title']) ?></span>
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
                <?php if ($link['description']): ?>
                    <p class="text-sm text-gray-600 mt-2"><?= h($link['description']) ?></p>
                <?php endif; ?>
                <p class="text-xs text-gray-400 mt-2">Added by <?= h($link['created_by_name']) ?></p>
                <?php if (in_array($role, ['admin', 'landlord', 'property_manager'])): ?>
                    <div class="mt-3 pt-3 border-t flex space-x-3">
                        <a href="/resources/<?= $link['id'] ?>/edit" class="text-blue-600 hover:underline text-sm">Edit</a>
                        <form method="POST" action="/resources/<?= $link['id'] ?>/delete" class="inline" onsubmit="return confirm('Delete this resource?')">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <button type="submit" class="text-red-600 hover:underline text-sm">Delete</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
