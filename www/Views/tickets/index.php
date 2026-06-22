<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Tickets</h1>
    <div class="flex items-center space-x-3">
        <a href="?show_archived=<?= $showArchived ? '0' : '1' ?>" class="text-sm <?= $showArchived ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700' ?> px-3 py-1.5 rounded-lg border transition">
            <?= $showArchived ? 'Showing archived' : 'Show archived' ?>
        </a>
        <?php if (in_array(\App\Core\Auth::instance()->user()['role'], ['admin', 'landlord', 'property_manager', 'tenant'])): ?>
            <a href="/tickets/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">New Ticket</a>
        <?php endif; ?>
    </div>
</div>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if (empty($tickets)): ?>
        <div class="p-6 text-center text-gray-500">No tickets found.</div>
    <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Subject</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Property</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Tenant</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Priority</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Assigned To</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($tickets as $ticket): ?>
                    <tr class="hover:bg-gray-50 <?= $ticket['archived_at'] ? 'opacity-60' : '' ?>">
                        <td class="px-6 py-4">
                            <a href="/tickets/<?= $ticket['id'] ?>" class="text-blue-600 hover:underline font-medium"><?= h($ticket['subject']) ?></a>
                            <?php if ($ticket['archived_at']): ?>
                                <span class="text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded ml-1">Archived</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= h($ticket['property_name']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= h($ticket['tenant_name']) ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full <?= $ticket['status'] === 'open' ? 'bg-yellow-100 text-yellow-800' : ($ticket['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' : ($ticket['status'] === 'resolved' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800')) ?>"><?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full <?= $ticket['priority'] === 'emergency' ? 'bg-red-100 text-red-800' : ($ticket['priority'] === 'high' ? 'bg-orange-100 text-orange-800' : ($ticket['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')) ?>"><?= ucfirst($ticket['priority']) ?></span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= h($ticket['assignee_name'] ?? 'Unassigned') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
