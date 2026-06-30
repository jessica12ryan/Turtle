<?php
$enabledRow = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'applications_enabled'");
$notesRow = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'applications_notes'");
$enabledVal = is_array($enabledRow) ? ($enabledRow['value'] ?? '0') : '0';
$notesVal = is_array($notesRow) ? ($notesRow['value'] ?? '') : '';
?>
<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4"><?= __('Tenancy Applications') ?></h2>
    <p class="text-sm text-gray-500 mb-6"><?= __('Configure the public tenancy application form. When enabled, a link to apply will appear on the login page.') ?></p>

    <form method="POST" action="/settings/applications">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="mb-6">
            <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                <input type="checkbox" name="applications_enabled" value="1" <?= $enabledVal === '1' ? 'checked' : '' ?> class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <span class="ml-3">
                    <span class="font-medium text-gray-800"><?= __('Enable Tenancy Applications') ?></span>
                    <span class="block text-sm text-gray-500"><?= __('Allow prospective tenants to submit applications through a public form.') ?></span>
                </span>
            </label>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Notes to Applicant') ?></label>
            <textarea name="applications_notes" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="<?= __('Optional: add a note or instructions that will be displayed at the top of the application form.') ?>"><?= h($notesVal) ?></textarea>
            <p class="text-xs text-gray-500 mt-1"><?= __('These notes will be shown at the top of the application form for applicants to read.') ?></p>
        </div>

        <div class="flex space-x-3">
            <button type="submit" class="bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700 font-medium"><?= __('Save Settings') ?></button>
        </div>
    </form>
</div>
