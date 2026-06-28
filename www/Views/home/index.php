<div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-800"><?= __('Home') ?></h1>
    <p class="text-gray-500 mt-1"><?= __('Welcome back') ?>, <?= h(\App\Core\Auth::instance()->user()['name']) ?></p>
</div>

<?php if (!empty($alerts['critical'])): ?>
        <div class="space-y-3 mb-6">
            <?php foreach ($alerts['critical'] as $a): ?>
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg flex items-start space-x-3">
                    <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-red-800"><?= h($a['msg']) ?></p>
                        <?php if (isset($a['link'])): ?>
                            <a href="<?= $a['link'] ?>" class="text-sm text-blue-600 hover:underline mt-1 inline-block"><?= __('Take action →') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($alerts['warning'])): ?>
        <div class="space-y-3 mb-6">
            <?php foreach ($alerts['warning'] as $a): ?>
                <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg flex items-start space-x-3">
                    <svg class="w-5 h-5 text-yellow-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-yellow-800"><?= h($a['msg']) ?></p>
                        <?php if (isset($a['link'])): ?>
                            <a href="<?= $a['link'] ?>" class="text-sm text-blue-600 hover:underline mt-1 inline-block"><?= __('Take action →') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <?php if (isset($stats['properties'])): ?>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-blue-600"><?= $stats['properties'] ?></p>
            <p class="text-sm text-gray-500"><?= __('Properties') ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($stats['occupied']) && isset($stats['total_units'])): ?>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-teal-600"><?= $stats['occupied'] ?>/<?= $stats['total_units'] ?></p>
            <p class="text-sm text-gray-500"><?= __('Occupancy') ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($stats['tenants'])): ?>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-green-600"><?= $stats['tenants'] ?></p>
            <p class="text-sm text-gray-500"><?= __('Tenants') ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($stats['leases'])): ?>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-purple-600"><?= $stats['leases'] ?></p>
            <p class="text-sm text-gray-500"><?= __('Documents') ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($stats['open_tickets'])): ?>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-2xl font-bold text-orange-600"><?= $stats['open_tickets'] ?></p>
            <p class="text-sm text-gray-500"><?= __('Open Tickets') ?></p>
        </div>
    <?php endif; ?>
</div>

<?php if (isset($recentTickets) && !empty($recentTickets)): ?>
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800"><?= __('Recent Tickets') ?></h2>
            <a href="/tickets" class="text-sm text-blue-600 hover:underline"><?= __('View all') ?></a>
        </div>
        <div class="p-6">
            <div class="space-y-3">
                <?php foreach ($recentTickets as $ticket): ?>
                    <a href="/tickets/<?= $ticket['id'] ?>" class="block p-4 border rounded-lg hover:bg-gray-50">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-medium text-gray-800"><?= h($ticket['subject']) ?></p>
                                <p class="text-sm text-gray-500 mt-1"><?= h($ticket['property_name']) ?> - <?= h($ticket['tenant_name']) ?></p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full <?= $ticket['status'] === 'open' ? 'bg-yellow-100 text-yellow-800' : ($ticket['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' : ($ticket['status'] === 'awaiting_parts' ? 'bg-purple-100 text-purple-800' : ($ticket['status'] === 'awaiting_contractor' ? 'bg-indigo-100 text-indigo-800' : ($ticket['status'] === 'closed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800')))) ?>"><?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($role === 'tenant' && !empty($properties)): ?>
    <div class="bg-white rounded-lg shadow mt-6">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-gray-800"><?= __('My Properties') ?></h2>
        </div>
        <div class="p-6 space-y-3">
            <?php foreach ($properties as $property): ?>
                <a href="/properties/<?= $property['id'] ?>" class="block p-4 border rounded-lg hover:bg-gray-50">
                    <p class="font-medium text-gray-800"><?= h($property['name']) ?></p>
                    <p class="text-sm text-gray-500"><?= h($property['landlord_name']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($role === 'tenant' && !empty($openTickets)): ?>
    <div class="bg-white rounded-lg shadow mt-6">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800"><?= __('Open Tickets') ?></h2>
            <a href="/tickets" class="text-sm text-blue-600 hover:underline"><?= __('View all') ?></a>
        </div>
        <div class="p-6 space-y-3">
            <?php foreach ($openTickets as $ticket): ?>
                <a href="/tickets/<?= $ticket['id'] ?>" class="block p-4 border rounded-lg hover:bg-gray-50">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium text-gray-800"><?= h($ticket['subject']) ?></p>
                            <p class="text-sm text-gray-500 mt-1"><?= h($ticket['property_name']) ?> - <?= h($ticket['tenant_name']) ?></p>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full <?= $ticket['status'] === 'open' ? 'bg-yellow-100 text-yellow-800' : ($ticket['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' : ($ticket['status'] === 'awaiting_parts' ? 'bg-purple-100 text-purple-800' : ($ticket['status'] === 'awaiting_contractor' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800'))) ?>"><?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
