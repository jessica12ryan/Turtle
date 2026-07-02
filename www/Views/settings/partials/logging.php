<div class="space-y-6">
    <!-- Log Level -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4"><?= __('Logging') ?></h2>
        <p class="text-sm text-gray-600 mb-6"><?= __('Configure logging level and view application logs.') ?></p>

        <form method="POST" action="/settings/logging">
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
    </div>

    <!-- Application Activity -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-700"><?= __('Application Activity') ?></h3>
            <a href="/settings/logs/download/activity?<?= h($_SERVER['QUERY_STRING'] ?? '') ?>" class="text-sm text-blue-600 hover:underline flex items-center space-x-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span><?= __('Download') ?></span>
            </a>
        </div>

        <form method="GET" action="/settings" class="mb-4">
            <input type="hidden" name="tab" value="logging">
            <div class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-xs text-gray-500 mb-1"><?= __('Action') ?></label>
                    <select name="action_filter" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                        <option value=""><?= __('All actions') ?></option>
                        <?php foreach ($activityActions as $a): ?>
                            <option value="<?= h($a['action']) ?>" <?= ($_GET['action_filter'] ?? '') === $a['action'] ? 'selected' : '' ?>><?= h($a['action']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1"><?= __('User') ?></label>
                    <select name="user_id" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="all"><?= __('All users') ?></option>
                        <?php foreach ($activityUsers as $u): ?>
                            <option value="<?= (int)$u['user_id'] ?>" <?= ($_GET['user_id'] ?? '') === (string)$u['user_id'] ? 'selected' : '' ?>><?= h($u['user_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1"><?= __('From') ?></label>
                    <input type="date" name="from_date" value="<?= h($_GET['from_date'] ?? '') ?>" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1"><?= __('To') ?></label>
                    <input type="date" name="to_date" value="<?= h($_GET['to_date'] ?? '') ?>" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex gap-1">
                    <button type="submit" class="bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-sm hover:bg-gray-300"><?= __('Filter') ?></button>
                    <a href="/settings?tab=logging" class="bg-gray-100 text-gray-500 px-3 py-1.5 rounded-lg text-sm hover:bg-gray-200 inline-flex items-center"><?= __('Clear') ?></a>
                </div>
            </div>
        </form>

        <?php if (empty($activityLogs)): ?>
            <p class="text-sm text-gray-500"><?= __('No activity logged yet.') ?></p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="py-2 pr-3 font-medium"><?= __('Time') ?></th>
                            <th class="py-2 pr-3 font-medium"><?= __('User') ?></th>
                            <th class="py-2 pr-3 font-medium"><?= __('Action') ?></th>
                            <th class="py-2 font-medium"><?= __('Description') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activityLogs as $log): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-2 pr-3 text-gray-500 whitespace-nowrap"><?= h($log['created_at']) ?></td>
                                <td class="py-2 pr-3 whitespace-nowrap"><?= h($log['user_name']) ?></td>
                                <td class="py-2 pr-3"><code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs"><?= h($log['action']) ?></code></td>
                                <td class="py-2 text-gray-700 max-w-xs truncate"><?= h($log['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-between mt-4">
                <p class="text-xs text-gray-500">
                    <?= __('Showing') ?> <?= (($currentPage - 1) * $perPage) + 1 ?>–<?= min($currentPage * $perPage, $totalLogs) ?> <?= __('of') ?> <?= $totalLogs ?>
                </p>
                <div class="flex gap-1">
                    <?php if ($currentPage > 1): ?>
                        <a href="?tab=logging&page=<?= $currentPage - 1 ?>&action_filter=<?= h($_GET['action_filter'] ?? '') ?>&user_id=<?= h($_GET['user_id'] ?? '') ?>&from_date=<?= h($_GET['from_date'] ?? '') ?>&to_date=<?= h($_GET['to_date'] ?? '') ?>" class="px-3 py-1 text-sm border border-gray-300 rounded-lg hover:bg-gray-100"><?= __('Previous') ?></a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    if ($startPage > 1): ?>
                        <a href="?tab=logging&page=1&action_filter=<?= h($_GET['action_filter'] ?? '') ?>&user_id=<?= h($_GET['user_id'] ?? '') ?>&from_date=<?= h($_GET['from_date'] ?? '') ?>&to_date=<?= h($_GET['to_date'] ?? '') ?>" class="px-3 py-1 text-sm border border-gray-300 rounded-lg hover:bg-gray-100">1</a>
                        <?php if ($startPage > 2): ?>
                            <span class="px-2 py-1 text-sm text-gray-400">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <a href="?tab=logging&page=<?= $p ?>&action_filter=<?= h($_GET['action_filter'] ?? '') ?>&user_id=<?= h($_GET['user_id'] ?? '') ?>&from_date=<?= h($_GET['from_date'] ?? '') ?>&to_date=<?= h($_GET['to_date'] ?? '') ?>" class="px-3 py-1 text-sm border rounded-lg <?= $p === $currentPage ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-100' ?>"><?= $p ?></a>
                    <?php endfor; ?>

                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <span class="px-2 py-1 text-sm text-gray-400">...</span>
                        <?php endif; ?>
                        <a href="?tab=logging&page=<?= $totalPages ?>&action_filter=<?= h($_GET['action_filter'] ?? '') ?>&user_id=<?= h($_GET['user_id'] ?? '') ?>&from_date=<?= h($_GET['from_date'] ?? '') ?>&to_date=<?= h($_GET['to_date'] ?? '') ?>" class="px-3 py-1 text-sm border border-gray-300 rounded-lg hover:bg-gray-100"><?= $totalPages ?></a>
                    <?php endif; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?tab=logging&page=<?= $currentPage + 1 ?>&action_filter=<?= h($_GET['action_filter'] ?? '') ?>&user_id=<?= h($_GET['user_id'] ?? '') ?>&from_date=<?= h($_GET['from_date'] ?? '') ?>&to_date=<?= h($_GET['to_date'] ?? '') ?>" class="px-3 py-1 text-sm border border-gray-300 rounded-lg hover:bg-gray-100"><?= __('Next') ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- PHP Error Log -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-700"><?= __('PHP Error Log') ?></h3>
            <div class="flex items-center space-x-3">
                <span class="text-xs text-gray-400"><?= h($phpLogPath) ?></span>
                <a href="/settings/logs/download/php" class="text-sm text-blue-600 hover:underline flex items-center space-x-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span><?= __('Download') ?></span>
                </a>
            </div>
        </div>

        <?php if (empty($phpLog)): ?>
            <p class="text-sm text-gray-500"><?= __('No PHP error log entries found at') ?> <?= h($phpLogPath) ?>.</p>
        <?php else: ?>
            <pre class="bg-gray-900 text-green-400 text-xs p-4 rounded-lg overflow-x-auto max-h-96 leading-relaxed"><?php foreach ($phpLog as $line): ?><?= h($line) ?><?php endforeach; ?></pre>
        <?php endif; ?>
    </div>

    <!-- Apache Logs -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-700"><?= __('Apache Logs') ?></h3>
            <a href="/settings/logs/download/apache" class="text-sm text-blue-600 hover:underline flex items-center space-x-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.293.707l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span><?= __('Download All') ?></span>
            </a>
        </div>

        <?php if (empty($apacheLogs)): ?>
            <p class="text-sm text-gray-500"><?= __('No Apache logs found. In Docker, Apache logs are typically streamed to stdout/stderr.') ?></p>
        <?php else: ?>
            <?php foreach ($apacheLogs as $name => $lines): ?>
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-600 mb-2"><?= h($name) ?></h4>
                    <pre class="bg-gray-900 text-green-400 text-xs p-4 rounded-lg overflow-x-auto max-h-64 leading-relaxed"><?php foreach ($lines as $line): ?><?= h($line) ?><?php endforeach; ?></pre>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- MySQL Logs -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-700"><?= __('MySQL Logs') ?></h3>
            <a href="/settings/logs/download/mysql" class="text-sm text-blue-600 hover:underline flex items-center space-x-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.293.707l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span><?= __('Download All') ?></span>
            </a>
        </div>

        <?php if (empty($mysqlLogs)): ?>
            <p class="text-sm text-gray-500"><?= __('No MySQL log files found. MySQL logs may need to be enabled in the MySQL configuration.') ?></p>
        <?php else: ?>
            <?php foreach ($mysqlLogs as $name => $lines): ?>
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-600 mb-2"><?= h($name) ?></h4>
                    <pre class="bg-gray-900 text-green-400 text-xs p-4 rounded-lg overflow-x-auto max-h-64 leading-relaxed"><?php foreach ($lines as $line): ?><?= h($line) ?><?php endforeach; ?></pre>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>