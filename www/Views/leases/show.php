<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?= h($lease['title']) ?></h1>
        <p class="text-gray-500"><?= h($lease['property_name']) ?> — Uploaded by <?= h($lease['uploader_name']) ?> on <?= display_time($lease['created_at'], 'M j, Y') ?></p>
        <?php if (!empty($lease['document_type'])): ?>
            <?php
            $docTypeLabels = [
                'lease_agreement' => __('Lease Agreement'),
                'rental_unit_condition' => __('Rental Unit Condition'),
                'government_issued_photo_id' => __('Government Issued Photo ID'),
                'security_deposit_claim' => __('Security Deposit Claim'),
                'notice_to_quit' => __('Notice to Quit'),
                'notice_to_enter' => __('Notice to Enter'),
                'other' => __('Other'),
            ];
            ?>
            <span class="inline-block mt-1 text-xs font-medium bg-blue-100 text-blue-800 px-2 py-0.5 rounded"><?= $docTypeLabels[$lease['document_type']] ?? h($lease['document_type']) ?></span>
        <?php endif; ?>
    </div>
    <div class="flex space-x-3">
        <a href="/properties/<?= $lease['property_id'] ?>" class="text-blue-600 hover:underline text-sm">View Property</a>
        <?php if (can('leases.archive')): ?>
            <form method="POST" action="/leases/<?= $lease['id'] ?>/delete" class="inline" onsubmit="return confirm('Archive this lease?')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="text-orange-600 hover:underline text-sm">Archive</button>
            </form>
        <?php endif; ?>
        <?php if (can('leases.delete')): ?>
            <form method="POST" action="/leases/<?= $lease['id'] ?>/hard-delete" class="inline" onsubmit="return confirm('WARNING: This will permanently delete this lease and all associated documents. This is NOT reversible. Continue?')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="text-red-800 hover:underline text-sm font-bold">Delete</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Description</h2>
    <p class="text-gray-600"><?= h($lease['description'] ?: 'No description.') ?></p>
</div>
<div class="bg-white rounded-lg shadow mt-6">
    <div class="px-6 py-4 border-b">
        <h2 class="text-lg font-semibold text-gray-800">Documents (<?= count($documents) ?>)</h2>
    </div>
    <div class="p-6">
        <?php if (empty($documents)): ?>
            <p class="text-gray-500 text-sm">No documents attached.</p>
        <?php else: ?>
            <ul class="divide-y">
                <?php foreach ($documents as $doc): ?>
                    <li class="py-3 flex justify-between items-center">
                        <div>
                            <p class="font-medium text-gray-800"><?= h($doc['original_name']) ?></p>
                            <p class="text-sm text-gray-500"><?= round($doc['size'] / 1024) ?> KB</p>
                        </div>
                        <a href="/documents/<?= $doc['id'] ?>/view" target="_blank" rel="noopener" class="bg-blue-600 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700 mr-2">View</a>
                        <a href="/documents/<?= $doc['id'] ?>/download" class="bg-blue-600 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700">Download</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
