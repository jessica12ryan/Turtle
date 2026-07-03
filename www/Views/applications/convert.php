<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?= __('Convert Application') ?> #<?= $application['id'] ?></h1>
    <a href="/applications/<?= $application['id'] ?>" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">&larr; <?= __('Back') ?></a>
</div>

<?php
$applicant = $data['primary_applicant'] ?? [];
$fullName = trim(
    ($applicant['first_name'] ?? '') . ' ' .
    (!empty($applicant['middle_names']) ? $applicant['middle_names'] . ' ' : '') .
    ($applicant['last_name'] ?? '')
);
$emergencyName = trim(
    ($applicant['emergency_contact']['first_name'] ?? '') . ' ' .
    (!empty($applicant['emergency_contact']['last_name']) ? $applicant['emergency_contact']['last_name'] : '')
);
$otherTenants = $data['other_tenants'] ?? [];
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Left column: original application review -->
    <div class="space-y-6">

        <!-- Applicant info -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Applicant Information') ?></h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div class="sm:col-span-2">
                    <dt class="text-gray-500 dark:text-gray-400"><?= __('Name') ?></dt>
                    <dd class="text-gray-900 dark:text-gray-100 font-medium"><?= h($applicant['first_name'] ?? '') ?> <?= !empty($applicant['middle_names']) ? h($applicant['middle_names']) . ' ' : '' ?><?= h($applicant['last_name'] ?? '') ?></dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400"><?= __('Birth Date') ?></dt>
                    <dd class="text-gray-900 dark:text-gray-100"><?= h($applicant['birth_date'] ?? '') ?></dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400"><?= __('Phone') ?></dt>
                    <dd class="text-gray-900 dark:text-gray-100"><?= h($applicant['phone'] ?? '') ?></dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400"><?= __('Email') ?></dt>
                    <dd class="text-gray-900 dark:text-gray-100 break-all"><?= h($applicant['email'] ?? '') ?></dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400"><?= __('Property') ?></dt>
                    <dd class="text-gray-900 dark:text-gray-100"><?= h($application['property_name'] ?? __('Not specified')) ?></dd>
                </div>
                <?php if (!empty($data['expected_move_in_date'])): ?>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400"><?= __('Expected Move In Date') ?></dt>
                    <dd class="text-gray-900 dark:text-gray-100"><?= h($data['expected_move_in_date']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($applicant['photo_id'])): ?>
                <div class="sm:col-span-2">
                    <dt class="text-gray-500 dark:text-gray-400 mb-1"><?= __('Government Issued Photo ID') ?></dt>
                    <dd>
                        <a href="/applications/<?= $application['id'] ?>/photo/primary" target="_blank" rel="noopener">
                            <img src="/applications/<?= $application['id'] ?>/photo/primary" alt="Photo ID" class="max-w-full h-auto rounded border border-gray-300 dark:border-gray-600" style="max-height:200px">
                        </a>
                        <p class="mt-1"><a href="/applications/<?= $application['id'] ?>/photo/primary" target="_blank" class="text-blue-600 hover:underline text-sm"><?= __('View Document') ?></a></p>
                    </dd>
                </div>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Current Address -->
        <?php $addr = $applicant['current_address'] ?? []; ?>
        <?php if (!empty($addr['street'])): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Current Address') ?></h2>
            <p class="text-sm text-gray-900 dark:text-gray-100">
                <?= h($addr['street']) ?><?= !empty($addr['apt_suite']) ? ', ' . h($addr['apt_suite']) : '' ?>
                <br><?= h($addr['city']) ?>, <?= h($addr['province']) ?> <?= h($addr['postal_code'] ?? '') ?>
            </p>
            <?php if (!empty($addr['date_moved_in'])): ?>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?= __('Date Moved In') ?>: <?= h($addr['date_moved_in']) ?></p>
            <?php endif; ?>
            <?php if (!empty($addr['reason_leaving'])): ?>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?= __('Reason For Leaving') ?>: <?= h($addr['reason_leaving']) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Employment -->
        <?php $emp = $applicant['employment'] ?? []; ?>
        <?php if (!empty($emp['occupation']) || !empty($emp['employer'])): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Employment & Income Information') ?></h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                <div><dt class="text-gray-500 dark:text-gray-400"><?= __('Occupation') ?></dt><dd class="text-gray-900 dark:text-gray-100"><?= h($emp['occupation'] ?: '—') ?></dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400"><?= __('Employer') ?></dt><dd class="text-gray-900 dark:text-gray-100"><?= h($emp['employer'] ?: '—') ?></dd></div>
                <?php if (!empty($emp['start_date'])): ?><div><dt class="text-gray-500 dark:text-gray-400"><?= __('Start Date') ?></dt><dd class="text-gray-900 dark:text-gray-100"><?= h($emp['start_date']) ?></dd></div><?php endif; ?>
                <?php if (!empty($emp['supervisor_name'])): ?><div><dt class="text-gray-500 dark:text-gray-400"><?= __('Supervisor') ?></dt><dd class="text-gray-900 dark:text-gray-100"><?= h($emp['supervisor_name']) ?></dd></div><?php endif; ?>
            </dl>
        </div>
        <?php endif; ?>

        <!-- Emergency Contact -->
        <?php $ec = $applicant['emergency_contact'] ?? []; ?>
        <?php if (!empty($ec['first_name']) || !empty($ec['last_name'])): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Emergency Contact') ?></h2>
            <p class="text-sm text-gray-900 dark:text-gray-100"><?= h($ec['first_name'] ?? '') ?> <?= h($ec['last_name'] ?? '') ?></p>
            <p class="text-sm text-gray-500 dark:text-gray-400"><?= h($ec['relationship'] ?? '') ?> <?= !empty($ec['phone']) ? '— ' . h($ec['phone']) : '' ?></p>
        </div>
        <?php endif; ?>

        <!-- Other Tenants -->
        <?php if (!empty($otherTenants)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Other Tenants (18 and older)') ?></h2>
            <div class="space-y-4">
                <?php foreach ($otherTenants as $i => $ot): ?>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <p class="font-medium text-gray-900 dark:text-gray-100"><?= h($ot['first_name'] ?? '') ?> <?= !empty($ot['middle_names']) ? h($ot['middle_names']) . ' ' : '' ?><?= h($ot['last_name'] ?? '') ?></p>
                    <dl class="grid grid-cols-2 gap-1 text-sm mt-1">
                        <dt class="text-gray-500 dark:text-gray-400"><?= __('Email') ?></dt><dd class="text-gray-900 dark:text-gray-100 break-all"><?= h($ot['email'] ?? '') ?: '—' ?></dd>
                        <dt class="text-gray-500 dark:text-gray-400"><?= __('Phone') ?></dt><dd class="text-gray-900 dark:text-gray-100"><?= h($ot['phone'] ?? '') ?: '—' ?></dd>
                        <dt class="text-gray-500 dark:text-gray-400"><?= __('Relationship') ?></dt><dd class="text-gray-900 dark:text-gray-100"><?= h($ot['relationship'] ?? '') ?: '—' ?></dd>
                    </dl>
                    <?php if (!empty($ot['photo_id'])): ?>
                    <div class="mt-2">
                        <a href="/applications/<?= $application['id'] ?>/photo/tenant_<?= $i ?>" target="_blank" rel="noopener">
                            <img src="/applications/<?= $application['id'] ?>/photo/tenant_<?= $i ?>" alt="Photo ID" class="max-w-full h-auto rounded border border-gray-300 dark:border-gray-600" style="max-height:120px">
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Right column: conversion form -->
    <div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Create Tenant') ?></h2>
            <form method="POST" action="/applications/<?= $application['id'] ?>/convert">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Full Name') ?> <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="<?= h(old('name') ?: $fullName) ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" autocomplete="off" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Email') ?> <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="<?= h(old('email') ?: ($applicant['email'] ?? '')) ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Phone Number') ?> <span class="text-red-500">*</span></label>
                    <input type="text" name="phone" value="<?= h(old('phone') ?: ($applicant['phone'] ?? '')) ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required placeholder="(555) 555-5555">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Property') ?> <span class="text-red-500">*</span></label>
                    <select name="property_id" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                        <option value=""><?= __('Select Property') ?></option>
                        <?php foreach ($properties as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= (old('property_id') == $p['id'] || $application['property_id'] == $p['id']) ? 'selected' : '' ?>><?= h($p['name']) ?> (<?= h($p['landlord_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Lease Start') ?> <span class="text-red-500">*</span></label>
                        <input type="date" name="lease_start" value="<?= h(old('lease_start') ?: ($data['expected_move_in_date'] ?? '')) ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Lease End') ?></label>
                        <input type="date" name="lease_end" value="<?= h(old('lease_end')) ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-400 mt-1"><?= __('Optional — leave blank for month-to-month') ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Scheduled Move Out') ?></label>
                        <input type="date" name="move_out_date" value="<?= h(old('move_out_date')) ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-400 mt-1"><?= __('Optional — tenant auto-archives on this date.') ?></p>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Lease Type') ?> <span class="text-red-500">*</span></label>
                    <select name="lease_type" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value=""><?= __('— Select —') ?></option>
                        <option value="fixed_term" <?= old('lease_type') === 'fixed_term' ? 'selected' : '' ?>><?= __('Fixed Term') ?></option>
                        <option value="year_to_year" <?= old('lease_type') === 'year_to_year' ? 'selected' : '' ?>><?= __('Year to Year') ?></option>
                        <option value="month_to_month" <?= old('lease_type') === 'month_to_month' ? 'selected' : '' ?>><?= __('Month to Month') ?></option>
                        <option value="week_to_week" <?= old('lease_type') === 'week_to_week' ? 'selected' : '' ?>><?= __('Week to Week') ?></option>
                        <option value="other" <?= old('lease_type') === 'other' ? 'selected' : '' ?>><?= __('Other') ?></option>
                    </select>
                </div>
                <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Emergency Contact Name') ?></label>
                        <input type="text" name="emergency_contact_name" value="<?= h(old('emergency_contact_name') ?: $emergencyName) ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Emergency Contact Phone') ?></label>
                        <input type="text" name="emergency_contact_phone" value="<?= h(old('emergency_contact_phone') ?: ($applicant['emergency_contact']['phone'] ?? '')) ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="(555) 555-5555">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Language') ?></label>
                    <select name="language" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value=""><?= __('Use default language') ?></option>
                        <?php foreach (languages() as $code => $name): ?>
                            <option value="<?= $code ?>" <?= old('language') === $code ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <?php $currentTimezone = old('timezone'); require base_path('www/Views/partials/timezone.php'); ?>
                </div>

                <?php if (!empty($otherTenants)): ?>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Other Tenants (18 and older)') ?></h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4"><?= __('These tenants will be added as secondary tenants.') ?></p>
                    <div id="secondary-tenants-container">
                        <?php foreach ($otherTenants as $i => $ot): ?>
                        <?php
                        $secName = trim(
                            ($ot['first_name'] ?? '') . ' ' .
                            (!empty($ot['middle_names']) ? $ot['middle_names'] . ' ' : '') .
                            ($ot['last_name'] ?? '')
                        );
                        $oldSecName = $_SESSION['_old']['secondary_name'][$i] ?? $secName;
                        $oldSecEmail = $_SESSION['_old']['secondary_email'][$i] ?? ($ot['email'] ?? '');
                        $oldSecPhone = $_SESSION['_old']['secondary_phone'][$i] ?? ($ot['phone'] ?? '');
                        $oldSecRel = $_SESSION['_old']['secondary_relationship'][$i] ?? ($ot['relationship'] ?? '');
                        ?>
                        <div class="secondary-tenant-entry border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Full Name') ?> <span class="text-red-500">*</span></label>
                                    <input type="text" name="secondary_name[<?= $i ?>]" value="<?= h($oldSecName) ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Email') ?></label>
                                    <input type="email" name="secondary_email[<?= $i ?>]" value="<?= h($oldSecEmail) ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <p class="text-xs text-gray-400 mt-1"><?= __('Required for account access') ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Phone') ?></label>
                                    <input type="text" name="secondary_phone[<?= $i ?>]" value="<?= h($oldSecPhone) ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="(555) 555-5555">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Relationship') ?></label>
                                    <input type="text" name="secondary_relationship[<?= $i ?>]" value="<?= h($oldSecRel) ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mb-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="send_welcome_email" value="1" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300"><?= __('Send tenant welcome email for onboarding') ?></span>
                    </label>
                </div>

                <div class="flex space-x-3">
                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700"><?= __('Accept & Create Tenant') ?></button>
                    <a href="/applications/<?= $application['id'] ?>" class="text-gray-600 dark:text-gray-400 px-6 py-2 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-700"><?= __('Cancel') ?></a>
                </div>
            </form>
        </div>
    </div>

</div>