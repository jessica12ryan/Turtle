<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-4"><?= __('Logging') ?></h2>
    <p class="text-sm text-gray-600 mb-6"><?= __('Configure logging level and view application logs.') ?></p>

    <form method="POST" action="/settings/logging" class="mb-8">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('Log Level') ?></label>
            <select name="log_level" class="w-full max-w-xs border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <option value="debug" <?= ($logLevel ?? 'debug') === 'debug' ? 'selected' : '' ?>><?= __('Debug') ?></option>
                <option value="info" <?= ($logLevel ?? '') === 'info' ? 'selected' : '' ?>><?= __('Info') ?></option>
                <option value="notice" <?= ($logLevel ?? '') === 'notice' ? 'selected' : '' ?>><?= __('Notice') ?></option>
                <option value="warning" <?= ($logLevel ?? '') === 'warning' ? 'selected' : '' ?>><?= __('Warning') ?></option>
                <option value="error" <?= ($logLevel ?? '') === 'error' ? 'selected' : '' ?>><?= __('Error') ?></option>
            </select>
            <p class="text-xs text-gray-500 mt-1"><?= __('Determines the minimum severity level to log.') ?></p>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium"><?= __('Save') ?></button>
    </form>

    <div class="border-t pt-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-md font-semibold text-gray-700"><?= __('Recent Logs') ?></h3>
            <a href="/settings/logs/download" class="text-sm text-blue-600 hover:underline flex items-center space-x-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span><?= __('Download Full Logs') ?></span>
            </a>
        </div>

        <?php
        $logPath = $logFilePath ?? ini_get('error_log') ?: '/var/log/php_errors.log';
        $logLines = [];
        if (file_exists($logPath) && is_readable($logPath)) {
            $lines = file($logPath);
            $logLines = array_slice($lines, -100);
        }
        ?>

        <?php if (empty($logLines)): ?>
            <p class="text-sm text-gray-500"><?= __('No log entries found.') ?></p>
        <?php else: ?>
            <pre class="bg-gray-900 text-green-400 text-xs p-4 rounded-lg overflow-x-auto max-h-96 leading-relaxed"><?php foreach ($logLines as $line): ?><?= h($line) ?><?php endforeach; ?></pre>
        <?php endif; ?>
    </div>
</div>
