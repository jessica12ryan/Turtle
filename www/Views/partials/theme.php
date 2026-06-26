<?php $currentTheme = $currentTheme ?? 'light'; ?>
<label class="block text-sm font-medium text-gray-700 mb-1">Theme Preference</label>
<select name="theme" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
    <option value="system" <?= $currentTheme === 'system' ? 'selected' : '' ?>>Use System Setting</option>
    <option value="light" <?= $currentTheme === 'light' ? 'selected' : '' ?>>Light</option>
    <option value="dark" <?= $currentTheme === 'dark' ? 'selected' : '' ?>>Dark</option>
</select>
<p class="text-xs text-gray-500 mt-1">Choose Light, Dark, or follow your system's appearance.</p>
