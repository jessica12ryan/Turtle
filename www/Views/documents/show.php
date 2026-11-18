<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            <?= h($document['original_name']) ?>
            <?php if ($document['archived_at']): ?>
                <span class="text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded ml-1 align-middle"><?= __('Archived') ?></span>
            <?php endif; ?>
        </h1>
        <p class="text-gray-500"><?= __('Document Details') ?></p>
    </div>
    <div class="flex space-x-3">
        <?php if ($document['archived_at']): ?>
            <?php if (can('leases.restore')): ?>
                <form method="POST" action="/documents/<?= $document['id'] ?>/restore" class="inline">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <button type="submit" data-tooltip="<?= __('Restore this item') ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm"><?= __('Restore') ?></button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <?php if (can('leases.archive')): ?>
                <form method="POST" action="/documents/<?= $document['id'] ?>/delete" class="inline" onsubmit="return confirm('<?= __('WARNING: This will archive this document. Continue?') ?>')">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <button type="submit" data-tooltip="<?= __('Archive this item') ?>" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 text-sm"><?= __('Archive') ?></button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (can('documents.delete')): ?>
            <form method="POST" action="/documents/<?= $document['id'] ?>/hard-delete" class="inline" onsubmit="return confirm('<?= __('WARNING: This will permanently delete this document. This action cannot be undone. Continue?') ?>')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button type="submit" data-tooltip="<?= __('Delete this item permanently') ?>" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm"><?= __('Delete') ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-6 max-w-3xl">
    <dl class="space-y-3">
        <div class="flex justify-between">
            <dt class="text-sm text-gray-500"><?= __('File Name') ?></dt>
            <dd class="text-sm font-medium"><?= h($document['original_name']) ?></dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-sm text-gray-500"><?= __('Size') ?></dt>
            <dd class="text-sm text-gray-700"><?= round((int)$document['size'] / 1024) ?> KB</dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-sm text-gray-500"><?= __('Type') ?></dt>
            <dd class="text-sm text-gray-700"><?= h($document['mime_type'] ?: __('Unknown')) ?></dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-sm text-gray-500"><?= __('Uploaded') ?></dt>
            <dd class="text-sm text-gray-700"><?= display_time($document['created_at']) ?></dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-sm text-gray-500"><?= __('Uploaded By') ?></dt>
            <dd class="text-sm text-gray-700"><?= h($document['uploader_name'] ?? __('Unknown')) ?></dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-sm text-gray-500"><?= __('Linked Lease') ?></dt>
            <dd class="text-sm text-gray-700"><?= $lease ? h($lease['title']) : __('None') ?></dd>
        </div>
        <?php if ($lease && $lease['property_name']): ?>
        <div class="flex justify-between">
            <dt class="text-sm text-gray-500"><?= __('Property') ?></dt>
            <dd class="text-sm text-gray-700"><?= h($lease['property_name']) ?></dd>
        </div>
        <?php endif; ?>
    </dl>

    <div class="mt-6 pt-6 border-t flex space-x-3">
        <a href="/documents/<?= $document['id'] ?>/view" target="_blank" rel="noopener" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm"><?= __('View') ?></a>
        <a href="/documents/<?= $document['id'] ?>/download" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm"><?= __('Download') ?></a>
        <a href="/leases" class="text-gray-600 px-4 py-2 rounded-lg border hover:bg-gray-50 text-sm"><?= __('Back to Documents') ?></a>
    </div>
</div>
