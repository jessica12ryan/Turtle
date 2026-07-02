<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?= __('Application') ?> #<?= $application['id'] ?></h1>
    <div class="flex items-center space-x-3">
        <a href="/applications" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">&larr; <?= __('Back to Applications') ?></a>
    </div>
</div>

<!-- Status & Meta -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <span class="px-3 py-1 text-sm rounded-full <?= $application['status'] === 'new' ? 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200' : ($application['status'] === 'in_progress' ? 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200' : ($application['status'] === 'accepted' ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : ($application['status'] === 'rejected' ? 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200' : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200'))) ?>">
                <?= ucfirst(str_replace('_', ' ', $application['status'])) ?>
            </span>
        </div>
        <div class="text-sm text-gray-500 dark:text-gray-400">
            <?= __('Submitted') ?>: <?= date('F j, Y g:i A', strtotime($application['created_at'])) ?>
        </div>
    </div>
    <div class="text-sm text-gray-600 dark:text-gray-400">
        <strong><?= __('Property') ?>:</strong> <?= h($application['property_name'] ?? __('Not specified')) ?>
    </div>

    <!-- Status update form -->
    <?php if (can('applications.edit')): ?>
        <form method="POST" action="/applications/<?= $application['id'] ?>/status" class="mt-4 flex items-center space-x-3">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300"><?= __('Update Status') ?>:</label>
            <select name="status" class="border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                <option value="new" <?= $application['status'] === 'new' ? 'selected' : '' ?>><?= __('New') ?></option>
                <option value="in_progress" <?= $application['status'] === 'in_progress' ? 'selected' : '' ?>><?= __('In Progress') ?></option>
                <option value="accepted" <?= $application['status'] === 'accepted' ? 'selected' : '' ?>><?= __('Accepted') ?></option>
                <option value="rejected" <?= $application['status'] === 'rejected' ? 'selected' : '' ?>><?= __('Rejected') ?></option>
            </select>
            <button type="submit" class="bg-blue-600 text-white px-4 py-1.5 rounded text-sm hover:bg-blue-700"><?= __('Update') ?></button>
        </form>
    <?php endif; ?>
</div>

<!-- Notes -->
<?php if (can('applications.edit')): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Internal Notes') ?></h2>
        <form method="POST" action="/applications/<?= $application['id'] ?>/notes">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <textarea name="notes" rows="4" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"><?= h($application['notes'] ?? '') ?></textarea>
            <div class="mt-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm"><?= __('Save Notes') ?></button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Primary Applicant -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Applicant Information') ?></h2>
    <?php $p = $data['primary_applicant']; ?>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Name') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($p['first_name']) ?> <?= h($p['middle_names'] ? $p['middle_names'] . ' ' : '') ?><?= h($p['last_name']) ?></dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Birth Date') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($p['birth_date']) ?></dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Phone') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($p['phone']) ?></dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Email') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($p['email']) ?></dd>
        </div>
        <?php if (!empty($p['photo_id'])): ?>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Government Issued Photo ID') ?></dt>
                <dd class="text-sm"><a href="/applications/<?= $application['id'] ?>/photo/primary" target="_blank" class="text-blue-600 hover:underline"><?= __('View Document') ?></a></dd>
            </div>
        <?php endif; ?>
    </dl>
</div>

<!-- Current Address -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Current Address') ?></h2>
    <?php $addr = $p['current_address']; ?>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Address') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($addr['street']) ?><?= $addr['apt_suite'] ? ', ' . h($addr['apt_suite']) : '' ?>, <?= h($addr['city']) ?>, <?= h($addr['province']) ?>, <?= h($addr['postal_code']) ?></dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Date Moved In') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($addr['date_moved_in']) ?></dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Reason For Leaving') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($addr['reason_leaving']) ?></dd>
        </div>
    </dl>
</div>

<!-- Employment -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Employment & Income Information') ?></h2>
    <?php $emp = $p['employment']; ?>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Occupation') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($emp['occupation']) ?: '—' ?></dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Employer') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($emp['employer']) ?: '—' ?></dd>
        </div>
        <?php if ($emp['street'] || $emp['city']): ?>
            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Employer Address') ?></dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($emp['street']) ?><?= $emp['suite'] ? ', ' . h($emp['suite']) : '' ?>, <?= h($emp['city']) ?>, <?= h($emp['province']) ?>, <?= h($emp['postal_code']) ?></dd>
            </div>
        <?php endif; ?>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Start Date') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($emp['start_date']) ?: '—' ?></dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Supervisor') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($emp['supervisor_name']) ?: '—' ?></dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Employer Phone') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($emp['phone']) ?: '—' ?></dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Other Income') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($emp['other_income_source']) ?: '—' ?></dd>
        </div>
    </dl>
</div>

<!-- Emergency Contact -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Emergency Contact') ?></h2>
    <?php $ec = $p['emergency_contact']; ?>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Name') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($ec['first_name']) ?> <?= h($ec['last_name']) ?></dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Relationship') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($ec['relationship']) ?></dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= __('Phone') ?></dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100"><?= h($ec['phone']) ?></dd>
        </div>
    </dl>
</div>

<!-- Background Information -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Background Information') ?></h2>
    <?php $bg = $p['background']; ?>
    <div class="space-y-3">
        <div>
            <span class="inline-block px-2 py-0.5 text-xs rounded-full <?= $bg['evicted'] === 'yes' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' ?> mr-2"><?= __('Evicted') ?>: <?= $bg['evicted'] === 'yes' ? __('Yes') : __('No') ?></span>
            <?php if ($bg['evicted'] === 'yes' && $bg['evicted_details']): ?>
                <p class="text-sm text-gray-700 dark:text-gray-300 mt-1"><?= h($bg['evicted_details']) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <span class="inline-block px-2 py-0.5 text-xs rounded-full <?= $bg['convicted'] === 'yes' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' ?> mr-2"><?= __('Convicted') ?>: <?= $bg['convicted'] === 'yes' ? __('Yes') : __('No') ?></span>
            <?php if ($bg['convicted'] === 'yes' && $bg['convicted_details']): ?>
                <p class="text-sm text-gray-700 dark:text-gray-300 mt-1"><?= h($bg['convicted_details']) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <span class="inline-block px-2 py-0.5 text-xs rounded-full <?= $bg['refused_rent'] === 'yes' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' ?> mr-2"><?= __('Refused Rent') ?>: <?= $bg['refused_rent'] === 'yes' ? __('Yes') : __('No') ?></span>
            <?php if ($bg['refused_rent'] === 'yes' && $bg['refused_rent_details']): ?>
                <p class="text-sm text-gray-700 dark:text-gray-300 mt-1"><?= h($bg['refused_rent_details']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Other Information -->
<?php if (!empty($p['other_info'])): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Other Information') ?></h2>
        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap"><?= nl2br(h($p['other_info'])) ?></p>
    </div>
<?php endif; ?>

<!-- Other Tenants -->
<?php if (!empty($data['other_tenants'])): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Other Tenants (18 and older)') ?></h2>
        <?php foreach ($data['other_tenants'] as $i => $t): ?>
            <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg mb-4">
                <h3 class="font-medium text-gray-800 dark:text-gray-200 mb-3"><?= __('Tenant') ?> #<?= $i + 2 ?>: <?= h($t['first_name']) ?> <?= h($t['last_name']) ?></h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-gray-500 dark:text-gray-400"><?= __('Relationship') ?></dt><dd class="text-gray-900 dark:text-gray-100"><?= h($t['relationship']) ?></dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400"><?= __('Birth Date') ?></dt><dd class="text-gray-900 dark:text-gray-100"><?= h($t['birth_date']) ?></dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400"><?= __('Phone') ?></dt><dd class="text-gray-900 dark:text-gray-100"><?= h($t['phone']) ?></dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400"><?= __('Email') ?></dt><dd class="text-gray-900 dark:text-gray-100"><?= h($t['email']) ?></dd></div>
                    <?php if (!empty($t['photo_id'])): ?>
                        <div><dt class="text-gray-500 dark:text-gray-400"><?= __('Government Issued Photo ID') ?></dt><dd class="text-gray-900 dark:text-gray-100"><a href="/applications/<?= $application['id'] ?>/photo/tenant_<?= $i ?>" target="_blank" class="text-blue-600 hover:underline"><?= __('View Document') ?></a></dd></div>
                    <?php endif; ?>
                </dl>

                <?php if (!empty($t['current_address']['street'])): ?>
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mt-3 mb-1"><?= __('Current Address') ?></h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400"><?= h($t['current_address']['street']) ?>, <?= h($t['current_address']['city']) ?>, <?= h($t['current_address']['province']) ?>, <?= h($t['current_address']['postal_code']) ?></p>
                <?php endif; ?>

                <?php if (!empty($t['employment']['employer'])): ?>
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mt-3 mb-1"><?= __('Employment') ?></h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400"><?= h($t['employment']['occupation']) ?> @ <?= h($t['employment']['employer']) ?></p>
                <?php endif; ?>

                <?php if (!empty($t['emergency_contact']['first_name'])): ?>
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mt-3 mb-1"><?= __('Emergency Contact') ?></h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400"><?= h($t['emergency_contact']['first_name']) ?> <?= h($t['emergency_contact']['last_name']) ?> (<?= h($t['emergency_contact']['relationship']) ?>) — <?= h($t['emergency_contact']['phone']) ?></p>
                <?php endif; ?>

                <?php if (!empty($t['background'])): ?>
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mt-3 mb-1"><?= __('Background') ?></h4>
                    <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        <p><?= __('Evicted') ?>: <?= $t['background']['evicted'] === 'yes' ? __('Yes') . ($t['background']['evicted_details'] ? ' - ' . h($t['background']['evicted_details']) : '') : __('No') ?></p>
                        <p><?= __('Convicted') ?>: <?= $t['background']['convicted'] === 'yes' ? __('Yes') . ($t['background']['convicted_details'] ? ' - ' . h($t['background']['convicted_details']) : '') : __('No') ?></p>
                        <p><?= __('Refused Rent') ?>: <?= $t['background']['refused_rent'] === 'yes' ? __('Yes') . ($t['background']['refused_rent_details'] ? ' - ' . h($t['background']['refused_rent_details']) : '') : __('No') ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($t['other_info'])): ?>
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mt-3 mb-1"><?= __('Other Information') ?></h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap"><?= nl2br(h($t['other_info'])) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Other Occupants -->
<?php if (!empty($data['other_occupants'])): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Other Occupants (Under 18)') ?></h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($data['other_occupants'] as $occ): ?>
                <div class="p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <p class="font-medium text-gray-800 dark:text-gray-200"><?= h($occ['first_name']) ?> <?= h($occ['last_name']) ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400"><?= __('Age') ?>: <?= h($occ['age']) ?> — <?= __('Relationship') ?>: <?= h($occ['relationship']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- References -->
<?php if (!empty($data['references'])): ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Personal References') ?></h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($data['references'] as $ref): ?>
                <div class="p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <p class="font-medium text-gray-800 dark:text-gray-200"><?= h($ref['first_name']) ?> <?= h($ref['last_name']) ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400"><?= h($ref['relationship']) ?> — <?= h($ref['phone']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
