<h1 class="text-2xl font-bold text-gray-800 mb-6">Dashboard</h1>
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-sm text-gray-500">Properties</p>
        <p class="text-3xl font-bold text-gray-800 mt-1"><?= $propertiesCount ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-sm text-gray-500">Active Tenants</p>
        <p class="text-3xl font-bold text-gray-800 mt-1"><?= $activeTenants ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-sm text-gray-500">Open Tickets</p>
        <p class="text-3xl font-bold text-yellow-600 mt-1"><?= $openTickets ?></p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-sm text-gray-500">Companies</p>
        <p class="text-3xl font-bold text-gray-800 mt-1"><?= count($companies) ?></p>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-gray-800">Your Companies</h2>
        </div>
        <div class="p-6">
            <?php if (empty($companies)): ?>
                <p class="text-gray-500 text-sm">No companies yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($companies as $company): ?>
                        <a href="/companies/<?= $company['id'] ?>" class="block p-4 border rounded-lg hover:bg-gray-50">
                            <p class="font-medium text-gray-800"><?= h($company['name']) ?></p>
                            <p class="text-sm text-gray-500 mt-1"><?= $company['properties_count'] ?> properties</p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">Recent Tickets</h2>
            <a href="/tickets" class="text-sm text-blue-600 hover:underline">View all</a>
        </div>
        <div class="p-6">
            <?php if (empty($recentTickets)): ?>
                <p class="text-gray-500 text-sm">No recent tickets.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recentTickets as $ticket): ?>
                        <a href="/tickets/<?= $ticket['id'] ?>" class="block p-4 border rounded-lg hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium text-gray-800"><?= h($ticket['subject']) ?></p>
                                    <p class="text-sm text-gray-500 mt-1"><?= h($ticket['property_name']) ?> - <?= h($ticket['tenant_name']) ?></p>
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
