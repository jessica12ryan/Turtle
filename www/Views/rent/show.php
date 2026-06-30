<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?= __('Rent') ?>: <?= h($property['name']) ?></h1>
        <p class="text-gray-500 dark:text-gray-400"><?= h($property['address']) ?>, <?= h($property['city']) ?>, <?= h($property['province']) ?></p>
    </div>
    <a href="/rent" class="text-sm text-blue-600 hover:underline"><?= __('← Back to Dashboard') ?></a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase"><?= __('Monthly Rent') ?></p>
        <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">$<?= number_format($property['rent_amount'] ?? 0, 2) ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase"><?= __('Due Day') ?></p>
        <p class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?= h($property['rent_due_day'] ?? '—') ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase"><?= __('This Month') ?></p>
        <?php if ($rentStatus === 'paid'): ?>
            <p class="text-2xl font-bold text-green-600">$<?= number_format($paidThisMonth, 2) ?></p>
            <span class="text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-2 py-0.5 rounded"><?= __('Paid') ?></span>
        <?php elseif ($rentStatus === 'partial'): ?>
            <p class="text-2xl font-bold text-yellow-600">$<?= number_format($paidThisMonth, 2) ?></p>
            <span class="text-xs bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 px-2 py-0.5 rounded"><?= __('Partial') ?></span>
        <?php elseif ($rentStatus === 'unpaid'): ?>
            <p class="text-2xl font-bold text-red-600">$0.00</p>
            <span class="text-xs bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 px-2 py-0.5 rounded"><?= __('Unpaid') ?></span>
        <?php else: ?>
            <p class="text-2xl font-bold text-gray-400">—</p>
            <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded"><?= __('Not Set') ?></span>
        <?php endif; ?>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase"><?= __('Tenants') ?></p>
        <p class="text-2xl font-bold text-blue-600"><?= count($tenants) ?></p>
    </div>
</div>

<?php if (can('rents.payments.create')): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Record Payment') ?></h2>
        <form method="POST" action="/properties/<?= $property['id'] ?>/rent">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Amount') ?> <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" step="0.01" min="0.01" required class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Date') ?> <span class="text-red-500">*</span></label>
                    <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Method') ?></label>
                    <select name="payment_method" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Reference') ?></label>
                    <input type="text" name="reference" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="<?= __('Cheque #, transaction ID, etc.') ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Notes') ?></label>
                    <input type="text" name="notes" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="<?= __('Optional notes') ?>">
                </div>
            </div>
            <div class="mb-4">
                <label class="flex items-center space-x-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="is_security_deposit" value="1" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                    <span><?= __('Security Deposit') ?></span>
                </label>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium"><?= __('Record Payment') ?></button>
        </form>
    </div>
<?php endif; ?>

<div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200"><?= __('Payment History') ?></h2>
    </div>
    <?php if (empty($payments)): ?>
        <div class="p-6 text-center text-gray-500 dark:text-gray-400"><?= __('No payments recorded yet.') ?></div>
    <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Date') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Tenant') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Amount') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Method') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Reference') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Type') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Notes') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Recorded By') ?></th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($payments as $pym): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-200"><?= h($pym['payment_date']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-200"><?= h($pym['tenant_name']) ?></td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-200">$<?= number_format($pym['amount'], 2) ?></td>
                        <td class="px-6 py-4 text-sm capitalize text-gray-900 dark:text-gray-200"><?= h(str_replace('_', ' ', $pym['payment_method'] ?? '—')) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><?= h($pym['reference'] ?? '—') ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-200"><?= !empty($pym['is_security_deposit']) ? '<span class="text-xs bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 px-2 py-0.5 rounded font-medium">' . __('Deposit Paid') . '</span>' : '—' ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-[200px] truncate"><?= h($pym['notes'] ?? '—') ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><?= h($pym['recorded_by_name']) ?></td>
                        <td class="px-6 py-4 text-sm space-x-2">
                            <?php if (can('rents.payments.edit')): ?>
                                <a href="#edit-<?= $pym['id'] ?>" onclick="document.getElementById('edit-form-<?= $pym['id'] ?>').classList.toggle('hidden')" class="text-blue-600 dark:text-blue-400 hover:underline"><?= __('Edit') ?></a>
                            <?php endif; ?>
                            <?php if (can('rents.payments.archive')): ?>
                                <form method="POST" action="/payments/<?= $pym['id'] ?>/archive" class="inline" onsubmit="return confirm('<?= __('Archive this payment?') ?>')">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <button type="submit" class="text-orange-600 dark:text-orange-400 hover:underline"><?= __('Archive') ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if (\App\Core\Auth::instance()->user()['role'] === 'admin'): ?>
                                <form method="POST" action="/payments/<?= $pym['id'] ?>/delete" class="inline" onsubmit="return confirm('<?= __('Permanently delete this payment? This cannot be undone.') ?>')">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <button type="submit" class="text-red-600 dark:text-red-400 hover:underline"><?= __('Delete') ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (can('rents.payments.edit')): ?>
                        <tr id="edit-form-<?= $pym['id'] ?>" class="hidden bg-blue-50 dark:bg-gray-700">
                            <td colspan="8" class="px-6 py-4">
                                <form method="POST" action="/payments/<?= $pym['id'] ?>/edit" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Amount') ?></label>
                                        <input type="number" name="amount" step="0.01" min="0.01" value="<?= $pym['amount'] ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Date') ?></label>
                                        <input type="date" name="payment_date" value="<?= $pym['payment_date'] ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Method') ?></label>
                                        <select name="payment_method" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500">
                                            <option value="">—</option>
                                            <option value="cash" <?= $pym['payment_method'] === 'cash' ? 'selected' : '' ?>><?= __('Cash') ?></option>
                                            <option value="e-transfer" <?= $pym['payment_method'] === 'e-transfer' ? 'selected' : '' ?>><?= __('E-Transfer') ?></option>
                                            <option value="cheque" <?= $pym['payment_method'] === 'cheque' ? 'selected' : '' ?>><?= __('Cheque') ?></option>
                                            <option value="credit_card" <?= $pym['payment_method'] === 'credit_card' ? 'selected' : '' ?>><?= __('Credit Card') ?></option>
                                            <option value="debit" <?= $pym['payment_method'] === 'debit' ? 'selected' : '' ?>><?= __('Debit') ?></option>
                                            <option value="other" <?= $pym['payment_method'] === 'other' ? 'selected' : '' ?>><?= __('Other') ?></option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Reference') ?></label>
                                        <input type="text" name="reference" value="<?= h($pym['reference'] ?? '') ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Notes') ?></label>
                                        <input type="text" name="notes" value="<?= h($pym['notes'] ?? '') ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div class="flex items-end space-x-2">
                                        <button type="submit" class="bg-blue-600 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700"><?= __('Save') ?></button>
                                        <button type="button" onclick="document.getElementById('edit-form-<?= $pym['id'] ?>').classList.add('hidden')" class="text-gray-600 dark:text-gray-400 px-3 py-1.5 rounded border dark:border-gray-600 text-sm hover:bg-gray-50 dark:hover:bg-gray-700"><?= __('Cancel') ?></button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
