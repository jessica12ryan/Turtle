<h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2"><?= __('Convert Application') ?> #<?= $application['id'] ?></h1>
<p class="text-gray-500 dark:text-gray-400 mb-6"><?= h($application['property_name'] ?? '') ?></p>

<div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 max-w-2xl mb-6">
    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4"><?= __('Main Tenant') ?></h2>
    <form method="POST" action="/applications/<?= $application['id'] ?>/convert">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <?php
        $applicant = $data['primary_applicant'] ?? [];
        $fullName = trim(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? ''));
        $emergencyName = trim(($applicant['emergency_contact']['first_name'] ?? '') . ' ' . ($applicant['emergency_contact']['last_name'] ?? ''));
        $otherTenants = $data['other_tenants'] ?? [];
        ?>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Full Name') ?> <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="<?= h(old('name') ?: $fullName) ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?= __('Lease Start') ?> <span class="text-red-500">*</span></label>
                <input type="date" name="lease_start" value="<?= h(old('lease_start')) ?>" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" required>
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
        <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
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
                <?php $secName = trim(($ot['first_name'] ?? '') . ' ' . ($ot['last_name'] ?? '')); ?>
                <div class="secondary-tenant-entry border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php
                        $oldSecName = $_SESSION['_old']['secondary_name'][$i] ?? $secName;
                        $oldSecEmail = $_SESSION['_old']['secondary_email'][$i] ?? ($ot['email'] ?? '');
                        $oldSecPhone = $_SESSION['_old']['secondary_phone'][$i] ?? ($ot['phone'] ?? '');
                        $oldSecRel = $_SESSION['_old']['secondary_relationship'][$i] ?? ($ot['relationship'] ?? '');
                        ?>
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
