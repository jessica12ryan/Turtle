<?php $currentTheme = $currentTheme ?? 'light'; ?>
<label class="block text-sm font-medium text-gray-700 mb-1"><?= __('Theme Preference') ?></label>
<select name="theme" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
    <option value="system" <?= $currentTheme === 'system' ? 'selected' : '' ?>><?= __('Use System Setting') ?></option>
    <option value="light" <?= $currentTheme === 'light' ? 'selected' : '' ?>><?= __('Light') ?></option>
    <option value="dark" <?= $currentTheme === 'dark' ? 'selected' : '' ?>><?= __('Dark') ?></option>
</select>
<p class="text-xs text-gray-500 mt-1"><?= __("Choose Light, Dark, or follow your system's appearance.") ?></p>
