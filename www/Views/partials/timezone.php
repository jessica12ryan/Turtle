<?php
$tz = $currentTimezone ?? '';
$timezones = \DateTimeZone::listIdentifiers();
?>
<label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Timezone') ?></label>
<select name="timezone" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
    <option value=""><?= __('Use default timezone') ?></option>
    <?php foreach ($timezones as $zone): ?>
        <option value="<?= $zone ?>" <?= $tz === $zone ? 'selected' : '' ?>><?= $zone ?></option>
    <?php endforeach; ?>
</select>
<p class="text-xs text-gray-400 mt-1"><?= __('Override the global timezone for this user.') ?></p>
