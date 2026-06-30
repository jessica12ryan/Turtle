<?php $hasMainPhoto = false;
$mainPhotoId = null;
foreach ($photos as $ph) { if ($ph['is_main']) { $hasMainPhoto = true; $mainPhotoId = $ph['id']; break; } } ?>

<div class="flex justify-between items-center mb-6">
    <div class="flex items-center space-x-4">
        <?php if ($hasMainPhoto): ?>
            <img src="/properties/<?= $property['id'] ?>/photos/<?= $mainPhotoId ?>" alt="Main photo" class="w-16 h-16 rounded-lg object-cover flex-shrink-0">
        <?php endif; ?>
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= h($property['name']) ?></h1>
            <p class="text-gray-500"><?= h($property['address']) ?>, <?= h($property['city']) ?>, <?= h($property['province']) ?><?= ($property['country'] ?? 'CA') !== 'CA' ? ', ' . h($property['country']) : '' ?></p>
        </div>
    </div>
    <div class="flex space-x-3">
        <?php if (can('properties.edit')): ?>
            <a href="/properties/<?= $property['id'] ?>/edit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm"><?= __('Edit') ?></a>
        <?php endif; ?>
        <?php if (can('leases.create')): ?>
            <a href="/leases/create?property_id=<?= $property['id'] ?>" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 text-sm"><?= __('Upload Document') ?></a>
        <?php endif; ?>
        <?php if (can('tickets.create')): ?>
            <a href="/tickets/create?property_id=<?= $property['id'] ?>" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 text-sm"><?= __('New Ticket') ?></a>
        <?php endif; ?>
        <?php if (can('properties.archive')): ?>
            <form method="POST" action="/properties/<?= $property['id'] ?>/delete" class="inline" onsubmit="return confirm('<?= __('WARNING: This will archive this property and all its associated tenants, leases, and tickets. This is not reversible. Continue?') ?>')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 text-sm"><?= __('Archive') ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
<div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
    <div class="px-6 py-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200"><?= __('Property Details') ?></h2>
    </div>
    <div class="p-6">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Landlord') ?></dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($property['landlord_name']) ?></dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Property Manager') ?></dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100"><?= $property['property_manager_name'] ? h($property['property_manager_name']) : '<span class="text-gray-400 dark:text-gray-500">—</span>' ?></dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Heating Type') ?></dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100"><?= $property['heating_type'] ? h($property['heating_type']) : '<span class="text-gray-400 dark:text-gray-500">—</span>' ?></dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Security Deposit') ?></dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100"><?= ($property['security_deposit'] ?? 0) > 0 ? '$' . number_format($property['security_deposit'], 2) : '<span class="text-gray-400 dark:text-gray-500">—</span>' ?></dd>
            </div>
            <?php if ($mainTenantLeaseType): ?>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Lease Type') ?></dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100"><?= __(ucwords(str_replace('_', ' ', $mainTenantLeaseType))) ?></dd>
            </div>
            <?php endif; ?>
        </dl>
    </div>
</div>

<?php if (can('rents.access') && ($property['rent_amount'] ?? 0) > 0 && !empty($tenants)): ?>
<div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
    <div class="px-6 py-4 border-b dark:border-gray-700 flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200"><?= __('Rent') ?></h2>
        <a href="/properties/<?= $property['id'] ?>/rent" class="text-sm text-blue-600 dark:text-blue-400 hover:underline"><?= __('View Details') ?></a>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase"><?= __('Monthly Rent') ?></p>
                <p class="text-xl font-bold text-gray-800 dark:text-gray-100">$<?= number_format($property['rent_amount'], 2) ?></p>
            </div>
            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase"><?= __('Due Day') ?></p>
                <p class="text-xl font-bold text-gray-800 dark:text-gray-100"><?= h($property['rent_due_day'] ?? '—') ?></p>
            </div>
            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase"><?= __('This Month') ?></p>
                <?php if ($rentStatus === 'paid'): ?>
                    <p class="text-xl font-bold text-green-600">$<?= number_format($paidThisMonth, 2) ?></p>
                    <span class="text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-2 py-0.5 rounded"><?= __('Paid') ?></span>
                <?php elseif ($rentStatus === 'partial'): ?>
                    <p class="text-xl font-bold text-yellow-600">$<?= number_format($paidThisMonth, 2) ?></p>
                    <span class="text-xs bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 px-2 py-0.5 rounded"><?= __('Partial') ?></span>
                <?php elseif ($rentStatus === 'unpaid'): ?>
                    <p class="text-xl font-bold text-red-600">$0.00</p>
                    <span class="text-xs bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 px-2 py-0.5 rounded"><?= __('Unpaid') ?></span>
                <?php else: ?>
                    <p class="text-xl font-bold text-gray-400 dark:text-gray-500">—</p>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($payments)): ?>
            <details class="mt-4">
                <summary class="text-sm text-blue-600 hover:underline cursor-pointer"><?= __('Payment History') ?> (<?= count($payments) ?>)</summary>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b dark:border-gray-700 text-left">
                                <th class="py-2 pr-3 font-medium text-gray-500 dark:text-gray-400"><?= __('Date') ?></th>
                                <th class="py-2 pr-3 font-medium text-gray-500 dark:text-gray-400"><?= __('Tenant') ?></th>
                                <th class="py-2 pr-3 font-medium text-gray-500 dark:text-gray-400"><?= __('Amount') ?></th>
                                <th class="py-2 pr-3 font-medium text-gray-500 dark:text-gray-400"><?= __('Method') ?></th>
                                <th class="py-2 pr-3 font-medium text-gray-500 dark:text-gray-400"><?= __('Type') ?></th>
                                <th class="py-2 pr-3 font-medium text-gray-500 dark:text-gray-400"><?= __('Recorded By') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($payments, 0, 10) as $pym): ?>
                                <tr class="border-b border-gray-100 dark:border-gray-700">
                                    <td class="py-2 pr-3 text-gray-900 dark:text-gray-200"><?= h($pym['payment_date']) ?></td>
                                    <td class="py-2 pr-3 text-gray-900 dark:text-gray-200"><?= h($pym['tenant_name']) ?></td>
                                    <td class="py-2 pr-3 font-medium text-gray-900 dark:text-gray-200">$<?= number_format($pym['amount'], 2) ?></td>
                                    <td class="py-2 pr-3 capitalize text-gray-900 dark:text-gray-200"><?= h(str_replace('_', ' ', $pym['payment_method'] ?? '—')) ?></td>
                                    <td class="py-2 pr-3 text-gray-900 dark:text-gray-200"><?= !empty($pym['is_security_deposit']) ? '<span class="text-xs bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 px-2 py-0.5 rounded font-medium">' . __('Deposit Paid') . '</span>' : '<span class="text-gray-400 dark:text-gray-500">—</span>' ?></td>
                                    <td class="py-2 pr-3 text-gray-500 dark:text-gray-400"><?= h($pym['recorded_by_name']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
        <?php if (can('rents.payments.create')): ?>
            <form method="POST" action="/properties/<?= $property['id'] ?>/rent" class="mt-4 p-4 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3"><?= __('Record Payment') ?></h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Amount') ?> <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" step="0.01" min="0.01" required class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Date') ?> <span class="text-red-500">*</span></label>
                        <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Method') ?></label>
                        <select name="payment_method" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value=""><?= __('— Select —') ?></option>
                            <option value="cash"><?= __('Cash') ?></option>
                            <option value="e-transfer"><?= __('E-Transfer') ?></option>
                            <option value="cheque"><?= __('Cheque') ?></option>
                            <option value="credit_card"><?= __('Credit Card') ?></option>
                            <option value="debit"><?= __('Debit') ?></option>
                            <option value="other"><?= __('Other') ?></option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Reference') ?></label>
                        <input type="text" name="reference" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="<?= __('Cheque #, transaction ID, etc.') ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Notes') ?></label>
                        <input type="text" name="notes" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500" placeholder="<?= __('Optional notes') ?>">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="flex items-center space-x-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="is_security_deposit" value="1" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                        <span><?= __('Security Deposit') ?></span>
                    </label>
                </div>
                <button type="submit" class="mt-3 bg-blue-600 text-white px-4 py-1.5 rounded-lg hover:bg-blue-700 text-sm font-medium"><?= __('Record Payment') ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="px-6 py-4 border-b dark:border-gray-700 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200"><?= __('Tenants') ?></h2>
                <?php if (can('tenants.create')): ?>
                    <a href="/tenants/create?property_id=<?= $property['id'] ?>" class="text-sm text-blue-600 dark:text-blue-400 hover:underline"><?= __('Add Tenant') ?></a>
                <?php endif; ?>
            </div>
            <div class="p-6">
                <?php if (empty($tenants)): ?>
                    <p class="text-gray-500 dark:text-gray-400 text-sm"><?= __('No tenants assigned.') ?></p>
                <?php else: ?>
                    <ul class="divide-y dark:divide-gray-700">
                        <?php foreach ($tenants as $t): ?>
                            <li class="py-3 flex justify-between items-center">
                                <div>
                                    <a href="/tenants/<?= $t['id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline font-medium"><?= h($t['name']) ?></a>
                                    <?php if ($t['is_main_tenant']): ?>
                                        <span class="text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-0.5 rounded ml-2"><?= __('Main') ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($t['lease_type'])): ?>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 ml-2"><?= __(ucwords(str_replace('_', ' ', $t['lease_type']))) ?></span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200"><?= __('Leases &amp; Documents') ?></h2>
            </div>
            <div class="p-6">
                <?php if (empty($leases)): ?>
                    <p class="text-gray-500 dark:text-gray-400 text-sm"><?= __('No leases uploaded.') ?></p>
                <?php else: ?>
                    <ul class="divide-y dark:divide-gray-700">
                        <?php foreach ($leases as $l): ?>
                            <li class="py-3"><a href="/leases/<?= $l['id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline"><?= h($l['title']) ?></a> <span class="text-sm text-gray-500 dark:text-gray-400">(<?= $l['documents_count'] ?> docs)</span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200"><?= __('Recent Tickets') ?></h2>
        </div>
        <div class="p-6">
            <?php if (empty($tickets)): ?>
                <p class="text-gray-500 dark:text-gray-400 text-sm"><?= __('No tickets.') ?></p>
            <?php else: ?>
                <ul class="divide-y dark:divide-gray-700">
                    <?php foreach ($tickets as $t): ?>
                        <li class="py-3">
                            <a href="/tickets/<?= $t['id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline text-sm"><?= h($t['subject']) ?></a>
                            <span class="text-xs text-gray-500 dark:text-gray-400 block"><?= h($t['tenant_name']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($photos)): ?>
<div class="mb-6">
    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3"><?= __('Photos') ?></h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        <?php foreach ($photos as $ph): ?>
            <div class="relative group border dark:border-gray-700 rounded-lg overflow-hidden <?= $ph['is_main'] ? 'ring-2 ring-blue-500' : '' ?>">
                <img src="/properties/<?= $property['id'] ?>/photos/<?= $ph['id'] ?>" alt="<?= h($ph['original_name']) ?>" class="w-full h-32 object-cover">
                <?php if ($ph['is_main']): ?>
                    <span class="absolute top-1 left-1 bg-blue-600 text-white text-xs px-2 py-0.5 rounded"><?= __('Main') ?></span>
                <?php endif; ?>
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center space-x-2">
                    <a href="/properties/<?= $property['id'] ?>/photos/<?= $ph['id'] ?>/download" class="bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700" title="<?= __('Download') ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
