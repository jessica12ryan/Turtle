<h1 class="text-2xl font-bold text-gray-800 mb-6">My Dashboard</h1>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-sm text-gray-500">My Properties</p>
        <p class="text-3xl font-bold text-gray-800 mt-1"><?= count($properties) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-sm text-gray-500">Recent Tickets</p>
        <p class="text-3xl font-bold text-gray-800 mt-1"><?= count($tickets) ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-sm text-gray-500">Leases</p>
        <p class="text-3xl font-bold text-gray-800 mt-1"><?= count($leases) ?></p>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <?php if (!empty($properties)): ?>
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-semibold text-gray-800">My Properties</h2>
            </div>
            <div class="p-6 space-y-3">
                <?php foreach ($properties as $property): ?>
                    <a href="/properties/<?= $property['id'] ?>" class="block p-4 border rounded-lg hover:bg-gray-50">
                        <p class="font-medium text-gray-800"><?= h($property['name']) ?></p>
                        <p class="text-sm text-gray-500"><?= h($property['company_name']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">My Tickets</h2>
            <a href="/tickets/create" class="text-sm bg-blue-600 text-white px-3 py-1.5 rounded hover:bg-blue-700">New Ticket</a>
        </div>
        <div class="p-6">
            <?php if (empty($tickets)): ?>
                <p class="text-gray-500 text-sm">No tickets yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($tickets as $ticket): ?>
                        <a href="/tickets/<?= $ticket['id'] ?>" class="block p-4 border rounded-lg hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium text-gray-800"><?= h($ticket['subject']) ?></p>
                                    <p class="text-sm text-gray-500"><?= h($ticket['property_name']) ?></p>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full <?= $ticket['status'] === 'open' ? 'bg-yellow-100 text-yellow-800' : ($ticket['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800') ?>"><?= ucfirst($ticket['status']) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
